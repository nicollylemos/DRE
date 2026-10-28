# Relatório de testes — DRE System 3.1.0

Testes feitos em 28/10/2026 com o sistema rodando como em produção: **nginx + PHP-FPM 8.3 (20 processos) + MariaDB 10.11**, numa máquina de **2 vCPU e 8 GB de RAM** (o mesmo porte de uma VPS pequena). A base tinha **12 a 13 mil lançamentos** de 2024 a 2026, 400 notas fiscais, 150 clientes, 60 fornecedores e 100 usuários.

| Teste | Meta | Resultado | Situação | Print |
|---|---|---|---|---|
| Importar 1.000 linhas | até 5 s | **1,41 s** (pior de 3 rodadas: 1,42 s) | ✅ Aprovado | `PRINT-importacao-1000-linhas.png` |
| Carregar o dashboard | até 2 s | **0,17 s** em rede local · **0,76 s** em 4G simulada | ✅ Aprovado | `PRINT-dashboard-carregamento.png` |
| Celular e tablet | sem quebrar | 12 telas × 3 tamanhos, nenhuma rolagem lateral, nenhum erro | ✅ Aprovado | pasta `celular-tablet/` |
| 100 usuários ao mesmo tempo | sem erros | **0 erros** em 9.908 requisições | ✅ Aprovado | `PRINT-carga-100-usuarios.png` |

## 1. Importação de 1.000 linhas

Planilha `importacao_1000_linhas.xlsx` (está nesta pasta) com 1.000 lançamentos e 13 colunas, importada pela tela **Importar / Exportar**. A própria tela agora mostra o tempo de cada etapa no final:

- leitura 0,1 s · conferência 0,4 s · gravação 0,91 s = **1,41 s**
- repetido 3 vezes pela API (desfazendo entre uma e outra): 1,31 s · 1,42 s · 1,30 s

Os números brutos estão em `importacao-tempos.json`.

## 2. Dashboard em até 2 segundos

Tempo medido dentro do navegador, do clique até os cards e o gráfico aparecerem preenchidos, 10 vezes em cada cenário:

- **Rede local:** mediana 0,17 s, pior 0,26 s
- **4G simulada** (150 ms de latência, 9 Mbps): mediana 0,76 s, pior 0,77 s

A consulta do dashboard (`/api/dre`) leva cerca de 65 ms com 12 mil lançamentos. Detalhes em `dashboard-tempos.json`.

## 3. Celular e tablet

Simulação no Chrome de **iPhone 13** (390 px), **iPad** em pé (810 px) e **iPad deitado** (1080 px). Telas conferidas: login, dashboard, lançamentos, notas fiscais, DRE, fluxo de caixa, calendário, formas de pagamento, importação, modal de nova nota, modal de novo lançamento e política de privacidade.

Nenhuma tela ficou mais larga que o aparelho e não apareceu nenhum erro de JavaScript (`celular-tablet/verificacao.json`). Os prints estão na pasta `celular-tablet/`.

> Vale fazer uma conferência rápida num celular de verdade (Safari no iPhone e Chrome no Android) depois de publicar — a simulação usa o motor do Chrome.

## 4. Hospedagem e 100 usuários simultâneos

Dois cenários com 100 usuários diferentes logados ao mesmo tempo, cada um navegando por dashboard, lançamentos, notas fiscais, DRE, fluxo de caixa e as APIs dessas telas:

| Cenário | Requisições | Erros | Páginas (p95) | API (mediana / p95) |
|---|---|---|---|---|
| Uso normal (pausa de 2–5 s entre cliques), 90 s | 2.557 | 0 | 12 ms | 19 ms / 85 ms |
| Estresse (todo mundo clicando sem parar), 45 s | 7.351 | 0 | 696 ms | 629 ms / 1,25 s |

No estresse o servidor atendeu **151 requisições por segundo** sem nenhuma falha. O login levou ~1,8 s porque os 100 entraram no mesmo segundo — a senha é verificada com bcrypt, que é lento de propósito pra dificultar ataques; no dia a dia os logins se espalham e cada um leva ~0,1 s.

### Recomendação de hospedagem

Pra garantir os 100 usuários com folga, a sugestão é uma **VPS de 2 vCPU e 8 GB** — exatamente o porte testado. Na Hostinger isso é o plano **KVM 2** (2 vCPU, 8 GB, 100 GB de disco). A configuração de nginx usada no teste está em `docs/nginx.conf.exemplo`.

Se o grupo preferir hospedagem compartilhada (mais simples, sem administrar servidor), o sistema funciona com o `.htaccess` que já vem no projeto, mas esses planos limitam CPU e processos PHP: o Web Premium tem 40 workers PHP e 1 núcleo, o Cloud Startup tem 100 workers e 4 núcleos. Pra 100 pessoas ao mesmo tempo, o **Cloud Startup** é o mínimo seguro; o compartilhado básico serve pra uso da equipe pequena. Qualquer que seja a escolha, ligue o **HTTPS** (Let's Encrypt é grátis).

## 5. Conferência dos bugs da auditoria

O `auditoria-dre.md` é da versão antiga em Node. Antes de mexer, cada item foi testado no PHP com lançamentos de exemplo (receita de R$ 1.000 no cartão com 2% + R$ 0,50 de taxa, despesa de R$ 300 no mesmo cartão e transferência de R$ 200 entre caixas):

| Bug | No PHP | O que foi feito |
|---|---|---|
| Dashboard e DRE com lucros diferentes | **Já corrigido** — dashboard, DRE resumo, demonstrativo e detalhado deram R$ 679,50 | nada |
| Fluxo de caixa por caixa ignorando transferências | **Já corrigido** — NU BANK +R$ 200 e Itaú −R$ 200 no saldo | nada |
| Taxa de cartão cobrada em despesa | **Já corrigido** — despesa ficou com taxa R$ 0,00 | limpeza automática de taxas antigas que ficaram em despesas no banco |
| Log de exclusão sem guardar o que foi apagado | **Ainda existia** | corrigido: toda exclusão guarda uma cópia do registro (botão "Ver apagado" na tela de Logs) |
