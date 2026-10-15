# DRE System — OAF 2026 (PHP + MySQL)

Sistema de controle financeiro (DRE) — versão **PHP**, convertida do Node/Express.
Mesma interface, mesmas regras de cálculo (inclusive as correções da `auditoria-dre.md`), mesmo banco MySQL.

**Interface (out/2026):** novo visual do protótipo — menu lateral marinho, botões dourados, tabelas com menu de ações (⋮) e paginação. Telas ativas por enquanto: **Lançamentos, Parceiros, Fornecedores, Categorias** e **Usuários** (admin). As rotas de DRE/fluxo de caixa/exportação continuam na API pra quando as telas voltarem.
Para usar o logo oficial, salve-o como `img/logo-oaf.png` (o `logo-oaf.svg` é só provisório).

**Novidades da versão anterior**

- **Categorias** — cadastro próprio (nome, tipo receita/despesa/ambos, cor, descrição, ativa/inativa). O lançamento escolhe a categoria, a lista filtra por categoria, o donut do Dashboard usa as cores delas e o DRE Detalhado agrupa por categoria. Categoria em uso não é excluída (só desativada) pra não perder histórico.
- **Parceiros** — CNPJ validado pelos dígitos verificadores (front e servidor), sem duplicar CNPJ, botão **Buscar** que preenche razão social/endereço pela BrasilAPI, contrato principal + **aditivos/outros contratos** anexados direto no cadastro do parceiro, busca por nome ou CNPJ.
- **Usuários** — o admin cria o acesso e marca **quais telas** a pessoa pode ver (agrupadas como no menu, com "marcar todas/limpar"). A tabela mostra as telas liberadas de cada um. O bloqueio vale no menu **e** na API. O sistema não deixa ficar sem nenhum admin.

---

## ⚙️ Requisitos

- PHP **8.0+** (testado no 8.3) com as extensões `pdo_mysql`, `mbstring`, `zip` (export Excel) e `iconv`
- MySQL 5.7+/8.x ou MariaDB 10.3+
- Apache com `mod_rewrite` (padrão em Hostinger, Locaweb, HostGator…) **ou** nginx
- **Não precisa de Composer nem `npm install`** — Excel e PDF são gerados em PHP puro.

## 🚀 Instalação (hospedagem / Apache)

1. Envie todo o conteúdo desta pasta pro `public_html` (ou uma subpasta, ex: `public_html/dre/` — funciona igual).
2. Copie `.env.example` para `.env` e preencha os dados do MySQL. Em produção deixe `APP_ENV=production`.
3. Dê permissão de escrita pro PHP nas pastas `uploads/` e `storage/` (geralmente já vem; senão `chmod 775`).
4. Acesse o site. As tabelas são criadas sozinhas no primeiro acesso
   (se preferir, importe `database.sql` pelo phpMyAdmin antes).
5. Na primeira vez aparece **"Criar acesso inicial"** — esse usuário vira o admin.

> Se a hospedagem não deixar o PHP criar o banco, crie o banco vazio no painel e só coloque o nome em `DB_NAME`.

### nginx

```nginx
root /var/www/dre;
index index.html;
location ^~ /api/ { rewrite ^/api/(.*)$ /api/index.php?__route=$1&$args last; }
location = /api/index.php { include fastcgi_params; fastcgi_param SCRIPT_FILENAME $document_root/api/index.php; fastcgi_pass unix:/run/php/php8.3-fpm.sock; }
location ~ ^/(app|storage)/ { deny all; }
location ~ /\.(env|git) { deny all; }
location ~ \.(sql|md|lock)$ { deny all; }
location ~* ^/uploads/.*\.(php\d?|phtml|phar|html?|svg|js)$ { deny all; }
location ~ \.php$ { deny all; }
client_max_body_size 22M;
```

### Rodando no seu PC

```bash
cp .env.example .env    # ajuste DB_* e coloque APP_ENV=development
php -S localhost:8000 router.php
```
Acesse http://localhost:8000

## 🔁 Vindo da versão Node

- Aponte o `.env` pro **mesmo banco** — as colunas novas (categoria nos lançamentos, observações do parceiro etc.) são adicionadas automaticamente, nada é apagado.
- Copie a pasta `uploads/` antiga (fotos, contratos, documentos) pra dentro desta.
- As senhas continuam as mesmas (o PHP lê os hashes do bcryptjs).
- Login agora usa sessão PHP (cookie httpOnly + SameSite=Strict) no lugar do JWT — não existe mais `JWT_SECRET`.

## 📁 Estrutura

```
index.html  css/  js/         interface (a mesma de antes, com as telas novas)
api/index.php                 entrada de todas as rotas /api/*
app/bootstrap.php             config, banco, sessão, permissões, upload, validação de CNPJ
app/schema.php                criação/migração automática das tabelas + seed
app/routes/*.php              rotas (auth, lançamentos, dre, cadastros/categorias, parceiros/documentos, usuários/logs, export)
app/lib/                      gerador de XLSX e PDF (sem dependências)
uploads/                      arquivos enviados (bloqueado executar script aqui)
storage/                      sessões e controle de versão do schema (bloqueado pra web)
router.php                    só pro servidor embutido do PHP (dev)
database.sql                  schema completo, opcional
```

## 🔐 Telas e permissões

| Chave              | Tela                                   |
|--------------------|----------------------------------------|
| dashboard          | Dashboard                              |
| lancamentos        | Receitas, Despesas e Transferências    |
| dre-resumo         | DRE Resumo                             |
| dre-detalhado      | DRE Detalhado                          |
| fluxo-caixa        | Fluxo de Caixa                         |
| formas-pagamento-rel | Relatório de Formas de Pagamento     |
| cad-clientes / cad-fornecedores / cad-parceiros / cad-categorias / cad-caixas / cad-formas | Cadastros |
| import             | Importar / Exportar (documentos e exportações) |

Perfil é sempre liberado. Usuários e Logs são só de admin.
