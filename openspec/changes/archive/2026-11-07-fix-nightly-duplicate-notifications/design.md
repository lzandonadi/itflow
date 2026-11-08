# Design

## Context

Ver proposal.md, "Why". `appNotify()` (`functions/logging.php:22`) grava uma linha em `notifications` para cada técnico ativo (`user_type = 1`, ativo, não arquivado), sem olhar se o mesmo aviso já existe. Dispensar um aviso só preenche `notification_dismissed_at` (`functions/notifications.php:209`, `:225`); a linha continua lá até a limpeza de 90 dias (`cron/nightly_tasks.php:163`). A tarefa noturna já protege os efeitos que mexem com dinheiro ou com o cliente: `cronInvoiceHistoryToday()` (`cron/nightly_tasks.php:119`) verifica o histórico do dia antes da multa e do lembrete por e-mail, e o extrato verifica o log de auditoria do dia. Os avisos internos não têm proteção nenhuma.

Avisos de `cron/nightly_tasks.php` e o que acontece numa segunda execução no mesmo dia:

| Linha | Tipo | Escolhido por | Repete hoje? |
|---|---|---|---|
| 225 | Settings (white label desligado) | chave inválida; a própria execução desliga | não |
| 275 | Domain Expiring | `domain_expire = CURDATE() + N` | **sim** |
| 318 | Certificate Expiring | `certificate_expire = CURDATE() + N` | **sim** |
| 334 | API Key Expiring | `api_key_expire = CURDATE() + 7` | **sim** |
| 358 | Asset Warranty Expiring | `asset_warranty_expire = CURDATE() + N` | **sim** |
| 376 | UPS Battery Due | data da troca `= CURDATE() + N` | **sim** |
| 390 | Service Review | `service_review_due = CURDATE()` | **sim** |
| 401 | Pending Tickets | existe chamado New (resumo com contagem) | **sim** |
| 507 | Invoice Late Charge | só quando a multa é aplicada, já protegida | não |
| 518 | Invoice Overdue | dia de lembrete das regras de cobrança | **sim** |
| 591 | Invoice (recorrente atrasada) | `next_date < CURDATE()` | **sim** |
| 601 | Draft Invoices | existe rascunho (resumo com contagem) | **sim** |
| 740 | Invoice Paid | pagamento automático; a fatura sai da seleção | não |
| 781 | Expense Created | registro da recorrência; a data avança | não |
| 789 | Expense (recorrente atrasada) | `next_date = CURDATE() - (carência + 1)` | **sim** |
| 1058 | Update | commit remoto diferente do atual (resumo) | **sim** |

Os avisos das outras tarefas diárias dependem de uma mudança de estado e não se repetem: problema de certificado (`cron/certificate_refresher.php:78`, só quando o estado passa de `ok` para erro), contrato encerrado e lembrete de contrato (`contractsNightly()`, que grava o status e `contract_reminded_at`). Os avisos de backup e de atualização aplicada valem para cada execução, porque cada uma produz um backup ou uma atualização nova.

Os e-mails a clientes já estão protegidos: lembrete (`cronInvoiceHistoryToday(..., 'Reminder emailed')` e equivalentes), extrato (log `Client`/`Statement` do dia) e multa.

O PHP e a sessão do banco usam o mesmo fuso (`includes/inc_set_timezone.php`), então `CURDATE()` e `notification_timestamp` falam do mesmo dia, como já acontece em `cronInvoiceHistoryToday()`.

## Goals / Non-Goals

**Goals:**
- Os 12 avisos marcados "sim" na tabela, criados no máximo uma vez por dia para cada técnico.
- Nenhuma mudança de esquema.

**Non-Goals:**
- Mudar o texto, o tipo ou o link de algum aviso.
- Deduplicar avisos fora da tarefa noturna, ou os avisos de acontecimento listados como "não" na tabela. Eles continuam com `appNotify()`.
- Repetir um aviso diário de propósito: a fatura recorrente atrasada e os resumos continuam aparecendo todo dia, uma vez.
- Mudar como `appNotify()` funciona para os outros chamadores (telas, API, outras tarefas).

