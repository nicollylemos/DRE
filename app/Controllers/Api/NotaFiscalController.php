<?php
namespace App\Controllers\Api;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Core\HttpException;
use App\Models\NotaFiscal;
use App\Models\Transacao;
use App\Services\NotaFiscalXml;
use App\Services\Upload;

/**
 * Notas fiscais: anexar, ver, substituir e remover PDF/XML; vincular a lançamento;
 * ler o XML pra preencher a nota e o lançamento; importar várias de uma vez.
 */
class NotaFiscalController extends Controller
{
    private const PASTA = 'notas';
    private const MAX_PDF = 15 * 1024 * 1024;
    private const MAX_XML = 5 * 1024 * 1024;
    private const MAX_LOTE = 20; // limite padrão do PHP (max_file_uploads = 20)

    public function index(): array
    {
        $this->autorizar('notas-fiscais');
        return NotaFiscal::listar([
            'tipo' => $this->query('tipo'), 'vinculo' => $this->query('vinculo'), 'year' => $this->query('year'),
            'month' => $this->query('month'), 'busca' => trim((string) $this->query('busca', '')), 'transaction_id' => $this->query('transaction_id'),
        ]);
    }

    public function show($id): array
    {
        $this->autorizar('notas-fiscais');
        return $this->nota((int) $id);
    }

    private function nota(int $id): array
    {
        $nf = NotaFiscal::buscar($id);
        if (!$nf) throw new HttpException(404, 'Nota fiscal não encontrada');
        return $nf;
    }

    // ─── Ler XML sem gravar (preenche o formulário) ─────────────────────────
    public function lerXml(): array
    {
        $this->autorizarEdicao('notas-fiscais');
        $f = Upload::lista('xml')[0] ?? null;
        if (!$f) throw new HttpException(400, 'Escolha o arquivo XML da nota');
        if ($f['error'] !== UPLOAD_ERR_OK || $f['size'] > self::MAX_XML) throw new HttpException(400, 'Arquivo XML inválido ou muito grande (máx. 5 MB)');
        $dados = NotaFiscalXml::ler((string) file_get_contents($f['tmp_name']));
        $dados['tipo'] = NotaFiscal::sugerirTipo($dados);
        $dados['duplicada'] = $dados['chave'] && strlen((string) $dados['chave']) === 44 ? NotaFiscal::comChave($dados['chave']) : null;
        return $dados;
    }

    // ─── Criar / editar ─────────────────────────────────────────────────────
    public function store(): array
    {
        $u = $this->autorizarEdicao('notas-fiscais');
        $in = $this->body();
        $pdf = Upload::lista('pdf')[0] ?? null;
        $xml = Upload::lista('xml')[0] ?? null;
        if (!$pdf && !$xml) throw new HttpException(400, 'Anexe o PDF e/ou o XML da nota');
        // Campos vazios são completados pelo XML (ex.: salvou sem esperar a leitura no navegador).
        if ($xml && $xml['error'] === UPLOAD_ERR_OK) {
            try {
                $lido = NotaFiscalXml::ler((string) file_get_contents($xml['tmp_name']));
                foreach (['modelo', 'numero', 'serie', 'chave', 'data_emissao', 'valor', 'emitente_nome', 'emitente_doc', 'destinatario_nome', 'destinatario_doc', 'descricao'] as $k)
                    if (($in[$k] ?? '') === '' || (($k === 'valor') && !(float) ($in[$k] ?? 0))) $in[$k] = $lido[$k];
                if (empty($in['tipo'])) $in['tipo'] = NotaFiscal::sugerirTipo($lido);
            } catch (HttpException $e) { throw new HttpException(400, 'XML: ' . $e->getMessage()); }
        }
        $d = NotaFiscal::campos($in, null);
        $txId = $this->txValido($in['transaction_id'] ?? null);
        $arqPdf = $pdf ? Upload::salvarPrivado($pdf, self::PASTA, ['pdf'], self::MAX_PDF) : null;
        $arqXml = $xml ? Upload::salvarPrivado($xml, self::PASTA, ['xml'], self::MAX_XML) : null;
        $id = NotaFiscal::create($d + [
            'pdf_path' => $arqPdf['path'] ?? null, 'pdf_nome' => $arqPdf['nome'] ?? null,
            'xml_path' => $arqXml['path'] ?? null, 'xml_nome' => $arqXml['nome'] ?? null,
            'transaction_id' => $txId, 'user_id' => $u['id'],
        ]);
        $this->log('create', 'notas_fiscais', $id, $this->rotulo($d) . ($txId ? " — vinculada ao lançamento #$txId" : ''));
        return $this->nota($id);
    }

