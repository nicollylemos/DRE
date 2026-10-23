<?php
namespace App\Controllers\Api;

use App\Core\Controller;
use App\Core\Database;
use App\Core\HttpException;
use App\Models\Perfil;
use App\Models\User;
use App\Services\Upload;

/** Gerenciamento de usuários (só administrador). */
class UsuarioController extends Controller
{
    public function index(): array
    {
        $this->somenteAdmin();
        return User::listar();
    }

    private function perfilValido($id): array
    {
        $p = $id ? Perfil::find((int) $id) : null;
        if (!$p) throw new HttpException(400, 'Escolha o nível de acesso');
        return $p;
    }

    /** Senha: "gerar" cria uma provisória (devolvida uma única vez) ou "definir" usa a digitada. */
    private function senhaDoFormulario(): array
    {
        if ($this->input('senha_modo', 'gerar') === 'definir') {
            $s = (string) $this->input('password', '');
            if ($err = senha_fraca($s)) throw new HttpException(400, $err);
            return [$s, false];
        }
        return [User::senhaProvisoria(), true];
    }

    public function store(): array
    {
        $this->somenteAdmin();
        $username = trim((string) $this->input('username', ''));
        User::validarUsername($username);
        $perfil = $this->perfilValido($this->input('perfil_id'));
        $email = str_or_null($this->input('email'));
        if ($email && !filter_var($email, FILTER_VALIDATE_EMAIL)) throw new HttpException(400, 'E-mail inválido');
        [$senha, $gerada] = $this->senhaDoFormulario();
        $trocar = $gerada || (bool) $this->input('trocar_senha', true);
        $id = User::create([
            'username' => $username, 'nome' => cut(str_or_null($this->input('nome')), 150), 'email' => cut($email, 150),
            'password' => password_hash($senha, PASSWORD_BCRYPT), 'role' => (int) $perfil['is_admin'] ? 'admin' : 'user',
            'perfil_id' => (int) $perfil['id'], 'ativo' => 1, 'deve_trocar_senha' => $trocar ? 1 : 0,
            'senha_alterada_em' => date('Y-m-d H:i:s'),
        ]);
        $this->log('create', 'users', $id, "$username — nível: {$perfil['nome']}" . ($trocar ? ' — troca de senha no 1º acesso' : ''));
        return ['id' => $id, 'username' => $username, 'senha_provisoria' => $gerada ? $senha : null];
    }

    public function update($id): array
    {
        $eu = $this->somenteAdmin();
        $id = (int) $id;
        $atual = User::comPerfil($id) ?? throw new HttpException(404, 'Usuário não encontrado');
        $username = trim((string) $this->input('username', $atual['username']));
        User::validarUsername($username, $id);
        $perfil = $this->perfilValido($this->input('perfil_id', $atual['perfil_id']));
        $email = str_or_null($this->input('email'));
        if ($email && !filter_var($email, FILTER_VALIDATE_EMAIL)) throw new HttpException(400, 'E-mail inválido');
        $viraAdmin = (int) $perfil['is_admin'] === 1;
        // Não deixa o sistema ficar sem administrador ativo (nem o admin se rebaixar).
        if ((int) $atual['is_admin'] && !$viraAdmin) {
            if ($id === (int) $eu['id']) throw new HttpException(400, 'Você não pode tirar o seu próprio acesso de administrador');
            if (!User::adminsRestantes($id)) throw new HttpException(400, 'O sistema precisa de pelo menos um administrador ativo');
        }
        User::update($id, [
            'username' => $username, 'nome' => cut(str_or_null($this->input('nome')), 150), 'email' => cut($email, 150),
            'perfil_id' => (int) $perfil['id'], 'role' => $viraAdmin ? 'admin' : 'user',
        ]);
        $mud = [];
        if ((int) $atual['perfil_id'] !== (int) $perfil['id']) $mud[] = 'nível: ' . ($atual['perfil_nome'] ?? '∅') . ' → ' . $perfil['nome'];
        if ($atual['username'] !== $username) $mud[] = "usuário: {$atual['username']} → $username";
        $this->log($mud && (int) $atual['perfil_id'] !== (int) $perfil['id'] ? 'update_role' : 'update', 'users', $id, $username . ($mud ? ' — ' . implode('; ', $mud) : ''));
        return ['ok' => true];
    }

    /** Redefine a senha de outra pessoa. A sessão dela cai na hora. */
    public function senha($id): array
    {
        $this->somenteAdmin();
        $id = (int) $id;
        $u = User::findOrFail($id, 'Usuário não encontrado');
        [$senha, $gerada] = $this->senhaDoFormulario();
        $trocar = $gerada || (bool) $this->input('trocar_senha', true);
        User::definirSenha($id, $senha, $trocar);
        $this->log('reset_senha', 'users', $id, $u['username'] . ($gerada ? ' — senha provisória gerada' : ' — senha definida pelo administrador'));
        return ['ok' => true, 'senha_provisoria' => $gerada ? $senha : null];
    }

    public function situacao($id): array
    {
        $eu = $this->somenteAdmin();
        $id = (int) $id;
        $u = User::comPerfil($id) ?? throw new HttpException(404, 'Usuário não encontrado');
        $ativo = $this->input('ativo') ? 1 : 0;
        if (!$ativo) {
            if ($id === (int) $eu['id']) throw new HttpException(400, 'Você não pode desativar o próprio acesso');
            if ((int) $u['is_admin'] && !User::adminsRestantes($id)) throw new HttpException(400, 'O sistema precisa de pelo menos um administrador ativo');
        }
        User::update($id, ['ativo' => $ativo]);
        $this->log('update', 'users', $id, $u['username'] . ($ativo ? ' — acesso reativado' : ' — acesso desativado'));
        return ['ok' => true];
    }

    public function desbloquear($id): array
    {
        $this->somenteAdmin();
        $u = User::findOrFail((int) $id, 'Usuário não encontrado');
        User::desbloquear((int) $id);
        $this->log('update', 'users', (int) $id, $u['username'] . ' — desbloqueado');
        return ['ok' => true];
    }

    public function destroy($id): array
    {
        $eu = $this->somenteAdmin();
        $id = (int) $id;
        if ($id === (int) $eu['id']) throw new HttpException(400, 'Você não pode apagar a si mesmo');
        $u = User::comPerfil($id) ?? throw new HttpException(404, 'Usuário não encontrado');
        if ((int) $u['is_admin'] && !User::adminsRestantes($id)) throw new HttpException(400, 'O sistema precisa de pelo menos um administrador ativo');
        User::delete($id);
        Upload::apagar($u['foto_path']);
        $this->log('delete', 'users', $id, "{$u['username']} ({$u['perfil_nome']})");
        return ['ok' => true];
    }
}
