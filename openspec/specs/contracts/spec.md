# contracts Specification

## Purpose
Define o contrato com cada cliente: ciclo de vida, renovação e aviso prévio, assinatura online, horas pré-pagas e as condições (SLA, valores por hora, prazo de pagamento) que o contrato impõe enquanto está em vigor.

## Requirements

### Requirement: Ciclo de vida do contrato
O sistema SHALL usar os status Draft, Active, Cancelled e Ended. Na tarefa noturna, SHALL renovar no fim do período o contrato com renovação automática, encerrar o que não renova (avisando a equipe) e lembrar o responsável, uma única vez, antes da data de aviso prévio. Um contrato cancelado SHALL continuar em vigor até a data de término.

#### Scenario: Fim do período com renovação automática
- **WHEN** chega o fim do período de um contrato ativo com renovação automática
- **THEN** a tarefa noturna renova o contrato

#### Scenario: Renovação atrasada por vários períodos
- **WHEN** a tarefa noturna encontra um contrato com renovação automática cujo fim passou há mais de um período
- **THEN** o contrato é renovado até o primeiro período que ainda não terminou

#### Scenario: Contrato termina sem renovação
- **WHEN** chega o fim de um contrato ativo sem renovação automática
- **THEN** o status passa a Ended, a equipe é avisada e as faturas recorrentes ligadas a ele são pausadas

#### Scenario: Data de aviso prévio se aproximando
- **WHEN** a data limite para avisar o encerramento está próxima
- **THEN** o responsável pelo contrato é lembrado

### Requirement: Condições do contrato em vigor
O sistema SHALL aplicar, enquanto o contrato está em vigor, o SLA dele para cada prioridade, o valor por hora dele (o valor fora de horário para chamados abertos fora do expediente) e o prazo de pagamento dele em faturas novas. Cada condição SHALL ser decidida separadamente: vale o contrato em vigor que começou por último entre os que definem aquela condição e, se nenhum define, o padrão da instalação. Para o tempo de um chamado, conta o contrato em vigor na data em que o chamado foi concluído.

#### Scenario: Chamado aberto fora do expediente
- **WHEN** o tempo de um chamado aberto fora do horário comercial é faturado e o contrato em vigor define valor fora de horário
- **THEN** o tempo é cobrado pelo valor fora de horário do contrato

#### Scenario: Cliente sem contrato
- **WHEN** o cliente não tem contrato em vigor
- **THEN** valem o valor por hora e o prazo de pagamento padrão da instalação

#### Scenario: Contrato mais novo sem valor por hora
- **WHEN** o cliente tem dois contratos em vigor e o mais recente não define valor por hora
- **THEN** o tempo é cobrado pelo valor do contrato mais antigo, e o prazo de pagamento vem do mais recente se ele o definir

### Requirement: Assinatura online
O sistema SHALL enviar o contrato para assinatura como um documento fixado no envio, identificado por hash, com link próprio. O cliente assina pelo link ou pelo portal, informando o nome, desenhando ou digitando a assinatura e marcando que concorda. A equipe é avisada da assinatura do cliente. Um técnico então contra-assina, o PDF assinado vai para o cliente e o rascunho se torna ativo. Contratos encerrados ou cancelados e clientes arquivados não SHALL receber pedido de assinatura.

#### Scenario: Assinatura completa
- **WHEN** o cliente assina e um técnico contra-assina
- **THEN** o contrato fica ativo e o cliente recebe o PDF assinado

#### Scenario: Documento muda enquanto o cliente lê
- **WHEN** o cliente envia a assinatura de uma versão do documento diferente da que está no pedido
- **THEN** a assinatura é recusada e o cliente é orientado a ler o contrato de novo

### Requirement: Pedido de assinatura retirado quando o documento muda
O sistema SHALL retirar o pedido de assinatura em aberto quando o contrato for alterado e o documento resultante mudar, ou quando o contrato for cancelado, terminar ou o cliente for arquivado. Nada SHALL ser assinado em um pedido retirado, e os pedidos anteriores SHALL ser mantidos.

#### Scenario: Contrato alterado durante a assinatura
- **WHEN** um técnico altera os termos de um contrato que está aguardando assinatura
- **THEN** o link enviado deixa de permitir assinar e um novo envio é necessário

### Requirement: Horas pré-pagas consumidas pelos chamados
O sistema SHALL consumir as horas pré-pagas dos contratos ativos do cliente com o tempo dos chamados faturáveis e resolvidos ou fechados, começando pelo bloco que vence primeiro, mesmo que seja de outro contrato, e arredondando pelas regras de faturamento. Um bloco só cobre chamados concluídos entre a data da compra e a do vencimento dele. O excedente SHALL continuar no tempo não faturado. O responsável SHALL ser avisado uma vez quando as horas ficarem baixas.

#### Scenario: Chamado totalmente coberto
- **WHEN** um chamado resolvido faturável usa 2 horas e o contrato tem 10 horas disponíveis
- **THEN** as 2 horas saem do bloco e o chamado não aparece no tempo não faturado

#### Scenario: Chamado parcialmente coberto
- **WHEN** um chamado usa 3 horas e restam 2 horas no contrato
- **THEN** 2 horas são consumidas e 1 hora fica no tempo não faturado

#### Scenario: Chamado concluído antes da compra das horas
- **WHEN** um chamado foi resolvido um dia antes da compra do único bloco de horas do cliente
- **THEN** nada é consumido e o chamado fica no tempo não faturado
