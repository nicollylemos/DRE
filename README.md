# DRE System — OAF Controle Financeiro (PHP MVC + MySQL)

Sistema de controle financeiro com DRE, fluxo de caixa, calendário de contas, cadastros, importação de planilhas e controle de acesso por usuário.

**Versão 3.1.1 (out/2026)** — aparece na tela de login (constante `APP_VERSION` em `app/bootstrap.php`).

PHP puro, **sem Composer e sem `npm install`**: Excel, PDF e leitura de planilhas são feitos em PHP.

---

## Sumário

1. [Requisitos](#-requisitos)
2. [Como rodar no seu PC com XAMPP (Windows)](#-como-rodar-no-seu-pc-com-xampp-windows)
3. [Como rodar só com o PHP (sem XAMPP)](#-como-rodar-só-com-o-php-sem-xampp)
4. [Como publicar na hospedagem](#-como-publicar-na-hospedagem-hostinger-locaweb-hostgator)
5. [Primeiro acesso](#-primeiro-acesso)
6. [Atualizando da versão 2.x](#-atualizando-da-versão-2x)
7. [Telas](#-telas)
8. [Usuários e níveis de acesso](#-usuários-e-níveis-de-acesso)
9. [Importação de planilhas](#-importação-de-planilhas)
10. [Estrutura MVC](#-estrutura-mvc)
11. [Problemas comuns](#-problemas-comuns)

---

## ⚙️ Requisitos

- **PHP 8.0 ou mais novo** (testado no 8.3) com as extensões `pdo_mysql`, `mbstring`, `zip` e `iconv` — no XAMPP já vêm ligadas
- **MySQL 5.7+ / 8.x** ou **MariaDB 10.3+**
- **Apache com `mod_rewrite`** (padrão no XAMPP e nas hospedagens) **ou** nginx

---

## 💻 Como rodar no seu PC com XAMPP (Windows)

1. **Instale o XAMPP** (https://www.apachefriends.org) e abra o **XAMPP Control Panel**.
2. Clique em **Start** no **Apache** e no **MySQL** (os dois ficam verdes).
3. Copie a pasta do projeto para dentro de `C:\xampp\htdocs\`. Exemplo: `C:\xampp\htdocs\DRE-php\`
   (a pasta precisa ter o `index.php`, a pasta `app\`, a pasta `assets\` etc. **direto dentro dela**).
4. Na pasta do projeto, **copie o arquivo `.env.example` e renomeie a cópia para `.env`**.
   Abra o `.env` no Bloco de Notas/VS Code. No XAMPP o padrão é:
   ```ini
   DB_HOST=localhost
   DB_PORT=3306
   DB_USER=root
   DB_PASS=
   DB_NAME=dre_oaf
   APP_ENV=development
   ```
   > O banco `dre_oaf` é **criado sozinho** no primeiro acesso, junto com todas as tabelas. Não precisa importar nada.
5. Abra no navegador: **http://localhost/DRE-php/**
6. Vai aparecer **"Primeiro acesso"** — crie seu usuário. Ele vira o **Administrador**.

> Dica: se o Windows não deixar criar um arquivo chamado `.env`, abra o Bloco de Notas, cole o conteúdo e em "Salvar como" escolha **Todos os arquivos** e digite `.env` com aspas: `".env"`.

---

## 🐘 Como rodar só com o PHP (sem XAMPP)

Precisa do PHP e de um MySQL/MariaDB rodando.

```bash
cd DRE-php
cp .env.example .env        # ajuste DB_USER / DB_PASS / DB_NAME
php -S localhost:8000 router.php
```

Acesse **http://localhost:8000**. O `router.php` faz no servidor embutido o mesmo papel do `.htaccess`.

Quer criar as tabelas antes de abrir o site? `php app/Database/Migrator.php --migrate`

---

## 🌐 Como publicar na hospedagem (Hostinger, Locaweb, HostGator…)

1. No painel da hospedagem, crie um **banco MySQL** e um usuário com acesso a ele. Anote nome do banco, usuário e senha.
2. Envie **todo o conteúdo** da pasta para o `public_html` (ou uma subpasta, ex.: `public_html/dre/` — funciona igual).
3. Crie o `.env` a partir do `.env.example` com os dados do banco e coloque **`APP_ENV=production`** (esconde mensagens técnicas de erro).
4. Garanta permissão de escrita nas pastas **`uploads/`** e **`storage/`** (geralmente já vem; senão `chmod 775`).
5. Acesse o site e faça o **primeiro acesso**.

> Se a hospedagem não deixar o PHP criar o banco, o banco vazio criado no painel já basta — as tabelas são criadas sozinhas. Ou importe o `database.sql` pelo phpMyAdmin.
> Atrás de Cloudflare/proxy? Coloque `TRUST_PROXY=1` no `.env` para o IP certo aparecer nos logs.

### nginx

```nginx
root /var/www/dre;
index index.php;
location ~ ^/(app|storage|docs)/ { deny all; }
location ~ /\.(env|git|ht) { deny all; }
location ~ \.(sql|md|lock|log)$ { deny all; }
location = /router.php { deny all; }
location ~* ^/uploads/.*\.(php\d?|phtml|phar|html?|svg|js)$ { deny all; }
location / { try_files $uri /index.php$is_args$args; }
location = /index.php { include fastcgi_params; fastcgi_param SCRIPT_FILENAME $document_root/index.php; fastcgi_pass unix:/run/php/php8.3-fpm.sock; }
location ~ \.php$ { deny all; }
client_max_body_size 25M;
```

Configuração completa (a mesma usada nos testes de carga) em `docs/nginx.conf.exemplo`.

---

## 🧪 Dados fictícios para testar

Gera uma clínica fictícia de jan/2025 a dez/2026: ~2.000 lançamentos (consultas, procedimentos, convênios, aluguel, folha, impostos, materiais…), transferências, 4 contas, clientes e fornecedores com CPF/CNPJ válidos e ~45 notas fiscais com XML e PDF. Tem contas atrasadas de propósito, meses futuros em aberto, um mês com prejuízo e crescimento de 2026 sobre 2025.

```bash
php app/Database/DadosFicticios.php            # gera (só se o banco não tiver lançamentos)
php app/Database/DadosFicticios.php --limpar   # apaga lançamentos, notas, clientes e fornecedores e gera de novo
```

No XAMPP: **XAMPP Control Panel → Shell** → `cd htdocs\DRE-php` → `php app\Database\DadosFicticios.php`.
Usuários de teste: **financeiro / Demo1234** e **consulta / Demo1234** (este vê o aceite LGPD). Se o banco não tinha ninguém, cria também **admin / Admin1234**.

> Use um banco separado pra testar (troque `DB_NAME` no `.env`, ex.: `dre_teste`) — o `--limpar` apaga os dados de movimento.

A auditoria do cálculo (12.680 conferências) está em `docs/testes/AUDITORIA-DRE.md`, com o script pra rodar de novo.

---

## 🔑 Primeiro acesso

- Com o banco vazio, a tela de login mostra **"Primeiro acesso"**: crie o administrador (senha com **8+ caracteres, letras e números**).
- Depois disso o cadastro público some. Novos usuários são criados em **Usuários e acessos**.
- Esqueceu a senha? Clique em **Esqueci minha senha** na tela de entrada: chega um link por e-mail (vale 1 hora). Precisa ter e-mail no cadastro e `APP_URL` + `MAIL_*` configurados no `.env`. Sem e-mail, outro administrador redefine em **Usuários e acessos → ⋮ → Redefinir senha**.
- No primeiro acesso de cada pessoa aparece o **Termo de uso e a Política de privacidade (LGPD)** para aceitar. Preencha `LGPD_CONTROLADOR`, `LGPD_CNPJ` e `LGPD_EMAIL` no `.env`.
- Se perder o único admin: rode no banco `UPDATE users SET tentativas_falhas=0, bloqueado_ate=NULL WHERE username='seu_usuario';` (desbloqueio) ou apague todos os usuários (`DELETE FROM users;`) para voltar ao "Primeiro acesso".

---

## 🔁 Atualizando da 3.0 para a 3.1

Substitua os arquivos mantendo `.env`, `uploads/` e `storage/`, e abra o site — o banco atualiza sozinho (tabela de notas fiscais, links de senha, colunas novas). Depois acrescente no `.env` as linhas novas do `.env.example` (`APP_URL`, `MAIL_*`, `LGPD_*`, `EMPRESA_CNPJ`). Todo mundo vai aceitar o termo LGPD no próximo login. Relatório dos testes em `docs/testes/RELATORIO-TESTES.md`.

## 🔁 Atualizando da versão 2.x

1. **Faça backup** do banco (phpMyAdmin → Exportar) e da pasta `uploads/`.
2. Substitua os arquivos do sistema pelos desta versão, **mantendo** o seu `.env` e a pasta `uploads/`.
3. Apague o que não existe mais (não quebra nada se ficar, mas fica lixo): `index.html`, `api/`, `css/`, `js/`, `img/`, `app/routes/`, `app/lib/`, `app/schema.php`, `app/router.php`.
4. Abra o site. O banco é atualizado sozinho (colunas novas, tabela de níveis de acesso e de importações). **Nada é apagado.**
   - Quem era **admin** vira nível **Administrador**; quem era **usuário** vira nível **Usuário** (mesmas telas de antes + Calendário).
   - As senhas continuam as mesmas (as antigas, de 6 caracteres, ainda funcionam; só senhas novas exigem 8+).

---

## 🖥️ Telas

| Tela | O que faz |
|---|---|
| **Dashboard** | KPIs do exercício, gráfico receitas × despesas por mês, comparação com o mesmo período do ano anterior, próximos vencimentos (7 dias), saldo por conta e despesas por categoria |
| **DRE** | Demonstrativo mês a mês: receita bruta → deduções (MDR) → receita líquida → despesas → resultado, com **AV%**, **ano anterior** e **AH%**. Agrupa por categoria, grupo/conta ou centro de custo; regime de **competência** ou **caixa**. Exporta Excel e PDF |
| **Calendário** | Contas a pagar e a receber por vencimento. Filtros (a receber / a pagar / vencidos / quitados), totais do mês, **dar baixa** com 1 clique, **nova conta** no dia (duplo clique no dia) |
| **Lançamentos** | Receitas e despesas com filtros, KPIs, baixa/reabrir. Etiqueta **NF** quando tem nota fiscal vinculada |
| **Notas fiscais** | Anexa, visualiza, substitui e remove **PDF e XML**; vincula a lançamento ou conta a pagar/receber; lê o XML (NF-e, NFC-e e NFS-e ABRASF/Nacional) e **preenche o lançamento sozinho** (valor, fornecedor, vencimento das duplicatas, forma de pagamento); importa várias notas de uma vez (até 20 arquivos) |
| **Fluxo de Caixa** | Dia a dia, realizado × previsto, por caixa |
| **Importar / Exportar** | Assistente de importação + Excel, CSV, XML e PDF + histórico com "desfazer" |
| **Clientes, Fornecedores, Parceiros** | Cadastros com CPF/CNPJ validado, busca na Receita (BrasilAPI), contratos e aditivos |
| **Categorias, Caixas/Banco, Formas de pagamento** | Cadastros de apoio (cor da categoria, saldo atual das contas, taxas MDR). Formas de pagamento podem ser **inativadas/reativadas** |
| **Usuários e acessos** | Usuários + níveis de acesso (só administrador) |
| **Logs** | Tudo que foi criado, alterado, apagado, importado, logins (inclusive falhos). Nas exclusões guarda **cópia do que foi apagado** ("Ver apagado") |
| **/termos e /privacidade** | Termo de uso e Política de privacidade (LGPD), abrem sem login |

O menu lateral pode ser **recolhido** (botão `‹` no topo) — o sistema lembra a escolha.

---

## 👥 Usuários e níveis de acesso

**Níveis de acesso** (aba "Níveis de acesso"): para cada tela você escolhe **Sem acesso**, **Ver** (só consulta) ou **Editar** (criar, alterar, apagar). O bloqueio vale no menu **e no servidor** (a API recusa mesmo se alguém tentar chamar direto).

Níveis que já vêm prontos:

| Nível | Para quem |
|---|---|
| **Administrador** | Acesso total + Usuários e acessos. Não pode ser apagado; o sistema nunca fica sem um admin ativo |
| **Usuário** | Dia a dia: lançamentos e cadastros de clientes/fornecedores/parceiros |
| **Financeiro** | Todas as telas financeiras e cadastros, sem gerenciar usuários |
| **Consulta** | Só visualiza, não altera nada |

Dá pra criar quantos níveis quiser (ex.: "Recepção", "Contador"), **duplicar** um existente e ajustar. Ao apagar um nível em uso, o sistema pede para qual nível os usuários vão.

**Usuários:**
- Ao criar, escolha **Gerar senha provisória** (aparece uma vez, pra você passar pra pessoa) ou **Definir agora**. Com senha provisória, no primeiro acesso a pessoa **é obrigada a criar a própria senha**.
- **Desativar** tira o acesso na hora sem apagar o histórico. **Redefinir senha** e **desativar** derrubam a sessão da pessoa imediatamente.
- A lista mostra último acesso (data e IP), quem está bloqueado e quem ainda não fez o primeiro acesso.

**Segurança do login:**
- 5 senhas erradas seguidas **bloqueiam o usuário por 15 min** (admin pode desbloquear antes). 15 erros do mesmo computador em 10 min bloqueiam o IP.
- "Manter conectado" guarda a sessão por 30 dias; sem marcar, a sessão cai ao fechar o navegador ou após 8h parada.
- Token CSRF em todas as ações, cookie `HttpOnly` + `SameSite=Strict` (+ `Secure` em HTTPS), senha com bcrypt.
- Mostrar/ocultar senha, aviso de Caps Lock e medidor de força da senha.

---

## 📥 Importação de planilhas

Em **Importar / Exportar**:

1. **Arquivo** — escolha o que vai importar (**Lançamentos**, **Clientes** ou **Fornecedores**) e envie um `.xlsx` ou `.csv` (até 10 MB / 5.000 linhas). Tem **planilha modelo** pra baixar.
2. **Colunas** — o sistema sugere qual coluna vai em cada campo pelo nome do cabeçalho (Data, Valor, Descrição, Categoria, Situação, Vencimento…). Ajuste se precisar.
3. **Conferir** — prévia linha a linha: **prontas**, **com aviso**, **duplicadas** e **com erro** (erros e duplicadas são puladas).
4. **Pronto** — importa tudo numa transação só. No **histórico** dá pra **desfazer** (apaga só o que aquela importação criou).

O que ele entende sozinho:
- Valores `R$ 1.234,56`, `1234.56`, `-230,45`, `(230,45)`
- Datas `05/04/2026`, `05/04/26`, `2026-04-05` e datas do Excel
- Tipo: Receita/Entrada/Crédito ou Despesa/Saída/Débito. Sem coluna de tipo: **valor negativo = despesa** (ou escolha "tudo despesa" / "tudo receita")
- Situação: Pago/Quitado/Recebido/Sim ou Em aberto/Pendente/Não. Sem coluna: o padrão que você escolher
- Categoria pelo nome; se não existir, cria (se marcar a opção) ou deixa sem categoria
- CSV com `;` ou `,`, em UTF-8 ou ANSI (o padrão do Excel no Brasil)
- Duplicados: mesma data + tipo + valor + descrição (no sistema ou repetido na planilha)

> Arquivo `.xls` antigo (Excel 97-2003) não é lido: no Excel use **Salvar como → Pasta de Trabalho do Excel (.xlsx)**.

---

## 🧱 Estrutura MVC

```
DRE-php/
├── index.php                 ← front controller: toda requisição passa por aqui
├── .htaccess                 ← manda tudo pro index.php e bloqueia app/, storage/, .env
├── router.php                ← o mesmo papel do .htaccess no "php -S"
├── .env.example              ← modelo da configuração (copie para .env)
├── database.sql              ← schema completo (opcional, as tabelas são criadas sozinhas)
├── app/
│   ├── bootstrap.php         ← carrega .env, autoload das classes, constantes
│   ├── helpers.php           ← funções pequenas (e(), url(), validação de CPF/CNPJ…)
│   ├── routes.php            ← TODAS as rotas (páginas e /api/*)
│   ├── Core/                 ← o "framework": App, Router, Request, Response, View,
│   │                            Database, Model, Controller, Auth, Session, Csrf, Permissions
│   ├── Controllers/
│   │   ├── PageController.php        ← entrega as telas (HTML)
│   │   └── Api/*Controller.php       ← JSON: Auth, Dre, Transacao, Cliente, Fornecedor, Parceiro,
│   │                                   Documento, Categoria, Caixa, FormaPagamento, Cadastro,
│   │                                   Usuario, Perfil, Log, Export, Importacao
│   ├── Models/               ← um por tabela, com o SQL e as validações
│   │                            (User, Perfil, Transacao, Transferencia, Cliente, Fornecedor, Parceiro,
│   │                             Documento, Categoria, Caixa, FormaPagamento, Cadastro, Importacao, Log, RateLimit)
│   ├── Services/             ← regras maiores: DreService (cálculos), ImportService, Upload,
│   │   └── Spreadsheet/         PlanilhaReader (lê xlsx/csv), XlsxWriter, PdfWriter
│   ├── Database/Migrator.php ← cria/atualiza as tabelas sozinho
│   └── Views/
│       ├── layouts/          ← app.php (menu + modais) e auth.php (login)
│       ├── pages/            ← uma view por tela
│       ├── partials/         ← menu lateral, modais compartilhados
│       ├── auth/login.php
│       └── errors/erro.php
├── assets/
│   ├── css/app.css
│   ├── js/core.js            ← utilitários, chamada à API, modais, menu, perfil
│   ├── js/lancamento-form.js ← modal de lançamento (Lançamentos e Calendário)
│   ├── js/pages/*.js         ← um script por tela
│   └── img/
├── uploads/                  ← arquivos enviados (bloqueado executar script)
└── storage/                  ← sessões, importações temporárias, versão do schema (bloqueado pra web)
```

**Como uma requisição anda:** `index.php` → `App::run()` → `Router` acha a rota em `routes.php` → **Controller** confere permissão e chama o **Model** (SQL) / **Service** (cálculo) → devolve JSON (API) ou renderiza a **View** (tela). Cada tela carrega seu JS, que busca os dados na API.

**Pra criar uma tela nova:**
1. Adicione a tela em `app/Core/Permissions.php` (chave, nome, grupo do menu, rota) — ela já aparece no menu e na tela de níveis de acesso.
2. Adicione em `PageController::PAGINAS` (título e script) e crie `app/Views/pages/<rota>.php` e `assets/js/pages/<rota>.js`.
3. Crie o Model/Controller da API e registre as rotas em `app/routes.php`. No controller use `$this->autorizar('tela')` pra ler e `$this->autorizarEdicao('tela')` pra alterar.

### Rotas da API (resumo)

| Método | Rota | |
|---|---|---|
| POST | `api/auth/login` · `auth/logout` · `auth/register` · `auth/trocar-senha` · `auth/profile` | login e conta |
| GET | `api/dre` · `dre/demonstrativo` · `dre/resumo` · `dre/detalhado` · `dre/fluxo-caixa` · `agenda` | relatórios |
| GET/POST/PUT/PATCH/DELETE | `api/transactions[/:id]` · `api/transfers[/:id]` | lançamentos |
| CRUD | `api/clientes` · `fornecedores` · `parceiros` · `categorias` · `caixas` · `formas_pagamento` · `documentos` | cadastros |
| CRUD | `api/users` (+ `/:id/senha`, `/:id/situacao`, `/:id/desbloquear`) · `api/perfis` | usuários e níveis |
| GET | `api/export/excel` · `csv` · `xml` · `pdf` · `dre-excel` | exportação |
| POST/GET/DELETE | `api/importacao/analisar` · `validar` · `confirmar` · `modelo` · `api/importacao/:id` | importação |

---

## 🩺 Problemas comuns

| Sintoma | Solução |
|---|---|
| "Não foi possível conectar ao banco de dados" | MySQL ligado no XAMPP? `DB_USER`/`DB_PASS`/`DB_NAME` certos no `.env`? No XAMPP a senha do root é vazia (`DB_PASS=`) |
| Página **404** em tudo menos a inicial / CSS não carrega | O `mod_rewrite` está desligado ou o Apache ignora o `.htaccess`. No XAMPP: `httpd.conf` com `LoadModule rewrite_module` sem `#` e `AllowOverride All` no `<Directory "C:/xampp/htdocs">` |
| "Sessão desatualizada. Recarregue a página" | A sessão expirou com a página aberta — recarregue (F5) |
| Upload falha com arquivo grande | Aumente `upload_max_filesize` e `post_max_size` no `php.ini` (XAMPP: Config → PHP (php.ini)) |
| Excel não exporta / .xlsx não importa | Habilite a extensão `zip` no `php.ini` (`extension=zip`) |
| Mudou algo no banco na mão e o sistema não percebeu | Apague `storage/schema_version` — as migrações rodam de novo (não apagam dados) |
| Usuário bloqueado | Admin: Usuários e acessos → ⋮ → Desbloquear (ou espere 15 min) |
