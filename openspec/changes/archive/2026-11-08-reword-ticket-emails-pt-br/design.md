# Design

## Context

Os e-mails de chamado são montados em `functions/ticket.php` (`ticketEmailContent()` para o cliente, `ticketAgentEmailContent()` para técnicos) e enviados pela fila (`emailQueue()`). Cada texto é uma chave em inglês passada por `__()`; o português vem de `lang/pt_BR/tickets.php` (e `lang/pt_BR.php` para o assunto de fechado). A chave em inglês identifica o texto: trocar só a tradução não mexe no inglês nem na lógica. Ver proposal.md para a motivação.

## Goals / Non-Goals

**Goals:**
- Trocar os textos em português dos e-mails ao cliente que o usuário indicar, mantendo os marcadores (`%s`, `%1$s`, `%2$s`).
- Registrar o comportamento atual como spec antes (regra do projeto: documentar antes de mudar).

**Non-Goals:**
- Criar e-mail novo (aviso de fechamento perto do prazo), que exigiria cron e modelo novos.
- Mudar layout, cores, botão ou saudação ("Olá, %s,") do e-mail.
- Tornar os textos editáveis pela interface (campo em settings, migração de banco).
- Mexer nos e-mails para técnicos e nos textos em inglês.
- Mudar a lógica de quando cada e-mail sai.

## Decisions

- **Só o arquivo de idioma.** Alterar a tradução em `lang/pt_BR/tickets.php` e deixar a chave em inglês intocada. Alternativa: editar o inglês em `functions/ticket.php`; descartada porque muda o produto para todos os idiomas e quebra a regra de que a chave é a fonte.
- **Marcadores preservados.** Todo texto novo mantém os mesmos `%s`/`%1$s`/`%2$s` da chave; `i18n_check` acusa divergência. O texto aprovado da confirmação ganha o `(%s)` com o número do chamado para o número continuar no corpo.
- **Rótulos do resumo: ficam como estão** (decisão do usuário). Nenhuma chave genérica (`Ticket`, `Subject`, `Priority`, `Status`) é alterada.
- **Aviso de fechamento.** O texto "será fechado automaticamente em %s" fica dentro do e-mail de resolvido (que já informa o prazo configurado), sem e-mail novo.
- **Textos aprovados (pt-BR):** resposta do técnico ("Seu chamado %s recebeu uma nova resposta da nossa equipe." / "Se tiver qualquer dúvida, responda a este e-mail ou acesse o painel do cliente."); resolvido (título "Seu chamado foi resolvido", com e sem fechamento automático); fechado (título "Seu chamado foi fechado", texto original mantido ("O chamado %s foi fechado. Esperamos que tenha sido resolvido como você queria."), porque a linha final do modelo já orienta a abrir um novo chamado). Chamado fechado não reabre por resposta; reabrir é um change à parte.

## Risks / Trade-offs

- [Trocar a tradução de uma chave genérica (`Ticket`, `Status`) muda outras telas] → conferir uso com `grep` antes e, se necessário, usar `_x()` com contexto de e-mail.
- [Texto novo sem `[referência]` no assunto quebra a ligação resposta-chamado] → assuntos só mudam mantendo `[%1$s]`; conferir na prévia e com uma resposta de teste.
- [Marcador perdido ou trocado de ordem gera texto quebrado] → `ddev exec php scripts/i18n_check.php pt_BR` e prévia dos e-mails.
- [Texto aprovado já aplicado fora do change (linha 747)] → tratado na tarefa 1 como verificação, não como retrabalho.
