<?php
namespace App\Services\Spreadsheet;

/**
 * XlsxWriter — gera .xlsx simples (várias abas, cabeçalho em negrito, números
 * como número) usando só ZipArchive. Substitui a lib "xlsx" do Node, sem Composer.
 */
final class XlsxWriter {
    private array $sheets = [];

    public function addSheet(string $name, array $rows): void {
        $name = mb_substr(preg_replace('#[\\\\/?*\[\]:]#', ' ', $name), 0, 31);
        $this->sheets[] = [$name, $rows];
    }

    private static function x(string $s): string {
        $s = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $s);
        return htmlspecialchars($s, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }
    private static function col(int $i): string {
        $s = ''; $i++;
        while ($i > 0) { $m = ($i - 1) % 26; $s = chr(65 + $m) . $s; $i = intdiv($i - 1, 26); }
        return $s;
    }

    /** Largura das colunas pelo maior conteúdo (limitada), pra planilha abrir legível. */
    private static function cols(array $rows): string {
        $w = [];
        foreach ($rows as $row) foreach (array_values($row) as $c => $v) {
            $len = is_float($v) ? strlen(number_format($v, 2)) + 2 : mb_strlen((string) $v);
            $w[$c] = max($w[$c] ?? 8, min(60, $len + 2));
        }
        if (!$w) return '';
        $out = '<cols>';
        foreach ($w as $c => $n) $out .= '<col min="' . ($c + 1) . '" max="' . ($c + 1) . '" width="' . $n . '" customWidth="1"/>';
        return $out . '</cols>';
    }

    private function sheetXml(array $rows): string {
        $out = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">' . self::cols($rows) . '<sheetData>';
        foreach (array_values($rows) as $r => $row) {
            $out .= '<row r="' . ($r + 1) . '">';
            foreach (array_values($row) as $c => $v) {
                $ref = self::col($c) . ($r + 1);
                $style = $r === 0 ? ' s="1"' : '';
                if ($v === null || $v === '') continue;
                if (is_float($v) && $r > 0) $out .= "<c r=\"$ref\" s=\"2\"><v>" . (0 + $v) . '</v></c>';
                elseif (is_int($v) || is_float($v)) $out .= "<c r=\"$ref\"$style><v>" . (0 + $v) . '</v></c>';
                else $out .= "<c r=\"$ref\" t=\"inlineStr\"$style><is><t xml:space=\"preserve\">" . self::x((string) $v) . '</t></is></c>';
            }
            $out .= '</row>';
        }
        return $out . '</sheetData></worksheet>';
    }

    public function output(): string {
        if (!class_exists('ZipArchive')) throw new \RuntimeException('Extensão PHP "zip" não está habilitada no servidor (necessária para exportar Excel).');
        $tmp = tempnam(sys_get_temp_dir(), 'xlsx');
        $z = new \ZipArchive();
        $z->open($tmp, \ZipArchive::OVERWRITE);
        $n = count($this->sheets);
        $ct = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>';
        for ($i = 1; $i <= $n; $i++) $ct .= "<Override PartName=\"/xl/worksheets/sheet$i.xml\" ContentType=\"application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml\"/>";
        $z->addFromString('[Content_Types].xml', $ct . '</Types>');
        $z->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>');
        $wb = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets>';
        $rels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">';
        foreach ($this->sheets as $i => [$name, $rows]) {
            $k = $i + 1;
            $wb .= '<sheet name="' . self::x($name) . "\" sheetId=\"$k\" r:id=\"rId$k\"/>";
            $rels .= "<Relationship Id=\"rId$k\" Type=\"http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet\" Target=\"worksheets/sheet$k.xml\"/>";
            $z->addFromString("xl/worksheets/sheet$k.xml", $this->sheetXml($rows));
        }
        $rels .= '<Relationship Id="rId' . ($n + 1) . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>';
        $z->addFromString('xl/workbook.xml', $wb . '</sheets></workbook>');
        $z->addFromString('xl/_rels/workbook.xml.rels', $rels . '</Relationships>');
        $z->addFromString('xl/styles.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><name val="Calibri"/></font></fonts>'
            . '<fills count="2"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill></fills>'
            . '<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
            . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            . '<cellXfs count="3"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/><xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1"/><xf numFmtId="4" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/></cellXfs>'
            . '<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
            . '</styleSheet>');
        $z->close();
        $data = file_get_contents($tmp);
        @unlink($tmp);
        return $data;
    }
}
