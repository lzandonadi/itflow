# Spec Delta

## Purpose

Define o contrato com cada cliente: ciclo de vida, renovação e aviso prévio, assinatura online, horas pré-pagas e as condições (SLA, valores por hora, prazo de pagamento) que o contrato impõe enquanto está em vigor.

## ADDED Requirements

### Requirement: Ciclo de vida do contrato
O sistema SHALL criar contratos como rascunho até serem ativados. Na tarefa noturna, SHALL renovar no fim do período o contrato com renovação automática, encerrar o que não renova e lembrar o responsável antes da data de aviso prévio. Contratos cancelados SHALL ser mantidos.

#### Scenario: Fim do período com renovação automática
- **WHEN** chega o fim do período de um contrato ativo com renovação automática
- **THEN** a tarefa noturna renova o contrato

#### Scenario: Data de aviso prévio se aproximando
- **WHEN** a data limite para avisar o encerramento está próxima
- **THEN** o responsável pelo contrato é lembrado

### Requirement: Condições do contrato em vigor
O sistema SHALL aplicar, enquanto o contrato está em vigor, o SLA dele para cada prioridade, o valor por hora dele (o valor fora de horário para chamados abertos fora do expediente) e o prazo de pagamento dele em faturas novas. Sem contrato em vigor, SHALL valer o padrão da instalação. Com dois em vigor, SHALL valer o que começou por último.

#### Scenario: Chamado aberto fora do expediente
- **WHEN** o tempo de um chamado aberto fora do horário comercial é faturado e o contrato em vigor define valor fora de horário
- **THEN** o tempo é cobrado pelo valor fora de horário do contrato

#### Scenario: Cliente sem contrato
- **WHEN** o cliente não tem contrato em vigor
- **THEN** valem o valor por hora e o prazo de pagamento padrão da instalação

### Requirement: Assinatura online
O sistema SHALL enviar o contrato para assinatura como um documento fixado no envio, identificado por hash, com link próprio. O cliente assina pelo link ou pelo portal, informando o nome e desenhando ou digitando a assinatura. Um técnico então contra-assina, o PDF assinado vai para o cliente e o rascunho se torna ativo.

#### Scenario: Assinatura completa
- **WHEN** o cliente assina e um técnico contra-assina
- **THEN** o contrato fica ativo e o cliente recebe o PDF assinado

### Requirement: Pedido de assinatura retirado quando o documento muda
O sistema SHALL retirar o pedido de assinatura em aberto quando o contrato for alterado e o documento resultante mudar, ou quando o contrato for cancelado, terminar ou o cliente for arquivado. Nada SHALL ser assinado em um pedido retirado, e os pedidos anteriores SHALL ser mantidos.

#### Scenario: Contrato alterado durante a assinatura
- **WHEN** um técnico altera os termos de um contrato que está aguardando assinatura
- **THEN** o link enviado deixa de permitir assinar e um novo envio é necessário

### Requirement: Horas pré-pagas consumidas pelos chamados
O sistema SHALL consumir as horas pré-pagas do contrato com o tempo dos chamados do cliente que estejam faturáveis e resolvidos ou fechados, começando pelo bloco que vence primeiro e arredondando pelas regras de faturamento. O excedente SHALL continuar no tempo não faturado. O responsável SHALL ser avisado uma vez quando as horas ficarem baixas.

#### Scenario: Chamado totalmente coberto
- **WHEN** um chamado resolvido faturável usa 2 horas e o contrato tem 10 horas disponíveis
- **THEN** as 2 horas saem do bloco e o chamado não aparece no tempo não faturado

#### Scenario: Chamado parcialmente coberto
- **WHEN** um chamado usa 3 horas e restam 2 horas no contrato
- **THEN** 2 horas são consumidas e 1 hora fica no tempo não faturado
