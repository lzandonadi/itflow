# Spec Delta

## ADDED Requirements

### Requirement: Mudança manual de status respeita o status atual
O sistema SHALL aceitar uma mudança manual de status de fatura só a partir dos status em que a tela a oferece: cancelar a partir de Draft, Sent, Viewed ou Partial; marcar como Non-Billable a partir de Sent ou Viewed com total zero; marcar como enviada a partir de Draft; registrar pagamento ou aplicar crédito a partir de Sent, Viewed ou Partial com saldo. Uma ação fora dessas regras SHALL ser recusada sem mudar status, histórico, estoque ou pagamentos, com um aviso de erro.

#### Scenario: Cancelar uma fatura paga por link montado à mão
- **WHEN** um técnico com permissão de edição abre o endereço de cancelamento de uma fatura Paid
- **THEN** a fatura continua Paid, nenhum produto volta ao estoque, nada entra no histórico e o técnico vê um aviso de erro

#### Scenario: Non-Billable numa fatura com valor
- **WHEN** alguém pede para marcar como Non-Billable uma fatura Sent com total maior que zero
- **THEN** a fatura continua Sent e o pedido é recusado com um aviso de erro

#### Scenario: Marcar como enviada uma fatura cancelada
- **WHEN** chega o pedido de marcar como enviada uma fatura Cancelled
- **THEN** a fatura continua Cancelled e o pedido é recusado com um aviso de erro

#### Scenario: Pagamento numa fatura cancelada
- **WHEN** chega o pedido de registrar um pagamento numa fatura Cancelled
- **THEN** nenhum pagamento é gravado, a fatura continua Cancelled e o pedido é recusado com um aviso de erro

#### Scenario: Cancelar uma fatura com pagamento parcial
- **WHEN** um técnico cancela pela tela uma fatura Partial
- **THEN** a fatura passa a Cancelled e os pagamentos já recebidos continuam registrados, como hoje
