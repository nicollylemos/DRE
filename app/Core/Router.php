<?php
namespace App\Core;

/**
 * Router: liga "MÉTODO caminho" a um [Controller::class, 'metodo'].
 * Parâmetros no caminho com ":nome" (ex: "api/users/:id") viram argumentos do método.
 */
final class Router
{
    private array $routes = [];

    public function add(string $methods, string $pattern, array $action): void
    {
        $regex = '#^' . preg_replace('#:([a-z_]+)#', '([^/]+)', trim($pattern, '/')) . '$#';
        foreach (explode('|', strtoupper($methods)) as $m) $this->routes[] = [$m, $regex, $action];
    }
    public function get(string $p, array $a): void    { $this->add('GET', $p, $a); }
    public function post(string $p, array $a): void   { $this->add('POST', $p, $a); }
    public function put(string $p, array $a): void    { $this->add('PUT', $p, $a); }
    public function patch(string $p, array $a): void  { $this->add('PATCH', $p, $a); }
    public function delete(string $p, array $a): void { $this->add('DELETE', $p, $a); }

    /** [action, params] ou [null, []]. Se o caminho existe com outro método → 405. */
    public function match(string $method, string $path): array
    {
        $outroMetodo = false;
        foreach ($this->routes as [$m, $regex, $action]) {
            if (!preg_match($regex, $path, $mm)) continue;
            if ($m !== $method) { $outroMetodo = true; continue; }
            array_shift($mm);
            return [$action, $mm];
        }
        if ($outroMetodo) throw new HttpException(405, 'Método não permitido');
        return [null, []];
    }

    public function dispatch(array $action, array $params)
    {
        // Argumentos fixos depois do método (ex: [CadastroController::class, 'listar', 'contas_receita']) vêm antes dos da URL.
        [$class, $method] = $action;
        $ctrl = new $class();
        return $ctrl->$method(...array_slice($action, 2), ...$params);
    }
}
