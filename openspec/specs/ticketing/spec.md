# ticketing Specification

## Purpose
Define o ciclo de vida dos chamados: status, reabertura e fechamento automático, o que conta como tempo trabalhado e como e-mails recebidos viram chamados ou respostas.

## Requirements

### Requirement: Status de chamado
O sistema SHALL ter os status padrão New, Open, On Hold, Resolved e Closed, identificados por número e não pelo nome, e SHALL permitir status adicionais configuráveis, cada um indicando se pausa o SLA e se a resposta do cliente devolve o chamado para Open. Um chamado novo SHALL começar em New.

#### Scenario: Chamado criado
- **WHEN** um chamado é aberto por qualquer canal
- **THEN** ele começa no status New

#### Scenario: Nome do status renomeado
- **WHEN** um administrador renomeia o status Resolved
- **THEN** o comportamento de resolvido (fechamento automático, reabertura) continua valendo, porque depende do status e não do nome

### Requirement: Resposta do cliente reabre o chamado resolvido
O sistema SHALL devolver para Open um chamado em qualquer status padrão (New, On Hold, Resolved) quando o cliente responder, reiniciando o prazo de resolução do SLA. Um status personalizado configurado para isso SHALL manter o chamado onde está. O sistema não SHALL aceitar resposta em um chamado fechado.

#### Scenario: Cliente responde depois da resolução
- **WHEN** o cliente responde a um chamado resolvido
- **THEN** o chamado deixa de estar resolvido e volta para o atendimento

#### Scenario: Cliente responde pelo portal a um chamado resolvido
- **WHEN** um contato responde pelo portal a um chamado resolvido
- **THEN** o chamado volta para Open sem data de resolução, aparece entre os chamados abertos e o relógio de resolução do SLA volta a correr, exatamente como numa resposta por e-mail

#### Scenario: Cliente responde por e-mail a um chamado fechado
- **WHEN** chega por e-mail uma resposta a um chamado fechado
- **THEN** a resposta não entra no chamado, o remetente recebe um aviso de que o chamado está fechado e a equipe é notificada para conferir a caixa

### Requirement: Chamado fechado não é excluído
O sistema SHALL manter os chamados fechados: a exclusão só vale para chamados ainda não fechados, e exige nível completo no módulo de suporte.

#### Scenario: Excluir chamado fechado
- **WHEN** um técnico com nível completo tenta excluir um chamado fechado
- **THEN** a exclusão é recusada com o aviso de que chamados fechados são mantidos

### Requirement: Fechamento automático de resolvidos
O sistema SHALL fechar na tarefa noturna os chamados resolvidos sem atualização há mais tempo que o intervalo configurado (de 24 a 8760 horas, 72 por padrão; não há como desligar), registrando o fechamento em nome do técnico responsável e avisando o cliente como em um fechamento manual.

#### Scenario: Resolvido sem resposta
- **WHEN** um chamado está resolvido há mais horas que o intervalo configurado, sem atualização
- **THEN** a tarefa noturna o fecha, o histórico registra o fechamento automático e o cliente é avisado por e-mail se os avisos gerais ao cliente estiverem ligados

### Requirement: Só o tempo digitado pelo técnico é tempo trabalhado
O sistema SHALL contar como tempo trabalhado apenas o tempo que o técnico digita em uma resposta. As respostas geradas pelo sistema (atribuição, mudança de prioridade, mesclagem, fechamento, fatura ou orçamento criado, agenda, tarefas) SHALL registrar tempo zero. Estimativas de tarefas não SHALL virar tempo trabalhado.

#### Scenario: Chamado reatribuído
- **WHEN** um chamado é atribuído a outro técnico
- **THEN** a resposta automática de atribuição tem tempo zero e não aparece nos totais de tempo, relatórios nem faturamento

