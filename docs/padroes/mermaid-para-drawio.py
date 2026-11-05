"""Converte o diagrama de classes Mermaid (com layout do SVG gerado pelo mmdc) em arquivo .drawio editável."""
import re, sys, html
from xml.sax.saxutils import escape

MMD, SVG, OUT = sys.argv[1:4]
src = open(MMD, encoding='utf-8').read()
svg = open(SVG, encoding='utf-8').read()

# ─── 1. Classes, membros e namespaces do Mermaid ───
classes, ns_de, ordem_ns = {}, {}, []
ns = None
linhas = src.split('\n')
i = 0
while i < len(linhas):
    l = linhas[i].strip()
    m = re.match(r'namespace (\w+)\s*\{', l)
    if m: ns = m.group(1); ordem_ns.append(ns); i += 1; continue
    m = re.match(r'class (\w+)\s*\{', l)
    if m:
        nome = m.group(1); c = {'nome': nome, 'ster': None, 'attrs': [], 'mets': []}
        i += 1
        while linhas[i].strip() != '}':
            t = linhas[i].strip()
            if t.startswith('<<'): c['ster'] = t[2:-2]
            elif t: (c['mets'] if '(' in t else c['attrs']).append(t)
            i += 1
        classes[nome] = c; ns_de[nome] = ns
        i += 1; continue
    if l == '}' and ns: ns = None
    i += 1

rels = []
for l in linhas:
    l = l.strip()
    m = re.match(r'(\w+)\s*(?:"([^"]*)"\s*)?(<\|--|<\|\.\.|-->|\.\.>|o--)\s*(?:"([^"]*)"\s*)?(\w+)\s*(?::\s*(.+))?$', l)
    if m and m.group(1) in classes and m.group(5) in classes:
        rels.append(m.groups())

# ─── 2. Posições e tamanhos do SVG ───
pos = {}
for m in re.finditer(r'id="my-svg-classId-(\w+?)-\d+"[^>]*transform="translate\(([-\d.]+),\s*([-\d.]+)\)"><g class="basic label-container[^"]*"><path d="M(-?[\d.]+) (-?[\d.]+)', svg):
    nome, cx, cy, x0, y0 = m.group(1), *map(float, m.groups()[1:])
    pos[nome] = (cx, cy, -x0 * 2, -y0 * 2)
clus = {}
for m in re.finditer(r'id="my-svg-(\w+)" data-look="classic"><rect style="" x="([-\d.]+)" y="([-\d.]+)" width="([-\d.]+)" height="([-\d.]+)"', svg):
    if m.group(1) in ordem_ns: clus[m.group(1)] = tuple(map(float, m.groups()[1:]))
faltam = set(classes) - set(pos)
assert not faltam, faltam

# ─── 3. Formatação UML dos membros ───
def membro(t):
    estatico = t.endswith('$') or ')$' in t; abstrato = t.endswith('*') or ')*' in t
    t = t.replace(')$', ')').replace(')*', ')').rstrip('$*').strip()
    vis = t[0] if t[0] in '+-#~' else ''
    t = t[1:] if vis else t
    if '(' in t:
        m = re.match(r'(.*\))\s*([\w\[\]]+)?$', t)
        txt = m.group(1) + (': ' + m.group(2) if m.group(2) else '')
    elif ':' in t:
        a, b = t.split(':', 1); txt = f'{a.strip()}: {b.strip()}'
    else:
        p = t.split()
        txt = f'{p[1]}: {p[0]}' if len(p) == 2 and not '.' in p[1] else t
    estilo = 4 if estatico else (2 if abstrato else 0)
    return f'{vis} {txt}'.strip() if vis else txt, estilo

ROW, SEP, FS = 20, 8, 11
PADRAO = {'Strategy', 'Factory', 'Observer', 'Singleton'}
TITULO_NS = {'FrontController_MVC': 'Front Controller + MVC', 'ClassesBase': 'Classes base (Layer Supertype)', 'Controllers': 'Controllers',
             'Models': 'Models', 'Strategy': 'Strategy — cálculo do DRE', 'Factory': 'Factory — leitura do XML da nota fiscal',
             'Observer': 'Observer — eventos dos lançamentos', 'Singleton': 'Singleton — conexão com o banco'}

