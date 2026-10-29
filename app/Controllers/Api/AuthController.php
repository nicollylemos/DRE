<?php
namespace App\Controllers\Api;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Csrf;
use App\Core\Database;
use App\Core\HttpException;
use App\Core\Termos;
use App\Models\Perfil;
use App\Models\RateLimit;
use App\Models\User;
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
        $perfilAdmin = (int) Database::value('SELECT id FROM perfis WHERE is_admin=1 ORDER BY sistema DESC, id LIMIT 1');
        $id = User::create([
            'username' => $username, 'nome' => cut(str_or_null($this->input('nome')), 150), 'password' => password_hash($senha, PASSWORD_BCRYPT),
            'role' => 'admin', 'perfil_id' => $perfilAdmin ?: null, 'senha_alterada_em' => date('Y-m-d H:i:s'),
            'termos_versao' => Termos::VERSAO, 'termos_aceitos_em' => date('Y-m-d H:i:s'),
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

    /** Edita o PRÓPRIO perfil (nome, e-mail, foto). POST porque o PHP não lê multipart em PUT. */
    public function perfil(): array
    {
        $u = Auth::require();
        $atual = User::find((int) $u['id']);
        $email = str_or_null($this->input('email'));
        if ($email && !filter_var($email, FILTER_VALIDATE_EMAIL)) throw new HttpException(400, 'E-mail inválido');
        $fone = function (string $campo, string $rotulo): ?string {
            $d = only_digits((string) $this->input($campo, ''));
            if ($d === '') return null;
            if (strlen($d) < 10 || strlen($d) > 11) throw new HttpException(400, "$rotulo inválido: use DDD + número (10 ou 11 dígitos)");
            return $d;
        };
        $ramal = only_digits((string) $this->input('ramal', ''));
        if (strlen($ramal) > 6) throw new HttpException(400, 'Ramal: até 6 números');
        $dados = ['nome' => cut(str_or_null($this->input('nome')), 150), 'email' => cut($email, 150),
                  'cargo' => cut(str_or_null($this->input('cargo')), 100), 'setor' => cut(str_or_null($this->input('setor')), 100),
                  'telefone' => $fone('telefone', 'Telefone comercial'), 'ramal' => $ramal !== '' ? $ramal : null,
                  'whatsapp' => $fone('whatsapp', 'WhatsApp comercial')];
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
