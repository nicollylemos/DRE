<?php
namespace App\Controllers\Api;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Csrf;
use App\Core\Database;
use App\Core\HttpException;
use App\Core\Termos;
use App\Models\PasswordReset;
use App\Models\Perfil;
use App\Models\RateLimit;
use App\Models\User;
use App\Services\Mailer;
use App\Services\Upload;

/** Login, primeiro acesso, troca de senha, perfil próprio. */
class AuthController extends Controller
{
    /** Hash qualquer: quando o usuário não existe a verificação leva o mesmo tempo (não entrega quais usuários existem). */
    private const HASH_FALSO = '$2y$10$EY8aN5to9FtwX.TH5iOpkeIXM1WmUcx2mUDVkMf8fBy7uzOxbyFyu';

    public function setupStatus(): array
    {
        return ['needsSetup' => User::count() === 0, 'version' => APP_VERSION];
    }

    /** Cadastro público só existe pro primeiro acesso (banco sem usuários) — esse vira administrador. */
    public function register(): array
    {
        RateLimit::verificar('register', 5, 600);
        if (User::count() > 0) throw new HttpException(403, 'Cadastro público desativado. Peça a um administrador para criar seu acesso.');
        $username = trim((string) $this->input('username', ''));
        $senha = (string) $this->input('password', '');
        User::validarUsername($username);
        if ($err = senha_fraca($senha)) throw new HttpException(400, $err);
        if ($senha !== (string) $this->input('confirmacao', $senha)) throw new HttpException(400, 'As senhas não conferem');
        if (!$this->input('aceite_termos')) throw new HttpException(400, 'Para continuar, aceite o Termo de uso e a Política de privacidade');
        $email = str_or_null($this->input('email'));
        if ($email && !filter_var($email, FILTER_VALIDATE_EMAIL)) throw new HttpException(400, 'E-mail inválido');
        $perfilAdmin = (int) Database::value('SELECT id FROM perfis WHERE is_admin=1 ORDER BY sistema DESC, id LIMIT 1');
        $id = User::create([
            'username' => $username, 'nome' => cut(str_or_null($this->input('nome')), 150), 'password' => password_hash($senha, PASSWORD_BCRYPT),
            'role' => 'admin', 'perfil_id' => $perfilAdmin ?: null, 'senha_alterada_em' => date('Y-m-d H:i:s'),
            'email' => cut($email, 150), 'termos_versao' => Termos::VERSAO, 'termos_aceitos_em' => date('Y-m-d H:i:s'),
        ]);
        $u = User::find($id);
        User::registrarLogin($id, client_ip());
        Auth::login($u, false);
        $this->log('setup_admin', 'auth', $id, "Primeiro administrador criado: $username");
        $this->log('aceite_termos', 'auth', $id, 'Termo de uso e Política de privacidade v' . Termos::VERSAO);
        return Auth::out() + ['csrf' => Csrf::token()];
    }

