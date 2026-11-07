# Tasks

Os testes manuais rodam no DDEV dentro de um snapshot (`ddev snapshot --name antes-<tarefa>`), recolocado ao final. O chamado de teste é um do Cliente Teste posto em Resolved com `ticket_resolved_at` preenchido. Para ver o relógio do SLA, o SLA da prioridade precisa ter meta de resolução (`sla_resolution_minutes`); os SLAs semeados não têm, então a tarefa 3.1 define uma.

## 1. Helper

- [x] 1.1 Criar `ticketUnresolve($ticket_id)` em `functions/ticket.php`, perto de `ticketResolve()`/`ticketReopen()`: apaga `ticket_resolved_at` só de chamado não fechado e chama `resetTicketResolutionSla()`; comentário dizendo quando chamar (status saiu de Resolved para algo que não é Closed). Validar: `ddev exec php -l functions/ticket.php`.

## 2. Caminhos corrigidos

- [x] 2.1 Resposta pelo portal (`client/post.php`, bloco `add_ticket_comment`): chamar `ticketUnresolve()` quando o status de antes era `TICKET_STATUS_RESOLVED`. Validar: `php -l`; como contato no portal, responder a um chamado resolvido e conferir no banco status 2 e `ticket_resolved_at` NULL; o chamado aparece entre os abertos no portal e na lista do técnico.
- [x] 2.2 Resposta do técnico (`agent/post/ticket.php`, `add_ticket_reply`): chamar `ticketUnresolve()` quando o chamado sai de Resolved para outro status que não Closed. Validar: `php -l`; responder a um chamado resolvido escolhendo Open (data NULL, relógio aberto em `sla_history`) e escolhendo On Hold (data NULL, relógio parado); responder mantendo Resolved não mexe na data.
- [x] 2.3 Resposta em lote (`agent/post/ticket.php`, `bulk_ticket_reply`): a mesma regra por chamado. Validar: `php -l`; resposta em lote com Open sobre um chamado resolvido e um aberto, e conferir que só o resolvido muda de data.
- [x] 2.4 API (`api/v1/ticket_replies/create.php`): chamar `syncTicketSlaClock()` depois da troca de status, como os outros caminhos, e `ticketUnresolve()` quando sai de Resolved. Validar: `php -l`; criar uma chave de API para o técnico de teste e enviar uma resposta com `ticket_status=2` a um chamado resolvido (curl), conferindo a data NULL e o relógio aberto.

## 3. Chamados já inconsistentes

- [x] 3.1 Em `cron/ticket_sla.php`, antes das trilhas de resposta e resolução, selecionar os chamados com status diferente de Resolved, sem `ticket_closed_at` e com `ticket_resolved_at`, e chamar `ticketUnresolve()` em cada um, com uma linha no histórico do chamado (em inglês, como os demais registros). Validar: `php -l`; num snapshot, deixar um chamado Open com data de resolução e meta de resolução definida, rodar `ddev exec php cron/cron.php` com Run Now em `ticket_sla` e conferir a data NULL, o intervalo novo em `sla_history`, o prazo de resolução recalculado e a linha no histórico; rodar de novo e conferir que nada muda; um chamado fechado com data de resolução não é tocado.
- [x] 3.2 Meta estourada: num snapshot, chamado resolvido com `ticket_resolution_sla_met = 0`, estágio 2 e saldo esgotado; tirar de Resolved por uma resposta do técnico e rodar a tarefa de SLA duas vezes. Validar: a violação continua registrada e nenhuma notificação nova de violação aparece.

## 4. Fechamento

- [x] 4.1 Entrada em `CHANGELOG.md`, em Bug Fixes, em linguagem para administradores: chamados reabertos por resposta (portal, técnico, em lote, API) deixavam de contar como abertos e com o prazo de SLA parado; os que já estavam assim são corrigidos sozinhos, e relatórios de períodos passados podem mudar para eles. Validar: leitura da entrada.
- [x] 4.2 Conferir o resto: os caminhos que já funcionavam (resposta por e-mail, Reabrir do portal, do link de convidado e do técnico) continuam iguais, revisando o diff; `ddev exec php scripts/i18n_check.php pt_BR` sem pendências, se algum texto de interface tiver mudado; `openspec validate fix-ticket-leave-resolved --strict` sem erros.

## Workflow follow-up

- Arquivar a change (`/opsx:archive`) para levar o cenário do portal e os dois requisitos novos para `openspec/specs/ticketing/spec.md`.
- Atualizar o achado 1 em `openspec/changes/archive/2026-11-06-document-system-baseline/tasks.md` com o nome desta change.
