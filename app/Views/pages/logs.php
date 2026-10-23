<section class="page active" id="page-logs">
  <header class="page-head">
    <div><h1>Logs</h1><p>Histórico de tudo que foi criado, alterado ou apagado no sistema, com quem fez e quando.</p></div>
  </header>
  <form class="toolbar" id="log-toolbar">
    <div class="select"><select id="log-f-acao" aria-label="Ação"></select></div>
    <div class="select"><select id="log-f-area" aria-label="Área"></select></div>
    <label class="search sm"><svg class="ico-search" viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg><input type="search" id="log-f-user" placeholder="Usuário"/></label>
    <label class="date-f"><span>De</span><input type="date" id="log-f-de"/></label>
    <label class="date-f"><span>Até</span><input type="date" id="log-f-ate"/></label>
    <button type="button" class="link" id="log-limpar">Limpar filtros</button>
    <span class="count" id="log-count"></span>
  </form>
  <div class="table-card">
    <div class="table-scroll">
      <table class="tbl" id="log-table">
        <thead><tr><th>Data / hora</th><th>Usuário</th><th>Ação</th><th>Área</th><th>Detalhes</th><th>IP</th></tr></thead>
        <tbody></tbody>
      </table>
    </div>
    <nav class="pager" id="log-pager"></nav>
  </div>
</section>
