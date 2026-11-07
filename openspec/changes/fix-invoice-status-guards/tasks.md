# Tasks

Os testes rodam no DDEV dentro de um snapshot (`ddev snapshot --name antes-invoice-guards`), recolocado ao final. O banco tem só duas faturas (uma Sent, uma Partial). A tarefa 3.1 cria pela tela as que faltam: Draft, Paid, Cancelled, uma Sent de total zero e uma Sent com produto de estoque. Os pedidos fora da regra são montados à mão com `curl`, com o token CSRF tirado de `agent/tickets.php`, como admin (Lucas) e como o técnico de teste (`tecnico@itsm.local`).

## 1. Regras

- [ ] 1.1 Em `functions/billing.php`, ao lado de `invoiceCanPay()`, criar `invoiceCanCancel($status)` (Draft, Sent, Viewed, Partial), `invoiceCanMarkNonBillable($row)` (Sent ou Viewed com total zero) e `invoiceCanMarkSent($status)` (Draft), com um comentário dizendo que a tela e os handlers usam as mesmas funções. Validar: `ddev exec php -l functions/billing.php`.
- [ ] 1.2 Em `agent/invoice.php`, trocar as condições dos botões Marcar como enviada, Non-Billable e Cancelar por essas funções, sem mudar quando cada botão aparece. Validar: `php -l`; abrir uma fatura Draft, uma Sent e uma Paid e conferir que os botões são os mesmos de antes.

## 2. Handlers

- [ ] 2.1 `agent/post/invoice.php`: em `cancel_invoice`, `mark_invoice_non-billable` e `mark_invoice_sent`, ler o status (e o total, para Non-Billable) e recusar fora da regra com `flashAlert(__('Invoice %s is no longer in a status that allows this.', ...), 'error')` e `redirect()` antes de qualquer gravação. Traduzir a mensagem em `lang/pt_BR/invoices.php`. Validar: `php -l`; `ddev exec php scripts/i18n_check.php pt_BR` sem pendências.
- [ ] 2.2 `agent/post/payment.php`: em `add_payment` e `apply_credit`, recusar quando `invoiceCanPay()` (com o saldo calculado no servidor) não permite, antes de gravar pagamento ou crédito. Validar: `php -l`.

## 3. Verificação

- [ ] 3.1 Num snapshot, preparar as faturas de teste pela tela (Draft, Paid, Cancelled, Sent de total zero, Sent com produto de estoque) e anotar status, histórico, estoque e pagamentos de cada uma. Validar: consulta no banco com os valores de antes.
- [ ] 3.2 Pedidos fora da regra, com `curl`: cancelar a Paid e a Cancelled; Non-Billable na Sent com valor e na Paid; marcar como enviada a Paid e a Cancelled; pagamento e crédito na Cancelled e na Draft. Validar: cada um volta com o aviso de erro e nada muda no banco (status, `history`, `product_stock`, `payments`, `credits`).
- [ ] 3.3 Pedidos dentro da regra continuam funcionando, pela tela: cancelar a Sent com produto (estoque devolvido), cancelar a Partial (pagamentos continuam), Non-Billable na Sent de total zero, marcar como enviada a Draft, registrar pagamento na Sent. Validar: status, histórico e estoque como antes da mudança. Recolocar o snapshot no fim.

## 4. Fechamento

- [ ] 4.1 Entrada em `CHANGELOG.md`, em Bug Fixes, para administradores: cancelar, marcar Non-Billable, marcar como enviada e registrar pagamento passam a ser recusados quando a fatura já não está num status que permita a ação (por exemplo, cancelar uma fatura paga por um link antigo), como a tela já mostrava. Validar: leitura da entrada.
- [ ] 4.2 Atualizar o achado de invoicing em `openspec/changes/archive/2026-11-06-document-system-baseline/tasks.md` com o nome desta change e registrar ali, como achados novos, os defeitos de `apply_credit` (total lido como zero, variável inexistente, modal sem tela) e o saldo de `add_payment` vindo do formulário. Rodar `openspec validate fix-invoice-status-guards --strict`. Validar: sem erros.

## Workflow follow-up

- Arquivar a change (`/opsx:archive`) para levar o requisito novo para `openspec/specs/invoicing/spec.md`.
