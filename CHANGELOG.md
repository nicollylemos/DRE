# Changelog

## 3.3.0 — outubro/2026
- **Meu perfil** redesenhado: cabeçalho com foto, cargo e perfil de acesso; abas *Dados profissionais* e *Segurança*.
- Novos dados **só da empresa** no perfil: cargo, setor, telefone comercial + ramal, WhatsApp comercial e e-mail corporativo (nada de CPF, endereço ou telefone pessoal). O cargo aparece na lista de usuários.
- **Apagar lançamento** direto no Calendário (lixeira em cada conta), na janela de detalhes e no formulário de edição — para lançamentos feitos por engano, sem precisar dar baixa. Continua guardando uma cópia nos Logs.
- **Visual mais limpo e minimalista**: marinho mais profundo (menos arroxeado) e dourado mais sóbrio; cabeçalho das tabelas claro, sem a faixa azul; cards sem sombra, só com borda fina.
- **Dashboard redesenhado**: um destaque só (lucro do ano, margem, receita e despesas), situação de agora numa faixa única, resumo do DRE sem bloco escuro e estados vazios mais caprichados.
- **Menu lateral responsivo à altura da tela**: em notebooks e telas baixas o menu fica mais compacto e, se ainda não couber, rola. O perfil e o último item nunca somem. As dicas do menu recolhido não são mais cortadas.
- **Níveis de acesso** reformulados: uma tabela só (telas × níveis) mostrando Editar, Ver ou sem acesso, no lugar dos cards cheios de etiquetas. Clique no nome do nível para editar.
- **Datas obrigatórias no lançamento**: além da data, conta em aberto exige o vencimento e lançamento quitado exige a data de pagamento (o campo fica marcado com * e em vermelho se faltar; o servidor também confere).
- **Calendário mostra todos os lançamentos**: antes ficavam de fora os quitados sem vencimento. Cada lançamento cai no vencimento; sem vencimento, na data de pagamento; sem as duas, na data do lançamento.
- **Comprovante de pagamento** nos lançamentos (PDF ou foto, até 10 MB): anexar no formulário ou pelo menu, ver, baixar, trocar e remover. Ícone de clipe na lista e no Calendário. O arquivo fica em `storage/comprovantes/` (fora da pasta pública) e é apagado junto com o lançamento ou ao desfazer uma importação.

## 3.2.0 — outubro/2026
Refatoração com padrões de projeto, **sem mudar nenhuma funcionalidade** (resultados idênticos antes e depois):
- **Strategy** no DRE: `app/Services/Dre/` — regimes (competência, caixa) e agrupamentos (categoria, grupo/conta, centro de custo) viraram classes.
- **Factory** na leitura do XML das notas: `app/Services/NotaFiscal/` — um leitor por layout (NF-e, NFS-e Nacional, NFS-e ABRASF) escolhido pela `FabricaLeitorNotaFiscal`.
- **Observer** nos lançamentos: `app/Eventos/` + `app/eventos.php` — criar/apagar lançamento dispara eventos; vincular/desvincular a nota fiscal virou ouvinte.
- Diagrama de classes (Mermaid + PNG) e explicação em `docs/padroes/`.
- Removidos `app/Models/PasswordReset.php` e `app/Services/Mailer.php`, que sobraram da recuperação de senha por e-mail.

## 3.1.3 — outubro/2026
- **Limites nos campos** de Caixas/Banco e Formas de pagamento (no navegador e conferidos no servidor):
  - Agência: até 4 números + dígito (0000-0) · Conta: até 12 números + dígito (00000-0) · o dígito pode ser X.
  - Taxas de intermediação e parcelamento: de 0 a 100, com até 2 casas · Tarifa fixa: até R$ 9.999,99.
  - Parcelas: 1 a 48 · Dias para recebimento: 0 a 365. Taxas aceitam vírgula ou ponto e ficam com 2 casas.

## 3.1.2 — outubro/2026
- **Retirada a recuperação de senha por e-mail** (tela "Esqueci minha senha", link por e-mail, envio SMTP, campo de e-mail do primeiro acesso e as variáveis `APP_URL`/`MAIL_*`). A tabela `password_resets` é apagada na atualização. Fica só a **troca de senha** em Meu perfil e a **redefinição pelo administrador** em Usuários e acessos.
- Logo do **menu lateral** em versão clara, sem o fundo branco (mesma ideia da tela de entrada); idem no topo das páginas de Termo e Privacidade.

## 3.1.1 — outubro/2026
- **Dados fictícios** para teste: `php app/Database/DadosFicticios.php` (clínica de jan/2025 a dez/2026, notas com XML e PDF, usuários de teste).
- **Fluxo de caixa:** o saldo projetado passa a contar as contas em aberto de meses anteriores (atrasadas e, em meses futuros, as que vencem antes). Antes a projeção de um mês futuro ignorava o que ainda estava em aberto.
- **Taxa de cartão histórica:** editar um lançamento sem mudar forma de pagamento nem valor mantém a taxa original.
- **DRE resumo (API):** saldo inicial do ano inclui o resultado dos anos anteriores.
- Auditoria completa do cálculo (12.680 conferências, 0 divergências) em `docs/testes/AUDITORIA-DRE.md`.

## 3.1.0 — outubro/2026

