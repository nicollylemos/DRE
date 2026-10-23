<?php
namespace App\Core;

/**
 * Catálogo das telas do sistema — fonte única usada pelo menu lateral, pela
 * tela de Níveis de acesso e pelo bloqueio no servidor.
 *
 * Cada nível de acesso (tabela `perfis`) guarda, por tela, um destes valores:
 *   0 = sem acesso · 1 = ver (só consulta) · 2 = editar (criar, alterar, apagar)
 * O perfil marcado como administrador tem 2 em tudo e ainda gerencia usuários.
 */
final class Permissions
{
    public const NENHUM = 0;
    public const VER = 1;
    public const EDITAR = 2;

    /** chave => [rótulo, grupo, rota, aceita "editar"?, descrição do que "editar" libera] */
    public const TELAS = [
        'dashboard'        => ['Dashboard', 'Visão geral', 'dashboard', false, ''],
        'dre'              => ['DRE', 'Visão geral', 'dre', false, ''],
        'calendario'       => ['Calendário', 'Visão geral', 'calendario', false, ''],
        'lancamentos'      => ['Lançamentos', 'Financeiro', 'lancamentos', true, 'criar, editar, dar baixa e apagar lançamentos e transferências'],
        'fluxo-caixa'      => ['Fluxo de Caixa', 'Financeiro', 'fluxo-caixa', false, ''],
        'importacao'       => ['Importar / Exportar', 'Financeiro', 'importacao', true, 'importar planilhas e desfazer importações (ver = só exportar)'],
        'cad-clientes'     => ['Clientes', 'Cadastros', 'clientes', true, 'cadastrar, editar e apagar'],
        'cad-fornecedores' => ['Fornecedores', 'Cadastros', 'fornecedores', true, 'cadastrar, editar, anexar contratos e apagar'],
        'cad-parceiros'    => ['Parceiros', 'Cadastros', 'parceiros', true, 'cadastrar, editar, anexar contratos e apagar'],
        'cad-categorias'   => ['Categorias', 'Cadastros', 'categorias', true, 'cadastrar, editar, ativar/desativar e apagar'],
        'cad-caixas'       => ['Caixas / Banco', 'Cadastros', 'caixas', true, 'cadastrar, editar e apagar contas'],
        'cad-formas'       => ['Formas de pagamento', 'Cadastros', 'formas', true, 'cadastrar, editar e apagar formas'],
        'logs'             => ['Logs', 'Sistema', 'logs', false, ''],
    ];

    /** Rota (URL) → chave da tela. Usuários é só de administrador. */
    public static function telaDaRota(string $rota): ?string
    {
        foreach (self::TELAS as $k => $t) if ($t[2] === $rota) return $k;
        return null;
    }

    public static function chaves(): array { return array_keys(self::TELAS); }

    /** Normaliza o que vem do formulário/banco: só chaves conhecidas, níveis 0..2. */
    public static function normalizar($perms): array
    {
        if (is_string($perms)) $perms = json_decode($perms, true);
        if (!is_array($perms)) $perms = [];
        $out = [];
        foreach (self::TELAS as $k => $t) {
            $n = (int) ($perms[$k] ?? 0);
            if (!$t[3] && $n > self::VER) $n = self::VER; // tela só de consulta
            $out[$k] = max(0, min(2, $n));
        }
        return $out;
    }

    public static function todas(int $nivel = self::EDITAR): array
    {
        return self::normalizar(array_fill_keys(self::chaves(), $nivel));
    }

    /** Catálogo pro front (tela de níveis de acesso). */
    public static function catalogo(): array
    {
        $out = [];
        foreach (self::TELAS as $k => [$label, $grupo, $rota, $editavel, $desc]) {
            $out[] = ['chave' => $k, 'label' => $label, 'grupo' => $grupo, 'rota' => $rota, 'editavel' => $editavel, 'editar_desc' => $desc];
        }
        return $out;
    }
}
