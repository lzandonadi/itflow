# Proposal

## Why

A tarefa noturna trata como paga, no vencimento, toda fatura de uma recorrência com cobrança automática **sem cartão salvo**. Ela grava um pagamento "Paid via AutoPay" pelo valor total e marca a fatura como Paid, sem cobrar nada de ninguém (`cron/nightly_tasks.php:752-759`). O dinheiro não entrou, mas a fatura sai das listas de cobrança, dos lembretes e do painel de inadimplência, e o caixa da conta registra uma entrada que não existe.

Esse tipo de cobrança automática não pode mais ser criado: desde a 2.2.3 a tela só aceita um cartão salvo (`agent/post/recurring_invoice.php:261`, `client/post.php:1498`). Ele sobra só em instalações atualizadas de versões antigas ou vindas de um backup antigo. Nessas instalações, a cobrança fica invisível e não pode ser desligada pela tela: a página da recorrência só mostra o seletor quando o cliente tem cartão. Era o achado de online-payments da change `document-system-baseline`.

## What Changes

- **BREAKING (comportamento):** sem um cartão utilizável, a tarefa noturna não registra pagamento nenhum. A fatura continua em aberto (Sent ou Viewed). Isso vale para três casos: cobrança sem cartão; cartão removido ou provedor desligado; dados do Stripe incompletos, como cliente sem ID no Stripe ou provedor sem chave.
- Toda cobrança automática que não acontece fica no histórico da fatura e no audit log, e a equipe recebe uma notificação no ITFlow. Isso vale também para o cartão recusado, que hoje só deixa histórico. Cada fatura gera no máximo uma notificação por dia, mesmo se a tarefa rodar de novo.
- "Tem cobrança automática" passa a significar "tem cartão salvo do cliente, com provedor ativo". Uma recorrência que só tem cobrança sem cartão volta a receber lembretes no vencimento e deixa de mostrar o ícone de cobrança automática.
- A página da recorrência avisa quando existe uma cobrança automática antiga sem cartão e oferece um botão para removê-la.

## Capabilities

### New Capabilities
<!-- Nenhuma. -->

### Modified Capabilities
- `online-payments`: o requisito "Cobrança automática no vencimento" troca o cenário "Cobrança automática sem cartão" (fatura paga sem cobrança) por "nada é registrado e a equipe é avisada". O cenário de cartão removido ou provedor desligado passa a incluir o aviso. Entra o cenário de cobrança que falha por dados incompletos do provedor.
- `invoicing`: o requisito "Lembretes de vencimento" deixa claro que só uma cobrança automática com cartão utilizável tira a fatura dos lembretes.

## Impact

- **Código:** `cron/nightly_tasks.php` (bloco de cobrança automática e consulta dos lembretes), `functions/billing_email.php` (`billingReminderAutopay()`), `agent/recurring_invoices.php` (ícone), `agent/recurring_invoice.php` (aviso e remoção da cobrança sem cartão) e as traduções pt_BR dos textos novos.
- **Banco:** nenhuma mudança de esquema nem migração. As linhas antigas sem cartão ficam até alguém removê-las pela tela.
- **Coordenação:** a change `fix-nightly-duplicate-notifications`, aberta em paralelo, também mexe em `cron/nightly_tasks.php`. A implementação desta change deve entrar depois dela, ou ser rebaseada sobre ela, e usar a mesma forma de evitar notificação repetida.
- **Instalações novas, como a produção prevista:** não têm cobrança sem cartão, então o efeito prático lá é a notificação de cobrança que falhou.
