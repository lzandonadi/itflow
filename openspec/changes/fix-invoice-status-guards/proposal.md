# Proposal

## Why

A tela da fatura só oferece cada mudança manual de status quando ela faz sentido: Cancelar some numa fatura Paid, Cancelled ou Non-Billable, Non-Billable só aparece numa fatura Sent ou Viewed de valor zero, Marcar como enviada só aparece num rascunho, e Registrar pagamento só aparece numa fatura em aberto com saldo. Os handlers que recebem essas ações, porém, gravam o novo status sem olhar o atual. Um link ou formulário montado à mão (ou uma aba antiga aberta antes de outra pessoa mudar a fatura) pode então cancelar uma fatura paga, devolvendo ao estoque produtos já vendidos, tirar uma fatura com valor de cobrança como Non-Billable, voltar uma fatura paga ou cancelada para Sent, ou registrar um pagamento numa fatura cancelada, que passa a Partial ou Paid. É o achado de invoicing da change `document-system-baseline`.

## What Changes

- O servidor passa a aplicar as mesmas regras que a tela mostra, para cada mudança manual de status de fatura:
  - **Cancelar:** só de Draft, Sent, Viewed ou Partial.
  - **Marcar como Non-Billable:** só de Sent ou Viewed, com total zero.
  - **Marcar como enviada:** só de Draft.
  - **Registrar pagamento** e **aplicar crédito:** só de Sent, Viewed ou Partial, com saldo.
- Uma ação recusada não muda nada (status, histórico, estoque, pagamentos) e mostra um aviso de erro dizendo que a fatura não está mais num status que permita a ação.
- As regras passam a morar em funções únicas, usadas tanto pela tela quanto pelos handlers, para que as duas não voltem a divergir.
- Os caminhos que já checam o status continuam iguais: envio por e-mail (só o rascunho vira Sent), pagamento em lote, cobrança no cartão salvo pela tela, pagamento online pelo link do cliente e a API de faturas, que só lê.

## Capabilities

### New Capabilities
<!-- Nenhuma. -->

### Modified Capabilities
- `invoicing`: entra um requisito dizendo que as mudanças manuais de status de fatura (cancelar, Non-Billable, marcar como enviada, registrar pagamento ou crédito) só valem a partir dos status em que a tela as oferece, e que uma ação recusada não muda nada.

## Impact

- **Código:** `functions/billing.php` (funções de regra novas, ao lado de `invoiceCanPay()`), `agent/post/invoice.php` (`mark_invoice_sent`, `mark_invoice_non-billable`, `cancel_invoice`), `agent/post/payment.php` (`add_payment`, `apply_credit`), `agent/invoice.php` (passa a usar as mesmas funções para mostrar os botões) e `lang/pt_BR/invoices.php` (a mensagem de recusa).
- **Banco:** nenhuma mudança de esquema nem de dados.
- **Uso normal pela tela:** nada muda; só pedidos que a tela não ofereceria passam a ser recusados.
- **API:** nada muda (`api/v1/invoices/` só tem leitura).
