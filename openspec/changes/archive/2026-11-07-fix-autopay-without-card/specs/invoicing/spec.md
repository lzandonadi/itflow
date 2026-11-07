# Spec Delta

## MODIFIED Requirements

### Requirement: Lembretes de vencimento
O sistema SHALL enviar lembretes de fatura nos dias antes e depois do vencimento definidos na configuração, no máximo um por dia por fatura. Faturas cobradas por cobrança automática no dia ou sem destinatário SHALL ficar de fora. Só conta como cobrança automática a que tem um cartão salvo do cliente com o provedor ativo.

#### Scenario: Lembrete já enviado hoje
- **WHEN** a tarefa noturna roda pela segunda vez no dia de um lembrete
- **THEN** o lembrete não é reenviado

#### Scenario: Cobrança automática antiga sem cartão
- **WHEN** a fatura vem de uma recorrência cuja cobrança automática não tem cartão salvo
- **THEN** a fatura recebe os lembretes como qualquer outra, inclusive antes e no dia do vencimento
