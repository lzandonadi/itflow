# Proposal

## Why

O pagamento manual de fatura confere o valor contra o saldo que o próprio formulário envia, num campo oculto (`balance`), e não contra o saldo calculado pelo servidor. Um pedido montado à mão, ou um formulário aberto antes de outro pagamento, pode registrar mais do que a fatura deve. Valor zero ou negativo também passa, porque só o navegador o barra. O mesmo acontece no pagamento em lote e na edição de um pagamento. Essa falha foi registrada como achado pela change `fix-invoice-status-guards`.

## What Changes

- **Pagamento manual de uma fatura:** o servidor calcula o saldo, ignora o `balance` do formulário e recusa, com aviso de erro, um valor acima do saldo, zero ou negativo. A moeda do pagamento passa a ser a da fatura, e não a do formulário.
- **Pagamento em lote:** o servidor soma o que o cliente deve nas faturas em aberto e recusa um valor acima dessa soma, zero ou negativo. Cada pagamento gravado leva a moeda da sua fatura. O audit log registra o valor realmente aplicado.
- **Edição de pagamento:** o novo valor precisa ser maior que zero e não pode passar do saldo da fatura somado ao valor que o pagamento já tinha.
- **Comparação em centavos:** todos esses limites comparam valores em centavos, como já fazem o pagamento online e a importação de extrato.
- **Sem mudança:** os caminhos que já calculam o valor no servidor continuam iguais. São eles o pagamento online pelo portal e pelo link, a cobrança com cartão salvo, a cobrança automática e a importação de extrato.

## Capabilities

### New Capabilities
<!-- Nenhuma. -->

### Modified Capabilities
- `invoicing`: entra o requisito "Pagamento manual não passa do saldo", que define os limites do pagamento manual, do pagamento em lote e da edição de pagamento.

## Impact

- **Código:** `agent/post/payment.php` (blocos `add_payment`, `add_bulk_payment` e `edit_payment`). Os campos ocultos `balance` dos modais `agent/modals/payment/payment_add.php` e `payment_bulk_add.php` deixam de ser lidos e podem sair. Uma tradução nova em `lang/pt_BR/finance.php`.
- **Banco:** sem mudança de esquema. Hoje não há no DDEV nenhuma fatura com pagamentos acima do total, nem pagamento com valor zero ou negativo.
- **Comportamento visível:** nenhum para quem usa a tela normalmente, porque o formulário já começa no saldo e o navegador já barra valores negativos. Muda só para pedidos montados à mão, para formulários desatualizados e para o lote com valor acima do devido, que antes era aceito e tinha o excedente descartado sem aviso.
