# Tasks

## 1. Spec de base e confirmação por e-mail

- [x] 1.1 Conferir no código cada requisito de `specs/ticket-emails/spec.md` (`functions/ticket.php`, `cron/ticket_email_parser.php`) e corrigir a spec onde divergir; verificar relendo cada cenário contra a função citada
- [x] 1.2 Confirmar que a tradução já aplicada em `lang/pt_BR/tickets.php` (confirmação de chamado criado por e-mail) bate com o cenário "Confirmação em português"; verificar com `ddev exec php -l lang/pt_BR/tickets.php` e `ddev exec php scripts/i18n_check.php pt_BR` sem pendências
- [x] 1.3 Disparar a prévia do e-mail de confirmação (`functions/email_preview.php`) ou criar um chamado por e-mail de teste e ler no Mailpit (`ddev mailpit`); verificar título, texto com o número TCK e assunto com `[TCK-n]`

## 2. Textos dos demais e-mails ao cliente

- [x] 2.1 Receber do usuário o texto novo de cada e-mail a mudar (resposta do técnico, resolvido com aviso de fechamento, rótulos do resumo) e anotá-los na seção Decisions do design; verificar com o usuário que a lista está completa
- [x] 2.2 Antes de trocar as chaves genéricas (`Ticket`, `Subject`, `Priority`, `Status`), listar onde aparecem com `grep` em `agent/ client/ includes/ functions/`; verificar que a troca não afeta telas fora do e-mail, ou usar `_x()` com contexto de e-mail
- [x] 2.3 Aplicar as traduções aprovadas em `lang/pt_BR/tickets.php` (e `lang/pt_BR.php` para o assunto de fechado), mantendo todos os marcadores `%s` e a referência `[%1$s]` nos assuntos; verificar com `ddev exec php -l` e `ddev exec php scripts/i18n_check.php pt_BR` sem divergência de marcador
- [x] 2.4 Conferir cada e-mail alterado na prévia do sistema (`ticket_reply`, `ticket_resolved` com e sem fechamento automático, `ticket_closed`) e verificar que o assunto continua com `[TCK-n]`; a ligação de uma resposta recebida por e-mail ao chamado (leitor de e-mails) não foi alterada e não foi testada aqui, por exigir uma caixa de suporte real

## 3. Documentação

- [x] 3.1 Registrar em `CHANGELOG.md`, na seção da versão em andamento (Bug Fixes ou New Features & Updates), que os textos em português dos e-mails de chamado mudaram, em linguagem para administradores; verificar a entrada no formato das vizinhas

## Workflow follow-up

- Arquivar o change depois da revisão (`/opsx:archive`), para a spec `ticket-emails` virar spec principal.
