#!/usr/bin/env python3
# Uso: php docs/testes/auditoria/sistema.php > sistema.json
#      python3 docs/testes/auditoria/auditor.py NOME_DO_BANCO sistema.json
# Precisa do cliente "mysql" no PATH (usuário root sem senha, como no XAMPP).
"""Recalcula DRE, dashboard e fluxo de caixa a partir dos lançamentos crus e compara com o sistema."""
import subprocess, json, sys, datetime, calendar
from collections import defaultdict
DB=sys.argv[1]; SIS=json.load(open(sys.argv[2]))
def q(sql):
    out=subprocess.run(['mysql','-uroot','--default-character-set=utf8mb4','-B','-N',DB,'-e',sql],capture_output=True,text=True).stdout
    return [l.split('\t') for l in out.splitlines() if l]
N=lambda v: None if v in ('NULL','',None) else v
TX=[dict(id=int(r[0]),tipo=r[1],data=r[2],valor=float(r[3]),taxa=float(r[4]),liq=float(r[5]) if N(r[5]) else None,status=r[6],venc=N(r[7]),pag=N(r[8]),caixa=r[9] if N(r[9]) else '',cat=N(r[10]) or 'Sem categoria',grupo=N(r[11]),conta=N(r[12]),centro=N(r[13]),forma=N(r[14]))
    for r in q("SELECT id,tipo,data,valor,taxa_mdr,valor_liquido,status,data_vencimento,data_pagamento,caixa_nome,categoria_nome,grupo,conta_nome,centro_custo_nome,forma_pagamento_nome FROM transactions")]
TR=[dict(data=r[0],valor=float(r[1]),orig=r[2],dest=r[3]) for r in q("SELECT data,valor,origem_nome,destino_nome FROM transfers")]
CX={r[0]:float(r[1]) for r in q("SELECT nome,saldo_inicial FROM caixas")}
FP={r[0]:(float(r[1]),float(r[2])) for r in q("SELECT nome,taxa_intermediacao,tarifa_fixa FROM formas_pagamento")}
HOJE=datetime.date.today().isoformat()
erros=[]; ok=[0]
def cmp(nome,a,b,tol=0.011):
    if a is None and b is None: ok[0]+=1; return
    if a is None or b is None or abs(float(a)-float(b))>tol: erros.append(f'{nome}: sistema={a} esperado={b}')
    else: ok[0]+=1
mes=lambda d:int(d[5:7]); ano=lambda d:int(d[:4])
pagto=lambda t: t['pag'] or t['data']

# 0) regras de cada lançamento
for t in TX:
    if t['tipo']=='expense': cmp(f"taxa em despesa #{t['id']}",t['taxa'],0)
    elif t['forma'] in FP:
        p,f=FP[t['forma']]; cmp(f"taxa receita #{t['id']}",t['taxa'],round(p/100*t['valor']+f,2))
    cmp(f"valor líquido #{t['id']}",t['liq'],round(t['valor']-t['taxa'],2))
    if t['status'] not in ('quitado','em_aberto'): erros.append(f"status estranho #{t['id']}: {t['status']}")

def dre(y,regime):
    L=[t for t in TX if (regime=='competencia' and ano(t['data'])==y) or (regime=='caixa' and t['status']=='quitado' and ano(pagto(t))==y)]
    dt=(lambda t:t['data']) if regime=='competencia' else pagto
    m=defaultdict(lambda:[0.0]*12)
    for t in L:
        i=mes(dt(t))-1
        if t['tipo']=='income': m['bruta'][i]+=t['valor']; m['ded'][i]+=t['taxa']
        else: m['desp'][i]+=t['valor']
    for i in range(12): m['liq'][i]=m['bruta'][i]-m['ded'][i]; m['res'][i]=m['liq'][i]-m['desp'][i]
    return m,L,dt
def chave(t,ag):
    if ag=='grupo': return ((t['grupo'] or 'Sem grupo')+(' — '+t['conta'] if t['conta'] else '')).strip()
    if ag=='centro': return t['centro'] or 'Sem centro de custo'
    return t['cat']

