<?php
namespace App\Controllers\Api;

use App\Core\Controller;
use App\Core\HttpException;
use App\Eventos\Despachante;
use App\Eventos\Evento;
use App\Models\Transacao;
use App\Models\Transferencia;
use App\Services\Upload;

/** Lançamentos (receitas/despesas) e transferências entre caixas. */
class TransacaoController extends Controller
{
    public function index(): array
    {
        $this->autorizar('lancamentos');
        return Transacao::listar([
            'year' => $this->query('year'), 'month' => $this->query('month'), 'type' => $this->query('type'),
            'status' => $this->query('status'), 'categoria_id' => $this->query('categoria_id'),
            'centro_custo' => $this->query('centro_custo'), 'forma_pagamento' => $this->query('forma_pagamento'),
            'caixa_banco' => $this->query('caixa_banco'), 'importacao_id' => $this->query('importacao_id'),
        ]);
    }

    public function emAberto(): array
    {
        $this->autorizar('dashboard', 'lancamentos', 'calendario');
        return Transacao::emAberto();
    }

    /** Pelo formulário, a data é obrigatória conforme a situação (a importação continua aceitando sem). */
    private static function exigirDatas(array $f): void
    {
        if ($f['status'] === 'em_aberto' && !$f['data_vencimento']) throw new HttpException(400, 'Conta em aberto precisa da data de vencimento.');
        if ($f['status'] === 'quitado' && !$f['data_pagamento']) throw new HttpException(400, 'Lançamento quitado precisa da data de pagamento.');
    }

    public function store(): array
    {
        $u = $this->autorizarEdicao('lancamentos');
        $f = Transacao::campos($this->body());
        self::exigirDatas($f);
        $f['user_id'] = $u['id'];
        $id = Transacao::create($f);
        $this->log('create', 'transactions', $id, "{$f['tipo']} - {$f['descricao']} - R\${$f['valor']}");
        // Observer: quem precisa reagir (ex.: vincular a nota fiscal de origem) está em app/eventos.php.
        Despachante::disparar(new Evento(Evento::LANCAMENTO_CRIADO, ['id' => $id, 'nota_fiscal_id' => (int) $this->input('nota_fiscal_id', 0)] + $f));
        return ['id' => $id];
    }

    public function update($id): array
    {
        $this->autorizarEdicao('lancamentos');
        $id = (int) $id;
        $atual = Transacao::findOrFail($id, 'Lançamento não encontrado');
        $f = Transacao::campos($this->body());
        self::exigirDatas($f);
        // Taxa de cartão é a do dia do lançamento: se a forma ou o valor não mudaram, mantém a taxa original
        // (mudar a taxa da forma de pagamento depois não pode alterar o DRE de meses passados).
        if ($f['tipo'] === $atual['tipo'] && $f['forma_pagamento_nome'] === (string) $atual['forma_pagamento_nome'] && abs($f['valor'] - (float) $atual['valor']) < 0.005) {
            $f['taxa_mdr'] = round((float) $atual['taxa_mdr'], 2);
            $f['valor_liquido'] = round($f['valor'] - $f['taxa_mdr'], 2);
        }
        Transacao::update($id, $f);
        $diff = diff_log($atual, $f, Transacao::ROTULOS);
        $this->log('update', 'transactions', $id, "{$f['tipo']} - {$f['descricao']} - R\${$f['valor']}" . ($diff ? " — $diff" : ''));
        return ['ok' => true];
    }

