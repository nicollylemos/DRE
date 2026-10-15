<?php
/** Router mínimo: Router::get('transactions/:id', fn($id) => ...). */
final class Router {
    private static array $routes = [];

    public static function add(string $methods, string $pattern, callable $handler): void {
        $regex = '#^' . preg_replace('#:([a-z_]+)#', '([^/]+)', trim($pattern, '/')) . '$#';
        foreach (explode('|', strtoupper($methods)) as $m) self::$routes[] = [$m, $regex, $handler];
    }
    public static function get(string $p, callable $h): void    { self::add('GET', $p, $h); }
    public static function post(string $p, callable $h): void   { self::add('POST', $p, $h); }
    public static function put(string $p, callable $h): void    { self::add('PUT', $p, $h); }
    public static function patch(string $p, callable $h): void  { self::add('PATCH', $p, $h); }
    public static function delete(string $p, callable $h): void { self::add('DELETE', $p, $h); }

    public static function match(string $method, string $route): array {
        foreach (self::$routes as [$m, $regex, $h]) {
            if ($m === $method && preg_match($regex, $route, $mm)) {
                array_shift($mm);
                return [$h, array_map('urldecode', $mm)];
            }
        }
        return [null, []];
    }
}
