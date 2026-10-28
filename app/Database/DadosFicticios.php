<?php
namespace App\Database;

use App\Core\Database;
use App\Core\Termos;
use App\Models\Transacao;
use App\Services\Spreadsheet\PdfWriter;

/**
 * Dados fictícios pra testar o sistema (uma clínica de jan/2025 a dez/2026).
 *
 *   php app/Database/DadosFicticios.php            → gera (só se ainda não houver lançamentos)
 *   php app/Database/DadosFicticios.php --limpar   → APAGA lançamentos, transferências, notas, clientes,
 *                                                     fornecedores e parceiros e gera tudo de novo
 *
 * No XAMPP: XAMPP Control Panel → botão "Shell" → cd htdocs\DRE-php → php app\Database\DadosFicticios.php
 *
 * O que é criado:
 *  - 3 contas + 1 aplicação, formas de pagamento com taxas reais de mercado, categorias e plano de contas
 *  - 30 clientes, 14 fornecedores (CPF/CNPJ válidos), 3 parceiros
 *  - ~2.700 lançamentos: consultas, procedimentos e convênios (receitas) e aluguel, folha, impostos,
 *    contas de consumo, software, marketing, materiais (despesas). Passado quitado (com alguns atrasados
 *    de propósito), meses futuros em aberto — o Calendário e o Fluxo previsto ficam cheios.
 *  - transferências (depósito do dinheiro da recepção e aplicação mensal)
 *  - ~40 notas fiscais com XML e PDF de verdade (NF-e de compra e NFS-e de convênio), a maioria vinculada,
 *    algumas sem vínculo e uma só com PDF, pra testar todas as telas
 *  - usuários de teste: financeiro / Demo1234 e consulta / Demo1234 (e admin / Admin1234 se o banco não tiver ninguém)
 *
 * Os números são gerados sempre iguais (semente fixa), então dá pra conferir o DRE à mão.
 */
final class DadosFicticios
{
    private const INICIO = '2025-01-01';
    private const FIM = '2026-12-31';
    private string $hoje;
    private int $nTx = 0;
    private array $txMateriais = [];
    private array $txConvenios = [];

    public static function executar(bool $limpar): array
    {
        return (new self())->rodar($limpar);
    }

    private function rodar(bool $limpar): array
    {
        mt_srand(2026);
        $this->hoje = date('Y-m-d');
        Migrator::migrar();
        if ($limpar) $this->limpar();
        elseif ((int) Database::value('SELECT COUNT(*) FROM transactions')) {
            throw new \RuntimeException("O banco já tem lançamentos. Use --limpar para apagar os dados de movimento e gerar de novo (ou aponte o .env para um banco de testes).");
        }
        Database::transaction(function () {
            $this->usuarios();
            $this->cadastros();
            $this->lancamentos();
            $this->transferencias();
        });
        $nf = $this->notas(); // arquivos em disco: fora da transação
        return [
            'lancamentos' => (int) Database::value('SELECT COUNT(*) FROM transactions'),
            'transferencias' => (int) Database::value('SELECT COUNT(*) FROM transfers'),
            'notas' => $nf,
            'clientes' => (int) Database::value('SELECT COUNT(*) FROM clientes'),
            'fornecedores' => (int) Database::value('SELECT COUNT(*) FROM fornecedores'),
        ];
    }

    private function limpar(): void
    {
        foreach (Database::all('SELECT pdf_path, xml_path FROM notas_fiscais') as $n)
            foreach ($n as $p) if ($p && strpos($p, 'storage/notas/') === 0 && is_file(ROOT_DIR . '/' . $p)) @unlink(ROOT_DIR . '/' . $p);
        foreach (['notas_fiscais', 'transactions', 'transfers', 'documentos', 'importacoes', 'parceiros', 'fornecedores', 'clientes'] as $t) Database::run("DELETE FROM `$t`");
    }

    // ─── Usuários de teste ──────────────────────────────────────────────────
    private function usuarios(): void
    {
        $perfil = fn(string $nome) => Database::value('SELECT id FROM perfis WHERE nome=? LIMIT 1', [$nome]);
        $criar = function (string $user, string $nome, string $senha, $perfilId, bool $termos) {
            if (Database::one('SELECT id FROM users WHERE username=?', [$user])) return;
            Database::run('INSERT INTO users (username, nome, email, password, role, perfil_id, ativo, senha_alterada_em, termos_versao, termos_aceitos_em) VALUES (?,?,?,?,?,?,1,NOW(),?,?)',
                [$user, $nome, "$user@exemplo.com", password_hash($senha, PASSWORD_BCRYPT), $user === 'admin' ? 'admin' : 'user', $perfilId,
                 $termos ? Termos::VERSAO : null, $termos ? date('Y-m-d H:i:s') : null]);
        };
        if (!(int) Database::value('SELECT COUNT(*) FROM users')) $criar('admin', 'Administrador', 'Admin1234', $perfil('Administrador'), true);
        $criar('financeiro', 'Fernanda Lima (financeiro)', 'Demo1234', $perfil('Financeiro'), true);
        $criar('consulta', 'Carlos Souza (só consulta)', 'Demo1234', $perfil('Consulta'), false); // vê o aceite LGPD no 1º login
    }