cells = []
nid = [2]
def novo():
    nid[0] += 1; return f'c{nid[0]}'

# ─── Layout próprio: pacotes em grade, mãe em cima e filhas embaixo ───
LAYOUT = {
    'FrontController_MVC': [['index_php', 'App'], ['Router', 'Request', 'Response'], ['View']],
    'ClassesBase': [['Controller', 'Model']],
    'Singleton': [['Database']],
    'Controllers': [['TransacaoController', 'DreController', 'NotaFiscalController', 'ExportController']],
    'Models': [['Transacao', 'NotaFiscal', 'Log', 'OutrosModels']],
    'Strategy': [['DreService', 'EstrategiasDre'], ['RegimeDre', 'AgrupamentoDre'],
                 ['RegimeCompetencia', 'RegimeCaixa', 'AgrupamentoPorCategoria', 'AgrupamentoPorGrupoConta', 'AgrupamentoPorCentroCusto']],
    'Factory': [['NotaFiscalXml', 'FabricaLeitorNotaFiscal', 'DocumentoXml'], ['LeitorNotaFiscal'], ['LeitorNfe', 'LeitorNfseNacional', 'LeitorNfseAbrasf']],
    'Observer': [['Despachante', 'Evento'], ['Ouvinte'], ['VincularNotaFiscal', 'DesvincularNotasFiscais', 'ApagarComprovante']],
}
GRADE = [['FrontController_MVC', 'ClassesBase', 'Singleton'], ['Controllers', 'Models'], ['Strategy', 'Factory', 'Observer']]
assert sorted(k for rows in LAYOUT.values() for r in rows for k in r) == sorted(classes), 'layout incompleto'

alturas, larguras = {}, {}
for nome, c in classes.items():
    head = 44 if c['ster'] else 30
    alturas[nome] = head + ROW * len(c['attrs']) + SEP + ROW * max(1, len(c['mets']))
    textos = [membro(a)[0] for a in c['attrs'] + c['mets']] + [nome, '«' + (c['ster'] or '') + '»']
    larguras[nome] = max(150, min(420, int(max(len(t) for t in textos) * 6.4 + 24)))

GX, GY, PAD, TOPO = 40, 70, 30, 50
geo, pacote = {}, {}
for n, rows in LAYOUT.items():
    lw = [sum(larguras[k] for k in r) + GX * (len(r) - 1) for r in rows]
    W = max(lw) + 2 * PAD
    y = TOPO
    for r, w in zip(rows, lw):
        x = PAD + (W - 2 * PAD - w) / 2
        h = max(alturas[k] for k in r)
        for k in r:
            geo[k] = [x, y, larguras[k], alturas[k]]; x += larguras[k] + GX
        y += h + GY
    pacote[n] = [0, 0, W, y - GY + PAD]

GAPX, GAPY = 80, 100
larg_linha = [sum(pacote[n][2] for n in r) + GAPX * (len(r) - 1) for r in GRADE]
total = max(larg_linha)
y0 = 0
for r, lw in zip(GRADE, larg_linha):
    x0 = (total - lw) / 2
    for n in r:
        pacote[n][0], pacote[n][1] = x0, y0; x0 += pacote[n][2] + GAPX
    y0 += max(pacote[n][3] for n in r) + GAPY
for k in geo:
    px, py = pacote[ns_de[k]][:2]; geo[k][0] += px; geo[k][1] += py

