<?php
/** Cabeçalho comum das páginas de LGPD. @var string $doc @var string $titulo @var bool $logado */
use App\Core\Termos;
$controlador = e(Termos::controlador()) . (Termos::cnpj() ? ', CNPJ ' . e(fmt_cnpj(Termos::cnpj())) : '');
$contato = Termos::email() ? '<a href="mailto:' . e(Termos::email()) . '">' . e(Termos::email()) . '</a>' : 'o administrador do sistema (menu <b>Usuários e acessos</b>)';
?>
<div class="lgpd">
  <header class="lgpd-top">
    <a class="lgpd-marca" href="<?= e(url($logado ? '' : 'login')) ?>"><img src="<?= e(url('assets/img/logo-oaf-mark.png')) ?>" alt=""/><span><b>OAF</b> Controle Financeiro</span></a>
    <nav><a href="<?= e(url('termos')) ?>" class="<?= $doc === 'termos' ? 'on' : '' ?>">Termo de uso</a><a href="<?= e(url('privacidade')) ?>" class="<?= $doc === 'privacidade' ? 'on' : '' ?>">Privacidade</a></nav>
  </header>
  <article class="lgpd-doc">
    <p class="lgpd-ver">Versão <?= e(Termos::VERSAO) ?> · vigente desde <?= e(Termos::VIGENCIA) ?></p>