for y in (2024,2025,2026):
    s=str(y); m,L,_=dre(y,'competencia')
    # 1) Dashboard
    D=SIS['dash'][s]; dl=D['dreLines']
    cmp(f'{y} dashboard receita bruta',dl['Receita Bruta'],sum(m['bruta'])); cmp(f'{y} dashboard deduções',dl['(-) Impostos e Deduções'],sum(m['ded']))
    cmp(f'{y} dashboard despesas',dl['(-) Despesas Operacionais'],sum(m['desp'])); cmp(f'{y} dashboard lucro',dl['Lucro / Prejuízo Líquido'],sum(m['res']))
    for i,ms in enumerate(D['summary']): cmp(f'{y} dashboard gráfico mês {i+1}',ms['saldo'],m['res'][i])
    cat=defaultdict(float)
    for t in L:
        if t['tipo']=='expense': cat[t['cat']]+=t['valor']
    for k,v in cat.items(): cmp(f'{y} dashboard despesa categoria {k}',D['expensesByCategory'].get(k),v)
    # comparativo com ano anterior (até o mês atual no ano corrente)
    ate=datetime.date.today().month if y==datetime.date.today().year else 12
    ma,_,_=dre(y-1,'competencia')
    cmp(f'{y} comparativo resultado atual',D['comparativo']['atual']['resultado'],sum(m['res'][:ate]))
    cmp(f'{y} comparativo resultado anterior',D['comparativo']['anterior']['resultado'],sum(ma['res'][:ate]))
    # 2) Demonstrativo (tela DRE, PDF e Excel) — os dois regimes e os três agrupamentos
    for reg in ('competencia','caixa'):
        mr,Lr,dt=dre(y,reg); ant,La,dta=dre(y-1,reg)
        liqT=sum(mr['liq'])
        for ag in ('categoria','grupo','centro'):
            dem=SIS['dem'][s][reg][ag]; lin=dem['linhas']
            g=lambda tipo,label=None:[l for l in lin if l['tipo']==tipo and (label is None or l['label']==label)]
            cmp(f'{y} {reg}/{ag} receita bruta',g('grupo','Receita bruta')[0]['total'],sum(mr['bruta']))
            cmp(f'{y} {reg}/{ag} receita líquida',g('subtotal')[0]['total'],liqT)
            cmp(f'{y} {reg}/{ag} despesas',g('grupo','(−) Despesas')[0]['total'],sum(mr['desp']))
            r=g('resultado')[0]
            cmp(f'{y} {reg}/{ag} resultado',r['total'],sum(mr['res']))
            for i in range(12): cmp(f'{y} {reg}/{ag} resultado mês {i+1}',r['valores'][i],mr['res'][i])
            cmp(f'{y} {reg}/{ag} resultado ano anterior',r['anterior'],sum(ant['res']))
            if r['anterior'] and abs(sum(ant['res']))>0.004: cmp(f'{y} {reg}/{ag} AH% resultado',r['ah'],(sum(mr['res'])-sum(ant['res']))/abs(sum(ant['res'])),1e-9)
            if liqT: cmp(f'{y} {reg}/{ag} margem',dem['margem']['total'],sum(mr['res'])/liqT,1e-9)
            itens=defaultdict(float); itA=defaultdict(float)
            for t in Lr: itens[(t['tipo'],chave(t,ag))]+=t['valor']
            for t in La: itA[(t['tipo'],chave(t,ag))]+=t['valor']
            got={(l['natureza'],l['label']):l for l in lin if l['tipo']=='item'}
            if set(got)!=set(itens): erros.append(f'{y} {reg}/{ag} itens diferentes: {set(got)^set(itens)}')
            for k,v in itens.items():
                if k in got:
                    cmp(f'{y} {reg}/{ag} item {k}',got[k]['total'],v); cmp(f'{y} {reg}/{ag} item {k} ano ant.',got[k]['anterior'],itA.get(k,0))
                    if liqT: cmp(f'{y} {reg}/{ag} AV% {k}',got[k]['av'],v/liqT,1e-9)
            # soma dos itens = grupo
            cmp(f'{y} {reg}/{ag} soma itens de receita = bruta',sum(v for (tp,_),v in itens.items() if tp=='income'),sum(mr['bruta']))
    # 3) DRE detalhado e resumo (API)
    for ag in ('categoria','grupo'):
        det=SIS['det'][s][ag]
        cmp(f'{y} detalhado/{ag} receita',sum(det['totReceita']),sum(m['liq'])); cmp(f'{y} detalhado/{ag} despesa',sum(det['totDespesa']),sum(m['desp']))
    cmp(f'{y} resumo lucro',SIS['res'][s]['totals']['lucro'],sum(m['res']))
    antes=sum((t['valor']-t['taxa']) if t['tipo']=='income' else -t['valor'] for t in TX if ano(t['data'])<y)
    cmp(f'{y} resumo saldo inicial do ano carrega anos anteriores',SIS['res'][s]['summary'][0]['saldo_inicial'],sum(CX.values())+antes)

