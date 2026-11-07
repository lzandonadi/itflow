# Spec Delta

## MODIFIED Requirements

### Requirement: Cobrança automática no vencimento
O sistema SHALL cobrar automaticamente, no dia do vencimento, as faturas Sent ou Viewed geradas por uma fatura recorrente que tem cobrança automática configurada com um cartão salvo do cliente. Um cartão recusado no dia não SHALL ser cobrado de novo no mesmo dia. Sem cobrança efetiva no provedor, o sistema não SHALL registrar pagamento: a fatura continua em aberto, a falha entra no histórico da fatura e no audit log, e a equipe é avisada uma vez por dia.

#### Scenario: Cobrança aprovada
- **WHEN** a tarefa noturna roda no vencimento de uma fatura com cobrança automática
- **THEN** o cartão salvo é cobrado, o pagamento é registrado, a fatura fica paga e o cliente recebe o recibo

#### Scenario: Cartão recusado e tarefa roda de novo
- **WHEN** o cartão foi recusado e a tarefa noturna roda outra vez no mesmo dia
- **THEN** não há nova tentativa de cobrança nem novo aviso à equipe

#### Scenario: Cobrança automática sem cartão
- **WHEN** a cobrança automática de uma recorrência está configurada sem cartão salvo, só com conta e forma de pagamento, como nas versões antigas
- **THEN** no vencimento nenhum pagamento é registrado, a fatura continua em aberto e a equipe é avisada de que a cobrança não aconteceu

#### Scenario: Cartão removido ou provedor desligado
- **WHEN** o cartão da cobrança automática não está mais salvo ou o provedor foi desligado
- **THEN** nada é cobrado nem registrado como pagamento, a falha fica no histórico e no audit log da fatura e a equipe é avisada

#### Scenario: Dados do provedor incompletos
- **WHEN** o cartão existe, mas falta ao provedor o que ele precisa para cobrar, como a chave do provedor ou o cadastro do cliente no Stripe
- **THEN** nada é cobrado nem registrado como pagamento, a falha fica no histórico e no audit log da fatura e a equipe é avisada
