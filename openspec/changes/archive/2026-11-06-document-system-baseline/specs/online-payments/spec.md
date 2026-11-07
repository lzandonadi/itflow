# Spec Delta

## Purpose

Define como clientes pagam faturas por cartão via Stripe, como funcionam cartões salvos e a cobrança automática no vencimento, e como as taxas do provedor são registradas.

## ADDED Requirements

### Requirement: Um pagamento por cartão pode quitar várias faturas
O sistema SHALL permitir que o cliente pague várias faturas com um único pagamento por cartão no portal. Esse pagamento SHALL ser registrado como um pagamento por fatura, todos com a mesma data e a mesma referência.

#### Scenario: Cliente paga duas faturas juntas
- **WHEN** um contato financeiro paga duas faturas em um só pagamento no portal
- **THEN** cada fatura recebe um pagamento com o seu valor, ambos com a mesma referência do Stripe

### Requirement: Pagamento registrado uma única vez
O sistema SHALL registrar cada pagamento por cartão uma única vez, mesmo quando a confirmação do Stripe chega mais de uma vez ou o formulário é enviado duas vezes.

#### Scenario: Confirmação repetida
- **WHEN** o portal recebe de novo a confirmação de um pagamento que já foi registrado
- **THEN** nenhum pagamento novo é criado e o cliente vê o recibo do pagamento original

### Requirement: Limite por pagamento com cartão
O sistema SHALL recusar um pagamento por cartão cujo valor atinja o limite configurado no provedor. Sem limite configurado, qualquer valor é aceito.

#### Scenario: Faturas acima do limite
- **WHEN** um contato seleciona faturas que somam mais que o limite
- **THEN** o pagamento não é iniciado e o portal pede para pagar menos faturas de uma vez ou pagar de outra forma

### Requirement: Cobrança automática no vencimento
O sistema SHALL cobrar automaticamente, no dia do vencimento, as faturas Sent ou Viewed geradas por uma fatura recorrente que tem cobrança automática configurada. Um cartão recusado no dia não SHALL ser cobrado de novo no mesmo dia.

#### Scenario: Cobrança aprovada
- **WHEN** a tarefa noturna roda no vencimento de uma fatura com cobrança automática
- **THEN** o cartão salvo é cobrado, o pagamento é registrado, a fatura fica paga e o cliente recebe o recibo

#### Scenario: Cartão recusado e tarefa roda de novo
- **WHEN** o cartão foi recusado e a tarefa noturna roda outra vez no mesmo dia
- **THEN** não há nova tentativa de cobrança

#### Scenario: Cobrança automática sem cartão
- **WHEN** a cobrança automática de uma recorrência está configurada sem cartão salvo, só com conta e forma de pagamento
- **THEN** no vencimento a fatura recebe um pagamento com essa forma de pagamento e fica paga, sem nenhuma cobrança externa

#### Scenario: Cartão removido ou provedor desligado
- **WHEN** o cartão da cobrança automática não está mais salvo ou o provedor foi desligado
- **THEN** nada é cobrado e a falha fica registrada no audit log da fatura

### Requirement: Taxas do provedor registradas como despesa
O sistema SHALL registrar como despesa a taxa cobrada pelo Stripe em cada pagamento por cartão, depois que o Stripe liquidar o pagamento, por uma tarefa que roda a cada poucos minutos. Cada taxa SHALL ser registrada uma única vez, mesmo quando o registro manual é usado ao mesmo tempo.

#### Scenario: Registro manual e automático simultâneos
- **WHEN** um administrador clica em "Record fees now" enquanto a tarefa de taxas está rodando
- **THEN** cada taxa aparece uma única vez nas despesas
