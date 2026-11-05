# Spec Delta

## Purpose

Define como clientes pagam faturas por cartão via Stripe, como funcionam cartões salvos e a cobrança automática no vencimento, e como as taxas do provedor são registradas.

## ADDED Requirements

### Requirement: Um pagamento por cartão pode quitar várias faturas
O sistema SHALL permitir que o cliente pague várias faturas com um único pagamento por cartão no portal. Esse pagamento SHALL ser registrado como um pagamento por fatura, todos com a mesma data e a mesma referência.

#### Scenario: Cliente paga duas faturas juntas
- **WHEN** um contato financeiro paga duas faturas em um só pagamento no portal
- **THEN** cada fatura recebe um pagamento com o seu valor, ambos com a mesma referência do Stripe

### Requirement: Cobrança automática no vencimento
O sistema SHALL cobrar automaticamente, no dia do vencimento, as faturas com status Sent ou Viewed de clientes com cobrança automática e cartão salvo. Um cartão recusado no dia não SHALL ser cobrado de novo no mesmo dia.

#### Scenario: Cobrança aprovada
- **WHEN** a tarefa noturna roda no vencimento de uma fatura com cobrança automática
- **THEN** o cartão salvo é cobrado, o pagamento é registrado, a fatura fica paga e o cliente recebe o recibo

#### Scenario: Cartão recusado e tarefa roda de novo
- **WHEN** o cartão foi recusado e a tarefa noturna roda outra vez no mesmo dia
- **THEN** não há nova tentativa de cobrança

### Requirement: Taxas do provedor registradas como despesa
O sistema SHALL registrar como despesa a taxa cobrada pelo Stripe em cada pagamento por cartão, depois que o Stripe liquidar o pagamento, por uma tarefa que roda a cada poucos minutos. Cada taxa SHALL ser registrada uma única vez, mesmo quando o registro manual é usado ao mesmo tempo.

#### Scenario: Registro manual e automático simultâneos
- **WHEN** um administrador clica em "Record fees now" enquanto a tarefa de taxas está rodando
- **THEN** cada taxa aparece uma única vez nas despesas