    /** Baixa / reabrir (atualização parcial). */
    public function patch($id): array
    {
        $this->autorizarEdicao('lancamentos');
        $id = (int) $id;
        Transacao::findOrFail($id, 'Lançamento não encontrado');
        $b = $this->body(); $d = [];
        if (array_key_exists('status', $b)) $d['status'] = $b['status'] === 'em_aberto' ? 'em_aberto' : 'quitado';
        if (array_key_exists('data_pagamento', $b)) $d['data_pagamento'] = ($b['data_pagamento'] && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $b['data_pagamento'])) ? $b['data_pagamento'] : null;
        if (array_key_exists('observacoes', $b)) $d['observacoes'] = (string) $b['observacoes'];
        if (!$d) throw new HttpException(400, 'Nenhum campo para atualizar');
        Transacao::update($id, $d);
        $this->log(($b['status'] ?? '') === 'quitado' ? 'baixa' : 'update_status', 'transactions', $id, $b['status'] ?? null);
        return ['ok' => true];
    }

    public function destroy($id): array
    {
        $this->autorizarEdicao('lancamentos');
        $id = (int) $id;
        $row = Transacao::findOrFail($id, 'Lançamento não encontrado');
        Transacao::delete($id);
        Despachante::disparar(new Evento(Evento::LANCAMENTO_APAGADO, ['id' => $id] + $row));
        $this->log('delete', 'transactions', $id, "{$row['tipo']} - {$row['descricao']} - R\${$row['valor']}", null, $row);
        return ['ok' => true];
    }

    // ─── Comprovante (PDF ou foto do pagamento/recebimento) ─────────────────
    private const COMPROVANTE_EXTS = ['pdf', 'png', 'jpg', 'jpeg', 'webp'];
    private const COMPROVANTE_MAX = 10 * 1024 * 1024;

    /** Anexa ou substitui o comprovante do lançamento. */
    public function anexarComprovante($id): array
    {
        $this->autorizarEdicao('lancamentos');
        $id = (int) $id;
        $row = Transacao::findOrFail($id, 'Lançamento não encontrado');
        $f = Upload::lista('comprovante')[0] ?? null;
        if (!$f) throw new HttpException(400, 'Escolha o arquivo do comprovante (PDF ou imagem)');
        $arq = Upload::salvarPrivado($f, 'comprovantes', self::COMPROVANTE_EXTS, self::COMPROVANTE_MAX);
        Upload::apagar($row['comprovante_path']);
        Transacao::update($id, ['comprovante_path' => $arq['path'], 'comprovante_nome' => $arq['nome']]);
        $this->log('update', 'transactions', $id, "{$row['descricao']} — comprovante " . ($row['comprovante_path'] ? 'substituído' : 'anexado') . ": {$arq['nome']}");
        return ['ok' => true, 'tem_comprovante' => true, 'comprovante_nome' => $arq['nome']];
    }

    public function removerComprovante($id): array
    {
        $this->autorizarEdicao('lancamentos');
        $id = (int) $id;
        $row = Transacao::findOrFail($id, 'Lançamento não encontrado');
        if (!$row['comprovante_path']) throw new HttpException(400, 'Esse lançamento não tem comprovante');
        Upload::apagar($row['comprovante_path']);
        Transacao::update($id, ['comprovante_path' => null, 'comprovante_nome' => null]);
        $this->log('update', 'transactions', $id, "{$row['descricao']} — comprovante removido: {$row['comprovante_nome']}");
        return ['ok' => true, 'tem_comprovante' => false];
    }

    /** Entrega o arquivo só pra quem vê lançamentos ou o calendário (fica fora da pasta pública). */
    public function verComprovante($id): void
    {
        $this->autorizar('lancamentos', 'calendario');
        $row = Transacao::findOrFail((int) $id, 'Lançamento não encontrado');
        $full = Upload::caminhoPrivado($row['comprovante_path']);
        if (!$full) throw new HttpException(404, 'Comprovante não encontrado');
        $ext = strtolower(pathinfo($full, PATHINFO_EXTENSION));
        $mime = ['pdf' => 'application/pdf', 'png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'webp' => 'image/webp'][$ext] ?? 'application/octet-stream';
        $nome = str_replace(['"', "\r", "\n"], '', (string) ($row['comprovante_nome'] ?: "comprovante-{$row['id']}.$ext"));
        header("Content-Type: $mime");
        header('Content-Disposition: ' . ($this->query('download') === '1' ? 'attachment' : 'inline') . '; filename="' . $nome . '"; filename*=UTF-8\'\'' . rawurlencode($nome));
        header('Content-Length: ' . filesize($full));
        header('Cache-Control: private, no-store');
        header('X-Frame-Options: SAMEORIGIN');
        readfile($full);
        exit;
    }

    // ─── Transferências ─────────────────────────────────────────────────────
    public function transferencias(): array
    {
        $this->autorizar('lancamentos');
        return Transferencia::listar($this->query('year') ? (int) $this->query('year') : null, $this->query('month') ? (int) $this->query('month') : null);
    }

    public function criarTransferencia(): array
    {
        $u = $this->autorizarEdicao('lancamentos');
        $f = Transferencia::campos($this->body()) + ['user_id' => $u['id']];
        $id = Transferencia::create($f);
        $this->log('create', 'transfers', $id, "{$f['origem_nome']} → {$f['destino_nome']} - R\${$f['valor']}");
        return ['id' => $id];
    }

    public function apagarTransferencia($id): array
    {
        $this->autorizarEdicao('lancamentos');
        $id = (int) $id;
        $row = Transferencia::findOrFail($id, 'Transferência não encontrada');
        Transferencia::delete($id);
        $this->log('delete', 'transfers', $id, "{$row['origem_nome']} → {$row['destino_nome']} - R\${$row['valor']}", null, $row);
        return ['ok' => true];
    }
}
