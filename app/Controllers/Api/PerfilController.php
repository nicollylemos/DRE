<?php
namespace App\Controllers\Api;

use App\Core\Controller;
use App\Core\Database;
use App\Core\HttpException;
use App\Core\Permissions;
use App\Models\Perfil;

/** Níveis de acesso (só administrador). */
class PerfilController extends Controller
{
    public function index(): array
    {
        $this->somenteAdmin();
        return ['perfis' => Perfil::listar(), 'telas' => Permissions::catalogo()];
    }

    public function store(): array
    {
        $this->somenteAdmin();
        $f = Perfil::campos(null) + ['is_admin' => 0, 'sistema' => 0];
        $id = Perfil::create($f);
        $this->log('create', 'perfis', $id, $f['nome']);
        return ['id' => $id];
    }

    public function update($id): array
    {
        $this->somenteAdmin();
        $id = (int) $id;
        $atual = Perfil::findOrFail($id, 'Nível de acesso não encontrado');
        if ((int) $atual['is_admin']) {
            // Administrador sempre tem tudo: só nome e descrição mudam.
            $nome = trim((string) $this->input('nome', $atual['nome'])) ?: $atual['nome'];
            if (Database::one('SELECT id FROM perfis WHERE nome=? AND id<>?', [$nome, $id])) throw new HttpException(400, 'Já existe um nível com esse nome');
            Perfil::update($id, ['nome' => mb_substr($nome, 0, 80), 'descricao' => cut(str_or_null($this->input('descricao')), 255)]);
            $this->log('update', 'perfis', $id, $nome);
            return ['ok' => true];
        }
        $f = Perfil::campos($id);
        Perfil::update($id, $f);
        $diff = Perfil::diffPermissoes(Permissions::normalizar($atual['permissoes']), json_decode($f['permissoes'], true));
        $this->log('update', 'perfis', $id, $f['nome'] . ($diff ? " — $diff" : ''));
        return ['ok' => true];
    }

    /** Apaga o nível. Se tiver usuários, precisa dizer pra qual nível eles vão (?mover_para=ID). */
    public function destroy($id): array
    {
        $this->somenteAdmin();
        $id = (int) $id;
        $p = Perfil::findOrFail($id, 'Nível de acesso não encontrado');
        if ((int) $p['sistema'] || (int) $p['is_admin']) throw new HttpException(400, 'O nível Administrador não pode ser apagado');
        $qtd = (int) Database::value('SELECT COUNT(*) FROM users WHERE perfil_id=?', [$id]);
        if ($qtd) {
            $destino = (int) $this->query('mover_para', 0);
            $d = $destino && $destino !== $id ? Perfil::find($destino) : null;
            if (!$d) throw new HttpException(400, "$qtd usuário(s) usam este nível. Escolha para qual nível eles vão antes de apagar.");
            Database::run('UPDATE users SET perfil_id=?, role=? WHERE perfil_id=?', [$destino, (int) $d['is_admin'] ? 'admin' : 'user', $id]);
        }
        Perfil::delete($id);
        $this->log('delete', 'perfis', $id, $p['nome'] . ($qtd ? " — $qtd usuário(s) movidos" : ''));
        return ['ok' => true];
    }
}
