# Proposal

## Why

Os e-mails de chamado enviados aos clientes saem com o texto padrão traduzido do inglês, num tom que não é o da Tellatech. O dono do sistema quer o tom dos e-mails de suporte que já usa como referência (confirmação curta, aviso de fechamento, bloco "Detalhes do Ticket"). Os textos não são configuráveis pela interface: ficam no código e no arquivo de idioma. Hoje também não existe spec dos e-mails de chamado, então não há contrato que impeça uma regressão (por exemplo, perder o `[TCK-11]` do assunto, que o leitor de e-mails usa para ligar a resposta ao chamado).

## What Changes

- Escrever a spec de base (`ticket-emails`) com o comportamento atual dos e-mails de chamado: quais existem, quando saem, o que o assunto carrega e o que nunca é repetido de volta ao remetente.
- Trocar o texto em português do e-mail de confirmação de chamado criado por e-mail (já aplicado em `lang/pt_BR/tickets.php`, falta registrar e validar): "Obrigado por entrar em contato. Um ticket de suporte (%s) foi aberto para a sua solicitação. Você será notificado por e-mail quando houver uma resposta."
- Reescrever, na mesma linha de tom, os demais e-mails ao cliente que o usuário indicar: resposta do técnico, resolvido (com o aviso de fechamento automático) e os rótulos do resumo ("ID do Ticket", "Assunto", "Prioridade", "Status"). Os textos exatos chegam do usuário, e-mail por e-mail.
- Fora do escopo: criar um e-mail novo de aviso de fechamento perto do prazo (exige código e cron), mudar o layout do e-mail, e traduzir/alterar os e-mails para técnicos.

## Capabilities

### New Capabilities
- `ticket-emails`: os e-mails que o sistema envia por causa de um chamado (ao cliente, a observadores e a técnicos): quando cada um sai, o que o assunto e o corpo carregam e quais garantias valem (referência no assunto, nada do remetente repetido de volta, idioma do momento).

### Modified Capabilities

## Impact

- `lang/pt_BR/tickets.php` (traduções), possivelmente `lang/pt_BR.php` (assunto do e-mail de fechado).
- `functions/ticket.php` só se algum rótulo do resumo mudar na origem em inglês (hoje `ticketEmailContent()`); o inglês deve continuar igual.
- Nenhuma mudança de banco, API ou dependência.
- Verificação: `ddev exec php scripts/i18n_check.php pt_BR`, prévia dos e-mails (`functions/email_preview.php`) e leitura na caixa do Mailpit.