### Notas fiscais (tela nova)
- Anexar, visualizar (PDF dentro do sistema, XML formatado), substituir e remover **PDF e XML** de cada nota. Arquivos ficam em `storage/notas`, fora do acesso direto pelo navegador.
- **Vincular** a nota a um lançamento ou conta a pagar/receber (lista ordenada por valor e data mais próximos; filtro "só em aberto").
- **Leitura do XML** (NF-e, NFC-e, NFS-e ABRASF e padrão Nacional): preenche a nota e abre o **lançamento já preenchido** — valor, fornecedor/cliente (pelo CNPJ cadastrado), vencimento das duplicatas, forma de pagamento.
- Entrada × saída deduzida pelo CNPJ da empresa (`EMPRESA_CNPJ` ou pelas notas já cadastradas).
- **Importar XML e PDF em lote** (até 20 arquivos), juntando o PDF ao XML pela chave ou número, com opção de criar os lançamentos.
- Chave de acesso duplicada é barrada; XML com DOCTYPE é recusado (segurança).
- Lançamentos mostram a etiqueta **NF** e ganharam "Anexar nota fiscal" no menu. Apagar o lançamento mantém a nota (só tira o vínculo).
- Nível de acesso novo "Notas fiscais" (níveis antigos herdam o acesso de Lançamentos).

### Formas de pagamento
- **Inativar / reativar** (some dos novos lançamentos, continua nos antigos e nos relatórios).
- Tipo **Transferência**; formas que faltavam (cartão de débito e transferência) são criadas se ainda não existirem.

### Login e segurança
- **LGPD**: Termo de uso e consentimento + Política de privacidade (`/termos`, `/privacidade`); aceite obrigatório no primeiro acesso e quando a versão muda, com data e versão gravadas.
- Logo da tela de entrada em versão clara, sem o fundo branco.

### Correções (auditoria)
- Log de exclusão agora guarda **cópia do que foi apagado** (inclusive ao desfazer importação) — botão "Ver apagado" nos Logs.
- Conferidos no PHP e já corretos: lucro igual no Dashboard e no DRE, transferências no fluxo por caixa, taxa de cartão só em receita (taxas antigas que ficaram em despesas são zeradas na migração).

### Testes
- Importação de 1.000 linhas em 1,4 s, dashboard em 0,17 s (0,76 s em 4G), celular/tablet sem quebras, 100 usuários simultâneos sem erro. Relatório e prints em `docs/testes/`.
- Tela de importação mostra o tempo de processamento.

## 3.0.0 — outubro/2026

### Arquitetura
- Projeto reorganizado em **MVC**: front controller `index.php`, `app/Core` (Router, Request, Response, View, Database, Model, Controller, Auth, Session, Csrf, Permissions), `app/Controllers`, `app/Models`, `app/Services`, `app/Views`.
- Cada tela agora é uma **página própria** com URL (`/dashboard`, `/dre`, `/calendario`…) e seu próprio JS em `assets/js/pages/`. O antigo `index.html` único com 1.500 linhas de JS foi dividido.
- Catálogo de telas centralizado em `Permissions.php` (menu, níveis de acesso e bloqueio no servidor saem da mesma lista).
- Migrações versionadas (`Migrator::VERSAO = 7`); atualização automática a partir da 2.x sem perder dados.

### Login
- Nova tela de login (layout dividido, responsiva), mostrar/ocultar senha, aviso de Caps Lock, botão com carregamento.
- **Manter conectado** (30 dias); sem marcar, a sessão expira após 8h parada.
- **Bloqueio por tentativas**: 5 erros seguidos bloqueiam o usuário por 15 min; 15 erros do mesmo IP em 10 min bloqueiam o IP (acertos não contam).
- **Troca de senha obrigatória** no primeiro acesso com senha provisória.
- Política de senha: 8+ caracteres com letras e números, com medidor de força.
- **Token CSRF** em todas as ações; verificação de tempo constante pra não revelar quais usuários existem.
- Sessão cai na hora quando o admin redefine a senha ou desativa o usuário.
- Usuário desativado não entra; último login e IP registrados.
- Trocar a própria senha agora pede a senha atual.

### Usuários e níveis de acesso
- Tabela `perfis`: níveis com **Sem acesso / Ver / Editar** por tela. Quatro níveis padrão (Administrador, Usuário, Financeiro, Consulta) e níveis personalizados (criar, duplicar, apagar movendo usuários).
- Usuários: e-mail, ativar/desativar, redefinir senha (provisória gerada ou definida), desbloquear, último acesso, filtros por nível e situação.
- O servidor diferencia **ver** de **editar** em todas as rotas (ex.: nível Consulta não cria lançamento nem pela API).

### Telas
- **DRE** (nova): demonstrativo mês a mês com AV%, ano anterior e AH%, agrupamento por categoria / grupo-conta / centro de custo, regime de competência ou caixa, exportação Excel e PDF.
- **Dashboard**: comparação com o mesmo período do ano anterior, próximos vencimentos e saldo por conta. O calendário saiu daqui.
- **Calendário** (nova): mês inteiro com filtros, KPIs do mês, baixa com 1 clique, nova conta no dia (duplo clique).
- **Importar / Exportar** (nova).
- Menu lateral com nomes e grupos, recolhível.

### Importação
- Lançamentos, clientes e fornecedores a partir de **.xlsx** ou **.csv** (leitor próprio, sem Composer).
- Mapeamento automático de colunas, prévia linha a linha (ok / aviso / duplicado / erro), criação de categorias, detecção de duplicados.
- Histórico com **desfazer** e filtro "lançamentos desta importação".
- Planilhas modelo para download.

### Outros
- PDF do DRE refeito com o demonstrativo (AV%, AH%, mês a mês). Excel com colunas largas e valores formatados.
- Logs de exportação, importação, desfazer importação, redefinição de senha e níveis de acesso.
- `X-Forwarded-For` só é usado com `TRUST_PROXY=1`.
