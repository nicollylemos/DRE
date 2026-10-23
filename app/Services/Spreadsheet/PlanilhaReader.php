<?php
namespace App\Services\Spreadsheet;

use App\Core\HttpException;

/**
 * Lê CSV e XLSX (Excel 2007+) em PHP puro — sem Composer.
 * Devolve uma lista de linhas, cada linha uma lista de textos.
 * Datas do Excel saem como "AAAA-MM-DD"; números como texto ("1234.5").
 */
final class PlanilhaReader
{
    public const MAX_LINHAS = 5000;

    public static function ler(string $arquivo, string $ext): array
    {
        $ext = strtolower($ext);
        if ($ext === 'xlsx') return self::xlsx($arquivo);
        if (in_array($ext, ['csv', 'txt'], true)) return self::csv($arquivo);
        if ($ext === 'xls') throw new HttpException(400, 'Arquivo .xls (Excel 97-2003) não é suportado. No Excel use "Salvar como" → Pasta de Trabalho do Excel (.xlsx) ou CSV.');
        throw new HttpException(400, 'Envie um arquivo .xlsx ou .csv');
    }

    // ─── CSV ────────────────────────────────────────────────────────────────
    public static function csv(string $arquivo): array
    {
        $raw = (string) file_get_contents($arquivo);
        if (strncmp($raw, "\xEF\xBB\xBF", 3) === 0) $raw = substr($raw, 3);
        // Excel BR costuma salvar CSV em Windows-1252 (ANSI).
        if (!mb_check_encoding($raw, 'UTF-8')) $raw = mb_convert_encoding($raw, 'UTF-8', 'Windows-1252');
        $primeira = strtok($raw, "\n") ?: '';
        $cont = [';' => substr_count($primeira, ';'), ',' => substr_count($primeira, ','), "\t" => substr_count($primeira, "\t")];
        arsort($cont);
        $sep = (string) array_key_first($cont);
        if ($cont[$sep] === 0) $sep = ';';

        $fh = fopen('php://temp', 'w+');
        fwrite($fh, $raw);
        rewind($fh);
        $rows = [];
        while (($r = fgetcsv($fh, 0, $sep, '"', '')) !== false) {
            if ($r === [null]) continue;
            $rows[] = array_map(fn($v) => trim((string) $v), $r);
            if (count($rows) > self::MAX_LINHAS + 1) throw new HttpException(400, 'Planilha grande demais (máx. ' . self::MAX_LINHAS . ' linhas por importação). Divida em arquivos menores.');
        }
        fclose($fh);
        return self::limpar($rows);
    }