    // ─── Cadastros ──────────────────────────────────────────────────────────
    private function cadastros(): void
    {
        $caixas = [['Itaú', 'banco', 'Itaú', '0350', '12345-6', 15000], ['NU BANK', 'banco', 'Nubank', '0001', '9876543-2', 5000],
                   ['Caixa da recepção', 'caixa', null, null, null, 800], ['Aplicação CDB', 'investimento', 'Itaú', '0350', '12345-6', 20000]];
        foreach ($caixas as [$n, $t, $b, $ag, $ct, $si]) {
            if (Database::one('SELECT id FROM caixas WHERE nome=?', [$n])) Database::run('UPDATE caixas SET tipo=?, banco=?, agencia=?, conta=?, saldo_inicial=? WHERE nome=?', [$t, $b, $ag, $ct, $si, $n]);
            else Database::run('INSERT INTO caixas (nome, tipo, banco, agencia, conta, saldo_inicial) VALUES (?,?,?,?,?,?)', [$n, $t, $b, $ag, $ct, $si]);
        }
        // nome, tipo, parcelas, dias p/ receber, taxa %, tarifa fixa
        $formas = [['PIX', 'pix', 1, 0, 0, 0], ['CARTÃO CRÉDITO', 'credito', 1, 30, 3.19, 0], ['CARTÃO DÉBITO', 'debito', 1, 1, 1.49, 0],
                   ['BOLETO BANCÁRIO', 'boleto', 1, 2, 0, 2.50], ['DINHEIRO', 'dinheiro', 1, 0, 0, 0], ['TRANSFERÊNCIA BANCÁRIA (TED/DOC)', 'transferencia', 1, 0, 0, 0]];
        foreach ($formas as [$n, $t, $p, $d, $tx, $tf]) {
            if (Database::one('SELECT id FROM formas_pagamento WHERE nome=?', [$n]))
                Database::run('UPDATE formas_pagamento SET tipo=?, parcelas=?, dias_recebimento=?, taxa_intermediacao=?, tarifa_fixa=?, ativo=1 WHERE nome=?', [$t, $p, $d, $tx, $tf, $n]);
            else Database::run('INSERT INTO formas_pagamento (nome, tipo, parcelas, dias_recebimento, taxa_intermediacao, taxa_parcelamento, tarifa_fixa, ativo) VALUES (?,?,?,?,?,0,?,1)', [$n, $t, $p, $d, $tx, $tf]);
        }
        // Forma antiga, inativa (pra testar o reativar)
        if (!Database::one("SELECT id FROM formas_pagamento WHERE nome='CHEQUE'"))
            Database::run("INSERT INTO formas_pagamento (nome, tipo, parcelas, dias_recebimento, ativo) VALUES ('CHEQUE','boleto',1,3,0)");

        $cats = [['Consultas', 'income', '#147A4A'], ['Procedimentos', 'income', '#0E6E7A'], ['Convênios', 'income', '#2F5E7E'], ['Outras Receitas', 'income', '#6B5CA5'],
                 ['Aluguel', 'expense', '#B33F3F'], ['Folha / Prestadores', 'expense', '#B98B2E'], ['Impostos', 'expense', '#B0577D'], ['Marketing', 'expense', '#1F5FBF'],
                 ['Materiais', 'expense', '#5E6E63'], ['Energia, água e internet', 'expense', '#C26A2E'], ['Software', 'expense', '#4A4E9C'], ['Outras Despesas', 'expense', '#8D97AC']];
        foreach ($cats as [$n, $t, $c]) if (!Database::one('SELECT id FROM categorias WHERE nome=? AND tipo=?', [$n, $t])) Database::run('INSERT INTO categorias (nome, tipo, cor, ativo) VALUES (?,?,?,1)', [$n, $t, $c]);

        $nomes = ['Ana Paula Ribeiro', 'Bruno Carvalho', 'Camila Duarte', 'Diego Martins', 'Eduarda Nogueira', 'Felipe Andrade', 'Gabriela Costa', 'Henrique Lopes',
                  'Isabela Fernandes', 'João Pedro Alves', 'Karina Moreira', 'Lucas Teixeira', 'Mariana Rocha', 'Nicolas Barbosa', 'Olívia Cardoso', 'Paulo Henrique Dias',
                  'Rafaela Pinto', 'Samuel Freitas', 'Tatiane Gomes', 'Vinícius Araújo', 'Larissa Mendes', 'Gustavo Ramos', 'Beatriz Campos', 'Rodrigo Pires', 'Juliana Prado'];
        foreach ($nomes as $i => $n)
            Database::run('INSERT INTO clientes (nome, cnpj_cpf, estado, cidade, telefone, email) VALUES (?,?,?,?,?,?)',
                [$n, self::fmtCpf(self::cpf()), 'SP', 'Sorocaba', sprintf('(15) 9%04d-%04d', mt_rand(1000, 9999), mt_rand(1000, 9999)), self::email($n)]);
        foreach (['Unimed Sorocaba', 'Bradesco Saúde', 'SulAmérica Saúde', 'Amil Assistência', 'Prefeitura de Votorantim (convênio)'] as $n)
            Database::run('INSERT INTO clientes (nome, razao_social, cnpj_cpf, estado, cidade) VALUES (?,?,?,?,?)', [$n, $n . ' LTDA', self::fmtCnpj(self::cnpj()), 'SP', 'Sorocaba']);

        $forn = [['Imobiliária Central', 'Imobiliária Central Sorocaba LTDA'], ['CPFL Paulista', 'Companhia Paulista de Força e Luz'], ['SAAE Sorocaba', 'Serviço Autônomo de Água e Esgoto'],
                 ['Vivo Empresas', 'Telefônica Brasil S.A.'], ['Dental Med Distribuidora', 'Dental Med Distribuidora de Produtos Médicos LTDA'], ['Cirúrgica Paulista', 'Cirúrgica Paulista Comércio LTDA'],
                 ['Papelaria Exemplo', 'Papelaria Exemplo LTDA'], ['Clinicorp Software', 'Clinicorp Sistemas de Gestão LTDA'], ['Agência Pulso Marketing', 'Pulso Comunicação Digital LTDA'],
                 ['Contabilidade Silva', 'Silva & Associados Contabilidade'], ['Limpa Bem Serviços', 'Limpa Bem Serviços de Limpeza LTDA'], ['Dra. Renata Oliveira (PJ)', 'R. Oliveira Serviços Médicos LTDA'],
                 ['Dr. Marcos Vieira (PJ)', 'M. Vieira Clínica Médica LTDA'], ['Receita Federal (DAS)', 'Simples Nacional']];
        foreach ($forn as [$n, $r]) Database::run('INSERT INTO fornecedores (nome, razao_social, cnpj_cpf, estado, cidade, email, ativo) VALUES (?,?,?,?,?,?,1)',
            [$n, $r, self::fmtCnpj(self::cnpj()), 'SP', 'Sorocaba', self::email($n)]);
        Database::run("UPDATE fornecedores SET ativo=0 WHERE nome='Limpa Bem Serviços'"); // fornecedor desativado

        $parc = [['Laboratório Vida', 'Laboratório Vida Análises Clínicas LTDA', 10], ['Ótica Visão', 'Ótica Visão Sorocaba LTDA', 8], ['Farmácia Bem Estar', 'Bem Estar Drogaria LTDA', 5]];
        foreach ($parc as [$n, $r, $c]) Database::run('INSERT INTO parceiros (nome, razao_social, cnpj, email, comissao, contato, responsavel, ativo) VALUES (?,?,?,?,?,?,?,1)',
            [$n, $r, self::fmtCnpj(self::cnpj()), self::email($n), $c, sprintf('(15) 3%03d-%04d', mt_rand(100, 999), mt_rand(1000, 9999)), 'Responsável ' . explode(' ', $n)[0]]);

        $cr = [['Atendimento particular', 'Consultas'], ['Atendimento particular', 'Procedimentos'], ['Convênios', 'Repasse de convênios']];
        $cd = [['Ocupação', 'Aluguel'], ['Pessoal', 'Prestadores médicos'], ['Pessoal', 'Equipe administrativa'], ['Tributos', 'Simples Nacional'],
               ['Consumo', 'Energia, água e internet'], ['Administrativo', 'Software e sistemas'], ['Comercial', 'Marketing digital'], ['Operacional', 'Materiais e insumos']];
        foreach ($cr as [$g, $c]) if (!Database::one('SELECT id FROM contas_receita WHERE grupo=? AND conta=?', [$g, $c])) Database::run('INSERT INTO contas_receita (grupo, conta) VALUES (?,?)', [$g, $c]);
        foreach ($cd as [$g, $c]) if (!Database::one('SELECT id FROM contas_despesa WHERE grupo=? AND conta=?', [$g, $c])) Database::run('INSERT INTO contas_despesa (grupo, conta) VALUES (?,?)', [$g, $c]);
    }

