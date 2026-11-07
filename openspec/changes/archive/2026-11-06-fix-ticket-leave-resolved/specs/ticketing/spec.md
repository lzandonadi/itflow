# Spec Delta

## MODIFIED Requirements

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

## ADDED Requirements

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
