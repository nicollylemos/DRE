<?php /** Termo de uso e consentimento (LGPD — Lei 13.709/2018). */ include __DIR__ . '/_topo.php'; ?>
    <h1>Termo de uso e consentimento</h1>
    <p class="lgpd-lead">Ao marcar "Li e concordo" você aceita as regras de uso do <b>OAF Controle Financeiro</b> e dá o consentimento para o tratamento dos seus dados pessoais descrito aqui e na <a href="<?= e(url('privacidade')) ?>">Política de privacidade</a>.</p>

    <h2>1. O que é o sistema</h2>
    <p>Ferramenta interna de controle financeiro de <b><?= $controlador ?></b>: lançamentos, contas a pagar e receber, DRE, fluxo de caixa, notas fiscais e cadastros. O acesso é liberado por um administrador; não existe cadastro público.</p>

    <h2>2. Sua conta</h2>
    <ul>
      <li>O usuário e a senha são pessoais e intransferíveis. Não compartilhe sua senha e avise o administrador se suspeitar de uso indevido.</li>
      <li>Você enxerga e altera só as telas liberadas pelo seu nível de acesso.</li>
      <li>Depois de algumas senhas erradas seguidas o acesso é bloqueado por alguns minutos, para proteger a conta.</li>
      <li>Troque sua senha quando quiser em <b>Meu perfil → Trocar senha</b>. Esqueceu? Um administrador redefine em <b>Usuários e acessos</b>.</li>
    </ul>

    <h2>3. Uso correto e sigilo</h2>
    <ul>
      <li>Use o sistema só para as atividades da empresa. Os dados financeiros, de clientes, fornecedores, parceiros e as notas fiscais são <b>confidenciais</b>.</li>
      <li>Cadastre informações verdadeiras e só os dados pessoais necessários para a finalidade financeira.</li>
      <li>Não exporte, copie ou envie dados para fora do sistema sem necessidade de trabalho. Exportações ficam registradas.</li>
      <li>Ao incluir dados de terceiros (clientes, fornecedores, emitentes de notas), você declara que eles foram obtidos de forma legítima, para a relação comercial ou para cumprir obrigação fiscal.</li>
    </ul>

    <h2>4. Consentimento para o tratamento dos seus dados</h2>
    <p>Nos termos dos arts. 7º, I, e 8º da LGPD, você autoriza, de forma livre, informada e inequívoca, que <b><?= e(\App\Core\Termos::controlador()) ?></b> trate seu nome, usuário, e-mail, foto (se enviar), endereço IP, data e hora de acesso e o histórico das ações feitas no sistema, para:</p>
    <ol>
      <li>identificar você e controlar o acesso de acordo com o seu nível;</li>
      <li>manter o registro de atividades (logs) para auditoria, segurança e prova das operações financeiras.</li>
    </ol>
    <p>Os demais tratamentos (dados financeiros, fiscais e de terceiros) se apoiam em outras bases legais — obrigação legal, execução de contrato e legítimo interesse — explicadas na Política de privacidade.</p>

    <h2>5. Revogar o consentimento</h2>
    <p>Você pode revogar este consentimento quando quiser, falando com <?= $contato ?>. Como o sistema não funciona sem identificar quem o usa, a revogação implica a desativação do seu acesso. Os registros que a lei obriga a guardar (por exemplo, quem fez um lançamento financeiro) são mantidos pelo prazo legal (art. 16).</p>

    <h2>6. Registro de atividades</h2>
    <p>Tudo o que é criado, alterado, apagado, importado ou exportado fica registrado com usuário, data, hora e IP. Nas exclusões o sistema guarda uma cópia do que foi apagado. Esses registros podem ser consultados por quem tem acesso à tela de Logs.</p>

    <h2>7. Disponibilidade e mudanças</h2>
    <p>O sistema pode ficar fora do ar para manutenção. Este termo pode ser atualizado; quando a mudança for relevante, a versão sobe e o aceite é pedido de novo no próximo acesso. Este termo segue a legislação brasileira.</p>
<?php include __DIR__ . '/_rodape.php'; ?>
