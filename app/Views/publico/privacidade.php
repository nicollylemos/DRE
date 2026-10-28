<?php /** Política de privacidade (LGPD — Lei 13.709/2018). */ include __DIR__ . '/_topo.php'; ?>
    <h1>Política de privacidade</h1>
    <p class="lgpd-lead">Esta política explica quais dados pessoais o <b>OAF Controle Financeiro</b> guarda, para quê, com quem compartilha, por quanto tempo e como você exerce seus direitos, conforme a Lei Geral de Proteção de Dados (Lei nº 13.709/2018 — LGPD).</p>

    <h2>1. Quem cuida dos dados</h2>
    <p>A controladora dos dados tratados neste sistema é <b><?= $controlador ?></b>. Dúvidas, pedidos e reclamações sobre dados pessoais podem ser enviados para <?= $contato ?>, que atua como canal de atendimento do encarregado (DPO).</p>

    <h2>2. Quais dados guardamos</h2>
    <ul>
      <li><b>De quem usa o sistema:</b> nome, usuário, e-mail, foto de perfil (opcional), senha (guardada só em formato criptografado — ninguém consegue lê-la), nível de acesso, data do último acesso e endereço IP.</li>
      <li><b>Registro de atividades (logs):</b> o que foi criado, alterado, apagado, importado ou exportado, por quem, quando e de qual IP. Nas exclusões guardamos uma cópia do registro apagado, para auditoria.</li>
      <li><b>De clientes, fornecedores e parceiros:</b> nome ou razão social, CPF/CNPJ, telefone, WhatsApp, e-mail, endereço, responsável, contratos e observações.</li>
      <li><b>Financeiros e fiscais:</b> lançamentos, contas a pagar e receber, caixas e contas bancárias (nome do banco, agência e conta), e notas fiscais (PDF e XML), que podem conter nome, CPF/CNPJ e endereço de emitentes e destinatários.</li>
    </ul>
    <p>O sistema <b>não</b> coleta dados sensíveis (saúde, religião, biometria etc.) e não é destinado a menores de 18 anos.</p>

    <h2>3. Para que usamos e com qual base legal</h2>
    <table class="lgpd-tbl">
      <thead><tr><th>Finalidade</th><th>Base legal (art. 7º da LGPD)</th></tr></thead>
      <tbody>
        <tr><td>Criar e manter sua conta, autenticar o acesso</td><td>Execução de contrato / procedimentos preliminares (inc. V) e consentimento (inc. I)</td></tr>
        <tr><td>Controle financeiro, DRE, fluxo de caixa e guarda de notas fiscais</td><td>Cumprimento de obrigação legal ou regulatória — fiscal e contábil (inc. II) e execução de contrato (inc. V)</td></tr>
        <tr><td>Cadastro de clientes, fornecedores e parceiros</td><td>Execução de contrato (inc. V) e legítimo interesse (inc. IX)</td></tr>
        <tr><td>Logs, bloqueio por tentativas de senha e prevenção a fraudes</td><td>Legítimo interesse (inc. IX) e proteção do crédito/segurança (inc. X)</td></tr>
        <tr><td>Atender a pedidos de autoridades e defesa em processos</td><td>Obrigação legal (inc. II) e exercício regular de direitos (inc. VI)</td></tr>
      </tbody>
    </table>
    <p>Não usamos os dados para propaganda, não vendemos dados e não tomamos decisões automatizadas sobre pessoas.</p>

    <h2>4. Com quem compartilhamos</h2>
    <ul>
      <li><b>Hospedagem e banco de dados:</b> o provedor onde o sistema está instalado, apenas para armazenar e processar os dados.</li>
      <li><b>Consulta de CNPJ (BrasilAPI):</b> quando você pede para buscar um CNPJ, só o número é enviado, para trazer dados públicos da Receita Federal.</li>
      <li><b>Contador, auditoria e autoridades:</b> quando houver obrigação legal ou ordem de autoridade competente.</li>
    </ul>

    <h2>5. Como protegemos</h2>
    <p>Senhas criptografadas (bcrypt), sessão com cookie protegido, proteção contra CSRF, bloqueio após tentativas de senha erradas, níveis de acesso por tela, notas fiscais guardadas fora da área pública do site (só abre para quem tem acesso), HTTPS na hospedagem e registro de todas as alterações. Se acontecer um incidente de segurança que possa causar risco ou dano relevante, avisaremos os titulares afetados e a ANPD (art. 48).</p>

    <h2>6. Por quanto tempo guardamos</h2>
    <ul>
      <li><b>Dados financeiros, fiscais e notas fiscais:</b> pelo menos 5 anos, prazo de guarda exigido pela legislação tributária, ou mais se houver processo em andamento.</li>
      <li><b>Logs de atividade:</b> enquanto forem necessários para auditoria e segurança (recomendado: no mínimo 6 meses).</li>
      <li><b>Conta de usuário:</b> enquanto você tiver acesso. Depois de desativada, os dados podem ser apagados ou anonimizados a pedido, mantendo só o que a lei obriga a guardar (art. 16).</li>
    </ul>

    <h2>7. Seus direitos (art. 18)</h2>
    <p>Você pode, a qualquer momento e sem custo: confirmar se tratamos seus dados; acessar os dados; corrigir dados incompletos ou errados; pedir anonimização, bloqueio ou eliminação do que for desnecessário ou excessivo; pedir a portabilidade; saber com quem compartilhamos; revogar o consentimento; e se opor a um tratamento. Nome, e-mail e foto você mesmo altera em <b>Meu perfil</b>. Para o resto, fale com <?= $contato ?>. Respondemos em até 15 dias. Você também pode reclamar à Autoridade Nacional de Proteção de Dados (<a href="https://www.gov.br/anpd" target="_blank" rel="noopener">gov.br/anpd</a>).</p>

    <h2>8. Cookies e armazenamento no navegador</h2>
    <p>Usamos só o que é necessário para o sistema funcionar: um cookie de sessão (mantém você conectado e protege contra CSRF) e, no seu navegador, a lembrança do último usuário digitado e se o menu lateral está recolhido. Não usamos cookies de propaganda nem de rastreamento.</p>

    <h2>9. Mudanças nesta política</h2>
    <p>Quando esta política mudar de forma relevante, a versão sobe e todo mundo precisa ler e aceitar de novo no próximo acesso.</p>
<?php include __DIR__ . '/_rodape.php'; ?>