# pacotes (fundo)
for n in ordem_ns:
    X, Y, Wp, Hp = pacote[n]
    destaque = n in PADRAO
    estilo = ('rounded=1;arcSize=2;whiteSpace=wrap;html=1;verticalAlign=top;align=left;spacingLeft=12;spacingTop=6;fontStyle=1;fontSize=13;'
              f'fillColor={"#FBF8F1" if destaque else "#F7F8FA"};strokeColor={"#C4A062" if destaque else "#C3CAD4"};dashed=1;fontColor=#0F213D;')
    cells.append(f'<mxCell id="ns_{n}" value="{escape(TITULO_NS.get(n, n))}" style="{estilo}" vertex="1" parent="1"><mxGeometry x="{X:.0f}" y="{Y:.0f}" width="{Wp:.0f}" height="{Hp:.0f}" as="geometry"/></mxCell>')

# classes
for nome, c in classes.items():
    x, y, W, H = geo[nome]
    head = 44 if c['ster'] else 30
    italico = c['ster'] in ('interface', 'abstract')
    rotulo = (f'«{escape(c["ster"])}»<br>' if c['ster'] else '') + (f'<b><i>{nome}</i></b>' if italico else f'<b>{nome}</b>')
    rotulo = rotulo.replace('index_php', 'index.php')
    estilo = (f'swimlane;fontStyle=0;align=center;verticalAlign=top;childLayout=stackLayout;horizontal=1;startSize={head};horizontalStack=0;'
              'resizeParent=1;resizeParentMax=0;resizeLast=0;collapsible=1;marginBottom=0;whiteSpace=wrap;html=1;'
              f'fillColor=#EDF1F6;swimlaneFillColor=#FFFFFF;strokeColor=#0F213D;fontColor=#0F213D;fontSize={FS+1};')
    cells.append(f'<mxCell id="{nome}" value="{escape(rotulo)}" style="{estilo}" vertex="1" parent="1"><mxGeometry x="{x:.0f}" y="{y:.0f}" width="{W:.0f}" height="{H}" as="geometry"/></mxCell>')
    yy = head
    txt_estilo = ('text;strokeColor=none;fillColor=none;align=left;verticalAlign=middle;spacingLeft=6;spacingRight=4;overflow=hidden;'
                  f'rotatable=0;points=[[0,0.5],[1,0.5]];portConstraint=eastwest;whiteSpace=wrap;html=1;fontSize={FS};fontColor=#1F2937;')
    for a in c['attrs']:
        t, fs = membro(a)
        cells.append(f'<mxCell id="{novo()}" value="{escape(t)}" style="{txt_estilo}fontStyle={fs};" vertex="1" parent="{nome}"><mxGeometry y="{yy}" width="{W:.0f}" height="{ROW}" as="geometry"/></mxCell>'); yy += ROW
    cells.append(f'<mxCell id="{novo()}" value="" style="line;strokeWidth=1;fillColor=none;align=left;verticalAlign=middle;spacingTop=-1;spacingLeft=3;spacingRight=3;rotatable=0;labelPosition=right;points=[];portConstraint=eastwest;strokeColor=#0F213D;" vertex="1" parent="{nome}"><mxGeometry y="{yy}" width="{W:.0f}" height="{SEP}" as="geometry"/></mxCell>'); yy += SEP
    for a in c['mets'] or ['']:
        t, fs = membro(a) if a else ('', 0)
        cells.append(f'<mxCell id="{novo()}" value="{escape(t)}" style="{txt_estilo}fontStyle={fs};" vertex="1" parent="{nome}"><mxGeometry y="{yy}" width="{W:.0f}" height="{ROW}" as="geometry"/></mxCell>'); yy += ROW

