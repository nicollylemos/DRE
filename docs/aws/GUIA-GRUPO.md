# Guia do grupo — hospedar o DRE na sua própria AWS

Cada integrante sobe o **mesmo projeto** (este repositório) no seu próprio AWS Academy Learner Lab. Leva uns 20 minutos, quase tudo esperando.

## 1. Criar a stack

1. No AWS Academy: **Start Lab** → esperar a bolinha ficar **verde** → clicar em **AWS**.
2. Conferir no canto superior direito a região **N. Virginia (us-east-1)**.
3. Baixar o template: [`infra/cloudformation.yaml`](../../infra/cloudformation.yaml) → botão **Download raw file**.
4. Console → **CloudFormation** → **Criar pilha** → **Com novos recursos (padrão)**.
5. **Escolher um modelo existente** → **Fazer upload de um arquivo de modelo** → selecionar o `cloudformation.yaml` → **Próximo**.
6. Parâmetros:
   - **Nome da pilha:** `dre-dev`
   - **AlertEmail:** o seu e-mail
   - **Não mexer no resto** (o `GitRepoUrl` já aponta para este repositório).
7. **Próximo** → **Próximo** → **Enviar** (se aparecer caixinha de "I acknowledge", marcar).
8. Esperar **15 a 20 min** até ficar **CREATE_COMPLETE** (verde). Não fechar o lab.
9. Confirmar o e-mail da AWS (**Confirm subscription**) para receber os alarmes.

> Se ficar vermelho (ROLLBACK): aba **Eventos** → print da primeira linha **FAILED** e mandar no grupo.

## 2. Abrir o site

1. Na stack `dre-dev`, aba **Saídas** → clicar no **AppUrl**.
2. Tela **Primeiro acesso** → criar o administrador.
3. Testar também o `AppUrl` + `/health` → tem que aparecer `"status":"UP"`.

Cada uma tem **um link diferente**. Guarde o seu.

## 3. Atualizar quando o código mudar

O deploy automático do GitHub vai só para a AWS da Nicolly. Para atualizar a sua:

1. Console → **EC2** → **Instâncias** → marcar **dre-dev-app** → **Conectar** → aba **EC2 Instance Connect** → **Conectar**.
2. No terminal que abrir:
   ```
   sudo bash /opt/dre/app/scripts/deploy.sh main
   ```
3. Tem que terminar com `deploy OK`.

## 4. Prints para a entrega (se o professor pedir de cada uma)

- Stack **CREATE_COMPLETE** (CloudFormation)
- Site aberto com o cadeado do HTTPS
- `/health` respondendo UP
- CloudWatch → **Painéis** → **dre-dev**

## Avisos

- O lab **desliga tudo** quando o cronômetro acaba (~4 h). Ao dar **Start Lab** de novo, a EC2 e o banco ligam sozinhos e o site volta em uns 5 minutos, com o mesmo link.
- Erro com `voc-cancel-cred` no console = a sessão do lab acabou. É só dar **Start Lab** de novo.
- Nunca colar o `labsuser.pem` (chave do lab) em grupo, chat ou dentro do projeto.