## Decisions

### D1. Um helper novo ao lado de `appNotify()`, por técnico
`appNotifyOncePerDay($type, $details, $action, $client_id, $entity_id, $match_details = true)` em `functions/logging.php`:
1. busca, numa consulta, os técnicos que já têm hoje (`notification_timestamp >= CURDATE()`) uma linha com o mesmo `notification_type`, `notification_action`, `notification_client_id` e `notification_entity_id`, e com o mesmo `notification` quando `$match_details` é verdadeiro;
2. grava o aviso só para os técnicos ativos que não estão nessa lista.

Ele recebe os valores já escapados, como `appNotify()`, e aplica o mesmo corte de tamanho e a mesma proteção contra barra invertida no final. Para isso, essa parte de `appNotify()` passa para uma função interna que as duas usam, sem mudar o comportamento de `appNotify()`.
- *Por quê:* a checagem por técnico atende o técnico ativado entre as execuções, e a linha dispensada continua contando. O helper fica em `functions/`, como pede a regra de não declarar funções nas tarefas de cron; `cronInvoiceHistoryToday()` é uma exceção antiga e não serve de modelo para algo novo.
- *Alternativa descartada:* checar se existe qualquer linha do aviso hoje, sem olhar o técnico. É mais simples, mas o técnico ativado entre as execuções ficaria sem o aviso do dia.
- *Alternativa descartada:* gravar um registro no histórico ou no log por aviso, como faz a multa. Duplica a informação que a própria tabela `notifications` já tem.
- *Alternativa descartada:* uma tabela ou coluna nova, como uma chave do aviso. Exigiria esquema e migração para algo que dá para consultar com os dados atuais.

### D2. O que é "o mesmo aviso"
- **Avisos sobre um item** comparam tipo, link, cliente, entidade e texto (`$match_details = true`). O texto entra porque alguns links não identificam o item sozinhos: a garantia usa `/agent/assets.php?client_id=X` para todos os ativos do cliente, e a despesa atrasada usa `/agent/recurring_expenses.php?status=late` para todas. Com o texto na comparação, dois ativos do mesmo cliente continuam gerando dois avisos.
- **Resumos** ("Pending Tickets", "Draft Invoices", "Update") comparam só tipo, link, cliente e entidade (`$match_details = false`). A contagem de chamados ou rascunhos, ou a versão, pode mudar entre as execuções, mas o recado do dia já foi dado.
- **"Invoice Overdue"** é um aviso sobre um item. Se o saldo mudou entre as execuções (um pagamento parcial), o texto muda e o aviso sai de novo, com o saldo novo. Isso é aceitável, porque é informação nova e acontece só se alguém mexer na fatura no meio do dia.
- *Alternativa descartada:* comparar sempre sem o texto. Juntaria os avisos de garantia de ativos diferentes do mesmo cliente.

### D3. Troca pontual em `cron/nightly_tasks.php`
Os 12 avisos da tabela passam de `appNotify(...)` para `appNotifyOncePerDay(...)`, com os mesmos argumentos, e os três resumos recebem `false` no último argumento. Os outros avisos da tarefa ficam como estão.

## Risks / Trade-offs

- **[Risco] Custo da consulta.** São uma consulta a mais por aviso, na tabela `notifications`, que tem índice por técnico (`notification_user_id`, `notification_dismissed_at`) mas não por data ou tipo. → É uma consulta por aviso, não por técnico, uma vez por noite, sobre uma tabela que a limpeza de 90 dias mantém pequena. Se precisar de índice, ele fica para outra change.
- **[Risco] Idioma diferente entre as execuções.** Os resumos e o aviso de fatura vencida são gerados no idioma do momento. Se o idioma da interface mudar entre as duas execuções, o texto muda. → Os resumos não comparam o texto. No aviso de fatura vencida, o aviso sairia de novo no idioma novo, o que é raro e inofensivo.
- **[Trade-off] Aviso dispensado não volta no mesmo dia.** É o objetivo: o técnico já viu o aviso.

## Migration Plan

Sem mudança de esquema. Para desfazer, basta reverter o código.
