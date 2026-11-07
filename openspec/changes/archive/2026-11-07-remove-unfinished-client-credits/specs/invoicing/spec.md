# Spec Delta

## MODIFIED Requirements

### Requirement: Mudança manual de status respeita o status atual
O sistema SHALL aceitar uma mudança manual de status de fatura só a partir dos status em que a tela a oferece: cancelar a partir de Draft, Sent, Viewed ou Partial; marcar como Non-Billable a partir de Sent ou Viewed com total zero; marcar como enviada a partir de Draft; registrar pagamento a partir de Sent, Viewed ou Partial com saldo. Uma ação fora dessas regras SHALL ser recusada sem mudar status, histórico, estoque ou pagamentos, com um aviso de erro.

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

## ADDED Requirements

### Requirement: Crédito de cliente não é criado nem aplicado
O sistema não SHALL oferecer nem aceitar a criação de crédito de cliente nem a aplicação de crédito em fatura, por nenhuma tela ou pedido. Créditos gravados por versões anteriores SHALL continuar guardados e aparecer na exportação de dados do cliente, e uma fatura que tenha crédito aplicado SHALL continuar mostrando a linha de crédito no total.

#### Scenario: Pedido de crédito montado à mão
- **WHEN** um técnico com permissão de vendas envia um pedido de criação de crédito para um cliente
- **THEN** nenhum crédito é gravado e o pedido fica registrado no log da aplicação como pedido não tratado

#### Scenario: Endereço do antigo formulário de aplicar crédito
- **WHEN** alguém abre o endereço do antigo formulário de aplicar crédito numa fatura
- **THEN** o formulário não existe mais e nada muda na fatura

#### Scenario: Página do cliente
- **WHEN** um técnico abre a página de um cliente
- **THEN** o menu de ações não oferece adicionar crédito

#### Scenario: Crédito de uma versão anterior
- **WHEN** um cliente tem um crédito gravado antes desta versão e uma fatura com crédito aplicado
- **THEN** o crédito aparece na exportação de dados do cliente e a fatura mostra a linha de crédito no total, na impressão e no PDF