    // ─── Lançamentos ────────────────────────────────────────────────────────
    private function cat(string $nome, string $tipo): ?int
    {
        static $c = [];
        return $c["$nome|$tipo"] ??= (int) Database::value('SELECT id FROM categorias WHERE nome=? AND tipo=?', [$nome, $tipo]) ?: null;
    }

    /** Grava usando a mesma regra do sistema (Transacao::campos calcula a taxa e o líquido). */
    private function tx(array $d): int
    {
        $d['categoria_id'] = $this->cat($d['categoria'], $d['tipo']);
        unset($d['categoria']);
        $f = Transacao::campos($d);
        $f['user_id'] = 1;
        $this->nTx++;
        return Transacao::create($f);
    }

    /**
     * Situação de acordo com hoje: vencimento no futuro → em aberto; no passado → quitado,
     * menos uma pequena parte que fica atrasada de propósito (inadimplência / conta esquecida).
     */
    private function situacao(string $venc, float $chanceAtraso, int $diasPagamento = 0): array
    {
        $pag = date('Y-m-d', strtotime("$venc +$diasPagamento day"));
        if ($pag > $this->hoje) return ['status' => 'em_aberto', 'data_pagamento' => null];
        $recente = strtotime($this->hoje) - strtotime($venc) < 75 * 86400; // atrasos só nos últimos ~2 meses
        if ($recente && mt_rand(1, 1000) <= $chanceAtraso * 1000) return ['status' => 'em_aberto', 'data_pagamento' => null];
        return ['status' => 'quitado', 'data_pagamento' => $pag];
    }

    private static function diaUtil(string $d): string
    {
        while ((int) date('N', strtotime($d)) >= 6) $d = date('Y-m-d', strtotime("$d +1 day"));
        return $d;
    }

