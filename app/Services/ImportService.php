<?php
namespace App\Services;

use App\Core\Auth;
use App\Core\Database;
use App\Core\HttpException;
use App\Models\Categoria;
use App\Models\Cliente;
use App\Models\Fornecedor;
use App\Models\Importacao;
use App\Models\Transacao;
use App\Services\Spreadsheet\PlanilhaReader;

/**
 * Importação de planilhas em 3 passos:
 *   1. analisar  — lê o arquivo, guarda as linhas em storage/imports/<token>.json e sugere o mapeamento das colunas
 *   2. validar   — aplica mapeamento + opções e devolve a prévia linha a linha (ok / aviso / erro / duplicado)
 *   3. confirmar — grava as linhas válidas numa transação e registra a importação (dá pra desfazer depois)
 */
final class ImportService
{
    public const TIPOS = ['lancamentos' => 'Lançamentos', 'clientes' => 'Clientes', 'fornecedores' => 'Fornecedores'];
    private const EXT = ['xlsx', 'csv', 'txt', 'xls'];
    private const MAX_BYTES = 10 * 1024 * 1024;

    /**
     * Campos de cada tipo: chave => [rótulo, obrigatório, sinônimos do cabeçalho].
     * Os sinônimos são comparados sem acento/maiúscula/pontuação.
     */
    public static function campos(string $tipo): array
    {
        $c = [
            'lancamentos' => [
                'data'               => ['Data', true, ['data', 'dt', 'data lancamento', 'data do lancamento', 'competencia', 'data competencia', 'date']],
                'tipo'               => ['Tipo (receita/despesa)', false, ['tipo', 'natureza', 'entrada saida', 'operacao', 'type', 'e s', 'credito debito']],
                'descricao'          => ['Descrição', false, ['descricao', 'historico', 'description', 'detalhe', 'lancamento']],
                'valor'              => ['Valor', true, ['valor', 'valor bruto', 'montante', 'total', 'amount', 'valor r', 'valor rs', 'quantia']],
                'categoria'          => ['Categoria', false, ['categoria', 'category', 'classificacao']],
                'cliente_fornecedor' => ['Cliente / fornecedor', false, ['cliente fornecedor', 'cliente', 'fornecedor', 'favorecido', 'pagador', 'beneficiario', 'contraparte', 'nome']],
                'forma_pagamento'    => ['Forma de pagamento', false, ['forma pagamento', 'forma de pagamento', 'forma', 'meio de pagamento', 'meio pagamento', 'pagamento forma']],
                'caixa'              => ['Caixa / banco', false, ['caixa banco', 'caixa', 'banco', 'conta bancaria', 'conta corrente']],
                'centro_custo'       => ['Centro de custo', false, ['centro de custo', 'centro custo', 'centro']],
                'grupo'              => ['Grupo (plano de contas)', false, ['grupo', 'grupo de contas', 'grupo contas']],
                'conta'              => ['Conta (plano de contas)', false, ['conta', 'conta contabil', 'subconta']],
                'status'             => ['Situação (quitado/em aberto)', false, ['status', 'situacao', 'pago', 'quitado', 'baixado']],
                'data_vencimento'    => ['Vencimento', false, ['vencimento', 'data vencimento', 'data de vencimento', 'dt vencimento', 'venc']],
                'data_pagamento'     => ['Data de pagamento', false, ['data pagamento', 'data de pagamento', 'pagamento', 'data baixa', 'dt pagamento', 'data recebimento', 'pago em']],
                'observacoes'        => ['Observações', false, ['observacoes', 'observacao', 'obs', 'notas', 'comentario']],
            ],
            'clientes' => [
                'nome'         => ['Nome', true, ['nome', 'cliente', 'nome fantasia', 'fantasia']],
                'razao_social' => ['Razão social', false, ['razao social', 'razao']],
                'cnpj_cpf'     => ['CPF / CNPJ', false, ['cnpj cpf', 'cpf cnpj', 'cpf', 'cnpj', 'documento', 'doc']],
                'email'        => ['E-mail', false, ['email', 'e mail']],
                'telefone'     => ['Telefone', false, ['telefone', 'fone', 'celular', 'whatsapp', 'contato']],
                'estado'       => ['Estado (UF)', false, ['estado', 'uf']],
                'cidade'       => ['Cidade', false, ['cidade', 'municipio']],
                'endereco'     => ['Endereço', false, ['endereco', 'logradouro', 'rua']],
                'observacoes'  => ['Observações', false, ['observacoes', 'observacao', 'obs']],
            ],
            'fornecedores' => [
                'nome'         => ['Nome', true, ['nome', 'fornecedor', 'nome fantasia', 'fantasia']],
                'razao_social' => ['Razão social', false, ['razao social', 'razao']],
                'cnpj_cpf'     => ['CNPJ / CPF', false, ['cnpj cpf', 'cpf cnpj', 'cnpj', 'cpf', 'documento', 'doc']],
                'email'        => ['E-mail', false, ['email', 'e mail']],
                'telefone'     => ['Telefone', false, ['telefone', 'fone', 'contato']],
                'whatsapp'     => ['WhatsApp', false, ['whatsapp', 'whats', 'celular']],
                'responsavel'  => ['Responsável', false, ['responsavel', 'contato nome', 'representante']],
                'estado'       => ['Estado (UF)', false, ['estado', 'uf']],
                'cidade'       => ['Cidade', false, ['cidade', 'municipio']],
                'endereco'     => ['Endereço', false, ['endereco', 'logradouro', 'rua']],
                'observacoes'  => ['Observações', false, ['observacoes', 'observacao', 'obs']],
            ],
        ];
        if (!isset($c[$tipo])) throw new HttpException(400, 'Tipo de importação inválido');
        return $c[$tipo];
    }

