# Tasks

Os testes rodam no DDEV dentro de um snapshot do banco (`ddev snapshot --name antes-nightly-avisos`), recolocado ao final, com o snapshot apagado depois. A tarefa noturna roda à mão com `ddev exec php cron/nightly_tasks.php`, duas vezes seguidas. Na primeira execução, a tarefa grava tudo o que é do dia (multas, faturas recorrentes etc.); só a contagem de `notifications` é comparada entre as duas.

## 1. Helper

- [x] 1.1 Em `functions/logging.php`, criar `appNotifyOncePerDay($type, $details, $action = null, $client_id = 0, $entity_id = 0, $match_details = true)`, como em design.md D1 e D2. Levar o corte de tamanho e a proteção contra barra invertida final de `appNotify()` para uma função interna usada pelas duas, sem mudar o que `appNotify()` grava. Comentário dizendo quando usar cada uma. Validar: `ddev exec php -l functions/logging.php`; `grep -rn "appNotify(" --include=*.php | wc -l` igual antes e depois (nenhum chamador mudou de assinatura).

## 2. Tarefa noturna

- [x] 2.1 Em `cron/nightly_tasks.php`, trocar por `appNotifyOncePerDay()` os 12 avisos marcados "sim" na tabela de design.md: Domain Expiring, Certificate Expiring, API Key Expiring, Asset Warranty Expiring, UPS Battery Due, Service Review, Pending Tickets, Invoice Overdue, Invoice (recorrente atrasada), Draft Invoices, Expense (recorrente atrasada) e Update. Os três resumos (Pending Tickets, Draft Invoices, Update) levam `false` no último argumento. Validar: `ddev exec php -l cron/nightly_tasks.php`; `grep -n "appNotify" cron/nightly_tasks.php` mostra só os avisos de acontecimento (white label, multa, fatura paga, despesa registrada) ainda em `appNotify()`.

## 3. Verificação

- [x] 3.1 Num snapshot, preparar dados que disparam os avisos: um chamado em New, um rascunho de fatura, uma fatura enviada vencida há 1 dia (com lembrete depois do vencimento ligado nas configurações de fatura), um domínio vencendo em 7 dias, dois ativos do mesmo cliente com garantia vencendo em 7 dias, e `config_update_latest_commit` diferente do commit atual. Rodar a tarefa noturna duas vezes. Validar com `SELECT notification_type, COUNT(*) FROM notifications WHERE notification_timestamp >= CURDATE() GROUP BY notification_type`: depois da segunda execução, as contagens não mudam; os dois ativos geram dois avisos de garantia por técnico; o e-mail de lembrete ao cliente continua um só (fila de e-mail e histórico da fatura).
- [x] 3.2 Casos de borda no mesmo snapshot:
  - dispensar um aviso como técnico, mudar a contagem de chamados New (criar mais um) e rodar de novo: nada é recriado, nem o aviso dispensado, nem o resumo com outra contagem;
  - ativar um técnico novo (ou reativar um arquivado) e rodar de novo: só ele recebe os avisos do dia.

  Validar pelas mesmas contagens, por `notification_user_id`. Depois, recolocar o snapshot e apagá-lo.
- [x] 3.3 Com uma execução só (snapshot novo ou o banco recolocado), conferir que a primeira execução do dia continua criando todos os avisos, como antes. Validar: mesmas contagens da primeira execução da 3.1.

## 4. Fechamento

- [x] 4.1 Entrada em `CHANGELOG.md`, em Bug Fixes, para administradores: rodar a tarefa noturna de novo no mesmo dia (Run Now) não repete mais os avisos internos, como fatura vencida, chamados aguardando atribuição, atualização disponível e vencimentos; e-mails a clientes já não se repetiam. Validar: leitura da entrada.
- [x] 4.2 Atualizar o achado de `scheduled-jobs/invoicing` em `openspec/changes/archive/2026-11-06-document-system-baseline/tasks.md` com o nome desta change. Rodar `ddev exec php scripts/i18n_check.php pt_BR` (nenhum texto novo é esperado) e `openspec validate fix-nightly-duplicate-notifications --strict`. Validar: sem pendências nem erros.

## Workflow follow-up

- Arquivar a change (`/opsx:archive`) para levar os cenários novos para `openspec/specs/scheduled-jobs/spec.md`.
