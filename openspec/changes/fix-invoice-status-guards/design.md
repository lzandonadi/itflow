# Design

## Context

Ver proposal.md, "Why". Onde o status de uma fatura muda hoje:

| Caminho | Regra na tela | Checagem no servidor |
|---|---|---|
| Cancelar (`agent/post/invoice.php:246`, `cancel_invoice`) | fora de Cancelled, Paid e Non-Billable (`agent/invoice.php:254`) | nenhuma; grava Cancelled e devolve ao estoque o que a fatura tirou (`productStockReturnInvoice()`) |
| Non-Billable (`agent/post/invoice.php:213`) | Sent ou Viewed com total zero (`agent/invoice.php:202`) | nenhuma |
| Marcar como enviada (`agent/post/invoice.php:165`, `mark_invoice_sent`) | só Draft (`agent/invoice.php:156`) | nenhuma; uma fatura Paid ou Cancelled volta para Sent |
| Registrar pagamento (`agent/post/payment.php:13`, `add_payment`) | `invoiceCanPay()`: Sent, Viewed ou Partial com saldo | nenhuma de status; o saldo comparado vem do próprio formulário |
| Aplicar crédito (`agent/post/payment.php:130`, `apply_credit`) | o modal `invoice_apply_credit.php` não é aberto por nenhuma tela | nenhuma de status |
| Envio por e-mail (`agent/post/invoice.php:422`) | — | só um Draft vira Sent |
| Pagamento em lote (`agent/post/payment.php:399`) | — | filtra Sent, Viewed, Partial |
| Cartão salvo pela tela (`agent/post/payment.php:240`) | — | só Sent ou Viewed (`:310`) |
| Pagamento pelo link do cliente (`guest/guest_pay_invoice_stripe.php`) | — | fora de Draft, Paid, Cancelled |
| Excluir pagamento (`functions/payments.php:30`) | — | recalcula, mas mantém Draft e Cancelled |
| API (`api/v1/invoices/`) | — | só leitura |
| Tarefa noturna (cobrança automática, `cron/nightly_tasks.php:724`, `:756`) | — | fica para a change do achado de cobrança automática |

Não há ação de status em lote para faturas (o único `bulk_*` de fatura troca a categoria). Uma fatura cancelada mantém hoje os pagamentos que já recebeu: eles continuam registrados e contando como recebidos, e excluir um deles não tira a fatura de Cancelled.

## Goals / Non-Goals

**Goals:**
- Cada handler da tabela acima sem checagem passa a recusar o pedido fora da regra da tela.
- A tela e o servidor usam a mesma função para cada regra.

**Non-Goals:**
- Mudar as regras em si. Por exemplo, continuar permitindo cancelar uma fatura Partial, que fica Cancelled com os pagamentos registrados, como hoje (ver Open Questions).
- Os outros defeitos de `apply_credit`: o total da fatura é lido como zero (`floatval('invoice_amount')`), a gravação do crédito usa uma variável inexistente (`$amount`) e o modal não é aberto por nenhuma tela. Esta change só impede que ele mude o status de uma fatura fora da regra; consertar ou remover a ação fica para um achado próprio.
- O saldo de `add_payment` vir do formulário (um saldo inflado no POST deixa pagar mais que o devido). Fica registrado como achado separado.
- A cobrança automática da tarefa noturna, tratada em outra change.
- Os pagamentos online pelo link do cliente aceitarem uma fatura Non-Billable: ela tem total zero, então não há o que pagar.

## Decisions

### D1. Funções de regra em `functions/billing.php`, usadas pela tela e pelos handlers
`invoiceCanCancel($status)`, `invoiceCanMarkNonBillable($row)` e `invoiceCanMarkSent($status)`, ao lado de `invoiceCanPay($row, $state)`, que já existe e passa a ser usada também por `add_payment` e `apply_credit`. `agent/invoice.php` troca as condições escritas nas linhas dos botões por essas funções.
- *Por quê:* o defeito é a regra existir só na tela. Com uma função só, mudar a regra muda os dois lados.
- *Alternativa descartada:* repetir a lista de status em cada handler. Corrige agora, mas é a mesma duplicação que deixou os handlers sem regra.

### D2. Recusa: nada muda, aviso de erro, volta para a página
O handler carrega o status (e, para Non-Billable, o total; para pagamento, o saldo calculado com `invoiceState()`), e se a regra não permite faz `flashAlert(..., 'error')` e `redirect()` antes de qualquer gravação. A mensagem é uma só, traduzível: "A fatura %s não está mais num status que permita isso."
- *Por quê:* é o padrão dos handlers do projeto (como "That invoice no longer exists."), e o caso real mais comum é uma aba antiga depois de outra pessoa mudar a fatura.
- *Alternativa descartada:* recusar com 403 (`permissionRefuse()`). O usuário tem a permissão; o que mudou foi a fatura.

### D3. A checagem e a gravação ficam separadas, sem `WHERE` condicional
A checagem lê o status e decide; o `UPDATE` continua como está.
- *Por quê:* a corrida entre duas pessoas mudando a mesma fatura no mesmo segundo é rara, e um `UPDATE ... WHERE invoice_status IN (...)` exigiria tratar "nenhuma linha afetada" em cada handler e no estoque. Fica registrado como risco aceito.

## Risks / Trade-offs

- **[Risco] Alguém dependia de cancelar uma fatura paga para "estornar".** → A tela nunca ofereceu isso; o caminho certo é excluir ou estornar o pagamento, que recalcula o status. A entrada do CHANGELOG explica.
- **[Risco] Corrida entre a checagem e o `UPDATE`.** → Aceito (D3); a janela é de milissegundos e as duas ações seriam legítimas um instante antes.
- **[Trade-off] `apply_credit` continua quebrado.** → Só ganha a mesma regra de status; o resto fica como achado.

## Open Questions

- Cancelar uma fatura com pagamento parcial deve continuar permitido? Hoje a tela permite, e a fatura fica Cancelled com os pagamentos registrados. Esta change mantém isso; mudar seria uma regra nova, numa change própria, sem alterar o que se constrói aqui.