    public static function normalizar(string $s): string
    {
        $s = mb_strtolower(trim($s));
        $s = strtr($s, ['á' => 'a', 'à' => 'a', 'â' => 'a', 'ã' => 'a', 'ä' => 'a', 'é' => 'e', 'ê' => 'e', 'è' => 'e', 'í' => 'i', 'ì' => 'i',
                        'ó' => 'o', 'ô' => 'o', 'õ' => 'o', 'ò' => 'o', 'ú' => 'u', 'ü' => 'u', 'ù' => 'u', 'ç' => 'c', 'º' => '', 'ª' => '']);
        return trim(preg_replace('/[^a-z0-9]+/', ' ', $s));
    }

    // ─── Passo 1: analisar ──────────────────────────────────────────────────
    public static function analisar(string $tipo, array $arquivo, bool $temCabecalho = true): array
    {
        $campos = self::campos($tipo);
        if (($arquivo['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) throw new HttpException(400, 'Envie o arquivo da planilha');
        if ($arquivo['size'] > self::MAX_BYTES) throw new HttpException(400, 'Arquivo muito grande (máx. 10 MB)');
        $ext = strtolower(pathinfo((string) $arquivo['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, self::EXT, true)) throw new HttpException(400, 'Envie um arquivo .xlsx ou .csv');

        $rows = PlanilhaReader::ler($arquivo['tmp_name'], $ext);
        // Pula linhas vazias do topo (título da planilha, etc.)
        while ($rows && !array_filter($rows[0], fn($v) => $v !== '')) array_shift($rows);
        if (!$rows) throw new HttpException(400, 'A planilha está vazia');
        $ncol = max(array_map('count', $rows));
        if ($temCabecalho) {
            $cab = array_shift($rows);
            $headers = [];
            for ($i = 0; $i < $ncol; $i++) $headers[] = trim((string) ($cab[$i] ?? '')) ?: 'Coluna ' . self::letra($i);
        } else {
            $headers = array_map(fn($i) => 'Coluna ' . self::letra($i), range(0, $ncol - 1));
        }
        $linhaInicial = $temCabecalho ? 2 : 1;
        $dados = [];
        foreach ($rows as $i => $r) {
            if (!array_filter($r, fn($v) => $v !== '')) continue;
            $dados[] = ['n' => $i + $linhaInicial, 'v' => array_pad(array_slice($r, 0, $ncol), $ncol, '')];
        }
        if (!$dados) throw new HttpException(400, 'Nenhuma linha com dados encontrada abaixo do cabeçalho');
        if (count($dados) > PlanilhaReader::MAX_LINHAS) throw new HttpException(400, 'Máximo de ' . PlanilhaReader::MAX_LINHAS . ' linhas por importação');

        self::limparAntigos();
        $token = bin2hex(random_bytes(16));
        $payload = ['user_id' => (int) Auth::user()['id'], 'tipo' => $tipo, 'arquivo' => mb_substr(basename((string) $arquivo['name']), 0, 255),
                    'headers' => $headers, 'linhas' => $dados, 'criado' => time()];
        if (@file_put_contents(self::caminho($token), json_encode($payload, JSON_UNESCAPED_UNICODE)) === false)
            throw new HttpException(500, 'Não foi possível guardar o arquivo temporário (confira permissão de escrita em storage/)');

        return [
            'token' => $token, 'tipo' => $tipo, 'arquivo' => $payload['arquivo'], 'total' => count($dados),
            'colunas' => $headers, 'mapeamento' => self::sugerirMapeamento($campos, $headers),
            'campos' => array_map(fn($k, $c) => ['chave' => $k, 'label' => $c[0], 'obrigatorio' => $c[1]], array_keys($campos), $campos),
            'amostra' => array_map(fn($l) => $l['v'], array_slice($dados, 0, 5)),
        ];
    }

    private static function letra(int $i): string
    {
        $s = ''; $i++;
        while ($i > 0) { $m = ($i - 1) % 26; $s = chr(65 + $m) . $s; $i = intdiv($i - 1, 26); }
        return $s;
    }

    /** campo => índice da coluna (ou null). Cada coluna é usada por no máximo um campo. */
    private static function sugerirMapeamento(array $campos, array $headers): array
    {
        $norm = array_map([self::class, 'normalizar'], $headers);
        $usadas = []; $map = [];
        // 1ª passada: igualdade exata com algum sinônimo; 2ª: cabeçalho que começa com o sinônimo.
        foreach ([true, false] as $exato) {
            foreach ($campos as $k => [, , $sin]) {
                if (isset($map[$k])) continue;
                foreach ($sin as $s) {
                    foreach ($norm as $i => $h) {
                        if (isset($usadas[$i]) || $h === '') continue;
                        if ($exato ? $h === $s : (strpos($h, $s . ' ') === 0 || strpos($h, ' ' . $s) !== false && strlen($s) > 4)) {
                            $map[$k] = $i; $usadas[$i] = true; break 2;
                        }
                    }
                }
            }
        }
        $out = [];
        foreach ($campos as $k => $c) $out[$k] = $map[$k] ?? null;
        return $out;
    }

    // ─── Conversões ─────────────────────────────────────────────────────────
    /** "R$ 1.234,56" | "1234.56" | "(1.234,56)" | "-1.234,56" → float, ou null se não for número. */
    public static function valor(string $s): ?float
    {
        $s = trim(str_ireplace(['R$', 'BRL', "\xC2\xA0", ' '], '', $s));
        if ($s === '') return null;
        $neg = false;
        if (preg_match('/^\((.*)\)$/', $s, $m)) { $neg = true; $s = $m[1]; }
        if (substr($s, -1) === '-') { $neg = true; $s = substr($s, 0, -1); }
        if (strpos($s, '-') === 0) { $neg = !$neg; $s = substr($s, 1); }
        if (strpos($s, '+') === 0) $s = substr($s, 1);
        $temP = strpos($s, '.') !== false; $temV = strpos($s, ',') !== false;
        if ($temP && $temV) {
            if (strrpos($s, ',') > strrpos($s, '.')) $s = str_replace(['.', ','], ['', '.'], $s);
            else $s = str_replace(',', '', $s);
        } elseif ($temV) {
            $s = preg_match('/^\d{1,3}(,\d{3})+$/', $s) && substr_count($s, ',') > 1 ? str_replace(',', '', $s) : str_replace(',', '.', $s);
        } elseif ($temP && preg_match('/^\d{1,3}(\.\d{3})+$/', $s)) {
            $s = str_replace('.', '', $s);
        }
        if (!is_numeric($s)) return null;
        return $neg ? -(float) $s : (float) $s;
    }

    /** Várias formas de data → AAAA-MM-DD, ou null. */
    public static function data(string $s): ?string
    {
        $s = trim($s);
        if ($s === '') return null;
        if (preg_match('/^(\d{4})-(\d{1,2})-(\d{1,2})/', $s, $m) || preg_match('#^(\d{4})/(\d{1,2})/(\d{1,2})#', $s, $m)) [$y, $mo, $d] = [(int) $m[1], (int) $m[2], (int) $m[3]];
        elseif (preg_match('#^(\d{1,2})[/.\-](\d{1,2})[/.\-](\d{2,4})#', $s, $m)) {
            [$d, $mo, $y] = [(int) $m[1], (int) $m[2], (int) $m[3]];
            if ($y < 100) $y += $y < 70 ? 2000 : 1900;
        } elseif (preg_match('/^\d{5}(\.\d+)?$/', $s) && (float) $s > 20000 && (float) $s < 80000) {
            return PlanilhaReader::serialParaData((float) $s); // data que veio como número do Excel
        } else return null;
        return checkdate($mo, $d, $y) && $y >= 1990 && $y <= 2100 ? sprintf('%04d-%02d-%02d', $y, $mo, $d) : null;
    }

    public static function tipo(string $s): ?string
    {
        $n = self::normalizar($s);
        if ($n === '') return null;
        if (in_array($n, ['receita', 'receitas', 'entrada', 'entradas', 'income', 'r', 'e', 'c', 'credito', 'recebimento', 'venda', '+'], true) || strpos($n, 'receita') === 0 || strpos($n, 'entrada') === 0) return 'income';
        if (in_array($n, ['despesa', 'despesas', 'saida', 'saidas', 'expense', 'd', 's', 'debito', 'pagamento', 'gasto', 'custo', '-'], true) || strpos($n, 'despesa') === 0 || strpos($n, 'saida') === 0) return 'expense';
        return null;
    }

    public static function status(string $s): ?string
    {
        $n = self::normalizar($s);
        if ($n === '') return null;
        if (in_array($n, ['quitado', 'pago', 'paga', 'recebido', 'recebida', 'sim', 's', 'liquidado', 'baixado', 'ok', 'realizado', 'concluido', 'true', 'verdadeiro', '1'], true)) return 'quitado';
        if (in_array($n, ['em aberto', 'em_aberto', 'aberto', 'pendente', 'a pagar', 'a receber', 'nao', 'n', 'previsto', 'agendado', 'false', 'falso', '0', 'vencido', 'em atraso'], true)) return 'em_aberto';
        return null;
    }

    // ─── Passo 2: validar (prévia) ──────────────────────────────────────────
    public static function opcoesPadrao(array $o): array
    {
        return [
            'tipo_padrao'        => in_array($o['tipo_padrao'] ?? '', ['income', 'expense', 'sinal'], true) ? $o['tipo_padrao'] : 'sinal',
            'status_padrao'      => ($o['status_padrao'] ?? '') === 'em_aberto' ? 'em_aberto' : 'quitado',
            'criar_categorias'   => !empty($o['criar_categorias']),
            'ignorar_duplicados' => !array_key_exists('ignorar_duplicados', $o) || !empty($o['ignorar_duplicados']),
        ];
    }

    private static function carregar(string $token): array
    {
        if (!preg_match('/^[a-f0-9]{32}$/', $token)) throw new HttpException(400, 'Importação inválida');
        $p = self::caminho($token);
        if (!is_file($p)) throw new HttpException(410, 'Essa prévia expirou. Envie o arquivo de novo.');
        $d = json_decode((string) file_get_contents($p), true);
        if (!is_array($d) || (int) $d['user_id'] !== (int) Auth::user()['id']) throw new HttpException(403, 'Importação de outro usuário');
        return $d;
    }

    /** Aplica mapeamento + opções em todas as linhas. Não grava nada. */
    public static function processar(string $token, array $mapa, array $opcoes): array
    {
        $d = self::carregar($token);
        $tipo = $d['tipo'];
        $campos = self::campos($tipo);
        $opcoes = self::opcoesPadrao($opcoes);
        foreach ($campos as $k => [$label, $obrig]) {
            if ($obrig && (!isset($mapa[$k]) || $mapa[$k] === null || $mapa[$k] === '')) throw new HttpException(400, "Escolha qual coluna da planilha é \"$label\"");
        }
        $col = fn(array $v, string $k) => (isset($mapa[$k]) && $mapa[$k] !== null && $mapa[$k] !== '') ? trim((string) ($v[(int) $mapa[$k]] ?? '')) : '';

        $linhas = [];
        $vistos = [];
        // Cadastros existentes, pra avisar quando o nome não bate.
        $existe = [
            'formas' => array_flip(array_map('mb_strtolower', array_column(Database::all('SELECT nome FROM formas_pagamento'), 'nome'))),
            'caixas' => array_flip(array_map('mb_strtolower', array_column(Database::all('SELECT nome FROM caixas'), 'nome'))),
            'centros' => array_flip(array_map('mb_strtolower', array_column(Database::all('SELECT nome FROM centros_custo'), 'nome'))),
            'clientes_doc' => array_flip(array_filter(array_map(fn($x) => only_digits((string) $x), array_column(Database::all('SELECT cnpj_cpf FROM clientes'), 'cnpj_cpf')))),
            'clientes_nome' => array_flip(array_map('mb_strtolower', array_column(Database::all('SELECT nome FROM clientes'), 'nome'))),
            'forn_doc' => array_flip(array_filter(array_map(fn($x) => only_digits((string) $x), array_column(Database::all('SELECT cnpj_cpf FROM fornecedores'), 'cnpj_cpf')))),
            'forn_nome' => array_flip(array_map('mb_strtolower', array_column(Database::all('SELECT nome FROM fornecedores'), 'nome'))),
        ];
        $catCache = [];

        foreach ($d['linhas'] as $l) {
            $v = $l['v'];
            $erros = []; $avisos = [];
            $reg = [];
            if ($tipo === 'lancamentos') {
                $data = self::data($col($v, 'data'));
                if (!$data) $erros[] = $col($v, 'data') === '' ? 'sem data' : 'data inválida "' . $col($v, 'data') . '"';
                $valorTxt = $col($v, 'valor');
                $valor = self::valor($valorTxt);
                if ($valor === null) $erros[] = $valorTxt === '' ? 'sem valor' : 'valor inválido "' . $valorTxt . '"';
                $tp = self::tipo($col($v, 'tipo'));
                if (!$tp) {
                    if ($col($v, 'tipo') !== '') $avisos[] = 'tipo "' . $col($v, 'tipo') . '" não reconhecido';
                    $tp = $opcoes['tipo_padrao'] === 'sinal' ? (($valor ?? 0) < 0 ? 'expense' : 'income') : $opcoes['tipo_padrao'];
                }
                if ($valor !== null && $valor == 0.0) $erros[] = 'valor zerado';
                $valor = $valor !== null ? round(abs($valor), 2) : null;
                $venc = $col($v, 'data_vencimento') !== '' ? self::data($col($v, 'data_vencimento')) : null;
                if ($col($v, 'data_vencimento') !== '' && !$venc) $avisos[] = 'vencimento inválido ignorado';
                $pag = $col($v, 'data_pagamento') !== '' ? self::data($col($v, 'data_pagamento')) : null;
                if ($col($v, 'data_pagamento') !== '' && !$pag) $avisos[] = 'data de pagamento inválida ignorada';
                $st = self::status($col($v, 'status'));
                if (!$st) {
                    if ($col($v, 'status') !== '') $avisos[] = 'situação "' . $col($v, 'status') . '" não reconhecida';
                    $st = $pag ? 'quitado' : $opcoes['status_padrao'];
                }
                if ($st === 'quitado' && !$pag && $data) $pag = $data;
                if ($st === 'em_aberto') $pag = null;

                $catNome = $col($v, 'categoria');
                $catId = null; $catCriar = false;
                if ($catNome !== '') {
                    $ck = mb_strtolower($catNome) . '|' . $tp;
                    if (!array_key_exists($ck, $catCache)) $catCache[$ck] = Categoria::porNome($catNome, $tp);
                    if ($catCache[$ck]) { $catId = (int) $catCache[$ck]['id']; $catNome = $catCache[$ck]['nome']; }
                    elseif ($opcoes['criar_categorias']) { $catCriar = true; $avisos[] = "categoria \"$catNome\" será criada"; }
                    else { $avisos[] = "categoria \"$catNome\" não existe — fica sem categoria"; $catNome = ''; }
                }
                $forma = $col($v, 'forma_pagamento'); $caixa = $col($v, 'caixa'); $centro = $col($v, 'centro_custo');
                if ($forma !== '' && !isset($existe['formas'][mb_strtolower($forma)])) $avisos[] = "forma \"$forma\" não cadastrada (sem taxa)";
                if ($caixa !== '' && !isset($existe['caixas'][mb_strtolower($caixa)])) $avisos[] = "caixa \"$caixa\" não cadastrado";
                if ($centro !== '' && !isset($existe['centros'][mb_strtolower($centro)])) $avisos[] = "centro de custo \"$centro\" não cadastrado";
                $desc = mb_substr($col($v, 'descricao'), 0, 255);

                $reg = [
                    'tipo' => $tp, 'data' => $data, 'valor' => $valor, 'descricao' => $desc,
                    'categoria_id' => $catId, 'categoria_nome' => $catNome !== '' ? $catNome : null, 'categoria_criar' => $catCriar,
                    'cliente_fornecedor' => $col($v, 'cliente_fornecedor'), 'forma_pagamento' => $forma, 'caixa_banco' => $caixa,
                    'centro_custo' => $centro, 'grupo_contas' => $col($v, 'grupo'), 'conta' => $col($v, 'conta'),
                    'status' => $st, 'data_vencimento' => $venc, 'data_pagamento' => $pag, 'observacoes' => $col($v, 'observacoes'),
                ];
                $dup = false;
                if (!$erros) {
                    $chave = "$tp|$data|$valor|" . mb_strtolower($desc);
                    if (isset($vistos[$chave])) { $dup = true; $avisos[] = 'repetido na própria planilha (linha ' . $vistos[$chave] . ')'; }
                    elseif (Transacao::existeIgual($tp, $data, $valor, $desc)) { $dup = true; $avisos[] = 'já existe um lançamento igual no sistema'; }
                    $vistos[$chave] = $vistos[$chave] ?? $l['n'];
                }
            } else {
                $forn = $tipo === 'fornecedores';
                $in = [];
                foreach ($campos as $k => $c) $in[$k] = $col($v, $k);
                $dup = false;
                $in['ativo'] = '1';
                // Até 3 tentativas: e-mail ou documento inválido viram aviso (o campo é limpo) em vez de barrar a linha.
                for ($tentativa = 0; $tentativa < 3; $tentativa++) {
                    try {
                        $reg = $forn ? Fornecedor::campos($in, null, false) : Cliente::campos($in, null);
                        break;
                    } catch (HttpException $e) {
                        $msg = $e->getMessage();
                        $reg = $in;
                        if (stripos($msg, 'já cadastrado') !== false) { $dup = true; $avisos[] = 'documento já cadastrado no sistema'; break; }
                        if (stripos($msg, 'E-mail inválido') !== false && $in['email'] !== '') { $avisos[] = 'e-mail inválido ignorado'; $in['email'] = ''; continue; }
                        if (stripos($msg, 'inválido') !== false && ($in['cnpj_cpf'] ?? '') !== '') { $avisos[] = 'CPF/CNPJ inválido ignorado'; $in['cnpj_cpf'] = ''; continue; }
                        $erros[] = mb_strtolower(str_replace('Campo obrigatório: ', 'sem ', $msg));
                        break;
                    }
                }
                if (!$erros && !$dup && $reg) {
                    $docD = only_digits((string) ($reg['cnpj_cpf'] ?? ''));
                    $nomeL = mb_strtolower($reg['nome']);
                    $mapDoc = $forn ? 'forn_doc' : 'clientes_doc'; $mapNome = $forn ? 'forn_nome' : 'clientes_nome';
                    if (($docD && isset($existe[$mapDoc][$docD])) || isset($existe[$mapNome][$nomeL])) { $dup = true; $avisos[] = 'já cadastrado no sistema'; }
                    elseif (isset($vistos[$nomeL]) || ($docD && isset($vistos[$docD]))) { $dup = true; $avisos[] = 'repetido na própria planilha'; }
                    $vistos[$nomeL] = true; if ($docD) $vistos[$docD] = true;
                }
            }
            $situacao = $erros ? 'erro' : ($dup ? 'duplicado' : ($avisos ? 'aviso' : 'ok'));
            $linhas[] = ['linha' => $l['n'], 'situacao' => $situacao, 'erros' => $erros, 'avisos' => $avisos, 'dados' => $reg];
        }

        $cont = ['ok' => 0, 'aviso' => 0, 'erro' => 0, 'duplicado' => 0];
        foreach ($linhas as $l) $cont[$l['situacao']]++;
        $importaveis = array_filter($linhas, fn($l) => self::importavel($l, $tipo, $opcoes));
        $soma = ['income' => 0.0, 'expense' => 0.0];
        if ($tipo === 'lancamentos') foreach ($importaveis as $l) $soma[$l['dados']['tipo']] += (float) $l['dados']['valor'];
        return [
            'tipo' => $tipo, 'arquivo' => $d['arquivo'], 'opcoes' => $opcoes, 'total' => count($linhas), 'contagem' => $cont,
            'importaveis' => count($importaveis), 'soma' => $soma, 'linhas' => $linhas,
        ];
    }

    /** Linha entra na importação? Duplicado só entra em lançamentos e se a pessoa desmarcar "ignorar duplicados". */
    private static function importavel(array $l, string $tipo, array $op): bool
    {
        if ($l['situacao'] === 'ok' || $l['situacao'] === 'aviso') return true;
        return $l['situacao'] === 'duplicado' && $tipo === 'lancamentos' && !$op['ignorar_duplicados'];
    }

    // ─── Passo 3: confirmar ─────────────────────────────────────────────────
    public static function confirmar(string $token, array $mapa, array $opcoes): array
    {
        $prev = self::processar($token, $mapa, $opcoes);
        $op = $prev['opcoes'];
        $tipo = $prev['tipo'];
        $linhas = array_values(array_filter($prev['linhas'], fn($l) => self::importavel($l, $tipo, $op)));
        if (!$linhas) throw new HttpException(400, 'Nenhuma linha válida para importar');
        $u = Auth::user();

        $res = Database::transaction(function () use ($linhas, $tipo, $prev, $u) {
            $impId = Importacao::create([
                'tipo' => $tipo, 'arquivo_nome' => $prev['arquivo'], 'total_linhas' => $prev['total'],
                'importados' => 0, 'ignorados' => 0, 'user_id' => $u['id'], 'username' => $u['username'],
            ]);
            $n = 0; $total = 0.0; $catsCriadas = [];
            foreach ($linhas as $l) {
                $r = $l['dados'];
                if ($tipo === 'lancamentos') {
                    if ($r['categoria_criar'] && $r['categoria_nome']) {
                        $ck = mb_strtolower($r['categoria_nome']) . '|' . $r['tipo'];
                        if (!isset($catsCriadas[$ck])) {
                            $ex = Categoria::porNome($r['categoria_nome'], $r['tipo']);
                            $catsCriadas[$ck] = $ex ? (int) $ex['id'] : Categoria::create(['nome' => mb_substr($r['categoria_nome'], 0, 150), 'tipo' => $r['tipo'], 'cor' => '#5E6E63', 'descricao' => 'Criada na importação', 'ativo' => 1]);
                        }
                        $r['categoria_id'] = $catsCriadas[$ck];
                    }
                    $f = Transacao::campos($r);
                    $f['user_id'] = $u['id'];
                    $f['importacao_id'] = $impId;
                    Transacao::create($f);
                    $total += $f['tipo'] === 'income' ? $f['valor'] : -$f['valor'];
                } elseif ($tipo === 'clientes') {
                    Cliente::create($r + ['importacao_id' => $impId]);
                } else {
                    Fornecedor::create($r + ['importacao_id' => $impId]);
                }
                $n++;
            }
            Importacao::update($impId, ['importados' => $n, 'ignorados' => $prev['total'] - $n, 'valor_total' => round($total, 2)]);
            return ['id' => $impId, 'importados' => $n, 'ignorados' => $prev['total'] - $n, 'categorias_criadas' => count(array_filter($catsCriadas)), 'saldo' => round($total, 2)];
        });
        @unlink(self::caminho($token));
        return $res + ['tipo' => $tipo, 'arquivo' => $prev['arquivo']];
    }

    // ─── Modelos de planilha ────────────────────────────────────────────────
    public static function modelo(string $tipo): array
    {
        $hoje = date('d/m/Y');
        if ($tipo === 'lancamentos') return [
            ['Data', 'Tipo', 'Descrição', 'Valor', 'Categoria', 'Cliente/Fornecedor', 'Forma de pagamento', 'Caixa/Banco', 'Centro de custo', 'Situação', 'Vencimento', 'Data de pagamento', 'Observações'],
            [$hoje, 'Receita', 'Consulta particular', 350.00, 'Consultas', 'Maria Silva', 'PIX', 'Itaú', 'RECEITA OPERACIONAL', 'Quitado', '', $hoje, ''],
            [$hoje, 'Despesa', 'Aluguel do mês', 2500.00, 'Aluguel', 'Imobiliária Exemplo', 'BOLETO BANCÁRIO', 'Itaú', 'DESPESAS OPERACIONAIS', 'Em aberto', $hoje, '', 'Vence todo dia 10'],
        ];
        if ($tipo === 'clientes') return [
            ['Nome', 'Razão social', 'CPF/CNPJ', 'E-mail', 'Telefone', 'Estado', 'Cidade', 'Endereço', 'Observações'],
            ['Maria Silva', '', '', 'maria@exemplo.com', '(15) 99999-0000', 'SP', 'Sorocaba', 'Rua Exemplo, 100', ''],
        ];
        return [
            ['Nome', 'Razão social', 'CNPJ/CPF', 'E-mail', 'Telefone', 'WhatsApp', 'Responsável', 'Estado', 'Cidade', 'Endereço', 'Observações'],
            ['Papelaria Exemplo', 'Papelaria Exemplo LTDA', '', 'contato@exemplo.com', '(15) 3333-0000', '(15) 99999-0000', 'João', 'SP', 'Sorocaba', '', ''],
        ];
    }

    // ─── Arquivos temporários ───────────────────────────────────────────────
    private static function caminho(string $token): string
    {
        $dir = STORAGE_DIR . '/imports';
        if (!is_dir($dir)) @mkdir($dir, 0770, true);
        return "$dir/$token.json";
    }

    private static function limparAntigos(): void
    {
        foreach (glob(STORAGE_DIR . '/imports/*.json') ?: [] as $f) if (filemtime($f) < time() - 86400) @unlink($f);
    }
}
