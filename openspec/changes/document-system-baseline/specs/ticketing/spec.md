# Spec Delta

## Purpose

Define o ciclo de vida dos chamados: status, reabertura e fechamento automático, o que conta como tempo trabalhado e como e-mails recebidos viram chamados ou respostas.

## ADDED Requirements

### Requirement: Status de chamado
O sistema SHALL ter os status padrão New, Open, On Hold, Resolved e Closed, identificados por número e não pelo nome, e SHALL permitir status adicionais configuráveis, cada um indicando se pausa o SLA e se aceita resposta do cliente. Um chamado novo SHALL começar em New.

#### Scenario: Chamado criado
- **WHEN** um chamado é aberto por qualquer canal
- **THEN** ele começa no status New

#### Scenario: Nome do status renomeado
- **WHEN** um administrador renomeia o status Resolved
- **THEN** o comportamento de resolvido (fechamento automático, reabertura) continua valendo, porque depende do status e não do nome

### Requirement: Resposta do cliente reabre o chamado resolvido
O sistema SHALL reabrir um chamado resolvido quando o cliente responder, e não SHALL aceitar resposta em um chamado fechado.

#### Scenario: Cliente responde depois da resolução
- **WHEN** o cliente responde a um chamado resolvido
- **THEN** o chamado deixa de estar resolvido e volta para o atendimento

### Requirement: Fechamento automático de resolvidos
O sistema SHALL fechar na tarefa noturna os chamados resolvidos sem atualização há mais tempo que o intervalo configurado, registrando o fechamento em nome do técnico responsável e avisando o cliente como em um fechamento manual.

#### Scenario: Resolvido sem resposta
- **WHEN** um chamado está resolvido há mais horas que o intervalo configurado, sem atualização
- **THEN** a tarefa noturna o fecha e o cliente é avisado

### Requirement: Só o tempo digitado pelo técnico é tempo trabalhado
O sistema SHALL contar como tempo trabalhado apenas o tempo que o técnico digita em uma resposta. As respostas geradas pelo sistema (atribuição, mudança de prioridade, mesclagem, fechamento, fatura ou orçamento criado, agenda, tarefas) SHALL registrar tempo zero. Estimativas de tarefas não SHALL virar tempo trabalhado.

#### Scenario: Chamado reatribuído
- **WHEN** um chamado é atribuído a outro técnico
- **THEN** a resposta automática de atribuição tem tempo zero e não aparece nos totais de tempo, relatórios nem faturamento

### Requirement: E-mail recebido vira resposta ou chamado
O sistema SHALL, quando a leitura da caixa de suporte está habilitada, transformar cada e-mail recebido, nesta ordem: em resposta ao chamado cujo número está no assunto; em resposta a um chamado recente parecido do mesmo contato ou domínio; em chamado novo para o contato cadastrado; em chamado novo para o cliente dono do domínio do remetente.

#### Scenario: Resposta com número no assunto
- **WHEN** chega um e-mail cujo assunto traz o número de um chamado existente
- **THEN** o e-mail é adicionado como resposta desse chamado

#### Scenario: Remetente de domínio cadastrado
- **WHEN** chega um e-mail de um endereço não cadastrado cujo domínio pertence a um cliente
- **THEN** um chamado novo é aberto para esse cliente

### Requirement: Remetentes desconhecidos
O sistema SHALL abrir chamado sem cliente para remetentes desconhecidos apenas quando a configuração permitir, e mesmo assim SHALL ignorar remetentes de rejeição de entrega (daemon, postmaster, bounce, mta).

#### Scenario: Aviso de falha de entrega
- **WHEN** remetentes desconhecidos são permitidos e chega um e-mail de `mailer-daemon@`
- **THEN** nenhum chamado é aberto
