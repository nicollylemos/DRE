<?php
namespace App\Services;

/**
 * Envio de e-mail sem Composer.
 *
 * Configuração no .env:
 *   MAIL_HOST=smtp.hostinger.com   MAIL_PORT=465   MAIL_SECURE=ssl   (ou 587 + tls)
 *   MAIL_USER=nao-responda@seudominio.com.br   MAIL_PASS=...
 *   MAIL_FROM=nao-responda@seudominio.com.br   MAIL_FROM_NAME="OAF Controle Financeiro"
 *
 * Sem MAIL_HOST:
 *   - em desenvolvimento grava o e-mail em storage/mail/*.eml (abre no Outlook/Thunderbird) — dá pra testar sem SMTP;
 *   - em produção tenta a função mail() do PHP (funciona na maioria das hospedagens compartilhadas).
 */
final class Mailer
{
    private $sock = null;
    private array $log = [];

    /** true se entregou ao servidor de e-mail (ou gravou o .eml em desenvolvimento). */
    public static function enviar(string $para, string $assunto, string $html, string $texto): bool
    {
        $m = new self();
        try {
            return $m->despachar($para, $assunto, $html, $texto);
        } catch (\Throwable $e) {
            error_log('[MAIL] ' . $e->getMessage() . ' | ' . implode(' / ', array_slice($m->log, -4)));
            return false;
        }
    }

    private function despachar(string $para, string $assunto, string $html, string $texto): bool
    {
        if (!filter_var($para, FILTER_VALIDATE_EMAIL)) throw new \RuntimeException('Destinatário inválido');
        $from = (string) env('MAIL_FROM', env('MAIL_USER', 'nao-responda@localhost'));
        $fromName = (string) env('MAIL_FROM_NAME', 'OAF Controle Financeiro');
        [$cabecalhos, $corpo] = self::montar($from, $fromName, $para, $assunto, $html, $texto);

        if (!env('MAIL_HOST')) {
            if (!IS_PROD) {
                $dir = STORAGE_DIR . '/mail';
                if (!is_dir($dir)) @mkdir($dir, 0770, true);
                $arq = $dir . '/' . date('Ymd-His') . '-' . bin2hex(random_bytes(3)) . '.eml';
                return (bool) file_put_contents($arq, "To: $para\r\nSubject: " . self::cab($assunto) . "\r\n" . $cabecalhos . "\r\n\r\n" . $corpo);
            }
            return mail($para, self::cab($assunto), $corpo, $cabecalhos, '-f' . $from);
        }
        return $this->smtp($from, $para, "To: $para\r\nSubject: " . self::cab($assunto) . "\r\n" . $cabecalhos, $corpo);
    }

    /** Assunto/nome com acento no formato que todo cliente de e-mail entende. */
    private static function cab(string $v): string
    {
        return preg_match('/[^\x20-\x7E]/', $v) ? '=?UTF-8?B?' . base64_encode($v) . '?=' : $v;
    }

    private static function montar(string $from, string $fromName, string $para, string $assunto, string $html, string $texto): array
    {
        $b = 'b' . bin2hex(random_bytes(12));
        $dominio = substr(strrchr($from, '@') ?: '@localhost', 1);
        $h = [
            'From: ' . self::cab($fromName) . " <$from>",
            'Reply-To: ' . $from,
            'Date: ' . date('r'),
            'Message-ID: <' . bin2hex(random_bytes(10)) . '@' . $dominio . '>',
            'MIME-Version: 1.0',
            'Content-Type: multipart/alternative; boundary="' . $b . '"',
            'X-Mailer: OAF-Controle-Financeiro',
        ];
        $corpo = "--$b\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n" . chunk_split(base64_encode($texto))
               . "--$b\r\nContent-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n" . chunk_split(base64_encode($html))
               . "--$b--\r\n";
        return [implode("\r\n", $h), $corpo];
    }

    // ─── SMTP ───────────────────────────────────────────────────────────────
    private function smtp(string $from, string $para, string $cabecalhos, string $corpo): bool
    {
        $host = (string) env('MAIL_HOST');
        $port = (int) env('MAIL_PORT', 587);
        $seg = strtolower((string) env('MAIL_SECURE', $port === 465 ? 'ssl' : 'tls'));
        $ctx = stream_context_create(['ssl' => ['verify_peer' => true, 'verify_peer_name' => true, 'SNI_enabled' => true, 'peer_name' => $host]]);
        $alvo = ($seg === 'ssl' ? 'ssl://' : 'tcp://') . $host . ':' . $port;
        $this->sock = @stream_socket_client($alvo, $errno, $errstr, 15, STREAM_CLIENT_CONNECT, $ctx);
        if (!$this->sock) throw new \RuntimeException("Sem conexão com $alvo: $errstr");
        stream_set_timeout($this->sock, 20);
        $this->esperar(220);
        $eu = preg_replace('/[^a-z0-9.\-]/i', '', (string) ($_SERVER['SERVER_NAME'] ?? 'localhost')) ?: 'localhost';
        $ehlo = $this->cmd("EHLO $eu", 250);
        if ($seg === 'tls') {
            if (stripos($ehlo, 'STARTTLS') === false) throw new \RuntimeException('Servidor não oferece STARTTLS (use MAIL_SECURE=ssl e porta 465?)');
            $this->cmd('STARTTLS', 220);
            if (!stream_socket_enable_crypto($this->sock, true, STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT)) throw new \RuntimeException('Falha no TLS');
            $this->cmd("EHLO $eu", 250);
        }
        if (env('MAIL_USER')) {
            $this->cmd('AUTH LOGIN', 334);
            $this->cmd(base64_encode((string) env('MAIL_USER')), 334);
            $this->cmd(base64_encode((string) env('MAIL_PASS', '')), 235, true);
        }
        $this->cmd("MAIL FROM:<$from>", 250);
        $this->cmd("RCPT TO:<$para>", [250, 251]);
        $this->cmd('DATA', 354);
        // Linhas que começam com "." ganham outro ponto (regra do SMTP).
        $dados = preg_replace('/^\./m', '..', str_replace(["\r\n", "\r"], "\n", $cabecalhos . "\r\n\r\n" . $corpo));
        fwrite($this->sock, str_replace("\n", "\r\n", $dados) . "\r\n.\r\n");
        $this->esperar(250);
        @fwrite($this->sock, "QUIT\r\n");
        @fclose($this->sock);
        return true;
    }

    private function cmd(string $c, $esperado, bool $segredo = false): string
    {
        fwrite($this->sock, $c . "\r\n");
        $this->log[] = '> ' . ($segredo ? '***' : (strpos($c, 'AUTH') === 0 || preg_match('/^[A-Za-z0-9+\/=]{8,}$/', $c) ? '***' : $c));
        return $this->esperar($esperado);
    }

    private function esperar($esperado): string
    {
        $resp = '';
        while (($l = fgets($this->sock, 1024)) !== false) {
            $resp .= $l;
            if (strlen($l) < 4 || $l[3] === ' ') break;
        }
        $this->log[] = '< ' . trim($resp);
        $cod = (int) substr($resp, 0, 3);
        if (!in_array($cod, (array) $esperado, true)) throw new \RuntimeException('SMTP respondeu: ' . trim($resp));
        return $resp;
    }
}