    private function lancamentos(): void
    {
        $clientes = array_column(Database::all("SELECT nome FROM clientes WHERE cnpj_cpf NOT LIKE '%/%' ORDER BY id"), 'nome');
        $convenios = array_column(Database::all("SELECT nome FROM clientes WHERE cnpj_cpf LIKE '%/%' ORDER BY id"), 'nome');
        $sorteia = function (array $pesos) { $r = mt_rand(1, array_sum($pesos)); foreach ($pesos as $k => $p) { if (($r -= $p) <= 0) return $k; } return array_key_first($pesos); };
        $formasRec = ['PIX' => 40, 'CARTÃO CRÉDITO' => 30, 'CARTÃO DÉBITO' => 20, 'DINHEIRO' => 10];
        $diasForma = ['PIX' => 0, 'CARTÃO CRÉDITO' => 30, 'CARTÃO DÉBITO' => 1, 'DINHEIRO' => 0];
        $caixaForma = fn($f) => $f === 'DINHEIRO' ? 'Caixa da recepção' : ($f === 'PIX' ? 'NU BANK' : 'Itaú');

        $mes = new \DateTime(self::INICIO);
        $fim = new \DateTime(self::FIM);
        $receitaMesAnterior = 38000.0;
        while ($mes <= $fim) {
            $y = (int) $mes->format('Y'); $m = (int) $mes->format('n'); $ult = (int) $mes->format('t');
            $sazonal = [1 => 0.85, 2 => 0.9, 3 => 1.05, 4 => 1.0, 5 => 1.05, 6 => 0.95, 7 => 0.9, 8 => 1.05, 9 => 1.05, 10 => 1.1, 11 => 1.05, 12 => 0.85][$m];
            $cresc = $y === 2026 ? 1.12 : 1.0; // 2026 cresce ~12% sobre 2025 (aparece no AH% do DRE)
            $receitaMes = 0.0;

            // Receitas particulares: consultas e procedimentos, em dias úteis
            for ($d = 1; $d <= $ult; $d++) {
                $data = sprintf('%04d-%02d-%02d', $y, $m, $d);
                if ((int) date('N', strtotime($data)) >= 6) continue;
                $x = mt_rand(200, 400) / 100 * $sazonal * $cresc;
                $qtd = (int) floor($x) + (mt_rand(1, 100) <= ($x - floor($x)) * 100 ? 1 : 0);
                for ($i = 0; $i < $qtd; $i++) {
                    $proc = mt_rand(1, 100) <= 18;
                    $valor = $proc ? mt_rand(80, 280) * 10 : [250, 300, 350, 400][mt_rand(0, 3)];
                    $forma = $sorteia($formasRec);
                    $s = $this->situacao($data, $forma === 'CARTÃO CRÉDITO' ? 0 : 0.02, $diasForma[$forma]);
                    $this->tx(['tipo' => 'income', 'data' => $data, 'valor' => $valor, 'categoria' => $proc ? 'Procedimentos' : 'Consultas',
                        'descricao' => ($proc ? 'Procedimento' : 'Consulta') . ' — ' . ($cli = $clientes[mt_rand(0, count($clientes) - 1)]),
                        'cliente_fornecedor' => $cli, 'forma_pagamento' => $forma, 'caixa_banco' => $caixaForma($forma),
                        'centro_custo' => 'RECEITA OPERACIONAL', 'grupo' => 'Atendimento particular', 'conta' => $proc ? 'Procedimentos' : 'Consultas',
                        'data_vencimento' => date('Y-m-d', strtotime("$data +{$diasForma[$forma]} day"))] + $s);
                    $receitaMes += $valor;
                }
            }
            // Convênios: faturados no fim do mês, recebidos em 30 dias por boleto (contas a receber)
            foreach (array_slice($convenios, 0, 3) as $k => $conv) {
                $valor = round(mt_rand(250, 700) * 10 * $sazonal * $cresc, 2);
                $data = sprintf('%04d-%02d-%02d', $y, $m, $ult);
                $venc = self::diaUtil(date('Y-m-d', strtotime("$data +30 day")));
                $s = $this->situacao($venc, 0.25);
                $id = $this->tx(['tipo' => 'income', 'data' => $data, 'valor' => $valor, 'categoria' => 'Convênios',
                    'descricao' => sprintf('Faturamento %s %02d/%d', $conv, $m, $y), 'cliente_fornecedor' => $conv, 'forma_pagamento' => 'BOLETO BANCÁRIO',
                    'caixa_banco' => 'Itaú', 'centro_custo' => 'RECEITA COMUM', 'grupo' => 'Convênios', 'conta' => 'Repasse de convênios', 'data_vencimento' => $venc] + $s);
                $this->txConvenios[] = ['id' => $id, 'cliente' => $conv, 'valor' => $valor, 'data' => $data, 'venc' => $venc, 'mes' => $m, 'ano' => $y];
                $receitaMes += $valor;
            }
            // Outras receitas: comissão de parceiros, a cada 2 meses
            if ($m % 2 === 0) {
                $data = self::diaUtil(sprintf('%04d-%02d-15', $y, $m));
                $this->tx(['tipo' => 'income', 'data' => $data, 'valor' => mt_rand(40, 120) * 10, 'categoria' => 'Outras Receitas', 'descricao' => 'Comissão de parceiros (laboratório e ótica)',
                    'cliente_fornecedor' => '', 'forma_pagamento' => 'PIX', 'caixa_banco' => 'NU BANK', 'centro_custo' => 'RECEITA COMUM', 'data_vencimento' => $data] + $this->situacao($data, 0));
            }

            // Despesas fixas
            $fixas = [
                [5, 'Folha / Prestadores', 'Dra. Renata Oliveira (PJ)', 'Repasse médico — Dra. Renata', 9500 * $cresc, 'TRANSFERÊNCIA BANCÁRIA (TED/DOC)', 'Pessoal', 'Prestadores médicos'],
                [5, 'Folha / Prestadores', 'Dr. Marcos Vieira (PJ)', 'Repasse médico — Dr. Marcos', 8200 * $cresc, 'TRANSFERÊNCIA BANCÁRIA (TED/DOC)', 'Pessoal', 'Prestadores médicos'],
                [5, 'Folha / Prestadores', '', 'Salários equipe recepção (2 pessoas)', 5600 * ($y === 2026 ? 1.06 : 1), 'TRANSFERÊNCIA BANCÁRIA (TED/DOC)', 'Pessoal', 'Equipe administrativa'],
                [10, 'Aluguel', 'Imobiliária Central', 'Aluguel sala comercial', $y === 2026 ? 4780 : 4500, 'BOLETO BANCÁRIO', 'Ocupação', 'Aluguel'],
                [12, 'Marketing', 'Agência Pulso Marketing', 'Gestão de redes sociais e anúncios', 1800, 'PIX', 'Comercial', 'Marketing digital'],
                [8, 'Software', 'Clinicorp Software', 'Sistema de agenda e prontuário', 389.90, 'CARTÃO CRÉDITO', 'Administrativo', 'Software e sistemas'],
                [15, 'Energia, água e internet', 'CPFL Paulista', 'Conta de energia', mt_rand(68000, 98000) / 100, 'BOLETO BANCÁRIO', 'Consumo', 'Energia, água e internet'],
                [18, 'Energia, água e internet', 'SAAE Sorocaba', 'Conta de água', mt_rand(14000, 21000) / 100, 'BOLETO BANCÁRIO', 'Consumo', 'Energia, água e internet'],
                [20, 'Energia, água e internet', 'Vivo Empresas', 'Internet e telefone', 249.90, 'BOLETO BANCÁRIO', 'Consumo', 'Energia, água e internet'],
                [20, 'Impostos', 'Receita Federal (DAS)', sprintf('Simples Nacional (DAS) ref. %02d/%d', $m === 1 ? 12 : $m - 1, $m === 1 ? $y - 1 : $y), round($receitaMesAnterior * 0.112, 2), 'BOLETO BANCÁRIO', 'Tributos', 'Simples Nacional'],
                [25, 'Outras Despesas', 'Contabilidade Silva', 'Honorários contábeis', 950, 'PIX', 'Administrativo', 'Software e sistemas'],
                [28, 'Outras Despesas', $y === 2025 && $m < 7 ? 'Limpa Bem Serviços' : '', 'Limpeza e conservação', 1100, 'PIX', 'Operacional', 'Materiais e insumos'],
            ];
            foreach ($fixas as [$dia, $cat, $forn, $desc, $valor, $forma, $g, $c]) {
                $venc = self::diaUtil(sprintf('%04d-%02d-%02d', $y, $m, min($dia, $ult)));
                $this->tx(['tipo' => 'expense', 'data' => $venc, 'valor' => round($valor, 2), 'categoria' => $cat, 'descricao' => $desc, 'cliente_fornecedor' => $forn,
                    'forma_pagamento' => $forma, 'caixa_banco' => 'Itaú', 'centro_custo' => $cat === 'Impostos' ? 'DESPESAS DE IMPOSTOS' : ($g === 'Pessoal' || $g === 'Administrativo' ? 'DESPESAS ADMINISTRATIVAS' : 'DESPESAS OPERACIONAIS'),
                    'grupo' => $g, 'conta' => $c, 'data_vencimento' => $venc] + $this->situacao($venc, 0.06));
            }
            // Materiais: 2 a 3 compras por mês, com nota fiscal (NF-e)
            for ($k = 0, $n = mt_rand(2, 3); $k < $n; $k++) {
                $forn = ['Dental Med Distribuidora', 'Cirúrgica Paulista', 'Papelaria Exemplo'][$k % 3];
                $data = self::diaUtil(sprintf('%04d-%02d-%02d', $y, $m, mt_rand(2, min(26, $ult))));
                $valor = $forn === 'Papelaria Exemplo' ? mt_rand(18000, 52000) / 100 : mt_rand(90000, 380000) / 100;
                $venc = self::diaUtil(date('Y-m-d', strtotime("$data +28 day")));
                $id = $this->tx(['tipo' => 'expense', 'data' => $data, 'valor' => $valor, 'categoria' => 'Materiais',
                    'descricao' => ($forn === 'Papelaria Exemplo' ? 'Material de escritório' : 'Materiais e insumos médicos') . ' — ' . $forn, 'cliente_fornecedor' => $forn,
                    'forma_pagamento' => 'BOLETO BANCÁRIO', 'caixa_banco' => 'Itaú', 'centro_custo' => 'DESPESAS OPERACIONAIS', 'grupo' => 'Operacional', 'conta' => 'Materiais e insumos',
                    'data_vencimento' => $venc] + $this->situacao($venc, 0.1));
                $this->txMateriais[] = ['id' => $id, 'fornecedor' => $forn, 'valor' => $valor, 'data' => $data, 'venc' => $venc];
            }
            // Despesa pontual grande (equipamento) — mostra um mês com prejuízo no DRE
            if (($y === 2025 && $m === 9) || ($y === 2026 && $m === 3)) {
                $data = self::diaUtil(sprintf('%04d-%02d-10', $y, $m));
                $this->tx(['tipo' => 'expense', 'data' => $data, 'valor' => $y === 2025 ? 48900 : 28000, 'categoria' => 'Materiais',
                    'descricao' => $y === 2025 ? 'Aparelho de ultrassom (à vista)' : 'Reforma da sala 2', 'cliente_fornecedor' => $y === 2025 ? 'Cirúrgica Paulista' : '',
                    'forma_pagamento' => 'TRANSFERÊNCIA BANCÁRIA (TED/DOC)', 'caixa_banco' => 'Aplicação CDB', 'centro_custo' => 'DESPESAS OPERACIONAIS', 'data_vencimento' => $data] + $this->situacao($data, 0));
            }
            $receitaMesAnterior = $receitaMes;
            $mes->modify('first day of next month');
        }
    }

