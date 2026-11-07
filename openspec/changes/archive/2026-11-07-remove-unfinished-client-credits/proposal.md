# Proposal

## Why

O crédito de cliente (saldo pré-pago, reembolso ou promoção, abatido nas faturas) ficou pela metade no upstream em 2025: a aplicação de crédito foi comentada ("Not ready for use 2025-08-27") e o botão "Add Credit" foi escondido. Os restos continuam no código e um deles ainda funciona: o handler `add_credit` cria créditos por um pedido montado à mão, créditos que nenhuma tela mostra e que nunca podem ser usados. O modal de aplicar crédito também continua acessível pelo endereço, com um formulário que ninguém trata. É o achado "invoicing: aplicar crédito está pela metade" da change `document-system-baseline`.

## What Changes

- Sai o que sobrou do recurso inacabado:
  - o handler `add_credit` (`agent/post/credit.php`);
  - o bloco comentado de `apply_credit` em `agent/post/payment.php`;
  - os modais `agent/modals/client/client_credit_add.php` e `agent/modals/payment/invoice_apply_credit.php`;
  - o item escondido "Add Credit" no menu do cliente e a variável que o esconde;
  - a consulta do saldo de crédito, que roda em toda página de cliente e não é usada.
- Os dados de demonstração deixam de criar créditos que ninguém vê.
- Continua como está:
  - a tabela `credits` e a coluna `invoices.invoice_credit_amount`;
  - a seção "Credits" da exportação de dados do cliente;
  - a linha "Credit" no total de uma fatura que tenha crédito, na tela de impressão e no PDF.

  Assim, nada gravado por uma versão antiga se perde.
- A spec `invoicing` deixa de prever "aplicar crédito" e passa a dizer que crédito de cliente não é criado nem aplicado.
- A outra opção era terminar o recurso. Ela fica registrada no design e, se for desejada, vira uma change nova.

## Capabilities

### New Capabilities
<!-- Nenhuma. -->

### Modified Capabilities
- `invoicing`: o requisito "Mudança manual de status respeita o status atual" deixa de citar a aplicação de crédito. Entra um requisito que diz que crédito de cliente não é criado nem aplicado, e que os créditos antigos continuam guardados e exportáveis.

## Impact

- **Código:** os arquivos removidos acima, mais `agent/includes/client_actions_menu.php`, `agent/includes/inc_client_top_head.php`, `agent/includes/inc_all_client.php`, o cabeçalho de `agent/post/payment.php` e `admin/post/demo_data_model.php`.
- **Traduções:** saem de `lang/pt_BR/` as entradas que ficarem sem uso ("Add Credit", "Apply Credit", "Credit Amount" e outras). "Credit" continua, porque o total da fatura e a exportação ainda a usam.
- **Banco:** nenhuma mudança de esquema.
- **Coordenação:** a change `fix-payment-amount-check` também mexe em `agent/post/payment.php`. As duas são aplicadas uma depois da outra, sem conflito de conteúdo: esta mexe só no cabeçalho e no bloco comentado.
