# Spec Delta

## Purpose

Define o comportamento dos documentos de cobrança (faturas, orçamentos e faturas recorrentes), seus status, lembretes de vencimento, multas por atraso e extratos mensais.

## ADDED Requirements

### Requirement: Estrutura comum dos documentos de cobrança
O sistema SHALL tratar faturas, orçamentos e faturas recorrentes como documentos numerados de um cliente, com escopo, categoria de receita, itens (nome, descrição, quantidade, preço e imposto), desconto e total. Os valores SHALL ser arredondados ao centavo quando calculados, para que o valor gravado seja o mesmo exibido, impresso e pago.

#### Scenario: Item com imposto
- **WHEN** um item é adicionado com quantidade, preço e imposto
- **THEN** o imposto e o total do item são gravados já arredondados ao centavo, e o total do documento é a soma desses valores

### Requirement: Status de fatura
O sistema SHALL usar os status de fatura Draft, Sent, Viewed, Partial, Paid, Overdue e Cancelled. Esses valores SHALL ser gravados e comparados exatamente assim, independente do idioma da interface.

#### Scenario: Pagamento parcial
- **WHEN** uma fatura enviada recebe um pagamento menor que o saldo
- **THEN** o status passa a Partial

#### Scenario: Pagamento total
- **WHEN** o saldo de uma fatura chega a zero
- **THEN** o status passa a Paid

### Requirement: Status de orçamento
O sistema SHALL usar os status de orçamento Draft, Sent, Viewed, Accepted, Declined e Invoiced. Esses valores SHALL ser gravados e comparados exatamente assim, e o cliente SHALL poder aceitar ou recusar um orçamento online.

#### Scenario: Cliente aceita online
- **WHEN** o cliente aceita um orçamento pelo link recebido
- **THEN** o status passa a Accepted

### Requirement: Faturas recorrentes
O sistema SHALL gerar na tarefa noturna as faturas das recorrências vencidas hoje ou perdidas por poucos dias, e SHALL avisar sobre recorrências atrasadas demais para serem cobradas sozinhas. O envio automático da fatura gerada depende da configuração.

#### Scenario: Cron parado por uma noite
- **WHEN** a tarefa noturna não rodou em um dia em que uma recorrência vencia
- **THEN** a fatura é gerada na noite seguinte, dentro do prazo de tolerância

### Requirement: Multa por atraso
O sistema SHALL, quando a multa está habilitada, acrescentar a cada 30 dias de atraso um item de multa igual ao percentual configurado sobre o saldo, no máximo uma vez por dia, e SHALL notificar a equipe. Faturas sem saldo devedor SHALL ser ignoradas.

#### Scenario: Fatura com 30 dias de atraso
- **WHEN** a tarefa noturna roda e uma fatura está há 30 dias vencida, com saldo e multa de 2% habilitada
- **THEN** a fatura ganha um item de multa de 2% do saldo e a equipe é notificada

#### Scenario: Tarefa noturna roda duas vezes no dia
- **WHEN** a tarefa noturna roda de novo no mesmo dia
- **THEN** nenhuma segunda multa é aplicada

### Requirement: Lembretes de vencimento
O sistema SHALL enviar lembretes de fatura nos dias antes e depois do vencimento definidos na configuração, no máximo um por dia por fatura. Faturas cobradas por cobrança automática no dia ou sem destinatário SHALL ficar de fora.

#### Scenario: Lembrete já enviado hoje
- **WHEN** a tarefa noturna roda pela segunda vez no dia de um lembrete
- **THEN** o lembrete não é reenviado

### Requirement: Extrato mensal
O sistema SHALL enviar todo mês, no dia definido nas configurações de fatura, um extrato a cada cliente com saldo em aberto.

#### Scenario: Dia do extrato
- **WHEN** chega o dia configurado
- **THEN** cada cliente com saldo recebe o extrato e clientes sem saldo não recebem
