# Design

## Context

Ver proposal.md, "Why". Como cada caminho que grava em `payments` chega ao valor hoje:

| Caminho | Origem do valor | Limite conferido |
|---|---|---|
| Pagamento manual (`agent/post/payment.php`, `add_payment`) | formulário (`amount`) | `amount > $_POST['balance']`, saldo vindo do formulário (`:20`, `:46`); zero e negativo passam; moeda vem do formulário (`:24`) |
| Pagamento em lote (`add_bulk_payment`) | formulário | `amount > $_POST['balance']` (`:421`, `:431`); o excedente acima do que as faturas devem é descartado sem aviso, e o audit log registra o valor digitado; zero ou negativo grava nada e mostra "Bulk Payment added" |
| Edição de pagamento (`edit_payment`) | formulário | nenhum limite de valor; só a trava de conciliação (`:119`) |
| Cobrança com cartão salvo (`add_payment_stripe`) | valor total da fatura, só em Sent ou Viewed | valor cobrado no Stripe |
| Pagamento online pelo link (`guest/guest_pay_invoice_stripe.php:233`) | Stripe | saldo calculado no servidor, comparado em centavos |
| Pagamento online pelo portal (`functions/billing_pay.php`, `billingStripeBook()`) | divisão do Stripe por fatura | soma da divisão igual ao recebido |
| Importação de extrato (`functions/income_import.php`) | linha do extrato | casa com saldos calculados, em centavos |
| Cobrança automática (`cron/nightly_tasks.php`) | Stripe | cobrança efetiva |

Depois da change `fix-invoice-status-guards`, o `add_payment` já calcula o que foi pago (`$pay_paid`) e o estado da fatura (`invoiceState()`, `functions/billing.php:733`, que devolve `balance` arredondado ao centavo). Só para conferir o status. O valor continua sendo comparado com o `balance` do formulário.

Pagar a mais não é um recurso do sistema. Nenhum caminho transforma excedente em crédito: o crédito só entra pela tela de crédito (`agent/post/credit.php`), e aplicar crédito está desligado. Uma fatura paga a mais hoje fica com status Partial, porque o `add_payment` define Paid só quando o saldo é exatamente zero (`:72`).

## Goals / Non-Goals

**Goals:**
- Os três caminhos manuais conferem o valor contra o saldo calculado no servidor, em centavos, e recusam zero e negativo.
- A moeda gravada no pagamento é a da fatura.

**Non-Goals:**
- Transformar pagamento a mais em crédito do cliente. Seria um recurso novo.
- A cobrança com cartão salvo do técnico (`add_payment_stripe`). Ela compara o valor cobrado com `intval()`, que trunca os centavos, o mesmo defeito que o pagamento pelo link já corrigiu. Como envolve uma cobrança real, fica registrado como achado para uma change própria.
- Aplicar crédito (`apply_credit`), que está desligado e é tratado em outra change.
- Mudar como o status é recalculado depois do pagamento.
- Os pagamentos já gravados: o DDEV não tem nenhum acima do saldo nem com valor zero ou negativo.

## Decisions

### D1. Uma função de limite em centavos, usada pelos três caminhos
`paymentAmountCheck($amount, $limit)` em `functions/payments.php`, ao lado de `updateInvoiceStatusFromPayments()`. Ela devolve `null` quando o valor serve, ou a mensagem de erro já traduzida: "maior que zero" ou "não pode passar do saldo". Compara `round($amount * 100)` com `round($limit * 100)`.
- *Por quê:* os três blocos têm o mesmo par de regras. Uma função só evita três comparações de float escritas de jeitos diferentes.
- *Alternativa descartada:* repetir as comparações em cada bloco, como hoje. Foi assim que o lote e a edição ficaram sem a regra.

### D2. Saldo sempre do servidor; o campo do formulário é ignorado e removido
- `add_payment` usa `$state['balance']` do `invoiceState()` que já calcula.
- `add_bulk_payment` soma os saldos positivos das faturas Sent, Viewed e Partial do cliente, com a mesma consulta que o modal já usa para mostrar o devido (`agent/modals/payment/payment_bulk_add.php:24`).
- `edit_payment` usa o saldo da fatura do pagamento mais o valor atual dele (`invoicePaid()` e `invoice_amount`).
- Os `<input type="hidden" name="balance">` dos dois modais saem.
- *Por quê:* um campo que o servidor não lê só confunde quem for mexer depois.
- *Alternativa descartada:* conferir os dois, formulário e servidor. O do formulário não acrescenta nada e pode estar desatualizado.

### D3. Lote acima do devido é recusado inteiro
- *Por quê:* hoje o excedente é descartado sem aviso e o audit log registra um valor que não entrou. Recusar mostra o problema na hora, e a tela já começa no valor devido.
- *Alternativa descartada:* aceitar e aplicar só o devido, avisando o que sobrou. Esconde um erro de digitação e o recibo sairia com um valor diferente do que o técnico digitou.
- O audit log do lote passa a registrar o total aplicado.

### D4. Moeda da fatura no lugar da do formulário
O pagamento manual grava `invoice_currency_code`, e o lote grava a moeda de cada fatura. A moeda do formulário é ignorada, como o saldo.

### D5. Mensagens
- Valor acima do saldo: reaproveita "Payment can not be more than the balance" e "Payment exceeds Client Balance.", que já têm tradução.
- Valor zero ou negativo: chave nova "The payment amount must be greater than zero.", em `lang/pt_BR/finance.php`.

## Risks / Trade-offs

- **[Risco] Formulário aberto antes de outro pagamento na mesma fatura.** Antes ele passava com o saldo antigo e pagava a mais; agora é recusado. → É o comportamento correto. A mensagem diz que o valor passa do saldo, e reabrir o modal mostra o saldo atual.
- **[Risco] Arredondamento.** Um saldo como 0,1 + 0,2 calculado em float pode diferir no último dígito. → A comparação em centavos (D1) resolve, como já fazem o pagamento pelo link e a importação.
- **[Trade-off] Edição limitada pelo saldo.** Um pagamento editado não pode mais passar do total da fatura. Quem precisar registrar um valor recebido a mais terá de usar outro caminho, como o crédito, quando ele for terminado.

## Migration Plan

Sem migração nem mudança de esquema. Para desfazer, basta reverter o commit.
