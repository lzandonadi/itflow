# Spec Delta

## ADDED Requirements

### Requirement: Pagamento manual não passa do saldo
O sistema SHALL conferir um pagamento manual contra o saldo que ele mesmo calcula, nunca contra um valor enviado pelo formulário. Um pagamento numa fatura SHALL ser maior que zero e no máximo igual ao saldo dela. Um pagamento em lote SHALL ser maior que zero e no máximo igual à soma dos saldos das faturas em aberto do cliente. Um pagamento editado SHALL ficar maior que zero e no máximo igual ao saldo da fatura mais o valor anterior dele. As comparações SHALL ser feitas em centavos.

#### Scenario: Pagamento acima do saldo com formulário adulterado
- **WHEN** chega um pedido de pagamento de 150,00 numa fatura com saldo de 100,00, com o campo de saldo do formulário alterado para 500,00
- **THEN** nenhum pagamento é gravado, a fatura não muda e o pedido é recusado com um aviso de erro

#### Scenario: Pagamento igual ao saldo
- **WHEN** um técnico registra um pagamento exatamente igual ao saldo da fatura
- **THEN** o pagamento é gravado e a fatura passa a Paid

#### Scenario: Valor zero ou negativo
- **WHEN** chega um pedido de pagamento com valor zero ou negativo, numa fatura ou em lote
- **THEN** nenhum pagamento é gravado e o pedido é recusado com um aviso de erro

#### Scenario: Lote acima do que o cliente deve
- **WHEN** um pagamento em lote informa um valor maior que a soma dos saldos em aberto do cliente
- **THEN** nenhum pagamento é gravado em nenhuma fatura e o pedido é recusado com um aviso de erro

#### Scenario: Edição que passaria do saldo
- **WHEN** um pagamento de 40,00 numa fatura de 100,00 com 60,00 de saldo é editado para 120,00
- **THEN** o pagamento continua com 40,00 e a edição é recusada com um aviso de erro; editar para 100,00 é aceito e a fatura passa a Paid
