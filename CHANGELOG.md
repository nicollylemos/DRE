# Changelog

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
