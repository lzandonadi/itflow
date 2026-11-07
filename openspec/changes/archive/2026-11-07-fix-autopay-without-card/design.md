# Design

## Context

Ver proposal.md, "Why". O bloco de cobrança automática da tarefa noturna (`cron/nightly_tasks.php:606-760`) seleciona as faturas Sent ou Viewed que vencem hoje e vêm de uma recorrência com linha em `recurring_payments`. Para cada uma:

| Situação | O que acontece hoje | Linha |
|---|---|---|
| Já houve "Stripe autopay failed" hoje | pula | 641 |
| `recurring_payment_saved_payment_id` vazio (linha antiga, sem cartão) | **grava pagamento "Paid via AutoPay", marca Paid**, histórico e audit | 750-759 |
| Cartão não encontrado ou provedor inativo | só audit log, `continue` | 657-660 |
| Provedor não é Stripe | nada, em silêncio | 678 |
| Stripe sem chave, sem cliente no Stripe ou sem método no cartão | nada, em silêncio | 679 |
| Exceção do Stripe | histórico "Payment failed" + app log + audit | 712-718 |
| Status diferente de `succeeded`, ou valor diferente | histórico "Payment failed" + audit | 744-747 |
| Sucesso | pagamento, Paid, recibo, notificação "automatically paid" | 721-742 |

Como surgem linhas sem cartão: a coluna `recurring_payment_saved_payment_id` entrou na 2.2.3 com padrão NULL. As linhas das versões anteriores, quando a cobrança automática era só "forma de pagamento + conta", ficaram com NULL. A 2.3.0 converteu as de Stripe e a 2.3.2 apagou as que sobraram com método Stripe. As demais (dinheiro, transferência...) continuam lá. Hoje a tela só cria linhas com cartão (`agent/post/recurring_invoice.php:309-310`, `client/post.php:1546-1547`), e a FK com `ON DELETE CASCADE` apaga a linha quando o cartão é apagado. O banco do DDEV não tem nenhuma linha em `recurring_payments`.

A existência de uma linha, com ou sem cartão, já basta para tirar a fatura dos lembretes até o dia do vencimento (`cron/nightly_tasks.php:479` e `:522`; `billingReminderAutopay()` em `functions/billing_email.php:148`) e para mostrar o ícone de carteira na lista (`agent/recurring_invoices.php:245`). Já a página da recorrência só mostra a cobrança quando o cartão existe (`agent/recurring_invoice.php:88` e `:133`), e o seletor só aparece quando o cliente tem cartão (`:357`). Sem cartão, a linha antiga não pode ser vista nem removida pela tela.

## Goals / Non-Goals

**Goals:**
- Nenhum pagamento sem cobrança efetiva no provedor.
- Toda cobrança automática que não acontece fica registrada e é avisada à equipe, uma vez por fatura e por dia.
- Uma definição única de "cobrança automática utilizável", usada pela tarefa, pelos lembretes e pelo ícone.
- Um jeito de remover pela tela uma cobrança antiga sem cartão.

**Non-Goals:**
- Recriar a cobrança automática sem cartão como um recurso de propósito ("dar baixa automática" para cliente em débito automático). Se for desejado, é uma change nova, com nome e tela próprios.
- Apagar as linhas antigas por migração.
- Nova tentativa de cobrança em outro dia depois de uma recusa, ou mudar a regra de "uma tentativa por dia".
- Outros provedores além do Stripe.
- Corrigir pagamentos "Paid via AutoPay" já registrados no passado. A entrada no CHANGELOG orienta o administrador a conferi-los.

## Decisions

### D1. Sem cartão utilizável, nada é registrado, e a falha segue o caminho de uma recusa
O ramo `else` que grava "Paid via AutoPay" sai. As linhas sem cartão, os cartões não encontrados, os provedores inativos ou não suportados e os dados incompletos do Stripe passam a ser tratados como uma cobrança que falhou: histórico `Payment failed` com descrição que começa por "Autopay not charged", audit log e notificação. A fatura fica como estava.
- *Por quê:* registrar um pagamento sem dinheiro é o defeito. Tratar como recusa reaproveita o que o sistema já faz quando o cartão é recusado.
- *Alternativa descartada:* manter a baixa automática sem cartão como recurso. A tela não permite mais configurá-la desde a 2.2.3, ela não aparece na página da recorrência, e o efeito para quem não sabe que ela existe é perder o controle da inadimplência.
- *Alternativa descartada:* apagar as linhas sem cartão numa migração. Some com a configuração sem que ninguém saiba quais recorrências dependiam dela, e uma migração de dados aumentaria a versão do banco só por isso. Com D1 as linhas ficam inofensivas, e a notificação no vencimento mostra onde estão.

