# Tasks

**Ordem:** implementar depois de `fix-nightly-duplicate-notifications`, ou rebasear sobre ela, porque as duas mexem em `cron/nightly_tasks.php`.

**Como testar:** os testes rodam no DDEV dentro de um snapshot do banco (`ddev snapshot --name antes-autopay-<tarefa>`), recolocado ao final com `ddev snapshot restore` e apagado com `ddev snapshot --cleanup`. Os dados de teste são uma recorrência do Cliente Teste com uma fatura Sent vencendo hoje, mais uma linha em `recurring_payments`. Para o caso antigo, a linha não tem cartão (`recurring_payment_saved_payment_id` NULL, método "Cash"). Para o caso de cartão inutilizável, ela aponta para um cartão salvo de um provedor com `payment_provider_active = 0`. A tarefa noturna roda com `ddev exec php cron/nightly_tasks.php`.

## 1. Tarefa noturna

- [x] 1.1 Criar em `functions/billing_pay.php` o helper que registra uma cobrança automática que falhou: histórico `Payment failed` com descrição começando por "Autopay not charged" (ou o texto de recusa do Stripe), audit log em inglês e `appNotify("Invoice", ...)` traduzível, com o número da fatura, o motivo e o link `/agent/invoice.php?invoice_id=`. Validar: `ddev exec php -l functions/billing_pay.php`.
- [x] 1.2 Em `cron/nightly_tasks.php`, remover o ramo que grava "Paid via AutoPay". Fazer os casos "sem cartão", "cartão não encontrado ou provedor inativo", "provedor não suportado" e "dados do Stripe incompletos" chamarem o helper. Fazer a exceção e o status diferente de `succeeded` também chamarem o helper. Pular a fatura se `cronInvoiceHistoryToday()` já achar "Autopay not charged" ou "Stripe autopay failed" no dia. Validar:
  - `php -l`;
  - num snapshot, com a linha sem cartão: rodar a tarefa e conferir que não há pagamento novo, a fatura continua Sent, há uma linha nova no histórico e uma notificação;
  - rodar de novo e conferir que nada novo aparece;
  - repetir com o cartão de provedor inativo.

## 2. O que conta como cobrança automática

- [x] 2.1 Na consulta dos lembretes (`cron/nightly_tasks.php`, coluna `autopay`), em `billingReminderAutopay()` (`functions/billing_email.php`) e no ícone de `agent/recurring_invoices.php`, exigir cartão salvo do cliente com provedor ativo. Validar:
  - `php -l` nos três arquivos;
  - num snapshot, com a linha sem cartão e um lembrete configurado para o dia do vencimento: a tarefa envia o lembrete (aparece no Mailpit);
  - a lista de recorrências não mostra o ícone de carteira para essa recorrência;
  - `agent/invoice.php` da fatura mostra o próximo lembrete.
- [x] 2.2 Em `agent/recurring_invoice.php`, quando existe linha em `recurring_payments` sem cartão utilizável, mostrar no cartão de cobrança automática um aviso de que ela não cobra nada e um botão "Turn off" (post `set_recurring_payment` com `saved_payment_id=0`), só para quem pode editar. Textos em `__()`, com tradução em `lang/pt_BR/`. Validar:
  - `php -l`;
  - num snapshot, abrir a recorrência com a linha sem cartão, ver o aviso, clicar no botão e conferir que a linha sumiu e que há registro no audit log;
  - um técnico sem permissão de edição em vendas não vê o botão.

## 3. Fechamento

- [x] 3.1 Entrada em `CHANGELOG.md`, para administradores:
  - em Bug Fixes: a cobrança automática sem cartão marcava a fatura como paga sem receber nada, e agora deixa a fatura em aberto e avisa;
  - toda cobrança automática que falha passa a avisar a equipe;
  - em Upgrading: como achar pagamentos antigos com referência "Paid via AutoPay" para conferir, e como desligar a cobrança antiga pela página da recorrência.

  Validar: leitura da entrada.
- [x] 3.2 Rodar `ddev exec php scripts/i18n_check.php pt_BR` (sem pendências) e `ddev exec php scripts/i18n_check.php pt_BR --hardcoded agent/recurring_invoice.php agent/recurring_invoices.php functions/billing_pay.php` (sem nada novo). Atualizar o achado de online-payments em `openspec/changes/archive/2026-11-06-document-system-baseline/tasks.md` com o nome desta change. Rodar `openspec validate fix-autopay-without-card --strict`. Validar: os três comandos sem erros.

## Workflow follow-up

- Arquivar a change (`/opsx:archive`) para levar os cenários novos para `openspec/specs/online-payments/spec.md` e `openspec/specs/invoicing/spec.md`.
