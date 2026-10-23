<?php
namespace App\Controllers\Api;

use App\Core\Controller;
use App\Models\Transacao;
use App\Services\DreService;

/** Relatórios: Dashboard, DRE, Fluxo de Caixa, agenda do Calendário. */
class DreController extends Controller
{
    public function dashboard(): array
    {
        $this->autorizar('dashboard');
        return DreService::dashboard(ano_param());
    }

    public function resumo(): array
    {
        $this->autorizar('dre');
        return DreService::resumo(ano_param());
    }

    public function detalhado(): array
    {
        $this->autorizar('dre');
        return DreService::detalhado(ano_param(), $this->query('agrupar') === 'categoria');
    }

    /** Demonstrativo completo da tela DRE (mês a mês, AV%, comparação com o ano anterior). */
    public function demonstrativo(): array
    {
        $this->autorizar('dre');
        return DreService::demonstrativo(ano_param(), (string) $this->query('agrupar', 'categoria'), (string) $this->query('regime', 'competencia'));
    }

    public function fluxoCaixa(): array
    {
        $this->autorizar('fluxo-caixa');
        $m = max(1, min(12, (int) $this->query('month', date('n'))));
        return DreService::fluxoCaixa(ano_param(), $m, $this->query('caixa'));
    }

    public function formasPagamento(): array
    {
        $this->autorizar('dre');
        return DreService::formasPagamento(ano_param());
    }

    /** Vencimentos do mês (Calendário; o Dashboard usa os "próximos"). */
    public function agenda(): array
    {
        $this->autorizar('calendario', 'dashboard');
        $m = max(1, min(12, (int) $this->query('month', date('n'))));
        return Transacao::agenda(ano_param(), $m);
    }
}