    public function update($id): array
    {
        $this->autorizarEdicao('notas-fiscais');
        $id = (int) $id;
        $atual = NotaFiscal::findOrFail($id, 'Nota fiscal não encontrada');
        $in = $this->body();
        $d = NotaFiscal::campos($in, $id);
        $extra = []; $troca = [];
        foreach (['pdf' => self::MAX_PDF, 'xml' => self::MAX_XML] as $qual => $max) {
            $f = Upload::lista($qual)[0] ?? null;
            if (!$f) continue;
            $arq = Upload::salvarPrivado($f, self::PASTA, [$qual], $max);
            Upload::apagar($atual[$qual . '_path']);
            $extra[$qual . '_path'] = $arq['path']; $extra[$qual . '_nome'] = $arq['nome'];
            $troca[] = strtoupper($qual) . ($atual[$qual . '_path'] ? ' substituído' : ' anexado') . ": {$arq['nome']}";
        }
        if (array_key_exists('transaction_id', $in)) $extra['transaction_id'] = $this->txValido($in['transaction_id']);
        NotaFiscal::update($id, $d + $extra + ['updated_at' => date('Y-m-d H:i:s')]);
        $diff = diff_log($atual, $d, NotaFiscal::ROTULOS);
        $this->log('update', 'notas_fiscais', $id, nome_e_mudancas($this->rotulo($d), implode('; ', array_filter([$diff, ...$troca]))));
        return $this->nota($id);
    }

    /** Remove só o PDF ou só o XML (a nota precisa ficar com pelo menos um arquivo). */
    public function removerArquivo($id, $qual): array
    {
        $this->autorizarEdicao('notas-fiscais');
        $id = (int) $id;
        if (!in_array($qual, ['pdf', 'xml'], true)) throw new HttpException(400, 'Arquivo inválido');
        $nf = NotaFiscal::findOrFail($id, 'Nota fiscal não encontrada');
        if (!$nf[$qual . '_path']) throw new HttpException(400, 'Essa nota não tem ' . strtoupper($qual));
        $outro = $qual === 'pdf' ? 'xml' : 'pdf';
        if (!$nf[$outro . '_path']) throw new HttpException(400, 'Esse é o único arquivo da nota. Para tirar tudo, apague a nota.');
        Upload::apagar($nf[$qual . '_path']);
        NotaFiscal::update($id, [$qual . '_path' => null, $qual . '_nome' => null, 'updated_at' => date('Y-m-d H:i:s')]);
        $this->log('update', 'notas_fiscais', $id, $this->rotulo($nf) . ' — ' . strtoupper($qual) . " removido: {$nf[$qual . '_nome']}");
        return $this->nota($id);
    }

    public function destroy($id): array
    {
        $this->autorizarEdicao('notas-fiscais');
        $id = (int) $id;
        $nf = NotaFiscal::findOrFail($id, 'Nota fiscal não encontrada');
        NotaFiscal::delete($id);
        Upload::apagar($nf['pdf_path']);
        Upload::apagar($nf['xml_path']);
        $this->log('delete', 'notas_fiscais', $id, $this->rotulo($nf), null, $nf);
        return ['ok' => true];
    }

