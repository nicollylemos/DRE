<?php
/**
 * Testes unitários sem dependências (o projeto não usa Composer).
 *   php tests/unit/run.php      → sai com código 1 se algum teste falhar (o CI quebra)
 */
declare(strict_types=1);
putenv('APP_ENV=testing');
require __DIR__ . '/../../app/bootstrap.php';

use App\Core\Logger;
use App\Services\Dre\EstrategiasDre;

$ok = 0; $falhas = [];
function teste(string $nome, callable $fn): void {
    global $ok, $falhas;
    try { $fn(); $ok++; echo "  ✔ $nome\n"; }
    catch (\Throwable $e) { $falhas[] = $nome; echo "  ✘ $nome — {$e->getMessage()}\n"; }
}
function igual($esperado, $obtido, string $msg = ''): void {
    if ($esperado !== $obtido) throw new \RuntimeException($msg . ' esperado ' . var_export($esperado, true) . ', veio ' . var_export($obtido, true));
}
function verdade(bool $v, string $msg = 'esperado verdadeiro'): void { if (!$v) throw new \RuntimeException($msg); }

echo "Helpers\n";
teste('only_digits remove máscara', fn() => igual('11222333000181', only_digits('11.222.333/0001-81')));
teste('cnpj_valido aceita CNPJ válido', fn() => verdade(cnpj_valido('11.222.333/0001-81')));
teste('cnpj_valido recusa dígito errado', fn() => verdade(!cnpj_valido('11.222.333/0001-82'), 'aceitou CNPJ inválido'));
teste('cnpj_valido recusa repetidos', fn() => verdade(!cnpj_valido('11111111111111'), 'aceitou 111...'));
teste('cpf_valido', fn() => verdade(cpf_valido('529.982.247-25') && !cpf_valido('529.982.247-24')));
teste('brl formata em reais', fn() => igual('R$ 1.234,50', brl(1234.5)));
teste('num trata não numérico como 0', fn() => igual(0.0, num('abc')));
teste('str_or_null', fn() => igual(null, str_or_null('   ')));
teste('e() escapa HTML (XSS)', fn() => igual('&lt;script&gt;', e('<script>')));
teste('senha_fraca exige 8+ com letra e número', function () {
    verdade(senha_fraca('abc') !== null); verdade(senha_fraca('abcdefgh') !== null); igual(null, senha_fraca('abcdef12'));
});
teste('dias_no_mes considera ano bissexto', fn() => igual(29, dias_no_mes(2, 2028)));

echo "Logger (CloudWatch)\n";
teste('linha é JSON com level e msg', function () {
    $j = json_decode(Logger::linha('ERROR', 'falhou', ['x' => 1]), true);
    igual('ERROR', $j['level']); igual('falhou', $j['msg']); igual(1, $j['x']); verdade(isset($j['ts'], $j['version']));
});
teste('linha nunca expõe senha/token', function () {
    $l = Logger::linha('INFO', 'login', ['password' => 'segredo123', 'token' => 'abc', 'usuario' => 'ana']);
    verdade(strpos($l, 'segredo123') === false && strpos($l, '"abc"') === false, 'vazou dado sensível');
    verdade(strpos($l, '"usuario":"ana"') !== false);
});

echo "DRE (Strategy)\n";
teste('regime desconhecido cai no padrão', fn() => igual(EstrategiasDre::regimes()[0]->chave(), EstrategiasDre::regime('xyz')->chave()));
teste('regime caixa é encontrado pela chave', fn() => igual('caixa', EstrategiasDre::regime('caixa')->chave()));

echo "\n" . $ok . ' ok, ' . count($falhas) . " falha(s)\n";
exit($falhas ? 1 : 0);
