# Design

## Context

"Resolvido" é guardado em dois lugares: o status (4, Resolved) e a data `ticket_resolved_at`. Quase todo o resto lê a data: o relógio de resolução (`syncTicketSlaClock()` só corre com a data vazia), o fechamento automático, o portal, os filtros, o painel e os relatórios (44 consultas com `ticket_resolved_at IS NULL` ou `IS NOT NULL`). Ver proposal.md, "Why".

Os caminhos que tiram um chamado de Resolved fazem isto hoje:

| Caminho | Limpa a data | Reinicia a meta | Volta o relógio |
|---|---|---|---|
| Reabrir do técnico (`ticketReopen()`, `functions/ticket.php`) | sim | sim | sim |
| Reabrir do portal (`client/post.php`, `reopen_ticket`) | sim | sim | sim |
| Reabrir do link de convidado (`guest/guest_post.php`) | sim | sim | sim |
| Resposta por e-mail (`cron/ticket_email_parser.php`, `addReply()`) | sim | sim | sim |
| **Resposta pelo portal** (`client/post.php`, `add_ticket_comment`) | não | não | não (a data ainda está lá) |
| **Resposta do técnico** (`agent/post/ticket.php`, `add_ticket_reply`) | não | não | não |
| **Resposta em lote** (`agent/post/ticket.php`, `bulk_ticket_reply`) | não | não | não |
| **Resposta pela API** (`api/v1/ticket_replies/create.php`) | não | não | não (nem chama `syncTicketSlaClock()`) |

A resposta do técnico oferece em "Status após isto" todos os status ativos menos New e Closed (`ticketReplyStatuses()`), então Open e On Hold aparecem num chamado resolvido. A troca de status pelo menu (`ticketChangeStatus()`) recusa chamados resolvidos e não entra aqui.

`resetTicketResolutionSla()` (`functions/sla.php`) já faz a parte do SLA: zera `ticket_resolution_sla_met` e o estágio de aviso, a menos que a meta tenha estourado com o saldo esgotado (aí mantém a violação, para o cron não avisar de novo), e chama `syncTicketSlaClock()`, que abre um novo intervalo e recalcula o prazo com o saldo restante.

## Goals / Non-Goals

**Goals:**
- Um único ponto que desfaz a resolução, chamado pelos quatro caminhos com defeito.
- Correção única dos chamados que já estão inconsistentes, sem migração de banco.

**Non-Goals:**
- Mudar os quatro caminhos que já funcionam. Eles continuam com o próprio código; passar todos para o helper novo seria só refatoração, sem mudança de comportamento, e fica para outra change se valer a pena.
- Mudar quais status a resposta do técnico oferece num chamado resolvido.
- Mudar o texto do histórico ou do audit log dos caminhos corrigidos: a resposta do técnico continua registrando "set the status to Open", e não "reopened".
- Tratar a duplicidade entre status e data (por exemplo, derivar "resolvido" só do status). Seria uma mudança de modelo bem maior.

## Decisions

### D1. Um helper, chamado depois da troca de status
`ticketUnresolve($ticket_id)` em `functions/ticket.php`: apaga `ticket_resolved_at` do chamado (só se ele não estiver fechado) e chama `resetTicketResolutionSla()`. Cada caminho chama o helper quando o status de antes era Resolved e o novo não é Resolved nem Closed, depois do `UPDATE` de status que já faz.
- *Por quê:* deixa o UPDATE de cada caminho como está e põe a regra num só lugar, com o mesmo efeito do Reabrir.
- *Alternativa descartada:* repetir `ticket_resolved_at = NULL` e `resetTicketResolutionSla()` em cada um dos quatro lugares, como fazem os caminhos que funcionam. Funciona, mas é exatamente a repetição que deixou quatro caminhos esquecidos.
- *Alternativa descartada:* usar `ticketReopen()`. Ele grava histórico e audit log próprios ("reopened the ticket") e dispara a ação personalizada `ticket_update`, o que duplicaria os registros que cada caminho já faz.

### D2. A correção dos dados existentes fica na tarefa de SLA, não numa migração
`cron/ticket_sla.php` ganha, antes das trilhas de resposta e resolução, uma consulta pelos chamados com status diferente de Resolved, sem data de fechamento e com data de resolução, e chama `ticketUnresolve()` em cada um, com uma linha no histórico do chamado.
- *Por quê:* recalcular o prazo usa horário comercial e o fuso da aplicação, que o `scripts/update_cli.php` não carrega. É o mesmo motivo pelo qual a tarefa de SLA já reconcilia relógios (comentário em `cron/ticket_sla.php`). Depois da primeira passada a consulta não encontra mais nada, e ela continua servindo de rede de segurança para algum caminho que ainda escape.
- *Alternativa descartada:* migração `admin/database_updates/3.5.7.php` só com SQL, apagando a data. Deixaria o relógio parado e o prazo antigo, o que faria o chamado estourar na hora.

### D3. Reabrir pela resposta do cliente continua indo para o status que já ia
O portal continua usando `ticketStatusAfterClientReply()`; só passa a chamar o helper quando o status de antes era Resolved. A spec já descreve esse destino.

## Risks / Trade-offs

- **[Risco] Os chamados corrigidos ganham o prazo de volta a partir da correção, e não da reabertura.** O tempo entre a reabertura e a primeira passada da tarefa não conta, porque nenhum intervalo do relógio estava aberto. → Aceito: não há como saber, pelo banco, quando a reabertura aconteceu sem ler o histórico; a passada roda no primeiro minuto depois da atualização e a perda é desse período, uma vez só.
- **[Risco] Relatórios de períodos passados mudam.** Um chamado reaberto deixa de contar como resolvido na data antiga. → É o comportamento correto (o mesmo do Reabrir) e entra no CHANGELOG.
- **[Risco] A correção roda só com o cron ligado.** → Sem cron, o SLA inteiro já não funciona. Os caminhos corrigidos passam a gravar certo de qualquer forma.
- **[Trade-off] Uma consulta a mais por minuto na tarefa de SLA.** Ela filtra por colunas da própria tabela de chamados e, depois da primeira passada, não devolve nada.

## Migration Plan

Sem mudança de esquema. A atualização de código entrega o helper e os quatro caminhos corrigidos; a tarefa de SLA corrige os chamados existentes no primeiro minuto em que rodar. Para desfazer, basta reverter o código. Os chamados já corrigidos ficam corretos, porque estão no mesmo estado de um chamado reaberto pelo botão.
