<?php
namespace App\Controllers\Api;

use App\Core\Controller;
use App\Core\Database;
use App\Core\HttpException;
use App\Models\Categoria;

class CategoriaController extends Controller
{
    public function index(): array
    {
        $this->autorizar('cad-categorias', 'lancamentos');
        return Categoria::listar((int) $this->query('year', date('Y')));
    }

    public function store(): array
    {
        $this->autorizarEdicao('cad-categorias');
        $f = Categoria::campos($this->body());
        if (Categoria::duplicada($f['nome'], $f['tipo'])) throw new HttpException(400, 'Já existe uma categoria com esse nome e tipo');
        $id = Categoria::create($f);
        $this->log('create', 'categorias', $id, $f['nome']);
        return ['id' => $id];
    }

    public function update($id): array
    {
        $this->autorizarEdicao('cad-categorias');
        $id = (int) $id;
        $atual = Categoria::findOrFail($id, 'Categoria não encontrada');
        $f = Categoria::campos($this->body());
        if (Categoria::duplicada($f['nome'], $f['tipo'], $id)) throw new HttpException(400, 'Já existe uma categoria com esse nome e tipo');
        // Não deixa mudar o tipo se isso deixaria lançamentos com categoria incompatível.
        if ($f['tipo'] !== 'ambos' && $f['tipo'] !== $atual['tipo']) {
            $conflito = (int) Database::value('SELECT COUNT(*) FROM transactions WHERE categoria_id=? AND tipo<>?', [$id, $f['tipo']]);
            if ($conflito) throw new HttpException(400, "Existem $conflito lançamento(s) do outro tipo usando essa categoria. Use \"Ambos\" ou troque a categoria deles antes.");
        }
        Categoria::update($id, $f);
        if ($f['nome'] !== $atual['nome']) Database::run('UPDATE transactions SET categoria_nome=? WHERE categoria_id=?', [$f['nome'], $id]);
        $this->log('update', 'categorias', $id, $f['nome']);
        return ['ok' => true];
    }

    public function destroy($id): array
    {
        $this->autorizarEdicao('cad-categorias');
        $id = (int) $id;
        $row = Categoria::findOrFail($id, 'Categoria não encontrada');
        $uso = (int) Database::value('SELECT COUNT(*) FROM transactions WHERE categoria_id=?', [$id]);
        if ($uso) throw new HttpException(400, "Categoria usada em $uso lançamento(s). Desative em vez de excluir, pra não perder o histórico.");
        Categoria::delete($id);
        $this->log('delete', 'categorias', $id, $row['nome'], null, $row);
        return ['ok' => true];
    }
}
