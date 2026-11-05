# Spec Delta

## Purpose

Define como os chamados recebem metas de SLA de resposta e resolução, como os prazos são calculados em horário comercial e como avisos e violações são sinalizados e notificados.

## ADDED Requirements

### Requirement: Origem do SLA de um chamado
O sistema SHALL atribuir a um chamado o SLA definido para a sua prioridade pelo contrato em vigor do cliente ou, na falta dele, o SLA padrão da prioridade. Sem nenhum dos dois, o chamado SHALL ficar sem SLA e ser ignorado pelo monitoramento, pelos destaques da lista e pelos relatórios.

#### Scenario: Cliente com contrato que define SLA
- **WHEN** um chamado de prioridade alta é aberto para um cliente cujo contrato em vigor define SLA para prioridade alta
- **THEN** o chamado recebe o SLA do contrato

#### Scenario: Nenhum SLA configurado
- **WHEN** não há SLA padrão nem de contrato para a prioridade
- **THEN** o chamado fica sem metas e nenhum aviso de SLA é gerado

### Requirement: Prazos calculados na gravação, em horário comercial
O sistema SHALL calcular os prazos de resposta e resolução em horário comercial, considerando dias úteis e dias de fechamento, e gravá-los no chamado ao criá-lo e ao mudar a prioridade, o cliente ou o SLA.

#### Scenario: Chamado aberto na sexta à noite
- **WHEN** um chamado com meta de resposta de 4 horas úteis é aberto depois do expediente de sexta
- **THEN** o prazo de resposta cai no próximo dia útil, dentro do expediente

### Requirement: Status que pausam o SLA
O sistema SHALL pausar o relógio do SLA enquanto o chamado estiver em um status marcado para pausar, como On Hold, Resolved e Closed nos status padrão.

#### Scenario: Chamado aguardando o cliente
- **WHEN** um chamado fica em On Hold
- **THEN** o tempo em On Hold não conta para a meta de resolução

### Requirement: Estágios de aviso e violação notificados uma vez
O sistema SHALL acompanhar a resposta e a resolução de cada chamado com SLA em três estágios (no prazo, aviso, violação), destacando aviso em amarelo e violação em vermelho na lista. Cada notificação SHALL ser enviada uma única vez.

#### Scenario: Chamado chega ao percentual de aviso
- **WHEN** o aviso está em 80% e um chamado consome 80% do prazo de resposta sem resposta
- **THEN** o aviso é enviado uma vez e o chamado aparece em amarelo

#### Scenario: Percentual de aviso fora da faixa
- **WHEN** o percentual de aviso configurado está fora de 1 a 99
- **THEN** só as violações são notificadas