    public function login(): array
    {
        // Por IP: 15 senhas erradas em 10 min bloqueiam este computador (acertos não contam).
        RateLimit::checar('login', 15, 600);
        $username = trim((string) $this->input('username', ''));
        $senha = (string) $this->input('password', '');
        if ($username === '' || $senha === '') throw new HttpException(400, 'Informe usuário e senha');

        $u = User::porUsername($username);
        if ($u && ($ate = User::bloqueadoAte($u))) {
            throw new HttpException(423, 'Muitas tentativas erradas para este usuário. Aguarde ' . RateLimit::tempo($ate - time()) . ' ou peça para um administrador desbloquear.', 'BLOQUEADO');
        }
        $ok = password_verify($senha, $u ? $u['password'] : self::HASH_FALSO);
        if (!$u || !$ok) {
            RateLimit::contar('login', 600);
            if ($u) {
                $restam = User::registrarFalha($u);
                $this->log('login_failed', 'auth', $u['id'], 'Senha incorreta' . ($restam === 0 ? ' — usuário bloqueado por ' . User::MINUTOS_BLOQUEIO . ' min' : ''), $username);
                if ($restam === 0) throw new HttpException(423, 'Muitas tentativas erradas. Este usuário ficou bloqueado por ' . User::MINUTOS_BLOQUEIO . ' minutos.', 'BLOQUEADO');
            } else {
                $this->log('login_failed', 'auth', null, "Usuário inexistente: $username", mb_substr($username, 0, 100));
            }
            throw new HttpException(401, 'Usuário ou senha incorretos');
        }
        if (!(int) $u['ativo']) {
            $this->log('login_failed', 'auth', $u['id'], 'Acesso desativado', $username);
            throw new HttpException(403, 'Este acesso está desativado. Fale com um administrador.', 'DESATIVADO');
        }
        // Hashes antigos (bcryptjs $2a$/$2b$) são aceitos pelo password_verify; regrava no formato atual.
        if (password_needs_rehash($u['password'], PASSWORD_BCRYPT)) {
            Database::run('UPDATE users SET password=? WHERE id=?', [password_hash($senha, PASSWORD_BCRYPT), $u['id']]);
            $u = User::find((int) $u['id']);
        }
        User::registrarLogin((int) $u['id'], client_ip());
        Auth::login($u, (bool) $this->input('lembrar', false));
        $this->log('login', 'auth', $u['id'], $this->input('lembrar') ? 'Manter conectado' : null);
        return Auth::out() + ['csrf' => Csrf::token()];
    }

    public function logout(): array
    {
        if ($u = Auth::user()) $this->log('logout', 'auth', $u['id']);
        Auth::logout();
        return ['ok' => true];
    }

    public function me(): array
    {
        Auth::require();
        return Auth::out() + ['csrf' => Csrf::token()];
    }

    /** Troca a própria senha. Obrigatória no primeiro acesso com senha provisória. */
    public function trocarSenha(): array
    {
        $u = Auth::require();
        $atual = (string) $this->input('senha_atual', '');
        $nova = (string) $this->input('nova', '');
        $conf = (string) $this->input('confirmacao', '');
        $full = User::find((int) $u['id']);
        if (!(int) $u['deve_trocar_senha'] || $atual !== '') {
            // Fora da troca obrigatória pede a senha atual (quem pegou o computador destravado não troca).
            if (!password_verify($atual, $full['password'])) throw new HttpException(400, 'Senha atual incorreta');
        }
        if ($err = senha_fraca($nova)) throw new HttpException(400, $err);
        if ($nova !== $conf) throw new HttpException(400, 'A confirmação não confere com a nova senha');
        if (password_verify($nova, $full['password'])) throw new HttpException(400, 'A nova senha precisa ser diferente da atual');
        User::definirSenha((int) $u['id'], $nova, false);
        Auth::reassinar();
        $this->log('update', 'users', $u['id'], 'Trocou a própria senha');
        return Auth::out(User::comPerfil((int) $u['id'])) + ['csrf' => Csrf::token()];
    }

    // ─── LGPD: aceite do Termo de uso e da Política de privacidade ─────────
    public function aceitarTermos(): array
    {
        $u = Auth::require();
        if (!$this->input('aceite')) throw new HttpException(400, 'Marque que você leu e concorda para continuar');
        User::update((int) $u['id'], ['termos_versao' => Termos::VERSAO, 'termos_aceitos_em' => date('Y-m-d H:i:s')]);
        $this->log('aceite_termos', 'auth', $u['id'], 'Termo de uso e Política de privacidade v' . Termos::VERSAO);
        return Auth::out(User::comPerfil((int) $u['id'])) + ['csrf' => Csrf::token()];
    }

