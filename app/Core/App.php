<?php
namespace App\Core;

use App\Database\Migrator;
use PDOException;

/**
 * App — chamado pelo index.php. Monta as rotas, aplica as travas globais
 * (CSRF, troca de senha pendente), chama o controller e trata os erros.
 */
final class App
{
    /** Rotas da API que aceitam POST sem token CSRF (ainda não existe sessão). */
    private const SEM_CSRF = ['api/auth/login', 'api/auth/register'];

    /** Com troca de senha pendente, só estas rotas funcionam. */
    private const DURANTE_TROCA = ['api/auth/me', 'api/auth/trocar-senha', 'api/auth/logout', 'api/auth/setup-status', 'login', 'sair', 'privacidade', 'termos'];

    /** Sem aceite do Termo/Política (LGPD), só estas rotas funcionam. */
    private const DURANTE_TERMOS = ['api/auth/me', 'api/auth/aceitar-termos', 'api/auth/logout', 'api/auth/setup-status', 'login', 'sair', 'privacidade', 'termos'];

    public static function run(): void
    {
        Response::securityHeaders();
        $method = Request::method();
        $path = Request::path();
        $api = Request::isApi();
        if ($api) header('Cache-Control: no-store');

        // Health check antes de migração/sessão: responde rápido e não cria sessão.
        if ($path === 'health' && $method === 'GET') \App\Controllers\HealthController::check();

        try {
            Migrator::garantir();

            $router = new Router();
            require APP_DIR . '/routes.php';

            if ($api && $method !== 'GET' && !in_array($path, self::SEM_CSRF, true)) Csrf::verificar();

            $u = Auth::user();
            if ($u && (int) $u['deve_trocar_senha'] === 1 && !in_array($path, self::DURANTE_TROCA, true)) {
                if ($api) throw new HttpException(403, 'Defina uma nova senha para continuar', 'TROCAR_SENHA');
                Response::redirect('login');
            }
            if ($u && (int) $u['deve_trocar_senha'] !== 1 && Termos::pendente($u) && !in_array($path, self::DURANTE_TERMOS, true)) {
                if ($api) throw new HttpException(403, 'Aceite o Termo de uso e a Política de privacidade para continuar', 'ACEITAR_TERMOS');
                Response::redirect('login' . ($path !== '' ? '?next=' . rawurlencode($path) : ''));
            }

            [$action, $params] = $router->match($method, $path);
            if (!$action) throw new HttpException(404, $api ? 'Rota não encontrada' : 'Página não encontrada');
            $result = $router->dispatch($action, $params);
            if ($api) Response::json($result ?? ['ok' => true]);
        } catch (HttpException $e) {
            if ($e->status >= 500) Logger::error($e->getMessage(), ['status' => $e->status]);
            elseif (in_array($e->status, [403, 423, 429], true) && $method !== 'GET') Logger::warn($e->getMessage(), ['status' => $e->status, 'code' => $e->codigo]);
            if ($api) Response::json(array_filter(['error' => $e->getMessage(), 'code' => $e->codigo]), $e->status);
            if ($e->status === 401) Response::redirect('login');
            self::paginaErro($e->status, $e->getMessage());
        } catch (PDOException $e) {
            Logger::error('Falha no banco de dados', ['sql_state' => $e->getCode(), 'mysql_code' => $e->errorInfo[1] ?? null, 'erro' => $e->getMessage()]);
            if ((int) ($e->errorInfo[1] ?? 0) === 1062 && $api) Response::json(['error' => 'Registro duplicado'], 400);
            $conexao = in_array((int) ($e->errorInfo[1] ?? $e->getCode()), [1045, 2002, 2003, 2005, 1044], true);
            $msg = $conexao
                ? 'Não foi possível conectar ao banco de dados. Confira DB_HOST, DB_USER, DB_PASS e DB_NAME no arquivo .env e se o MySQL está ligado.'
                : (IS_PROD ? 'Erro no banco de dados' : $e->getMessage());
            if ($api) Response::json(['error' => $msg], 500);
            self::paginaErro(500, $msg);
        } catch (\Throwable $e) {
            Logger::error('Erro não tratado', ['erro' => $e->getMessage(), 'arquivo' => basename($e->getFile()) . ':' . $e->getLine(), 'tipo' => get_class($e)]);
            $msg = IS_PROD ? 'Erro interno' : $e->getMessage() . ' @ ' . basename($e->getFile()) . ':' . $e->getLine();
            if ($api) Response::json(['error' => $msg], 500);
            self::paginaErro(500, $msg);
        }
    }

    private static function paginaErro(int $status, string $msg): void
    {
        http_response_code($status);
        View::render('errors/erro', ['status' => $status, 'mensagem' => $msg, 'titulo' => 'Erro ' . $status], 'layouts/auth');
    }
}