    private function transferencias(): void
    {
        $mes = new \DateTime(self::INICIO);
        while ($mes->format('Y-m-d') <= $this->hoje) {
            $y = (int) $mes->format('Y'); $m = (int) $mes->format('n');
            // Depósito do dinheiro da recepção no Itaú (toda sexta-feira com dinheiro na semana)
            $din = (float) Database::value("SELECT COALESCE(SUM(valor),0) FROM transactions WHERE caixa_nome='Caixa da recepção' AND tipo='income' AND status='quitado' AND YEAR(data)=? AND MONTH(data)=?", [$y, $m]);
            $dep = self::diaUtil($mes->format('Y-m-t')) > $mes->format('Y-m-t') ? date('Y-m-d', strtotime($mes->format('Y-m-t') . ' -2 day')) : self::diaUtil($mes->format('Y-m-t'));
            if ($din > 0 && $dep <= $this->hoje) Database::run('INSERT INTO transfers (data, descricao, valor, origem_nome, destino_nome, user_id) VALUES (?,?,?,?,?,1)',
                [$dep, 'Depósito do dinheiro da recepção', round($din * 0.9, 2), 'Caixa da recepção', 'Itaú']);
            // Aplicação mensal e resgate antes da compra grande
            $apl = self::diaUtil(sprintf('%04d-%02d-06', $y, $m));
            if ($apl <= $this->hoje) Database::run('INSERT INTO transfers (data, descricao, valor, origem_nome, destino_nome, user_id) VALUES (?,?,?,?,?,1)', [$apl, 'Aplicação mensal', 4000, 'Itaú', 'Aplicação CDB']);
            // PIX recebido no Nubank vai pro Itaú todo dia 20
            // PIX recebido no Nubank: tudo vai pro Itaú no último dia útil (é de lá que saem as contas)
            $pixMes = (float) Database::value("SELECT COALESCE(SUM(valor),0) FROM transactions WHERE caixa_nome='NU BANK' AND tipo='income' AND status='quitado' AND YEAR(COALESCE(data_pagamento,data))=? AND MONTH(COALESCE(data_pagamento,data))=?", [$y, $m]);
            if ($pixMes > 0 && $dep <= $this->hoje) Database::run('INSERT INTO transfers (data, descricao, valor, origem_nome, destino_nome, user_id) VALUES (?,?,?,?,?,1)', [$dep, 'Concentração de saldo no Itaú', round($pixMes, 2), 'NU BANK', 'Itaú']);
            $mes->modify('first day of next month');
        }
    }

