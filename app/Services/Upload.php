<?php
namespace App\Services;

use App\Core\HttpException;

/** Upload de arquivos com nome aleatório e lista de extensões permitidas. */
final class Upload
{
    public const IMAGEM    = ['png', 'jpg', 'jpeg', 'gif', 'webp'];
    public const DOCUMENTO = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'odt', 'txt', 'png', 'jpg', 'jpeg', 'gif', 'webp', 'xml', 'zip'];
    public const CONTRATO  = ['pdf', 'doc', 'docx', 'odt', 'png', 'jpg', 'jpeg', 'webp'];

    /**
     * Salva em uploads/<pasta>/ e devolve ['path' => 'uploads/pasta/arq.ext', 'nome' => 'nome original'] ou null
     * (quando o campo não veio). Não confia no nome vindo do navegador.
     */
    public static function salvar(string $campo, string $pasta, array $exts, int $maxBytes = 20 * 1024 * 1024): ?array
    {
        if (empty($_FILES[$campo]) || ($_FILES[$campo]['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) return null;
        $f = $_FILES[$campo];
        if ($f['error'] !== UPLOAD_ERR_OK) {
            throw new HttpException(400, in_array($f['error'], [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true) ? 'Arquivo muito grande' : 'Falha no upload do arquivo');
        }
        if ($f['size'] > $maxBytes) throw new HttpException(400, 'Arquivo muito grande (máx. ' . round($maxBytes / 1048576) . ' MB)');
        $ext = strtolower(pathinfo((string) $f['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, $exts, true)) throw new HttpException(400, 'Tipo de arquivo não permitido (.' . $ext . ')');
        $dir = UPLOADS_DIR . '/' . $pasta;
        if (!is_dir($dir)) mkdir($dir, 0775, true);
        $nome = round(microtime(true) * 1000) . '-' . bin2hex(random_bytes(6)) . '.' . $ext;
        if (!move_uploaded_file($f['tmp_name'], "$dir/$nome")) throw new HttpException(500, 'Não foi possível salvar o arquivo');
        return ['path' => "uploads/$pasta/$nome", 'nome' => mb_substr(basename((string) $f['name']), 0, 255)];
    }

    /**
     * Arquivo PRIVADO: grava em storage/<pasta>/ (o navegador não acessa direto; quem entrega é um controller
     * que confere a permissão). Recebe o item de $_FILES (um arquivo só). Devolve ['path','nome','ext'].
     * $conteudo valida o começo do arquivo (ex.: PDF tem que começar com %PDF) pra não aceitar arquivo renomeado.
     */
    public static function salvarPrivado(array $f, string $pasta, array $exts, int $maxBytes = 20 * 1024 * 1024): array
    {
        if (($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new HttpException(400, in_array($f['error'] ?? 0, [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true) ? 'Arquivo muito grande' : 'Falha no upload do arquivo');
        }
        if ($f['size'] > $maxBytes) throw new HttpException(400, 'Arquivo muito grande (máx. ' . round($maxBytes / 1048576) . ' MB)');
        $ext = strtolower(pathinfo((string) $f['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, $exts, true)) throw new HttpException(400, 'Tipo de arquivo não permitido (.' . $ext . ')');
        $inicio = (string) @file_get_contents($f['tmp_name'], false, null, 0, 1024);
        if ($ext === 'pdf' && strpos($inicio, '%PDF') === false) throw new HttpException(400, 'O arquivo "' . basename((string) $f['name']) . '" não é um PDF válido');
        if ($ext === 'xml' && strpos(ltrim($inicio, "\xEF\xBB\xBF \t\r\n"), '<') !== 0) throw new HttpException(400, 'O arquivo "' . basename((string) $f['name']) . '" não é um XML válido');
        $dir = STORAGE_DIR . '/' . $pasta;
        if (!is_dir($dir)) mkdir($dir, 0770, true);
        $nome = round(microtime(true) * 1000) . '-' . bin2hex(random_bytes(6)) . '.' . $ext;
        $ok = is_uploaded_file($f['tmp_name']) ? move_uploaded_file($f['tmp_name'], "$dir/$nome") : @rename($f['tmp_name'], "$dir/$nome");
        if (!$ok) throw new HttpException(500, 'Não foi possível salvar o arquivo');
        return ['path' => "storage/$pasta/$nome", 'nome' => mb_substr(basename((string) $f['name']), 0, 255), 'ext' => $ext];
    }

    /** Lista de arquivos de um campo múltiplo (name="arquivos[]") no formato de um $_FILES simples. */
    public static function lista(string $campo): array
    {
        if (empty($_FILES[$campo])) return [];
        $f = $_FILES[$campo];
        if (!is_array($f['name'])) return ($f['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE ? [] : [$f];
        $out = [];
        foreach ($f['name'] as $i => $n) {
            if (($f['error'][$i] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) continue;
            $out[] = ['name' => $n, 'type' => $f['type'][$i] ?? '', 'tmp_name' => $f['tmp_name'][$i], 'error' => $f['error'][$i], 'size' => $f['size'][$i]];
        }
        return $out;
    }

    /** Caminho absoluto de um arquivo privado (só dentro de storage/, sem "..") ou null. */
    public static function caminhoPrivado(?string $path): ?string
    {
        if (!$path) return null;
        $rel = ltrim($path, '/');
        if (strpos($rel, 'storage/') !== 0 || strpos($rel, '..') !== false) return null;
        $full = ROOT_DIR . '/' . $rel;
        return is_file($full) ? $full : null;
    }

    /** Apaga um arquivo de upload (aceita caminho antigo com "/" na frente) ou arquivo privado de storage/notas. */
    public static function apagar(?string $path): void
    {
        if (!$path) return;
        $rel = ltrim($path, '/');
        if (strpos($rel, '..') !== false) return;
        if (strpos($rel, 'uploads/') !== 0 && strpos($rel, 'storage/notas/') !== 0) return;
        $full = ROOT_DIR . '/' . $rel;
        if (is_file($full)) @unlink($full);
    }
}
