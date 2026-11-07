# Proposal

## Why

Um chamado resolvido que volta para o atendimento por uma resposta continua com a data de resolução gravada. Com isso, o relógio de resolução do SLA fica parado num chamado aberto, e as listas, filtros, painéis e relatórios que perguntam "está resolvido?" pela data o tratam como resolvido. O problema foi o achado 1 da change `document-system-baseline`, reproduzido na resposta do cliente pelo portal; a investigação para esta change o reproduziu também na resposta do técnico.

## What Changes

- Todo caminho que tira um chamado do status Resolved passa a desfazer a resolução, como o botão Reabrir já faz: limpa a data de resolução, zera o resultado e o estágio de aviso da meta de resolução (salvo quando a meta já estourou e o saldo acabou) e volta a correr o relógio com o saldo restante. Os caminhos corrigidos são:
  - a resposta do cliente pelo portal;
  - a resposta do técnico com "Status após isto" diferente de Resolved;
  - a resposta em lote do técnico;
  - a resposta pela API com `ticket_status`.
- Os chamados que já estão nesse estado (fora de Resolved e de Closed, mas com data de resolução) são corrigidos uma vez, da mesma forma, pela tarefa de SLA.
- Os caminhos que já fazem o certo continuam iguais: resposta por e-mail, Reabrir do portal, Reabrir do link de convidado e Reabrir do técnico.

## Capabilities

### New Capabilities
<!-- Nenhuma. -->

### Modified Capabilities
- `ticketing`: o requisito "Resposta do cliente reabre o chamado resolvido" ganha o cenário do portal, e entra um requisito novo dizendo que sair de Resolved desfaz a resolução, por qualquer caminho, inclusive para os chamados que já estão inconsistentes.

## Impact

- **Código:** `client/post.php` (resposta pelo portal), `agent/post/ticket.php` (resposta e resposta em lote), `api/v1/ticket_replies/create.php`, um helper novo em `functions/ticket.php` e a correção pontual em `cron/ticket_sla.php`.
- **Banco:** nenhuma mudança de esquema. A correção dos dados existentes é feita pela tarefa de SLA, e não por migração, porque recalcular o prazo depende do horário comercial e do fuso da aplicação, que o `scripts/update_cli.php` não carrega.
- **API:** a resposta continua igual; muda só o efeito no chamado quando `ticket_status` tira um chamado de Resolved.
- **Relatórios e SLA:** chamados reabertos passam a contar como abertos e a consumir o prazo de resolução de novo, como já acontecia ao reabrir pelo botão.
