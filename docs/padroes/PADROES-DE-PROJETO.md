# Padrões de projeto — DRE System (OAF)

Projeto Integrador II · Grupo OAF (Ordilei)

O DRE System é um sistema de controle financeiro em PHP puro com MySQL, organizado em MVC. Nesta etapa aplicamos três padrões do catálogo GoF em pontos onde o código tinha `if/else` crescendo ou classes que sabiam demais umas das outras: **Strategy** no cálculo do DRE, **Factory** na leitura do XML das notas fiscais e **Observer** nos eventos dos lançamentos. A refatoração não mudou nenhuma funcionalidade: rodamos os mesmos relatórios, leituras de XML e operações antes e depois da mudança e o resultado foi idêntico byte a byte.

O diagrama completo está em `diagrama-classes.mmd` (código Mermaid) e `diagrama-classes.png`. Cada padrão novo também tem um diagrama separado (`1-strategy`, `2-factory`, `3-observer`).

## 1. Strategy — regime e agrupamento do DRE

**Problema.** A tela DRE pode ser vista em dois regimes (competência ou caixa) e agrupada de três jeitos (categoria, grupo/conta ou centro de custo). Antes, o `DreService` decidia isso com `if ($regime === 'caixa')` e `if ($agrupar === 'grupo')` no meio do cálculo, e a lista de opções ficava repetida em constantes usadas também pela exportação em PDF.

**Solução.** Cada regime virou uma classe que implementa `RegimeDre` (`RegimeCompetencia`, `RegimeCaixa`) e cada agrupamento uma classe que implementa `AgrupamentoDre` (`AgrupamentoPorCategoria`, `AgrupamentoPorGrupoConta`, `AgrupamentoPorCentroCusto`). O `DreService` é o contexto: recebe as estratégias escolhidas pela classe `EstrategiasDre` e monta o demonstrativo sem saber qual regime ou agrupamento está usando.

**Ganho.** Criar um agrupamento novo (por fornecedor, por exemplo) é escrever uma classe pequena e registrar numa linha, sem tocar no cálculo. Os nomes que aparecem na tela e no PDF passam a vir das próprias estratégias, num lugar só.

Arquivos: `app/Services/Dre/` e `app/Services/DreService.php`.

## 2. Factory — leitura do XML da nota fiscal

**Problema.** O sistema lê três layouts de nota: NF-e/NFC-e (SEFAZ), NFS-e no padrão Nacional e NFS-e no padrão ABRASF das prefeituras. Tudo ficava numa classe só, com uma sequência de `if` decidindo o layout e um método enorme para cada um.

**Solução.** Cada layout virou um leitor, subclasse de `LeitorNotaFiscal` (`LeitorNfe`, `LeitorNfseNacional`, `LeitorNfseAbrasf`). Cada leitor sabe reconhecer o próprio XML (`reconhece()`) e extrair os campos (`ler()`). A `FabricaLeitorNotaFiscal` recebe o XML já carregado (`DocumentoXml`), pergunta aos leitores qual o reconhece e devolve o objeto certo. Quem usa (`NotaFiscalXml`, a importação em lote, a sugestão de lançamento) só chama `ler()` e não conhece nenhum layout.

**Ganho.** Aceitar um layout novo de prefeitura é criar uma subclasse e incluí-la na fábrica. O que é comum a todos (formato da resposta, conversão de datas, valores e CNPJ) ficou na classe base, sem repetição. A parte de segurança (recusar XML com DOCTYPE) ficou isolada no `DocumentoXml`.

Arquivos: `app/Services/NotaFiscal/` e `app/Services/NotaFiscalXml.php`.

## 3. Observer — eventos dos lançamentos

**Problema.** O controller de lançamentos precisava saber regras das notas fiscais: ao criar um lançamento a partir de uma nota, vincular os dois; ao apagar um lançamento, tirar o vínculo da nota. Cada regra nova desse tipo ia deixando o controller mais acoplado a outras partes do sistema.

**Solução.** O `TransacaoController` agora só avisa que algo aconteceu, disparando um `Evento` (`lancamento.criado` ou `lancamento.apagado`) no `Despachante`, que é o sujeito observado. Quem precisa reagir implementa a interface `Ouvinte` e é registrado em `app/eventos.php`: `VincularNotaFiscal` e `DesvincularNotasFiscais`.

**Ganho.** O controller não conhece mais as notas fiscais. Uma reação nova ao apagar um lançamento (avisar o financeiro, recalcular algo) entra como outro ouvinte, sem mexer no código que já funciona.

Arquivos: `app/Eventos/`, `app/eventos.php` e `app/Controllers/Api/TransacaoController.php`.

## Padrões que o sistema já usava

Além dos três novos, o diagrama mostra a estrutura que já existia:

- **MVC:** Models (`app/Models`) concentram o SQL, Views (`app/Views`) montam o HTML e Controllers (`app/Controllers`) recebem a requisição e respondem.
- **Front Controller:** toda requisição entra por `index.php`, que chama `App::run()`. O `App` aplica as travas globais (CSRF, troca de senha, aceite LGPD) e usa o `Router` para chamar o controller certo.
- **Singleton:** a classe `Database` guarda uma única conexão PDO por requisição e todo o sistema a reaproveita.
- **Classes base (Layer Supertype):** `Model` e `Controller` são abstratas e concentram o que todos os filhos usam: CRUD básico e tabela no `Model`; autorização, leitura da requisição e log no `Controller`.

## Como conferimos que nada mudou

1. Antes da refatoração, geramos os dados fictícios do sistema (cerca de 2.000 lançamentos e 47 notas com XML) e salvamos o resultado de todos os relatórios: dashboard, DRE nos dois regimes e nos três agrupamentos de 2024 a 2027, fluxo de caixa de 24 meses em todas as contas, leitura de todos os XML (inclusive os inválidos) e sugestões de lançamento.
2. Depois da refatoração rodamos tudo de novo: o resultado foi idêntico byte a byte (2,3 MB).
3. Rodamos o mesmo roteiro pela API nas duas versões, lado a lado: criar nota pelo XML, gerar o lançamento vinculado, apagar e desvincular, importação em lote e as cinco exportações. As respostas e até a ordem dos registros de log foram as mesmas.