# relações
def _d(ex, ey, nx, ny): return f'exitX={ex};exitY={ey};exitDx=0;exitDy=0;entryX={nx};entryY={ny};entryDx=0;entryDy=0;'
# Por onde algumas setas saem/entram, pra não passarem por cima do texto das classes
DICAS = {
    ('DreService', 'EstrategiasDre'): _d(1, 0.2, 0, 0.2),
    ('DreService', 'RegimeDre'): _d(0.35, 1, 0.5, 0),
    ('DreService', 'AgrupamentoDre'): _d(0.75, 1, 0.3, 0),
    ('EstrategiasDre', 'RegimeDre'): _d(0.2, 1, 0.8, 0),
    ('EstrategiasDre', 'AgrupamentoDre'): _d(0.6, 1, 0.75, 0),
    ('NotaFiscalXml', 'FabricaLeitorNotaFiscal'): _d(1, 0.3, 0, 0.3),
    ('NotaFiscalXml', 'DocumentoXml'): _d(0.5, 1, 0, 0.85),
    ('FabricaLeitorNotaFiscal', 'LeitorNotaFiscal'): _d(0.5, 1, 0.5, 0),
    ('LeitorNotaFiscal', 'DocumentoXml'): _d(1, 0.4, 0.5, 1),
    ('Despachante', 'Evento'): _d(1, 0.5, 0, 0.5),
}
BASE = 'edgeStyle=orthogonalEdgeStyle;rounded=1;html=1;fontSize=10;fontColor=#475569;labelBackgroundColor=#FFFFFF;strokeColor=#475569;endSize=10;'
for a, ma, tipo, mb, b, rot in rels:
    rot = rot or ''
    arvore = 'exitX=0.5;exitY=0;exitDx=0;exitDy=0;entryX=0.5;entryY=1;entryDx=0;entryDy=0;'
    if tipo == '<|--':   s, t, est = b, a, arvore + 'endArrow=block;endFill=0;'
    elif tipo == '<|..': s, t, est = b, a, arvore + 'endArrow=block;endFill=0;dashed=1;'
    elif tipo == '-->':  s, t, est = a, b, 'endArrow=open;endFill=0;'
    elif tipo == '..>':  s, t, est = a, b, 'endArrow=open;endFill=0;dashed=1;'
    elif tipo == 'o--':  s, t, est = a, b, 'startArrow=diamondThin;startFill=0;startSize=14;endArrow=none;'
    est += DICAS.get((s, t), '')
    eid = novo()
    cells.append(f'<mxCell id="{eid}" value="{escape(rot)}" style="{BASE}{est}" edge="1" parent="1" source="{s}" target="{t}"><mxGeometry relative="1" as="geometry"/></mxCell>')
    for val, xr in ((ma, -0.8), (mb, 0.8)):
        if val:
            cells.append(f'<mxCell id="{novo()}" value="{escape(val)}" style="edgeLabel;html=1;align=center;verticalAlign=middle;resizable=0;points=[];fontSize=11;" vertex="1" connectable="0" parent="{eid}"><mxGeometry x="{xr}" relative="1" as="geometry"><mxPoint as="offset"/></mxGeometry></mxCell>')

# título
minx = min(float(re.search(r'x="([-\d.]+)"', c).group(1)) for c in cells if 'vertex="1" parent="1"' in c)
miny = min(float(re.search(r' y="([-\d.]+)"', c).group(1)) for c in cells if 'vertex="1" parent="1"' in c)
cells.insert(0, f'<mxCell id="titulo" value="&lt;b&gt;DRE System (OAF)&lt;/b&gt; — Diagrama de classes com os padrões de projeto" style="text;html=1;align=left;verticalAlign=middle;fontSize=18;fontColor=#0F213D;fillColor=none;strokeColor=none;" vertex="1" parent="1"><mxGeometry x="{minx:.0f}" y="{miny-60:.0f}" width="900" height="36" as="geometry"/></mxCell>')

xml = ('<mxfile host="app.diagrams.net" agent="DRE System" version="24.7.17">'
       '<diagram id="dre-classes" name="Diagrama de classes">'
       '<mxGraphModel dx="1600" dy="900" grid="1" gridSize="10" guides="1" tooltips="1" connect="1" arrows="1" fold="1" page="0" pageScale="1" pageWidth="1654" pageHeight="1169" math="0" shadow="0">'
       '<root><mxCell id="0"/><mxCell id="1" parent="0"/>' + ''.join(cells) + '</root></mxGraphModel></diagram></mxfile>')
open(OUT, 'w', encoding='utf-8').write(xml)
print('classes', len(classes), 'relacoes', len(rels))
