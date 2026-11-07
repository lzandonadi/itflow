# invoicing Specification

## Purpose
Define o comportamento dos documentos de cobrança (faturas, orçamentos e faturas recorrentes), seus status, lembretes de vencimento, multas por atraso e extratos mensais.

## Requirements

### Requirement: Estrutura comum dos documentos de cobrança
O sistema SHALL tratar faturas, orçamentos e faturas recorrentes como documentos numerados de um cliente, com escopo, categoria de receita, itens (nome, descrição, quantidade, preço e imposto), desconto e total. Os valores SHALL ser arredondados ao centavo quando calculados, para que o valor gravado seja o mesmo exibido, impresso e pago.

#### Scenario: Item com imposto
- **WHEN** um item é adicionado com quantidade, preço e imposto
- **THEN** o imposto e o total do item são gravados já arredondados ao centavo, e o total do documento é a soma desses valores

### Requirement: Status de fatura
O sistema SHALL gravar os status de fatura Draft, Sent, Viewed, Partial, Paid, Cancelled e Non-Billable, e SHALL compará-los exatamente assim, independente do idioma da interface. O atraso não é um status gravado: uma fatura Sent, Viewed ou Partial com vencimento passado é exibida como atrasada ao lado do status.

#### Scenario: Pagamento parcial
- **WHEN** uma fatura enviada recebe um pagamento menor que o saldo
- **THEN** o status passa a Partial

#### Scenario: Cliente abre a fatura
- **WHEN** o cliente abre pela primeira vez uma fatura Sent, pelo link recebido ou no portal
- **THEN** o status passa a Viewed, a visualização entra no histórico da fatura e a equipe é avisada; um técnico vendo o portal como o contato não muda nada

#### Scenario: Fatura vence
- **WHEN** o vencimento de uma fatura Sent passa sem pagamento
- **THEN** o status gravado continua Sent e a fatura aparece como atrasada nas listas e no painel

#### Scenario: Pagamento total
- **WHEN** o saldo de uma fatura chega a zero
- **THEN** o status passa a Paid

### Requirement: Status de orçamento
O sistema SHALL gravar os status de orçamento Draft, Sent, Viewed, Accepted, Declined e Invoiced, e SHALL compará-los exatamente assim. O cliente SHALL poder aceitar ou recusar online um orçamento Sent ou Viewed; aceitar exige que a validade não tenha passado. O vencimento da validade não é gravado como status.

#### Scenario: Cliente aceita online
- **WHEN** o cliente aceita um orçamento pelo link recebido
- **THEN** o status passa a Accepted

#### Scenario: Orçamento vencido
- **WHEN** o cliente tenta aceitar um orçamento cuja validade já passou
- **THEN** o aceite é recusado com a data em que o orçamento venceu

### Requirement: Numeração sequencial por tipo
O sistema SHALL numerar faturas, orçamentos e faturas recorrentes cada um na sua própria sequência, com prefixo configurável, sem repetir número mesmo quando dois documentos são criados ao mesmo tempo.

#### Scenario: Duas faturas criadas ao mesmo tempo
- **WHEN** dois técnicos criam uma fatura no mesmo instante
- **THEN** cada fatura recebe um número diferente e a sequência avança dois

### Requirement: Faturas recorrentes
O sistema SHALL gerar na tarefa noturna as faturas das recorrências vencidas hoje ou perdidas por até 7 dias, e SHALL avisar sobre recorrências atrasadas demais para serem cobradas sozinhas. O envio automático da fatura gerada depende da configuração.

#### Scenario: Cron parado por uma noite
- **WHEN** a tarefa noturna não rodou em um dia em que uma recorrência vencia
- **THEN** a fatura é gerada na noite seguinte, dentro do prazo de tolerância

#### Scenario: Recorrência atrasada mais de 7 dias
- **WHEN** a próxima data de uma recorrência ativa passou há mais de 7 dias
- **THEN** nenhuma fatura é gerada sozinha e a equipe é avisada de que é preciso faturar ou avançar a recorrência

### Requirement: Multa por atraso
O sistema SHALL, quando a multa está habilitada, acrescentar a cada 30 dias de atraso um item de multa igual ao percentual configurado sobre o saldo, no máximo uma vez por dia, e SHALL notificar a equipe. Faturas sem saldo devedor SHALL ser ignoradas.

#### Scenario: Fatura com 30 dias de atraso
- **WHEN** a tarefa noturna roda e uma fatura está há 30 dias vencida, com saldo e multa de 2% habilitada
- **THEN** a fatura ganha um item de multa de 2% do saldo e a equipe é notificada

#### Scenario: Tarefa noturna roda duas vezes no dia
- **WHEN** a tarefa noturna roda de novo no mesmo dia
- **THEN** nenhuma segunda multa é aplicada

### Requirement: Lembretes de vencimento
O sistema SHALL enviar lembretes de fatura nos dias antes e depois do vencimento definidos na configuração, no máximo um por dia por fatura. Faturas cobradas por cobrança automática no dia ou sem destinatário SHALL ficar de fora. Só conta como cobrança automática a que tem um cartão salvo do cliente com o provedor ativo.

#### Scenario: Lembrete já enviado hoje
- **WHEN** a tarefa noturna roda pela segunda vez no dia de um lembrete
- **THEN** o lembrete não é reenviado

#### Scenario: Cobrança automática antiga sem cartão
- **WHEN** a fatura vem de uma recorrência cuja cobrança automática não tem cartão salvo
- **THEN** a fatura recebe os lembretes como qualquer outra, inclusive antes e no dia do vencimento

### Requirement: Extrato mensal
O sistema SHALL enviar todo mês, no dia definido nas configurações de fatura, um extrato a cada cliente com saldo em aberto e lembretes ligados, no máximo uma vez por dia, quando o envio de e-mail está configurado.

#### Scenario: Dia do extrato
- **WHEN** chega o dia configurado
- **THEN** cada cliente com saldo recebe o extrato e clientes sem saldo não recebem

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
