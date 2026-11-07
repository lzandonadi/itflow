# Design

## Context

Ver proposal.md, "Why". O que existe hoje do crédito de cliente:

| Peça | Onde | Estado |
|---|---|---|
| Tabela `credits` (valor, tipo `prepaid/manual/refund/promotion/usage`, nota, validade, fatura, cliente) | `admin/database_updates/2.2.4.php`, `2.2.7.php`, `db.sql` | existe; 0 linhas no DDEV |
| Coluna `invoices.invoice_credit_amount` | 2.2.4 | sempre 0 (`select ... invoice_credit_amount <> 0` = 0); só seria gravada pelo `apply_credit` |
| Criar crédito: handler `add_credit` | `agent/post/credit.php:9-34` | **ativo**: `post.php` carrega o arquivo, então um POST montado à mão (vendas nível 2) grava o crédito. A validade é lida e não é gravada, e o tipo vai direto para o `enum` |
| Criar crédito: modal | `agent/modals/client/client_credit_add.php` | não é incluído (`agent/includes/inc_client_top_head.php:127` comentado, "Credit Not Ready 2025-08-27") |
| Botão "Add Credit" | `agent/includes/client_actions_menu.php:31-36` | escondido por `$show_add_credit = 0` (`inc_client_top_head.php:17`); usa o módulo `module_billing`, que não existe |
| Saldo de crédito | `agent/includes/inc_all_client.php:175-179` | consultado em toda página de cliente; só o modal escondido usa `$credit_balance` |
| Aplicar crédito: handler | `agent/post/payment.php:139-247` | todo comentado ("Not ready for use 2025-08-27 - JQ"). Defeitos: `floatval('invoice_amount')` (string literal, sempre 0), `credit_tally` (coluna inexistente) e `-$amount` (variável indefinida) |
| Aplicar crédito: modal | `agent/modals/payment/invoice_apply_credit.php` | nenhuma tela abre; acessível pelo endereço. O campo se chama `amount_credit_applied`, e o handler lê `credit_amount_applied` |
| Linha "Credit" no total | `functions/billing.php:604`, `functions/billing_pdf.php:321`, `includes/billing_paper.php:358` | aparece só com `invoice_credit_amount > 0` |
| Exportação | `functions/client_export.php:402`, `:857` | lista os créditos do cliente |
| Dados de demonstração | `admin/post/demo_data_model.php:3390-3398` cria 500 de crédito `prepaid` para clientes break-fix; `:813` apaga na limpeza | créditos que ninguém vê nem usa |
| Audit log | `functions/audit_log.php:90` (`'Credit'`) | ícone e módulo para linhas antigas |

Histórico: o recurso começou no upstream em `197dd9f29` e `4d71eb5de` (2025-07-24). Em `d584c6b1e` ele foi comentado ("not ready for next release running into way to many pitfalls"), e em `b34298e45` o botão foi escondido. Desde então ninguém voltou a ele, e este fork não acompanha o upstream.

O "pré-pago" que o crédito cobriria (`credit_type = 'prepaid'`, o texto da demonstração "prepaid block of hours") já existe de outra forma: as horas pré-pagas dos contratos (spec `contracts`).

O crédito faz parte da área de faturas (`invoicing` no mapa de capabilities, que já cita "aplicar crédito" no requisito de mudança manual de status). Por isso não é preciso escrever antes uma spec de linha de base: a delta fica em `invoicing`.

## Goals / Non-Goals

**Goals:**
- Nenhum caminho, nem a tela nem um pedido montado à mão, cria ou aplica crédito.
- Nada do que já foi gravado se perde: tabela, coluna, exportação e linha no total continuam.
- O código sem uso sai, junto com a consulta inútil por página e as traduções que ficarem sem uso.

**Non-Goals:**
- Apagar a tabela `credits` ou a coluna `invoice_credit_amount`. Isso perderia dados de instalações antigas, e não há ganho.
- Mostrar em alguma tela o saldo de créditos antigos. Hoje nenhuma tela o mostra, e a exportação continua.
- Créditos órfãos depois de excluir um cliente: a exclusão de cliente não apaga `credits` hoje. Fica como está; com a criação removida, não aparecem novos.
- Terminar o recurso (opção B abaixo).

## Decisions

### D1. Remover os restos (opção A), e não terminar o recurso (opção B)
**Opção A, recomendada:** remover o handler, os modais, o botão escondido e a consulta do saldo, e parar de criar créditos na demonstração.
- *Por quê:* nenhum caminho visível cria crédito; o banco do DDEV tem 0 créditos e 0 faturas com crédito. O único caminho ativo é um pedido montado à mão que grava um saldo que ninguém vê nem usa. O autor original abandonou o recurso por "muitas armadilhas", e o caso mais comum, horas pré-pagas, já é coberto pelos contratos. Remover é pequeno, reversível e tira um caminho de escrita sem tela.

**Opção B, descartada por ora:** terminar o recurso, aplicando o saldo de crédito de um cliente a uma fatura aberta.
- *O que exigiria:*
  - criar crédito por tela, com a validade gravada e o tipo validado;
  - aplicar com as guardas de `invoiceCanPay()` e o saldo calculado no servidor;
  - decidir como o crédito entra no saldo da fatura (`invoiceState()`), nos extratos, no portal do cliente, nos relatórios de receita e na conciliação bancária, já que crédito não é dinheiro entrando numa conta;
  - decidir o que acontece com o crédito ao cancelar uma fatura, ao excluir um pagamento ou quando a validade vence;
  - mais a API e a tradução.
- *Por que não agora:* é um recurso novo, com decisões de contabilidade. Não é correção de defeito. Se o negócio precisar dele, vira uma change própria, partindo da spec escrita aqui.

**Pergunta ao usuário:** confirmar a opção A. Se a resposta for B, esta change é descartada e uma nova é proposta.

### D2. O handler sai por inteiro, sem virar recusa explícita
Sem `agent/post/credit.php`, um POST `add_credit` cai no tratamento de pedido não tratado de `agent/post.php`: um aviso `Request` no App Log e nenhum efeito.
- *Alternativa descartada:* manter o handler devolvendo um erro, o que mantém código para um recurso que não existe.

### D3. As traduções sem uso saem
Depois da remoção, `scripts/i18n_check.php` lista as entradas sem uso. Saem as que pertencem a este recurso ("Add Credit", "Apply Credit", "Credit Amount", "Adding %1$s (Credit Balance: %2$s)", "- Select Credit Type -", "%s credit added" e as demais do modal). "Credit" e "Credits" continuam, porque ainda são usadas no total da fatura e na exportação.

## Risks / Trade-offs

- **[Risco] Alguém usa o POST `add_credit` por automação.** → Improvável: não há tela nem API, e o crédito criado não serve para nada. Fica registrado no CHANGELOG.
- **[Risco] Conflito com `fix-payment-amount-check`, que também mexe em `agent/post/payment.php`.** → As duas são aplicadas em sequência. Esta mexe só no comentário do cabeçalho e no bloco comentado do fim; quem aplicar por último relê o arquivo.
- **[Trade-off] Instalações com créditos antigos não veem o saldo em nenhuma tela.** É como já é hoje; a exportação continua mostrando.

## Migration Plan

Sem migração de banco. Para desfazer, basta reverter o commit; os modais e o handler voltam, e o botão continua escondido como antes.
