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

    /** Apaga um arquivo de upload (aceita caminho antigo com "/" na frente). */
    public static function apagar(?string $path): void
    {
        if (!$path) return;
        $rel = ltrim($path, '/');
        if (strpos($rel, 'uploads/') !== 0 || strpos($rel, '..') !== false) return;
        $full = ROOT_DIR . '/' . $rel;
        if (is_file($full)) @unlink($full);
    }
}
