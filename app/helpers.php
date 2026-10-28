<?php
/**
 * helpers.php — funções pequenas usadas em todo lugar (views, models, controllers).
 */

const MESES_LONGOS = ['Janeiro', 'Fevereiro', 'Março', 'Abril', 'Maio', 'Junho', 'Julho', 'Agosto', 'Setembro', 'Outubro', 'Novembro', 'Dezembro'];
const MESES_CURTOS = ['Jan', 'Fev', 'Mar', 'Abr', 'Mai', 'Jun', 'Jul', 'Ago', 'Set', 'Out', 'Nov', 'Dez'];

function env(string $k, $default = null) {
    $v = getenv($k);
    return ($v === false || $v === '') ? $default : $v;
}

/** Escapa texto pra HTML (usado nas views). */
function e($v): string { return htmlspecialchars((string) ($v ?? ''), ENT_QUOTES, 'UTF-8'); }

/** Caminho base da aplicação (funciona na raiz ou em subpasta, ex: localhost/DRE-php/). */
function base_path(): string {
    static $b = null;
    if ($b !== null) return $b;
    $dir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/'));
    $b = rtrim($dir, '/') . '/';
    return $b;
}
function url(string $path = ''): string { return base_path() . ltrim($path, '/'); }
/** Arquivo estático com ?v= pra não pegar cache velho depois de atualizar o sistema. */
function asset(string $path): string {
    $full = ROOT_DIR . '/assets/' . ltrim($path, '/');
    $v = is_file($full) ? filemtime($full) : APP_VERSION;
    return url('assets/' . ltrim($path, '/')) . '?v=' . $v;
}

function str_or_null($v): ?string { $v = trim((string) ($v ?? '')); return $v === '' ? null : $v; }
function num($v): float { return is_numeric($v) ? (float) $v : 0.0; }
function only_digits(string $v): string { return preg_replace('/\D/', '', $v); }
function cut(?string $v, int $n): ?string { return $v === null ? null : mb_substr($v, 0, $n); }
function brl(float $v): string { return 'R$ ' . number_format($v, 2, ',', '.'); }
/** IP do cliente. X-Forwarded-For só vale com TRUST_PROXY=1 (senão qualquer um forja o IP e fura o limite de tentativas). */
function client_ip(): string {
    $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
    if (env('TRUST_PROXY') === '1' && !empty($_SERVER['HTTP_X_FORWARDED_FOR'])) $ip = trim(explode(',', (string) $_SERVER['HTTP_X_FORWARDED_FOR'])[0]);
    return substr($ip, 0, 64);
}
/** Caminho relativo de upload pro front (sem barra inicial). */
function public_path(?string $p): ?string { return $p ? ltrim($p, '/') : null; }

/** Valida CNPJ pelos dígitos verificadores. */
function cnpj_valido(string $cnpj): bool {
    $c = only_digits($cnpj);
    if (strlen($c) !== 14 || preg_match('/^(\d)\1{13}$/', $c)) return false;
    $calc = function (string $base, array $pesos): int {
        $s = 0; foreach ($pesos as $i => $p) $s += (int) $base[$i] * $p;
        $r = $s % 11; return $r < 2 ? 0 : 11 - $r;
    };
    $d1 = $calc(substr($c, 0, 12), [5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2]);
    $d2 = $calc(substr($c, 0, 12) . $d1, [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2]);
    return $c[12] == $d1 && $c[13] == $d2;
}
function cpf_valido(string $cpf): bool {
    $c = only_digits($cpf);
    if (strlen($c) !== 11 || preg_match('/^(\d)\1{10}$/', $c)) return false;
    for ($t = 9; $t < 11; $t++) {
        $s = 0; for ($i = 0; $i < $t; $i++) $s += (int) $c[$i] * (($t + 1) - $i);
        $d = ((10 * $s) % 11) % 10;
        if ((int) $c[$t] !== $d) return false;
    }
    return true;
}
/** Fornecedor/cliente pode ser empresa (CNPJ) ou pessoa física (CPF). */
function doc_valido(string $doc): bool { $n = strlen(only_digits($doc)); return $n === 14 ? cnpj_valido($doc) : ($n === 11 && cpf_valido($doc)); }
function fmt_cnpj(string $cnpj): string {
    $c = only_digits($cnpj);
    return strlen($c) === 14 ? substr($c, 0, 2) . '.' . substr($c, 2, 3) . '.' . substr($c, 5, 3) . '/' . substr($c, 8, 4) . '-' . substr($c, 12) : $cnpj;
}
function fmt_doc(string $doc): string {
    $c = only_digits($doc);
    if (strlen($c) === 11) return substr($c, 0, 3) . '.' . substr($c, 3, 3) . '.' . substr($c, 6, 3) . '-' . substr($c, 9);
    return fmt_cnpj($doc);
}

/** Agência (0000 ou 0000-0) e conta (até 12 dígitos + DV). DV pode ser X. Vazio = null. */
function agencia_valida(?string $v): ?string {
    $v = strtoupper(trim((string) $v));
    if ($v === '') return null;
    if (!preg_match('/^\d{1,4}(-[\dX])?$/', $v)) throw new \App\Core\HttpException(400, 'Agência inválida: use até 4 números e, se tiver, o dígito (ex.: 0350 ou 0350-1)');
    return $v;
}
function conta_valida(?string $v): ?string {
    $v = strtoupper(trim((string) $v));
    if ($v === '') return null;
    if (!preg_match('/^\d{1,12}(-[\dX])?$/', $v)) throw new \App\Core\HttpException(400, 'Conta inválida: use até 12 números e o dígito (ex.: 12345-6)');
    return $v;
}

/** Texto curto do que mudou entre o registro antigo e o novo, pro log. */
function diff_log(array $antes, array $depois, array $rotulos): string {
    $m = [];
    foreach ($rotulos as $c => $label) {
        $a = $antes[$c] ?? null; $d = $depois[$c] ?? null;
        if (is_numeric($a) && is_numeric($d) ? (float) $a == (float) $d : (string) $a === (string) $d) continue;
        $m[] = "$label: " . (($a === null || $a === '') ? '∅' : $a) . ' → ' . (($d === null || $d === '') ? '∅' : $d);
    }
    return implode('; ', $m);
}
function nome_e_mudancas(string $nome, string $diff): string { return $diff ? "$nome — $diff" : $nome; }

/** Ano vindo da query (?year=), limitado a uma faixa sã. */
function ano_param(): int { $y = (int) ($_GET['year'] ?? date('Y')); return ($y >= 1900 && $y <= 2200) ? $y : (int) date('Y'); }
function mes_de($data): int { return (int) substr((string) $data, 5, 2); }
function dias_no_mes(int $m, int $y): int { return (int) (new DateTime("$y-$m-01"))->format('t'); }

/** Regra de senha nova: 8+ caracteres, com pelo menos uma letra e um número. */
function senha_fraca(string $senha): ?string {
    if (strlen($senha) < 8) return 'A senha precisa ter pelo menos 8 caracteres';
    if (!preg_match('/[A-Za-z]/', $senha) || !preg_match('/\d/', $senha)) return 'A senha precisa ter letras e números';
    return null;
}