### D2. A equipe é avisada de toda cobrança automática que falha, uma vez por dia
Um helper novo em `functions/billing_pay.php`, por exemplo `billingAutopayFailed($invoice_id, $client_id, $number, $reason)`, grava o histórico, o audit log e a notificação "Invoice" com o motivo e o link da fatura. Ele fica em `functions/` porque um job do cron não pode declarar funções. Antes de chamá-lo, a tarefa verifica com `cronInvoiceHistoryToday()` se a fatura já tem "Autopay not charged" ou "Stripe autopay failed" no dia, e só segue se não tiver. A recusa de cartão passa a chamar o mesmo helper.
- *Ajuste na implementação:* a notificação usa `appNotifyOncePerDay()` (`functions/logging.php`), criada pela change `fix-nightly-duplicate-notifications`, com o link da fatura e o `invoice_id` como entidade. A checagem de `cronInvoiceHistoryToday()` continua, porque é ela que impede uma nova tentativa de cobrança e um novo histórico no mesmo dia. O helper de notificação garante, além disso, um aviso só por técnico e por dia.
- *Por quê:* sem aviso, uma fatura que deveria ter sido cobrada some em silêncio até o primeiro lembrete de atraso. Hoje nem a recusa avisa ninguém.
- *Alternativa descartada:* avisar só nos casos novos. Deixaria a recusa, o caso mais comum numa instalação nova, sem aviso.

### D3. "Cobrança automática" = linha com cartão salvo do cliente e provedor ativo
A consulta dos lembretes (`:479`), `billingReminderAutopay()` e o ícone da lista passam a exigir o cartão (JOIN em `client_saved_payment_methods` e `payment_providers` com `payment_provider_active = 1`), como a tarefa já exige antes de cobrar.
- *Por quê:* sem isso, uma recorrência que nunca será cobrada continua sem lembrete até o vencimento e exibe um ícone que promete "cobrado num cartão salvo".

### D4. Aviso e remoção na página da recorrência
Quando há linha em `recurring_payments` sem cartão utilizável, `agent/recurring_invoice.php` mostra, no cartão de cobrança automática, um aviso de que ela não cobra nada e um botão "Turn off", que posta `set_recurring_payment` com `saved_payment_id=0`. O handler existente já apaga a linha nesse caso. O botão só aparece para quem pode editar (`module_sales` 2).
- *Por quê:* hoje a linha é invisível e não há como removê-la sem apagar a recorrência.

## Risks / Trade-offs

- **[Risco] Instalações antigas que usavam a baixa automática de propósito**, para clientes em débito em conta, passam a ver essas faturas em aberto e atrasadas. → É o comportamento correto para o sistema: o pagamento precisa ser registrado por alguém. A notificação e a entrada no CHANGELOG explicam a mudança. Quem quiser a baixa automática de volta pode pedir um recurso próprio (ver Non-Goals).
- **[Risco] Mais notificações:** uma por fatura com cobrança automática que falha, no dia do vencimento. → No máximo uma por fatura por dia. A recusa de cartão é rara, e as linhas sem cartão somem quando são removidas pelo botão.
- **[Risco] Conflito com `fix-nightly-duplicate-notifications`**, que mexe no mesmo arquivo e no mesmo tema (notificação repetida na segunda execução). → Implementar esta change depois daquela e usar a mesma forma de verificação diária.
- **[Trade-off] Os pagamentos "Paid via AutoPay" já registrados continuam lá.** Desfazê-los automaticamente seria arriscado, porque alguns podem ter sido reais (débito em conta). A entrada no CHANGELOG diz como encontrá-los: forma de pagamento com referência "Paid via AutoPay".

## Migration Plan

Sem mudança de esquema e sem migração. A atualização de código muda o comportamento a partir da próxima execução noturna. Para desfazer, basta reverter o código, e a baixa automática sem cartão volta.