### Requirement: E-mail recebido vira resposta ou chamado
O sistema SHALL, quando a leitura da caixa de suporte está habilitada, transformar cada e-mail recebido, nesta ordem: em resposta ao chamado cujo número está no assunto; em resposta a um chamado do mesmo cliente aberto nos últimos 7 dias, ainda não resolvido, com assunto pelo menos 95% igual; em chamado novo para o contato cadastrado; em chamado novo para o cliente dono do domínio do remetente, cadastrando o remetente como contato.

#### Scenario: Resposta com número no assunto
- **WHEN** chega um e-mail cujo assunto traz o número de um chamado existente
- **THEN** o e-mail é adicionado como resposta desse chamado

#### Scenario: Remetente de domínio cadastrado
- **WHEN** chega um e-mail de um endereço não cadastrado cujo domínio pertence a um cliente
- **THEN** um chamado novo é aberto para esse cliente e o remetente vira contato dele

#### Scenario: Resposta de quem não é contato do cliente
- **WHEN** chega uma resposta a um chamado, pelo número no assunto, de um endereço que não é contato do cliente
- **THEN** a resposta entra como nota interna, marcada com o aviso de que o e-mail do contato não confere

#### Scenario: Anexo de tipo não permitido
- **WHEN** um e-mail traz um anexo com extensão fora da lista permitida
- **THEN** o anexo não é salvo e o bloqueio fica no audit log

### Requirement: Remetentes desconhecidos
O sistema SHALL abrir chamado sem cliente para remetentes desconhecidos apenas quando a configuração permitir, e mesmo assim SHALL ignorar remetentes de rejeição de entrega (daemon, postmaster, bounce, mta).

#### Scenario: Aviso de falha de entrega
- **WHEN** remetentes desconhecidos são permitidos e chega um e-mail de `mailer-daemon@`
- **THEN** nenhum chamado é aberto

### Requirement: Sair de Resolved desfaz a resolução
O sistema SHALL tratar como reabertura toda mudança de um chamado do status Resolved para outro status que não seja Closed, por qualquer caminho: resposta do cliente, resposta do técnico, resposta em lote ou API. A data de resolução SHALL ser apagada, a meta de resolução SHALL voltar a ser acompanhada e o relógio SHALL voltar a correr com o saldo que restava, salvo se o novo status pausar o SLA.

#### Scenario: Técnico responde e escolhe Open
- **WHEN** um técnico responde a um chamado resolvido escolhendo Open em "Status após isto"
- **THEN** o chamado fica Open, sem data de resolução, e conta de novo para o prazo de resolução

#### Scenario: Técnico responde e escolhe On Hold
- **WHEN** um técnico responde a um chamado resolvido escolhendo On Hold
- **THEN** o chamado fica On Hold, sem data de resolução, e o relógio de resolução continua parado por causa do status

#### Scenario: Resposta pela API muda o status
- **WHEN** uma resposta enviada pela API leva um chamado resolvido para Open
- **THEN** o chamado fica sem data de resolução e o relógio de resolução volta a correr

#### Scenario: Meta de resolução já estourada
- **WHEN** um chamado cuja meta de resolução estourou e cujo saldo acabou é tirado de Resolved
- **THEN** a violação continua registrada e nenhum aviso novo de violação é enviado

### Requirement: Chamados já inconsistentes são corrigidos
O sistema SHALL corrigir, pela tarefa de SLA, todo chamado que não está Resolved nem fechado mas ainda tem data de resolução, do mesmo modo que uma reabertura. A correção SHALL acontecer uma vez por chamado e deixá-lo igual a um chamado reaberto naquele momento.

#### Scenario: Chamado reaberto pelo portal antes da correção
- **WHEN** a tarefa de SLA roda e encontra um chamado Open com data de resolução
- **THEN** a data de resolução é apagada, o relógio de resolução volta a correr a partir dali e as execuções seguintes não mexem mais nele

#### Scenario: Chamado fechado
- **WHEN** a tarefa de SLA encontra um chamado fechado com data de resolução
- **THEN** nada muda, porque um chamado fechado guarda a data em que foi resolvido