# 4) Fluxo de caixa — definição independente
def mov(c):
    """(data, valor) de tudo que mexeu no saldo do caixa c (None = todos)."""
    v=[(pagto(t), t['valor'] if t['tipo']=='income' else -t['valor']) for t in TX if t['status']=='quitado' and (c is None or t['caixa']==c)]
    if c: v+=[(t['data'],t['valor']) for t in TR if t['dest']==c]+[(t['data'],-t['valor']) for t in TR if t['orig']==c]
    return v
for c in [None]+list(CX):
    nome=c or 'Todos'; M=mov(c); si=CX[c] if c else sum(CX.values())
    prev=None; prevProj=None
    abertos=[((t['venc'] or t['data']), t['valor'] if t['tipo']=='income' else -t['valor']) for t in TX if t['status']=='em_aberto' and (c is None or t['caixa']==c)]
    for y in (2025,2026):
        for mm in range(1,13):
            F=SIS['fluxo'][f'{y}-{mm}-{nome}']; ini=f'{y}-{mm:02d}-01'
            ab=si+sum(v for d,v in M if d<ini)
            cmp(f'fluxo {nome} {y}-{mm:02d} saldo de abertura',F['saldo_abertura'],ab)
            if prev is not None: cmp(f'fluxo {nome} {y}-{mm:02d} abertura = fechamento do mês anterior',F['saldo_abertura'],prev)
            pend=sum(v for d,v in abertos if d<ini)
            cmp(f'fluxo {nome} {y}-{mm:02d} pendente de meses anteriores',F['pendente_anterior'],pend)
            ultimo=F['dias'][-1]['data']
            # projetado no fim do mês anterior = projetado "de largada" deste mês
            if prevProj is not None: cmp(f'fluxo {nome} {y}-{mm:02d} projeção contínua entre meses',ab+pend,prevProj)
            prev=F['dias'][-1]['saldo']; prevProj=F['dias'][-1]['saldo_projetado']
            for dd in F['dias']:
                esperado=ab+sum(v for d,v in M if ini<=d<=dd['data'])
                cmp(f"fluxo {nome} {dd['data']} saldo",dd['saldo'],esperado)
                cmp(f"fluxo {nome} {dd['data']} projetado",dd['saldo_projetado'],esperado+sum(v for d,v in abertos if d<=dd['data']))
                if dd['data']==HOJE and c: cmp(f'fluxo {nome} saldo HOJE = saldo da conta no dashboard',dd['saldo'],next(x['saldo_atual'] for x in SIS['caixas'] if x['nome']==c))
    if c is None:
        cmp('dashboard saldo total das contas = fluxo "Todos" hoje',sum(x['saldo_atual'] for x in SIS['caixas']),si+sum(v for d,v in M if d<=HOJE))

# 5) contas em aberto do dashboard
ab=[t for t in TX if t['status']=='em_aberto']
cmp('a receber',SIS['aberto']['a_receber'],sum(t['valor'] for t in ab if t['tipo']=='income'))
cmp('a pagar',SIS['aberto']['a_pagar'],sum(t['valor'] for t in ab if t['tipo']=='expense'))
cmp('vencidos',SIS['aberto']['qtd_vencidos'],sum(1 for t in ab if (t['venc'] or t['data'])<HOJE))

# 6) Observação: em aberto ATRASADO de meses anteriores entra na projeção?
y,mm=datetime.date.today().year,datetime.date.today().month
atras=[t for t in ab if (t['venc'] or t['data'])<f'{y}-{mm:02d}-01']
print(json.dumps({'conferencias_ok':ok[0],'divergencias':len(erros),'exemplos':erros[:25],
  'em_aberto_atrasado_de_meses_anteriores':{'qtd':len(atras),'a_receber':round(sum(t['valor'] for t in atras if t['tipo']=='income'),2),'a_pagar':round(sum(t['valor'] for t in atras if t['tipo']=='expense'),2)}},ensure_ascii=False,indent=1))