    // ─── XLSX ───────────────────────────────────────────────────────────────
    public static function xlsx(string $arquivo): array
    {
        if (!class_exists('ZipArchive')) throw new HttpException(500, 'Extensão PHP "zip" não está habilitada no servidor (necessária para ler Excel). Use CSV ou habilite a extensão.');
        $z = new \ZipArchive();
        if ($z->open($arquivo) !== true) throw new HttpException(400, 'Não consegui abrir o arquivo. Ele é mesmo um .xlsx?');
        try {
            $strings = self::sharedStrings($z);
            [$datas] = self::estilosData($z);
            $sheetPath = self::primeiraAba($z);
            $xml = $z->getFromName($sheetPath);
            if ($xml === false) throw new HttpException(400, 'Planilha vazia ou corrompida');
            $sx = @simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NONET | LIBXML_COMPACT);
            if (!$sx) throw new HttpException(400, 'Planilha corrompida');

            $rows = [];
            foreach ($sx->sheetData->row as $row) {
                $linha = [];
                $idx = 0;
                foreach ($row->c as $c) {
                    $ref = (string) $c['r'];
                    if ($ref !== '') $idx = self::colIndex($ref);
                    $t = (string) $c['t'];
                    $s = (int) $c['s'];
                    $v = isset($c->v) ? (string) $c->v : '';
                    if ($t === 's') $val = $strings[(int) $v] ?? '';
                    elseif ($t === 'inlineStr') $val = self::textoRico($c->is);
                    elseif ($t === 'b') $val = $v === '1' ? 'VERDADEIRO' : 'FALSO';
                    elseif ($t === 'str' || $t === 'e') $val = $v;
                    elseif ($v !== '' && isset($datas[$s]) && is_numeric($v)) $val = self::serialParaData((float) $v);
                    else $val = $v;
                    $linha[$idx] = trim($val);
                    $idx++;
                }
                if ($linha) {
                    $max = max(array_keys($linha));
                    $cheia = array_fill(0, $max + 1, '');
                    foreach ($linha as $i => $v) $cheia[$i] = $v;
                    $rows[] = $cheia;
                } else {
                    $rows[] = [];
                }
                if (count($rows) > self::MAX_LINHAS + 1) throw new HttpException(400, 'Planilha grande demais (máx. ' . self::MAX_LINHAS . ' linhas por importação). Divida em arquivos menores.');
            }
            return self::limpar($rows);
        } finally {
            $z->close();
        }
    }

    private static function sharedStrings(\ZipArchive $z): array
    {
        $xml = $z->getFromName('xl/sharedStrings.xml');
        if ($xml === false) return [];
        $sx = @simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NONET | LIBXML_COMPACT);
        if (!$sx) return [];
        $out = [];
        foreach ($sx->si as $si) $out[] = self::textoRico($si);
        return $out;
    }

    private static function textoRico($el): string
    {
        if (!$el) return '';
        if (isset($el->t)) return (string) $el->t;
        $s = '';
        foreach ($el->r as $r) $s .= (string) $r->t;
        return $s;
    }

    /** Índices de estilo (cellXfs) que são formato de data. */
    private static function estilosData(\ZipArchive $z): array
    {
        $xml = $z->getFromName('xl/styles.xml');
        if ($xml === false) return [[]];
        $sx = @simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NONET | LIBXML_COMPACT);
        if (!$sx) return [[]];
        $custom = [];
        if (isset($sx->numFmts)) foreach ($sx->numFmts->numFmt as $nf) $custom[(int) $nf['numFmtId']] = (string) $nf['formatCode'];
        $datas = [];
        $i = 0;
        if (isset($sx->cellXfs)) foreach ($sx->cellXfs->xf as $xf) {
            $id = (int) $xf['numFmtId'];
            $ehData = ($id >= 14 && $id <= 22) || ($id >= 45 && $id <= 47) || ($id >= 27 && $id <= 36) || ($id >= 50 && $id <= 58);
            if (!$ehData && isset($custom[$id])) {
                $fmt = preg_replace('/"[^"]*"|\[[^\]]*\]|\\\\./', '', $custom[$id]);
                $ehData = (bool) preg_match('/[dy]|m{3,}|(^|[^h:])mm?([^:]|$)/i', $fmt) && !preg_match('/^[#0.,\s%]+$/', $fmt);
            }
            if ($ehData) $datas[$i] = true;
            $i++;
        }
        return [$datas];
    }

    private static function primeiraAba(\ZipArchive $z): string
    {
        $wb = $z->getFromName('xl/workbook.xml');
        $rels = $z->getFromName('xl/_rels/workbook.xml.rels');
        if ($wb !== false && $rels !== false) {
            $w = @simplexml_load_string($wb, 'SimpleXMLElement', LIBXML_NONET);
            $r = @simplexml_load_string($rels, 'SimpleXMLElement', LIBXML_NONET);
            if ($w && $r && isset($w->sheets->sheet[0])) {
                $rid = (string) $w->sheets->sheet[0]->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships')['id'];
                foreach ($r->Relationship as $rel) {
                    if ((string) $rel['Id'] === $rid) {
                        $t = ltrim((string) $rel['Target'], '/');
                        return strpos($t, 'xl/') === 0 ? $t : 'xl/' . $t;
                    }
                }
            }
        }
        return 'xl/worksheets/sheet1.xml';
    }

    private static function colIndex(string $ref): int
    {
        $letras = preg_replace('/\d/', '', strtoupper($ref));
        $n = 0;
        for ($i = 0; $i < strlen($letras); $i++) $n = $n * 26 + (ord($letras[$i]) - 64);
        return $n - 1;
    }

    /** Número de série do Excel → AAAA-MM-DD (base 30/12/1899). */
    public static function serialParaData(float $serial): string
    {
        $d = new \DateTime('1899-12-30');
        $d->modify('+' . (int) floor($serial) . ' days');
        return $d->format('Y-m-d');
    }

    /** Tira linhas totalmente vazias do fim e colunas vazias à direita. */
    private static function limpar(array $rows): array
    {
        $rows = array_map(fn($r) => array_map(fn($v) => (string) $v, $r), $rows);
        while ($rows && !array_filter(end($rows), fn($v) => $v !== '')) array_pop($rows);
        return $rows;
    }
}