    // ─── Notas fiscais com XML e PDF ────────────────────────────────────────
    private function notas(): int
    {
        $dir = STORAGE_DIR . '/notas';
        if (!is_dir($dir)) mkdir($dir, 0770, true);
        $empresa = '11222333000181'; // CNPJ fictício da clínica (válido)
        $docDe = fn(string $nome, string $tab) => only_digits((string) Database::value("SELECT cnpj_cpf FROM `$tab` WHERE nome=?", [$nome]));
        $n = 0;
        // NF-e de compra (materiais) dos últimos 12 meses — 85% vinculadas, o resto pra testar "Vincular"
        $compras = array_values(array_filter($this->txMateriais, fn($t) => $t['data'] <= $this->hoje && $t['data'] >= date('Y-m-d', strtotime($this->hoje . ' -12 months'))));
        foreach ($compras as $i => $t) {
            $num = 4100 + $i * 7;
            $cnpj = $docDe($t['fornecedor'], 'fornecedores');
            $chave = self::chaveNfe($cnpj, $t['data'], $num);
            $itens = $t['fornecedor'] === 'Papelaria Exemplo' ? [['Papel A4 500 fls', 0.35], ['Toner HP 85A', 0.45], ['Canetas e pastas', 0.2]]
                   : [['Luvas de procedimento cx 100', 0.3], ['Seringas descartáveis 10ml', 0.25], ['Gaze estéril pct', 0.2], ['Álcool 70% 1L', 0.25]];
            $dups = $t['valor'] > 2000 ? [[$t['venc'], round($t['valor'] / 2, 2)], [date('Y-m-d', strtotime($t['venc'] . ' +30 day')), round($t['valor'] - round($t['valor'] / 2, 2), 2)]] : [[$t['venc'], $t['valor']]];
            $xml = self::xmlNfe($chave, $num, $t['data'], $cnpj, $t['fornecedor'], $empresa, $itens, $t['valor'], $dups);
            $vinc = $i % 7 !== 3;
            $this->gravarNota(['tipo' => 'entrada', 'modelo' => 'NF-e', 'numero' => (string) $num, 'serie' => '1', 'chave' => $chave, 'data_emissao' => $t['data'], 'valor' => $t['valor'],
                'emitente_nome' => $t['fornecedor'] . ' LTDA', 'emitente_doc' => $cnpj, 'destinatario_nome' => 'Clínica Demonstração LTDA', 'destinatario_doc' => $empresa,
                'descricao' => implode(', ', array_slice(array_column($itens, 0), 0, 2)) . ' e mais ' . (count($itens) - 2) . ' ite' . (count($itens) - 2 === 1 ? 'm' : 'ns'),
                'transaction_id' => $vinc ? $t['id'] : null], $xml, "NF-e $num", $t['fornecedor'], $t['valor'], $chave, $i % 5 !== 4);
            $n++;
        }
        // NFS-e dos convênios (saída) dos últimos 6 meses
        $conv = array_values(array_filter($this->txConvenios, fn($t) => $t['data'] <= $this->hoje && $t['data'] >= date('Y-m-d', strtotime($this->hoje . ' -6 months'))));
        foreach ($conv as $i => $t) {
            $num = 900 + $i;
            $cod = strtoupper(substr(md5("nfse$num"), 0, 4) . '-' . substr(md5("nfse$num"), 4, 4));
            $cnpj = $docDe($t['cliente'], 'clientes');
            $desc = sprintf('Serviços médicos prestados a beneficiários — competência %02d/%d', $t['mes'], $t['ano']);
            $xml = self::xmlNfse($num, $cod, $t['data'], $empresa, $cnpj, $t['cliente'], $t['valor'], $desc);
            $this->gravarNota(['tipo' => 'saida', 'modelo' => 'NFS-e', 'numero' => (string) $num, 'chave' => $cod, 'data_emissao' => $t['data'], 'valor' => $t['valor'],
                'emitente_nome' => 'Clínica Demonstração LTDA', 'emitente_doc' => $empresa, 'destinatario_nome' => $t['cliente'] . ' LTDA', 'destinatario_doc' => $cnpj,
                'descricao' => $desc, 'transaction_id' => $i === 0 ? null : $t['id']], $xml, "NFS-e $num", $t['cliente'], $t['valor'], $cod, true);
            $n++;
        }
        // Uma nota só com PDF (recibo escaneado), sem dados — pra testar o "completar à mão"
        $pdf = self::pdfNota('Recibo avulso', 'Chaveiro Rápido', 180.00, $this->hoje, '—', 'Cópia de chaves e troca de fechadura');
        $p = $this->salvarArquivo($pdf, 'pdf');
        Database::run('INSERT INTO notas_fiscais (tipo, modelo, numero, valor, descricao, pdf_path, pdf_nome, user_id) VALUES (?,?,?,?,?,?,?,1)',
            ['entrada', 'Outro', null, 0, 'recibo_chaveiro_escaneado', $p, 'recibo_chaveiro_escaneado.pdf']);
        return $n + 1;
    }