    // ─── Arquivos (entregues só pra quem tem acesso à tela) ────────────────
    public function arquivo($id, $qual): void
    {
        $this->autorizar('notas-fiscais');
        if (!in_array($qual, ['pdf', 'xml'], true)) throw new HttpException(404, 'Arquivo não encontrado');
        $nf = NotaFiscal::findOrFail((int) $id, 'Nota fiscal não encontrada');
        $full = Upload::caminhoPrivado($nf[$qual . '_path']);
        if (!$full) throw new HttpException(404, 'Arquivo não encontrado');
        $nome = str_replace(['"', "\r", "\n"], '', (string) ($nf[$qual . '_nome'] ?: "nota-{$nf['id']}.$qual"));
        // PDF abre dentro da própria tela (iframe do mesmo site); XML sempre baixa (o navegador não interpreta).
        $inline = $qual === 'pdf' && $this->query('download') !== '1';
        header('Content-Type: ' . ($qual === 'pdf' ? 'application/pdf' : 'application/xml; charset=utf-8'));
        header('Content-Disposition: ' . ($inline ? 'inline' : 'attachment') . '; filename="' . $nome . '"; filename*=UTF-8\'\'' . rawurlencode($nome));
        header('Content-Length: ' . filesize($full));
        header('Cache-Control: private, no-store');
        header('X-Frame-Options: SAMEORIGIN');
        readfile($full);
        exit;
    }

    /** Conteúdo do XML + leitura, pra mostrar na tela (texto escapado no navegador). */
    public function xml($id): array
    {
        $this->autorizar('notas-fiscais');
        $nf = NotaFiscal::findOrFail((int) $id, 'Nota fiscal não encontrada');
        $full = Upload::caminhoPrivado($nf['xml_path']);
        if (!$full) throw new HttpException(404, 'Essa nota não tem XML');
        $txt = (string) file_get_contents($full);
        try { $dados = NotaFiscalXml::ler($txt); $erro = null; } catch (HttpException $e) { $dados = null; $erro = $e->getMessage(); }
        if (!preg_match('//u', $txt)) $txt = mb_convert_encoding($txt, 'UTF-8', 'ISO-8859-1');
        return ['xml' => mb_substr(self::indentar($txt), 0, 400000), 'dados' => $dados, 'erro' => $erro];
    }

    private static function indentar(string $xml): string
    {
        if (stripos($xml, '<!DOCTYPE') !== false) return $xml;
        $d = new \DOMDocument(); $d->preserveWhiteSpace = false; $d->formatOutput = true;
        $prev = libxml_use_internal_errors(true);
        $ok = @$d->loadXML($xml, LIBXML_NONET);
        libxml_use_internal_errors($prev);
        return $ok ? (string) $d->saveXML() : $xml;
    }

    // ─── Vínculo com lançamento ─────────────────────────────────────────────
    public function vincular($id): array
    {
        $this->autorizarEdicao('notas-fiscais');
        $id = (int) $id;
        $nf = NotaFiscal::findOrFail($id, 'Nota fiscal não encontrada');
        $txId = $this->txValido($this->input('transaction_id'));
        if ($txId) {
            $tx = Transacao::find($txId);
            $esperado = $nf['tipo'] === 'entrada' ? 'expense' : 'income';
            if ($tx['tipo'] !== $esperado) throw new HttpException(400, 'Nota de ' . ($nf['tipo'] === 'entrada' ? 'entrada vai com despesa' : 'saída vai com receita') . '. Troque o tipo da nota ou escolha outro lançamento.');
        }
        NotaFiscal::update($id, ['transaction_id' => $txId, 'updated_at' => date('Y-m-d H:i:s')]);
        $this->log('vincular', 'notas_fiscais', $id, $this->rotulo($nf) . ($txId ? " → lançamento #$txId" : ' — vínculo removido'));
        return $this->nota($id);
    }

    public function candidatos($id): array
    {
        $this->autorizar('notas-fiscais');
        $nf = NotaFiscal::findOrFail((int) $id, 'Nota fiscal não encontrada');
        return NotaFiscal::candidatos($nf, trim((string) $this->query('busca', '')), $this->query('so_aberto') === '1');
    }

    /** Dados pro formulário de lançamento já preenchido com a nota (valor, fornecedor, vencimento das duplicatas…). */
    public function sugestaoLancamento($id): array
    {
        $this->autorizar('notas-fiscais');
        $nf = NotaFiscal::findOrFail((int) $id, 'Nota fiscal não encontrada');
        return NotaFiscal::sugerirLancamento($nf, $this->xmlLido($nf));
    }

    private function xmlLido(array $nf): ?array
    {
        $full = Upload::caminhoPrivado($nf['xml_path']);
        if (!$full) return null;
        try { return NotaFiscalXml::ler((string) file_get_contents($full)); } catch (HttpException $e) { return null; }
    }