    // ─── Esqueci minha senha ────────────────────────────────────────────────
    /**
     * Recebe usuário OU e-mail e manda o link por e-mail. A resposta é sempre a mesma
     * (não revela se o usuário existe nem se tem e-mail cadastrado).
     */
    public function esqueciSenha(): array
    {
        RateLimit::verificar('esqueci', 5, 900);
        $login = trim((string) $this->input('login', ''));
        $resposta = ['ok' => true, 'mensagem' => 'Se esse usuário ou e-mail estiver cadastrado, enviamos um link para criar uma nova senha. Ele vale por ' . PasswordReset::VALIDADE_MIN . ' minutos. Confira também a caixa de spam.'];
        if ($login === '' || mb_strlen($login) > 150) throw new HttpException(400, 'Informe seu usuário ou e-mail');

        $usuarios = strpos($login, '@') !== false
            ? Database::all('SELECT id, username, nome, email, ativo FROM users WHERE email=? AND ativo=1 LIMIT 5', [$login])
            : Database::all('SELECT id, username, nome, email, ativo FROM users WHERE username=? AND ativo=1', [$login]);
        $base = self::urlBase();
        foreach ($usuarios as $u) {
            if (!$u['email'] || !filter_var($u['email'], FILTER_VALIDATE_EMAIL)) {
                $this->log('senha_esqueci', 'auth', $u['id'], 'Pediu nova senha, mas não tem e-mail cadastrado', $u['username']);
                continue;
            }
            if (PasswordReset::recente((int) $u['id'])) continue;
            if (!$base) {
                error_log('[ESQUECI] APP_URL não configurado no .env — e-mail de nova senha não enviado (obrigatório em produção).');
                continue;
            }
            $token = PasswordReset::criar((int) $u['id'], client_ip());
            $link = $base . 'login?redefinir=' . $token;
            $ok = Mailer::enviar($u['email'], 'Criar nova senha — OAF Controle Financeiro', self::emailHtml($u, $link), self::emailTexto($u, $link));
            $this->log('senha_esqueci', 'auth', $u['id'], $ok ? 'Link de nova senha enviado para ' . self::mascararEmail($u['email']) : 'FALHA ao enviar o e-mail de nova senha (veja o log do PHP)', $u['username']);
        }
        if (!$usuarios) $this->log('senha_esqueci', 'auth', null, 'Pedido para usuário/e-mail não cadastrado', mb_substr($login, 0, 100));
        return $resposta;
    }

    /** Cria a nova senha a partir do link do e-mail. */
    public function redefinirSenha(): array
    {
        RateLimit::verificar('redefinir', 10, 900);
        $token = (string) $this->input('token', '');
        $nova = (string) $this->input('nova', '');
        $r = PasswordReset::valido($token);
        if (!$r) throw new HttpException(400, 'Esse link não vale mais (expirou ou já foi usado). Peça um novo em "Esqueci minha senha".', 'LINK_INVALIDO');
        if ($err = senha_fraca($nova)) throw new HttpException(400, $err);
        if ($nova !== (string) $this->input('confirmacao', '')) throw new HttpException(400, 'A confirmação não confere com a nova senha');
        User::definirSenha((int) $r['id'], $nova, false);
        PasswordReset::usar((int) $r['reset_id'], (int) $r['id']);
        $this->log('senha_redefinida', 'auth', $r['id'], 'Nova senha criada pelo link do e-mail', $r['username']);
        return ['ok' => true, 'username' => $r['username']];
    }

    /** Endereço do sistema pros links do e-mail. Em produção tem que vir do .env (APP_URL) — o Host do pedido pode ser forjado. */
    private static function urlBase(): ?string
    {
        $app = trim((string) env('APP_URL', ''));
        if ($app !== '') return rtrim($app, '/') . '/';
        if (IS_PROD) return null;
        $host = preg_replace('/[^a-z0-9.\-:\[\]]/i', '', (string) ($_SERVER['HTTP_HOST'] ?? 'localhost'));
        return (\App\Core\Session::https() ? 'https://' : 'http://') . $host . base_path();
    }