    private function gravarNota(array $d, string $xml, string $titulo, string $quem, float $valor, string $chave, bool $comPdf): void
    {
        $px = $this->salvarArquivo($xml, 'xml');
        $pp = $comPdf ? $this->salvarArquivo(self::pdfNota($titulo, $quem, $valor, $d['data_emissao'], $chave, (string) $d['descricao']), 'pdf') : null;
        $nomeBase = strtolower(str_replace([' ', '-'], ['_', ''], $titulo));
        Database::insert('INSERT INTO notas_fiscais (tipo, modelo, numero, serie, chave, data_emissao, valor, emitente_nome, emitente_doc, destinatario_nome, destinatario_doc,
                          descricao, xml_path, xml_nome, pdf_path, pdf_nome, transaction_id, user_id) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,1)',
            [$d['tipo'], $d['modelo'], $d['numero'], $d['serie'] ?? null, $d['chave'], $d['data_emissao'], $d['valor'], $d['emitente_nome'], $d['emitente_doc'],
             $d['destinatario_nome'], $d['destinatario_doc'], mb_substr((string) $d['descricao'], 0, 255), $px, "$nomeBase.xml", $pp, $pp ? "$nomeBase.pdf" : null, $d['transaction_id']]);
    }

    private function salvarArquivo(string $conteudo, string $ext): string
    {
        $nome = round(microtime(true) * 1000) . '-' . bin2hex(random_bytes(6)) . ".$ext";
        file_put_contents(STORAGE_DIR . "/notas/$nome", $conteudo);
        return "storage/notas/$nome";
    }

    private static function xmlNfe(string $chave, int $num, string $data, string $cnpjEmit, string $emit, string $cnpjDest, array $itens, float $total, array $dups): string
    {
        $e = fn($s) => htmlspecialchars((string) $s, ENT_XML1);
        $det = ''; $soma = 0.0;
        foreach ($itens as $k => [$prod, $parte]) {
            $v = $k === count($itens) - 1 ? round($total - $soma, 2) : round($total * $parte, 2); $soma += $v;
            $det .= sprintf('<det nItem="%d"><prod><cProd>%d</cProd><xProd>%s</xProd><qCom>1.0000</qCom><vProd>%.2f</vProd></prod></det>', $k + 1, 100 + $k, $e($prod), $v);
        }
        $dup = '';
        foreach ($dups as $k => [$venc, $v]) $dup .= sprintf('<dup><nDup>%03d</nDup><dVenc>%s</dVenc><vDup>%.2f</vDup></dup>', $k + 1, $venc, $v);
        return '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
            . '<nfeProc xmlns="http://www.portalfiscal.inf.br/nfe" versao="4.00"><NFe><infNFe Id="NFe' . $chave . '" versao="4.00">'
            . sprintf('<ide><cUF>35</cUF><natOp>Venda de mercadoria</natOp><mod>55</mod><serie>1</serie><nNF>%d</nNF><dhEmi>%sT10:00:00-03:00</dhEmi><tpNF>1</tpNF></ide>', $num, $data)
            . '<emit><CNPJ>' . $cnpjEmit . '</CNPJ><xNome>' . $e($emit) . ' LTDA</xNome><xFant>' . $e($emit) . '</xFant></emit>'
            . '<dest><CNPJ>' . $cnpjDest . '</CNPJ><xNome>Clínica Demonstração LTDA</xNome></dest>' . $det
            . sprintf('<total><ICMSTot><vProd>%.2f</vProd><vNF>%.2f</vNF></ICMSTot></total>', $total, $total)
            . sprintf('<cobr><fat><nFat>%d</nFat><vOrig>%.2f</vOrig><vLiq>%.2f</vLiq></fat>%s</cobr>', $num, $total, $total, $dup)
            . sprintf('<pag><detPag><tPag>15</tPag><vPag>%.2f</vPag></detPag></pag>', $total)
            . '<infAdic><infCpl>Documento fictício gerado para testes do sistema.</infCpl></infAdic></infNFe></NFe>'
            . '<protNFe><infProt><chNFe>' . $chave . '</chNFe></infProt></protNFe></nfeProc>';
    }

    private static function xmlNfse(int $num, string $cod, string $data, string $cnpjPrest, string $cnpjToma, string $toma, float $valor, string $desc): string
    {
        $e = fn($s) => htmlspecialchars((string) $s, ENT_XML1);
        return '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
            . '<CompNfse xmlns="http://www.abrasf.org.br/nfse.xsd"><Nfse versao="2.02"><InfNfse Id="nfse' . $num . '">'
            . "<Numero>$num</Numero><CodigoVerificacao>$cod</CodigoVerificacao><DataEmissao>{$data}T18:00:00</DataEmissao>"
            . sprintf('<ValoresNfse><BaseCalculo>%.2f</BaseCalculo><ValorLiquidoNfse>%.2f</ValorLiquidoNfse></ValoresNfse>', $valor, $valor)
            . "<PrestadorServico><IdentificacaoPrestador><CpfCnpj><Cnpj>$cnpjPrest</Cnpj></CpfCnpj></IdentificacaoPrestador><RazaoSocial>Clínica Demonstração LTDA</RazaoSocial></PrestadorServico>"
            . sprintf('<DeclaracaoPrestacaoServico><InfDeclaracaoPrestacaoServico><Servico><Valores><ValorServicos>%.2f</ValorServicos></Valores><Discriminacao>%s</Discriminacao></Servico>', $valor, $e($desc))
            . "<Tomador><IdentificacaoTomador><CpfCnpj><Cnpj>$cnpjToma</Cnpj></CpfCnpj></IdentificacaoTomador><RazaoSocial>" . $e($toma) . ' LTDA</RazaoSocial></Tomador>'
            . '</InfDeclaracaoPrestacaoServico></DeclaracaoPrestacaoServico></InfNfse></Nfse></CompNfse>';
    }

    /** PDF simples no estilo de um DANFE, com aviso de documento fictício. */
    private static function pdfNota(string $titulo, string $quem, float $valor, string $data, string $chave, string $desc): string
    {
        $p = new PdfWriter(); $p->addPage();
        $p->rect(40, 40, 515, 60, '#282A5C');
        $p->font(true, 18); $p->text(56, 54, $titulo, '#FFFFFF');
        $p->font(false, 10); $p->text(56, 80, 'DOCUMENTO FICTÍCIO — gerado para testes do OAF Controle Financeiro', '#C9A55F');
        $y = 130;
        foreach ([['Emitente / tomador', $quem], ['Data de emissão', date('d/m/Y', strtotime($data))], ['Valor total', 'R$ ' . number_format($valor, 2, ',', '.')],
                  ['Chave / código', $chave], ['Descrição', $desc]] as [$k, $v]) {
            $p->font(true, 10); $p->text(56, $y, $k, '#7A7E98');
            $p->font(false, 12); $p->text(200, $y, mb_substr($v, 0, 70));
            $p->line(56, $y + 22, 540, $y + 22); $y += 36;
        }
        return $p->output();
    }

    // ─── Documentos fictícios válidos ───────────────────────────────────────
    private static function cpf(): string
    {
        $n = ''; for ($i = 0; $i < 9; $i++) $n .= mt_rand(0, 9);
        for ($t = 9; $t < 11; $t++) { $s = 0; for ($i = 0; $i < $t; $i++) $s += (int) $n[$i] * (($t + 1) - $i); $n .= ((10 * $s) % 11) % 10; }
        return $n;
    }

    private static function cnpj(): string
    {
        $n = ''; for ($i = 0; $i < 8; $i++) $n .= mt_rand(0, 9); $n .= '0001';
        foreach ([[5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2], [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2]] as $pesos) {
            $s = 0; foreach ($pesos as $i => $p) $s += (int) $n[$i] * $p;
            $r = $s % 11; $n .= $r < 2 ? 0 : 11 - $r;
        }
        return $n;
    }

    private static function chaveNfe(string $cnpj, string $data, int $num): string
    {
        $base = '35' . date('ym', strtotime($data)) . str_pad($cnpj, 14, '0', STR_PAD_LEFT) . '55' . '001' . str_pad((string) $num, 9, '0', STR_PAD_LEFT) . '1' . str_pad((string) ($num * 7919 % 100000000), 8, '0', STR_PAD_LEFT);
        $s = 0; $peso = 2;
        for ($i = strlen($base) - 1; $i >= 0; $i--) { $s += (int) $base[$i] * $peso; $peso = $peso === 9 ? 2 : $peso + 1; }
        $dv = 11 - ($s % 11);
        return $base . ($dv >= 10 ? 0 : $dv);
    }

    private static function fmtCpf(string $c): string { return substr($c, 0, 3) . '.' . substr($c, 3, 3) . '.' . substr($c, 6, 3) . '-' . substr($c, 9); }
    private static function fmtCnpj(string $c): string { return fmt_cnpj($c); }
    private static function email(string $n): string
    {
        $s = strtolower(preg_replace('/[^a-z0-9]+/i', '.', iconv('UTF-8', 'ASCII//TRANSLIT', $n)));
        return trim($s, '.') . '@exemplo.com';
    }
}