    private function txValido($v): ?int
    {
        if ($v === null || $v === '' || (int) $v === 0) return null;
        if (!Transacao::find((int) $v)) throw new HttpException(400, 'Lançamento não encontrado');
        return (int) $v;
    }

    private function rotulo(array $d): string
    {
        $n = trim(($d['modelo'] ?: 'NF') . ' ' . ($d['numero'] ?: 's/ nº'));
        $quem = $d['tipo'] === 'saida' ? ($d['destinatario_nome'] ?? '') : ($d['emitente_nome'] ?? '');
        return $n . ($quem ? " — $quem" : '') . ' — R$' . number_format((float) $d['valor'], 2, '.', '');
    }

    // ─── Importação em lote (vários XML e PDF de uma vez) ───────────────────
    public function lote(): array
    {
        $u = $this->autorizarEdicao('notas-fiscais');
        $arquivos = Upload::lista('arquivos');
        if (!$arquivos) throw new HttpException(400, 'Escolha os arquivos XML e/ou PDF');
        if (count($arquivos) > self::MAX_LOTE) throw new HttpException(400, 'Envie no máximo ' . self::MAX_LOTE . ' arquivos por vez');
        $gerar = $this->input('gerar_lancamentos') === '1' && Auth::podeEditar('lancamentos');
        $situacao = in_array($this->input('situacao'), ['auto', 'em_aberto', 'quitado'], true) ? $this->input('situacao') : 'auto';

        $xmls = []; $pdfs = []; $res = [];
        foreach ($arquivos as $f) {
            $nome = basename((string) $f['name']);
            $ext = strtolower(pathinfo($nome, PATHINFO_EXTENSION));
            if ($f['error'] !== UPLOAD_ERR_OK) { $res[] = ['arquivos' => [$nome], 'situacao' => 'erro', 'msg' => 'Falha no envio (arquivo muito grande?)']; continue; }
            if ($ext === 'xml') {
                try { $xmls[] = ['f' => $f, 'nome' => $nome, 'dados' => NotaFiscalXml::ler((string) file_get_contents($f['tmp_name']))]; }
                catch (HttpException $e) { $res[] = ['arquivos' => [$nome], 'situacao' => 'erro', 'msg' => $e->getMessage()]; }
            } elseif ($ext === 'pdf') {
                $pdfs[] = ['f' => $f, 'nome' => $nome, 'usado' => false];
            } else {
                $res[] = ['arquivos' => [$nome], 'situacao' => 'erro', 'msg' => 'Só XML e PDF'];
            }
        }

        // Junta o PDF com o XML pela chave (44 dígitos no nome do arquivo) ou pelo número da nota.
        $parDe = function (array $x) use (&$pdfs): ?int {
            $chave = (string) ($x['dados']['chave'] ?? '');
            $num = ltrim((string) ($x['dados']['numero'] ?? ''), '0');
            $achados = [];
            foreach ($pdfs as $i => $p) {
                if ($p['usado']) continue;
                $dig = preg_replace('/\D+/', ' ', pathinfo($p['nome'], PATHINFO_FILENAME));
                if ($chave !== '' && strpos(str_replace(' ', '', $dig), $chave) !== false) return $i;
                if ($num !== '' && in_array($num, array_map(fn($t) => ltrim($t, '0'), preg_split('/\s+/', trim($dig))), true)) $achados[] = $i;
            }
            return count($achados) === 1 ? $achados[0] : null;
        };

        foreach ($xmls as $x) {
            $d = $x['dados'];
            $iPdf = $parDe($x);
            if ($iPdf !== null) $pdfs[$iPdf]['usado'] = true;
            $pdf = $iPdf !== null ? $pdfs[$iPdf] : null;
            $nomes = array_values(array_filter([$x['nome'], $pdf['nome'] ?? null]));
            try {
                $dup = $d['chave'] && strlen((string) $d['chave']) === 44 ? Database::one('SELECT * FROM notas_fiscais WHERE chave=?', [$d['chave']]) : null;
                if ($dup) {
                    $msg = 'Nota já cadastrada (nº ' . ($dup['numero'] ?: $dup['id']) . ')';
                    if ($pdf && !$dup['pdf_path']) {
                        $a = Upload::salvarPrivado($pdf['f'], self::PASTA, ['pdf'], self::MAX_PDF);
                        NotaFiscal::update((int) $dup['id'], ['pdf_path' => $a['path'], 'pdf_nome' => $a['nome'], 'updated_at' => date('Y-m-d H:i:s')]);
                        $msg .= ' — PDF anexado a ela';
                    }
                    $res[] = ['arquivos' => $nomes, 'situacao' => 'duplicada', 'msg' => $msg, 'nota_id' => (int) $dup['id']];
                    continue;
                }
                $d['tipo'] = NotaFiscal::sugerirTipo($d);
                $campos = NotaFiscal::campos($d, null);
                $ax = Upload::salvarPrivado($x['f'], self::PASTA, ['xml'], self::MAX_XML);
                $ap = $pdf ? Upload::salvarPrivado($pdf['f'], self::PASTA, ['pdf'], self::MAX_PDF) : null;
                $nfId = NotaFiscal::create($campos + ['xml_path' => $ax['path'], 'xml_nome' => $ax['nome'],
                    'pdf_path' => $ap['path'] ?? null, 'pdf_nome' => $ap['nome'] ?? null, 'user_id' => $u['id']]);
                $txId = null;
                if ($gerar && $campos['valor'] > 0) {
                    $sug = NotaFiscal::sugerirLancamento(['id' => $nfId] + $campos, $d);
                    if ($situacao !== 'auto') $sug['status'] = $situacao;
                    if ($sug['status'] === 'em_aberto' && !$sug['data_vencimento']) $sug['data_vencimento'] = $sug['data'];
                    $f = Transacao::campos($sug);
                    $f['user_id'] = $u['id'];
                    $txId = Transacao::create($f);
                    NotaFiscal::update($nfId, ['transaction_id' => $txId]);
                    $this->log('create', 'transactions', $txId, "{$f['tipo']} - {$f['descricao']} - R\${$f['valor']} (nota fiscal)");
                }
                $this->log('create', 'notas_fiscais', $nfId, $this->rotulo($campos) . ' (importação em lote)' . ($txId ? " — lançamento #$txId" : ''));
                $res[] = ['arquivos' => $nomes, 'situacao' => 'ok', 'msg' => $this->rotulo($campos) . ($txId ? ' · lançamento criado' : ''), 'nota_id' => $nfId, 'transaction_id' => $txId];
            } catch (HttpException $e) {
                $res[] = ['arquivos' => $nomes, 'situacao' => 'erro', 'msg' => $e->getMessage()];
            }
        }
        // PDF sem XML vira nota "só PDF" pra completar à mão.
        foreach ($pdfs as $p) {
            if ($p['usado']) continue;
            try {
                $ap = Upload::salvarPrivado($p['f'], self::PASTA, ['pdf'], self::MAX_PDF);
                $num = preg_match('/(\d{3,9})/', pathinfo($p['nome'], PATHINFO_FILENAME), $m) && strlen(only_digits($p['nome'])) < 44 ? ltrim($m[1], '0') : null;
                $nfId = NotaFiscal::create(['tipo' => 'entrada', 'numero' => $num ?: null, 'valor' => 0, 'descricao' => mb_substr(pathinfo($p['nome'], PATHINFO_FILENAME), 0, 255),
                    'pdf_path' => $ap['path'], 'pdf_nome' => $ap['nome'], 'user_id' => $u['id']]);
                $this->log('create', 'notas_fiscais', $nfId, "PDF sem XML: {$ap['nome']} (importação em lote)");
                $res[] = ['arquivos' => [$p['nome']], 'situacao' => 'aviso', 'msg' => 'Só PDF — confira tipo, valor e emitente', 'nota_id' => $nfId];
            } catch (HttpException $e) {
                $res[] = ['arquivos' => [$p['nome']], 'situacao' => 'erro', 'msg' => $e->getMessage()];
            }
        }
        $cont = array_count_values(array_column($res, 'situacao'));
        return ['resultado' => $res, 'criadas' => ($cont['ok'] ?? 0) + ($cont['aviso'] ?? 0), 'duplicadas' => $cont['duplicada'] ?? 0,
                'erros' => $cont['erro'] ?? 0, 'lancamentos' => count(array_filter(array_column($res, 'transaction_id')))];
    }
}
