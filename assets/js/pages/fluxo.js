/* Fluxo de Caixa: realizado x previsto, dia a dia. */
(function () {
  const fx = { dias: [], abertura: 0 };
  const hoje0 = new Date();
  $('fx-mes').innerHTML = MESES.map((m, i) => `<option value="${i + 1}" ${i === hoje0.getMonth() ? 'selected' : ''}>${m}</option>`).join('');
  $('fx-ano').innerHTML = anosLista().map(a => `<option value="${a}" ${a === hoje0.getFullYear() ? 'selected' : ''}>${a}</option>`).join('');
  ['fx-mes', 'fx-ano', 'fx-caixa'].forEach(id => $(id).addEventListener('change', carregar));
  $('fx-dias').addEventListener('change', render);
  $('fx-toolbar').addEventListener('submit', e => { e.preventDefault(); carregar(); });
  function passo(delta) {
    let m = Number($('fx-mes').value) + delta, a = Number($('fx-ano').value);
    if (m < 1) { m = 12; a--; } if (m > 12) { m = 1; a++; }
    if (![...$('fx-ano').options].some(o => Number(o.value) === a)) $('fx-ano').insertAdjacentHTML('beforeend', `<option value="${a}">${a}</option>`);
    $('fx-mes').value = String(m); $('fx-ano').value = String(a); carregar();
  }
  $('fx-prev').addEventListener('click', () => passo(-1));
  $('fx-next').addEventListener('click', () => passo(1));

  async function carregar() {
    const qs = new URLSearchParams({ year: $('fx-ano').value, month: $('fx-mes').value });
    if ($('fx-caixa').value) qs.set('caixa', $('fx-caixa').value);
    const r = await api('dre/fluxo-caixa?' + qs);
    if (r.error) { toast(r.error, 'err'); fx.dias = []; fx.abertura = 0; }
    else { fx.dias = r.dias || []; fx.abertura = r.saldo_abertura || 0; }
    render();
  }
  function render() {
    const d = fx.dias, ult = d[d.length - 1] || { saldo: fx.abertura, saldo_projetado: fx.abertura };
    const soma = k => d.reduce((s, x) => s + (x[k] || 0), 0);
    $('fx-abertura').textContent = fmtBRLs(fx.abertura);
    $('fx-rec').textContent = fmtBRL(soma('receita'));
    $('fx-desp').textContent = fmtBRL(soma('despesa'));
    $('fx-saldo').textContent = fmtBRLs(ult.saldo);
    $('fx-saldo').classList.toggle('neg-on-dark', ult.saldo < 0);
    $('fx-proj').textContent = fmtBRLs(ult.saldo_projetado);
    const prev = soma('previsto');
    $('fx-proj-sub').textContent = prev ? `${prev > 0 ? '+' : '−'} ${fmtBRL(Math.abs(prev))} em aberto no mês` : 'nada em aberto no mês';
    const so = fv('fx-dias') === 'mov', hoje = hojeISO();
    const linhas = d.filter(x => !so || x.receita || x.despesa || x.transferencia || x.receita_prevista || x.despesa_prevista || x.data === hoje);
    const v = (n, cls = '') => n ? `<td class="money r ${cls}">${fmtBRL(n)}</td>` : '<td class="r dim">—</td>';
    const vs = n => n ? `<td class="money r ${n < 0 ? 'neg' : 'pos'}">${fmtBRLs(n)}</td>` : '<td class="r dim">—</td>';
    document.querySelector('#fx-table tbody').innerHTML = linhas.length ? linhas.map(x => {
      const dt = new Date(x.data + 'T12:00'), fds = dt.getDay() === 0 || dt.getDay() === 6;
      return `<tr class="${x.data === hoje ? 'today' : ''} ${fds ? 'weekend' : ''}">
        <td class="nowrap num"><span class="ttl">${fmtData(x.data).slice(0, 5)}</span> <span class="dim">${dt.toLocaleDateString('pt-BR', { weekday: 'short' }).replace('.', '')}</span></td>
        ${v(x.receita, 'pos')}${v(x.despesa, 'neg')}${v(x.transferencia)}${vs(x.resultado)}
        <td class="money r ${x.saldo < 0 ? 'neg' : ''}">${fmtBRLs(x.saldo)}</td>
        ${x.previsto ? `<td class="money r ${x.previsto < 0 ? 'neg' : 'pos'}" title="A receber ${fmtBRL(x.receita_prevista)} · a pagar ${fmtBRL(x.despesa_prevista)}">${fmtBRLs(x.previsto)}</td>` : '<td class="r dim">—</td>'}
        <td class="money r ${x.saldo_projetado < 0 ? 'neg' : ''}">${fmtBRLs(x.saldo_projetado)}</td>
      </tr>`;
    }).join('') : `<tr><td colspan="8" class="empty"><strong>Nenhuma movimentação neste mês</strong>Troque o mês ou veja “Todos os dias”.</td></tr>`;
    document.querySelector('#fx-table tfoot').innerHTML = d.length ? `<tr><td>Total do mês</td><td class="r pos">${fmtBRL(soma('receita'))}</td><td class="r neg">${fmtBRL(soma('despesa'))}</td>
      <td class="r">${fmtBRL(soma('transferencia'))}</td><td class="r">${fmtBRLs(soma('resultado'))}</td><td class="r">${fmtBRLs(ult.saldo)}</td><td class="r">${fmtBRLs(prev)}</td><td class="r">${fmtBRLs(ult.saldo_projetado)}</td></tr>` : '';
  }

  carregarCadastros().then(c => {
    $('fx-caixa').innerHTML = '<option value="">Todos os caixas</option>' + (c.caixasBancos || []).map(n => `<option value="${esc(n)}">${esc(n)}</option>`).join('');
    carregar();
  });
})();