// Linha de comando
if (PHP_SAPI === 'cli' && realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    require_once dirname(__DIR__) . '/bootstrap.php';
    $limpar = in_array('--limpar', $argv, true);
    if ($limpar && !in_array('--sim', $argv, true)) {
        echo "ATENÇÃO: --limpar apaga lançamentos, transferências, notas fiscais, clientes, fornecedores e parceiros do banco \"" . env('DB_NAME') . "\".\nDigite SIM para continuar: ";
        if (trim((string) fgets(STDIN)) !== 'SIM') { echo "Cancelado.\n"; exit(1); }
    }
    try {
        $t = microtime(true);
        $r = DadosFicticios::executar($limpar);
        printf("Pronto em %.1f s: %d lançamentos, %d transferências, %d notas fiscais, %d clientes, %d fornecedores.\n",
            microtime(true) - $t, $r['lancamentos'], $r['transferencias'], $r['notas'], $r['clientes'], $r['fornecedores']);
        echo "Usuários de teste: financeiro / Demo1234 · consulta / Demo1234" . ((int) Database::value("SELECT COUNT(*) FROM users WHERE username='admin'") ? " · admin / Admin1234 (se foi criado agora)" : '') . "\n";
    } catch (\Throwable $e) {
        fwrite(STDERR, 'Erro: ' . $e->getMessage() . "\n");
        exit(1);
    }
}
