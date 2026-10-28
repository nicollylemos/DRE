<?php
namespace App\Core;

/**
 * LGPD — Termo de uso/consentimento e Política de privacidade.
 * Mudou o texto de forma relevante? Suba a VERSAO: todo mundo aceita de novo no próximo acesso.
 * Quem é o controlador e o contato do encarregado (DPO) vêm do .env (LGPD_CONTROLADOR, LGPD_CNPJ, LGPD_EMAIL).
 */
final class Termos
{
    public const VERSAO = '1.0';
    public const VIGENCIA = '28/10/2026';

    public static function controlador(): string { return (string) env('LGPD_CONTROLADOR', 'OAF Consultoria Empresarial'); }
    public static function cnpj(): string { return (string) env('LGPD_CNPJ', ''); }
    public static function email(): string { return (string) env('LGPD_EMAIL', ''); }

    /** Usuário precisa (re)aceitar? */
    public static function pendente(?array $u): bool
    {
        return $u !== null && (string) ($u['termos_versao'] ?? '') !== self::VERSAO;
    }
}
