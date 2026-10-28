# Changelog

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
- **Esqueci minha senha** com link por e-mail (SMTP próprio, sem Composer; 1 hora, uso único, só o hash fica no banco; resposta não revela se o usuário existe).
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
