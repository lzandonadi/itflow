# ticket-sla Specification

## Purpose
Define como os chamados recebem metas de SLA de resposta e resolução, como os prazos são calculados em horário comercial e como avisos e violações são sinalizados e notificados.

## Requirements

### Requirement: Origem do SLA de um chamado
O sistema SHALL atribuir a um chamado o SLA definido para a sua prioridade pelo contrato em vigor do cliente ou, na falta dele, o SLA padrão da prioridade. Sem nenhum dos dois, o chamado SHALL ficar sem SLA e ser ignorado pelo monitoramento, pelos destaques da lista e pelos relatórios.

#### Scenario: Cliente com contrato que define SLA
- **WHEN** um chamado de prioridade alta é aberto para um cliente cujo contrato em vigor define SLA para prioridade alta
- **THEN** o chamado recebe o SLA do contrato

#### Scenario: Nenhum SLA configurado
- **WHEN** não há SLA padrão nem de contrato para a prioridade
- **THEN** o chamado fica sem metas e nenhum aviso de SLA é gerado

### Requirement: Prazos calculados na gravação, em horário comercial
O sistema SHALL calcular os prazos de resposta e resolução em horário comercial, considerando os dias úteis, o horário e os dias de fechamento cadastrados pelo administrador, e gravá-los no chamado ao criá-lo e ao mudar a prioridade, o cliente ou o SLA. Recalcular o SLA de um chamado SHALL zerar os estágios de aviso dele.

#### Scenario: Chamado aberto na sexta à noite
- **WHEN** um chamado com meta de resposta de 4 horas úteis é aberto depois do expediente de sexta
- **THEN** o prazo de resposta cai no próximo dia útil, dentro do expediente

### Requirement: Status que pausam o SLA
O sistema SHALL pausar o relógio de resolução enquanto o chamado estiver em um status que pausa. Nos status padrão isso é fixo: New e Open sempre contam, On Hold, Resolved e Closed sempre pausam; os status personalizados são configuráveis. O prazo de resposta nunca pausa. Quando o relógio volta a correr, o prazo de resolução SHALL ser recalculado com o saldo que restava.

#### Scenario: Chamado aguardando o cliente
- **WHEN** um chamado fica em On Hold
- **THEN** o tempo em On Hold não conta para a meta de resolução, e ao sair de On Hold o prazo de resolução é empurrado pelo tempo parado

#### Scenario: On Hold antes da primeira resposta
- **WHEN** um chamado sem nenhuma resposta é colocado em On Hold
- **THEN** o prazo de resposta continua correndo

### Requirement: Estágios de aviso e violação notificados uma vez
O sistema SHALL acompanhar a resposta e a resolução de cada chamado com SLA em três estágios (no prazo, aviso, violação), destacando aviso em amarelo e violação em vermelho na lista. Cada notificação SHALL ser enviada uma única vez.

#### Scenario: Chamado chega ao percentual de aviso
- **WHEN** o aviso está em 80% e um chamado consome 80% do prazo de resposta sem resposta
- **THEN** o aviso é enviado uma vez e o chamado aparece em amarelo

#### Scenario: Prazo de resposta estoura
- **WHEN** o prazo de resposta passa sem resposta e o monitor roda várias vezes
- **THEN** a violação é notificada uma única vez, a meta fica registrada como não cumprida e o chamado aparece em vermelho

#### Scenario: Técnico restrito a outro cliente
- **WHEN** um aviso de SLA é gerado para um chamado do cliente B
- **THEN** um técnico restrito ao cliente A não vê esse aviso

#### Scenario: Percentual de aviso fora da faixa
- **WHEN** o percentual de aviso configurado está fora de 1 a 99
- **THEN** só as violações são notificadas
