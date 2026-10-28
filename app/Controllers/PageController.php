<?php
namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\HttpException;
use App\Core\Permissions;
use App\Core\Response;
use App\Core\Termos;
use App\Core\View;
use App\Models\User;

/**
 * Páginas (HTML). Cada tela é uma view em app/Views/pages/<rota>.php com o
 * layout app/Views/layouts/app.php; os dados chegam pelo JS via /api/*.
 */
class PageController extends Controller
{
    /** Título, subtítulo e scripts de cada tela. */
    private const PAGINAS = [
        'dashboard'    => ['Dashboard', ['pages/dashboard.js']],
        'dre'          => ['DRE', ['pages/dre.js']],
        'calendario'   => ['Calendário', ['lancamento-form.js', 'pages/calendario.js']],
        'lancamentos'  => ['Lançamentos', ['lancamento-form.js', 'pages/lancamentos.js']],
        'notas-fiscais' => ['Notas fiscais', ['lancamento-form.js', 'pages/notas-fiscais.js']],
        'fluxo-caixa'  => ['Fluxo de Caixa', ['pages/fluxo.js']],
        'importacao'   => ['Importar / Exportar', ['pages/importacao.js']],
        'clientes'     => ['Clientes', ['pages/clientes.js']],
        'fornecedores' => ['Fornecedores', ['pages/entidades.js']],
        'parceiros'    => ['Parceiros', ['pages/entidades.js']],
        'categorias'   => ['Categorias', ['pages/categorias.js']],
        'caixas'       => ['Caixas / Banco', ['pages/caixas.js']],
        'formas'       => ['Formas de pagamento', ['pages/formas.js']],
        'usuarios'     => ['Usuários e acessos', ['pages/usuarios.js']],
        'logs'         => ['Logs', ['pages/logs.js']],
        'sem-acesso'   => ['Sem acesso', []],
    ];

    public function inicio(): void
    {
        Response::redirect(Auth::check() ? Auth::home() : 'login');
    }

    public function login(): void
    {
        $u = Auth::user();
        $trocar = $u && (int) $u['deve_trocar_senha'];
        $termos = $u && !$trocar && Termos::pendente($u);
        if ($u && !$trocar && !$termos) Response::redirect($this->destino());
        View::render('auth/login', [
            'titulo' => 'Entrar',
            'precisaSetup' => User::count() === 0,
            'trocarSenha' => $trocar,
            'aceitarTermos' => $termos,
            'usuario' => $u,
            'next' => $this->destino(false),
            'scriptsAuth' => ['pages/login.js'],
        ], 'layouts/auth');
    }

    /** LGPD — páginas públicas (abrem sem login, inclusive pelo link da tela de entrada). */
    public function privacidade(): void { $this->documentoLgpd('privacidade', 'Política de privacidade'); }
    public function termos(): void { $this->documentoLgpd('termos', 'Termo de uso e consentimento'); }

    private function documentoLgpd(string $doc, string $titulo): void
    {
        View::render("publico/$doc", ['titulo' => $titulo, 'doc' => $doc, 'logado' => Auth::check()], 'layouts/auth');
    }

    /** Destino seguro depois do login (?next=rota), só rotas internas conhecidas. */
    private function destino(bool $padraoHome = true): string
    {
        $next = (string) ($_GET['next'] ?? '');
        if ($next !== '' && isset(self::PAGINAS[$next]) && $next !== 'sem-acesso') return $next;
        return $padraoHome ? Auth::home() : '';
    }

    public function pagina(string $rota): void
    {
        if (!isset(self::PAGINAS[$rota])) throw new HttpException(404, 'Página não encontrada');
        $u = Auth::user();
        if (!$u) Response::redirect('login' . ($rota !== 'sem-acesso' ? '?next=' . rawurlencode($rota) : ''));

        if ($rota === 'usuarios') {
            if (!Auth::isAdmin()) $this->negado();
            $tela = null;
        } elseif ($rota === 'sem-acesso') {
            $tela = null;
        } else {
            $tela = Permissions::telaDaRota($rota);
            if (!$tela || !Auth::pode($tela)) $this->negado();
        }
        [$titulo, $scripts] = self::PAGINAS[$rota];
        View::render("pages/$rota", [
            'titulo' => $titulo, 'pagina' => $rota, 'tela' => $tela, 'scripts' => $scripts,
            'podeEditar' => $tela ? Auth::podeEditar($tela) : Auth::isAdmin(),
        ]);
    }

    private function negado(): void
    {
        http_response_code(403);
        View::render('pages/sem-acesso', ['titulo' => 'Sem acesso', 'pagina' => 'sem-acesso', 'tela' => null, 'scripts' => [], 'podeEditar' => false, 'negado' => true]);
    }
}
