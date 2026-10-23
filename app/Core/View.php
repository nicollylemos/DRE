<?php
namespace App\Core;

/**
 * Renderiza uma view PHP (app/Views/...) dentro de um layout.
 * Dentro da view as chaves de $data viram variáveis; $content no layout é o HTML da view.
 */
final class View
{
    public static function render(string $view, array $data = [], ?string $layout = 'layouts/app'): void
    {
        $content = self::partial($view, $data);
        if ($layout) $content = self::partial($layout, $data + ['content' => $content]);
        header('Content-Type: text/html; charset=utf-8');
        header('Cache-Control: no-store');
        echo $content;
        exit;
    }

    public static function partial(string $view, array $data = []): string
    {
        $file = VIEWS_DIR . '/' . $view . '.php';
        if (!is_file($file)) throw new \RuntimeException("View não encontrada: $view");
        extract($data, EXTR_SKIP);
        ob_start();
        include $file;
        return (string) ob_get_clean();
    }
}
