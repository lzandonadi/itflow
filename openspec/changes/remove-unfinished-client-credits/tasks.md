# Tasks

Os testes rodam no DDEV (https://itsm.ddev.site) dentro de um snapshot do banco (`ddev snapshot --name antes-credit-removal`), com os jobs em `cron_jobs` desligados durante o teste. Ao final, o snapshot é recolocado e apagado, e os scripts descartáveis e cookie jars são removidos. A change `fix-payment-amount-check` também mexe em `agent/post/payment.php`: aplicar as duas em sequência e reler o arquivo antes de editar.

## 1. Remoção

- [x] 1.1 Apagar `agent/post/credit.php`. Em `agent/post/payment.php`, remover o bloco comentado de `apply_credit` e ajustar o comentário do cabeçalho ("credits & payments" e "Credits keep their old checks until the feature is finished"). Validar: `ddev exec php -l agent/post/payment.php`; `grep -rn "add_credit\|apply_credit" agent admin client guest api functions includes` vazio.
- [x] 1.2 Apagar `agent/modals/client/client_credit_add.php` e `agent/modals/payment/invoice_apply_credit.php`. Remover:
  - o item "Add Credit" de `agent/includes/client_actions_menu.php`;
  - `$show_add_credit` e o `require_once` comentado de `agent/includes/inc_client_top_head.php`;
  - a consulta do saldo de crédito de `agent/includes/inc_all_client.php`.

  Antes, confirmar com `grep` que `$credit_balance`, `$show_add_credit` e `addCreditModal` não têm outro uso. Validar: `php -l` dos três arquivos; o `grep` dos três nomes fica vazio.
- [x] 1.3 Em `admin/post/demo_data_model.php`, deixar de criar o crédito `prepaid` dos clientes break-fix, mantendo o `DELETE FROM credits` da limpeza (apaga dados de demonstração antigos). Validar: `php -l`; leitura do trecho.

## 2. Traduções

- [x] 2.1 Rodar `ddev exec php scripts/i18n_check.php pt_BR` e remover de `lang/pt_BR/` as entradas que ficaram sem uso por causa desta remoção; manter "Credit" e "Credits". Validar: o checker sem pendências e sem as entradas do recurso na lista de não usadas; `--hardcoded` do escopo oficial (`agent admin client guest includes js functions login.php join.php`) em 0.

## 3. Verificação

- [x] 3.1 Num snapshot, como admin, com `curl` e um token CSRF válido:
  - POST `add_credit` para o cliente 1 → nenhuma linha nova em `credits`, e um aviso `Request` de pedido não tratado no App Log;
  - POST `apply_credit` para uma fatura Sent → fatura, pagamentos e `credits` sem mudança;
  - GET de `agent/modals/payment/invoice_apply_credit.php?id=<fatura>` e de `agent/modals/client/client_credit_add.php` → 404.

  Validar: contagens antes e depois, e as linhas do App Log.
- [x] 3.2 No mesmo snapshot:
  - a página do cliente 1 (visão geral e menu de ações) abre sem erro e sem "Add Credit";
  - a página de uma fatura abre sem erro;
  - com um crédito antigo inserido por SQL em `credits` e `invoice_credit_amount` = 10 numa fatura, a exportação de dados do cliente lista o crédito, e a impressão (`includes/billing_paper.php`) e o PDF da fatura mostram a linha "Credit";
  - nenhum erro novo do PHP em `ddev logs -s web`.

  Recolocar e apagar o snapshot. Validar: as respostas HTTP e o conteúdo conferido.

## 4. Fechamento

- [x] 4.1 Entrada no `CHANGELOG.md` (New Features & Updates), em linguagem para administradores: os restos do crédito de cliente inacabado foram removidos, sem tela e sem efeito; um pedido que ainda criava créditos invisíveis deixa de funcionar; créditos antigos continuam na exportação e no total das faturas. Validar: leitura da entrada.
- [x] 4.2 Atualizar o achado "invoicing: aplicar crédito está pela metade" em `openspec/changes/archive/2026-11-06-document-system-baseline/tasks.md` com o nome desta change. Rodar `openspec validate remove-unfinished-client-credits --strict`. Validar: sem erros.

## Workflow follow-up

- Arquivar a change (`/opsx:archive`) para levar a mudança do requisito e o requisito novo para `openspec/specs/invoicing/spec.md`. Se `fix-payment-amount-check` for arquivada antes e também modificar "Mudança manual de status respeita o status atual", atualizar o bloco MODIFIED desta delta a partir da spec principal antes de arquivar.
