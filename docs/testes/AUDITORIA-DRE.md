# Auditoria do cálculo — DRE, Dashboard e Fluxo de Caixa (v3.1.1)

Feita em 28/10/2026 com os dados fictícios (`php app/Database/DadosFicticios.php`): ~2.000 lançamentos de jan/2025 a dez/2026, 64 transferências, 4 contas, cartão com taxa, convênios a receber, contas atrasadas e meses futuros em aberto.

## Como foi feito

O script `auditoria/auditor.py` **recalcula tudo do zero** a partir dos lançamentos crus do banco (sem usar nenhum código do sistema) e compara com o que as telas mostram (`auditoria/sistema.php` chama as mesmas funções que a API usa).

**Resultado: 12.680 conferências, 0 divergências.**

| O que foi conferido | Regra |
|---|---|
| Cada lançamento | taxa só em receita · taxa = % da forma × valor + tarifa fixa · líquido = valor − taxa |
| Dashboard (2024–2026) | receita bruta, deduções, despesas, lucro, gráfico mês a mês, despesas por categoria, comparação com o ano anterior |
| Tela DRE / PDF / Excel | os 2 regimes (competência e caixa) × 3 agrupamentos: totais, cada mês, cada item, ano anterior, AV%, AH% e margem; soma dos itens = total do grupo |
| DRE resumo e detalhado (API) | lucro do ano e saldo inicial carregando os anos anteriores |
| Fluxo de caixa | 24 meses × 5 visões (todos + 4 contas), dia a dia: abertura, saldo realizado e saldo projetado; abertura de um mês = fechamento do anterior; saldo de hoje = saldo da conta no dashboard |
| Contas em aberto | a receber, a pagar e quantidade de vencidos |

## Fórmulas do DRE (confirmadas)

```
Receita bruta            = soma das receitas
(−) Taxas e deduções     = soma da taxa de cartão/boleto das receitas
= Receita líquida
(−) Despesas             = soma das despesas
= Resultado líquido
AV% = item ÷ receita líquida        AH% = (ano − ano anterior) ÷ |ano anterior|
Competência: pela data do lançamento (tudo, pago ou não)
Caixa:       só o quitado, pela data de pagamento (cartão D+30 cai no mês em que entrou na conta)
```

## O que estava errado e foi corrigido

1. **Saldo projetado do fluxo de caixa ignorava contas em aberto de meses anteriores.** Olhando novembro/2026, o projetado aparecia R$ 147.655,64 quando o certo era R$ 166.774,19 — as contas de outubro ainda não pagas/recebidas (e as atrasadas) sumiam da projeção. Agora o projetado começa do saldo realizado + tudo que ainda está em aberto com vencimento anterior ao mês, e a tela mostra esse valor separado ("em aberto de meses anteriores").
2. **Editar um lançamento antigo recalculava a taxa do cartão com a taxa atual.** Se a taxa da maquininha mudasse, qualquer edição (até da descrição) alterava o DRE de meses passados. Agora a taxa original é mantida enquanto a forma de pagamento e o valor não mudarem.
3. **DRE resumo (API) recomeçava o saldo acumulado do zero a cada ano.** Agora o saldo inicial do ano inclui o resultado dos anos anteriores. (Essa rota não aparece em nenhuma tela, mas ficava errada pra quem usasse a API.)

## Pontos de atenção (não são erro, são decisões)

- **Lançamentos com data futura entram no DRE por competência.** Contas a pagar já lançadas para novembro e dezembro aparecem no "lucro do exercício" do dashboard. É o comportamento padrão de DRE por competência (vira um DRE projetado); para ver só o que já aconteceu, use o regime **caixa** na tela DRE.
- **Comparação com o ano anterior** no dashboard usa o mês atual inteiro dos dois anos, mesmo com o mês atual ainda pela metade.
- **Lançamento sem caixa/banco** entra no fluxo "Todos os caixas", mas não no saldo de nenhuma conta (a tela Caixas/Banco avisa quantos são).
- **Hierarquia contábil completa** (CPV, despesas administrativas/comerciais/financeiras, EBITDA) continua como evolução futura — item 1 da auditoria antiga.

## Fluxos de uso testados no navegador

Com os perfis **admin**, **financeiro** e **consulta**: todas as 15 telas, 60+ chamadas de relatório (2024–2027), criar/editar/dar baixa/reabrir/apagar lançamento, taxa mostrada no formulário, baixa com 1 clique no calendário, troca de regime e agrupamento no DRE, os 5 downloads (Excel, DRE Excel, PDF, CSV, XML), navegação do fluxo por mês e por conta, leitura dos XML, sugestão de lançamento e vínculo de nota, reativar forma de pagamento, aceite LGPD no 1º login. Bloqueios conferidos: transferência para o mesmo caixa, nota de entrada vinculada a receita, apagar caixa com movimento, perfil "consulta" sem conseguir criar/apagar e sem ver Caixas, Formas, Logs e Usuários. **Nenhum erro de JavaScript nem erro 500.**