    private static function mascararEmail(string $e): string
    {
        [$a, $d] = array_pad(explode('@', $e, 2), 2, '');
        return mb_substr($a, 0, 2) . str_repeat('•', max(1, mb_strlen($a) - 2)) . '@' . $d;
    }

    private static function emailTexto(array $u, string $link): string
    {
        $nome = $u['nome'] ?: $u['username'];
        return "Olá, $nome!\n\nRecebemos um pedido para criar uma nova senha no OAF Controle Financeiro (usuário: {$u['username']}).\n\n"
             . "Abra o link abaixo para criar a nova senha. Ele vale por " . PasswordReset::VALIDADE_MIN . " minutos e só pode ser usado uma vez:\n$link\n\n"
             . "Se não foi você, ignore este e-mail — sua senha atual continua valendo.\n";
    }

    private static function emailHtml(array $u, string $link): string
    {
        $nome = e($u['nome'] ?: $u['username']);
        $l = e($link);
        $min = PasswordReset::VALIDADE_MIN;
        return <<<HTML
<!doctype html><html lang="pt-BR"><body style="margin:0;background:#F2F3F7;font-family:Arial,Helvetica,sans-serif;color:#3C3F5C">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#F2F3F7;padding:32px 12px"><tr><td align="center">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:520px;background:#ffffff;border-radius:16px;overflow:hidden">
<tr><td style="background:#282A5C;padding:22px 28px;color:#ffffff;font-size:18px;font-weight:bold">OAF <span style="color:#C9A55F">Controle Financeiro</span></td></tr>
<tr><td style="padding:28px">
<p style="font-size:16px;margin:0 0 14px">Olá, <b>{$nome}</b>!</p>
<p style="font-size:15px;line-height:1.5;margin:0 0 22px">Recebemos um pedido para criar uma nova senha para o usuário <b>{$u['username']}</b>. Clique no botão abaixo — o link vale por <b>{$min} minutos</b> e só pode ser usado uma vez.</p>
<p style="margin:0 0 24px"><a href="{$l}" style="display:inline-block;background:#282A5C;color:#ffffff;text-decoration:none;font-weight:bold;padding:13px 22px;border-radius:10px">Criar nova senha</a></p>
<p style="font-size:13px;color:#7A7E98;line-height:1.5;margin:0 0 8px">Se o botão não abrir, copie este endereço no navegador:<br><span style="word-break:break-all;color:#34376F">{$l}</span></p>
<p style="font-size:13px;color:#7A7E98;line-height:1.5;margin:16px 0 0">Não foi você? Ignore este e-mail — sua senha atual continua valendo.</p>
</td></tr></table></td></tr></table></body></html>
HTML;
    }

    /** Edita o PRÓPRIO perfil (nome, e-mail, foto). POST porque o PHP não lê multipart em PUT. */
    public function perfil(): array
    {
        $u = Auth::require();
        $atual = User::find((int) $u['id']);
        $email = str_or_null($this->input('email'));
        if ($email && !filter_var($email, FILTER_VALIDATE_EMAIL)) throw new HttpException(400, 'E-mail inválido');
        $dados = ['nome' => cut(str_or_null($this->input('nome')), 150), 'email' => cut($email, 150)];
        if ($foto = Upload::salvar('foto', 'perfil', Upload::IMAGEM, 8 * 1024 * 1024)) {
            Upload::apagar($atual['foto_path']);
            $dados['foto_path'] = $foto['path']; $dados['foto_nome'] = $foto['nome'];
        } elseif ($this->input('remover_foto') === '1') {
            Upload::apagar($atual['foto_path']);
            $dados['foto_path'] = $dados['foto_nome'] = null;
        }
        User::update((int) $u['id'], $dados);
        $this->log('update', 'users', $u['id'], 'Atualizou o próprio perfil');
        return Auth::out(User::comPerfil((int) $u['id']));
    }
}
