# Tasks

Os testes rodam no DDEV dentro de um snapshot do banco (`ddev snapshot --name antes-payment-amount`), recolocado e apagado ao final. Durante o teste, os jobs de `cron_jobs` ficam desligados, para o cron do contêiner não mexer nas faturas; a restauração do snapshot os religa. Os pedidos são montados com `curl` como admin, com token CSRF válido. Se for preciso, a senha do admin é trocada só dentro do snapshot. Cookie jars e scripts descartáveis ficam no scratchpad e são apagados no fim.

## 1. Regra

- [ ] 1.1 Criar `paymentAmountCheck($amount, $limit)` em `functions/payments.php`. Ela devolve `null` se o valor serve, ou a mensagem traduzida: maior que zero, ou não passa do limite. Compara em centavos (`round(x * 100)`). Acrescentar a chave "The payment amount must be greater than zero." em `lang/pt_BR/finance.php`. Validar: `ddev exec php -l functions/payments.php lang/pt_BR/finance.php`.

## 2. Caminhos manuais

- [ ] 2.1 `add_payment` (`agent/post/payment.php`): deixar de ler `$_POST['balance']` e `$_POST['currency_code']`. Usar o `balance` do `invoiceState()` que o bloco já calcula como limite em `paymentAmountCheck()`, com recusa por `flashAlert(..., 'error')` + `redirect()` antes de gravar. Gravar a moeda da fatura. Validar: `php -l`; pagamento normal pela tela continua funcionando (3.2).
- [ ] 2.2 `add_bulk_payment`: calcular no servidor a soma dos saldos positivos das faturas Sent, Viewed e Partial do cliente, com a mesma consulta do modal, e usá-la como limite. Gravar cada pagamento com a moeda da sua fatura. Registrar no audit log o total aplicado. Validar: `php -l`; lote normal continua funcionando (3.2).
- [ ] 2.3 `edit_payment`: limite = saldo da fatura do pagamento + valor atual do pagamento, conferido depois da trava de conciliação e antes do `UPDATE`. Validar: `php -l`; edição normal continua funcionando (3.2).
- [ ] 2.4 Tirar o `<input type="hidden" name="balance">` de `agent/modals/payment/payment_add.php` e `payment_bulk_add.php`, e o `currency_code` oculto se nenhum handler o ler mais. Validar: `php -l`; o modal de pagamento e o de lote abrem e mostram o devido como antes.

## 3. Verificação no DDEV

- [ ] 3.1 Pedidos fora da regra, cada um seguido da conferência no banco de que nada mudou (pagamentos, status, histórico, audit):
  - fatura com saldo 100,00: `amount=150` com `balance=500` adulterado → recusado;
  - `amount=0` e `amount=-10` numa fatura → recusados;
  - lote com valor acima da soma devida pelo cliente → recusado, nenhuma fatura recebe pagamento;
  - lote com `amount=0` → recusado;
  - edição de um pagamento de 40,00, numa fatura de 100,00 com 60,00 de saldo, para 120,00 → recusada, o pagamento continua 40,00.
- [ ] 3.2 Pedidos dentro da regra continuam funcionando:
  - pagamento parcial → Partial;
  - pagamento igual ao saldo → Paid;
  - pagamento com centavos (por exemplo 33,33 + 33,33 + 33,34 numa fatura de 100,00) → Paid no último;
  - lote igual ao devido → todas Paid; lote menor → a última fatura alcançada fica Partial;
  - edição para 100,00 no caso da 3.1 → aceita, fatura Paid;
  - moeda gravada igual à da fatura, mesmo com `currency_code` adulterado no pedido.

  Recolocar e apagar o snapshot; apagar cookie jars e scripts.

## 4. Fechamento

- [ ] 4.1 Lint de todos os arquivos tocados; `ddev exec php scripts/i18n_check.php pt_BR` sem pendências; `ddev exec php scripts/i18n_check.php pt_BR --hardcoded agent admin client guest includes js functions login.php join.php` em 0. Validar: saída dos comandos.
- [ ] 4.2 Entrada em `CHANGELOG.md`, em Bug Fixes, em linguagem para administradores: o pagamento manual, o pagamento em lote e a edição de pagamento passam a recusar valores acima do que a fatura (ou o cliente) deve, e valores zero ou negativos; antes o limite vinha do próprio formulário, e o lote descartava o excedente sem avisar. Validar: leitura da entrada.
- [ ] 4.3 Em `openspec/changes/archive/2026-11-06-document-system-baseline/tasks.md`, marcar como resolvido o achado "invoicing: `add_payment` compara o valor com o saldo enviado pelo formulário", com o nome desta change. Registrar como achado novo a comparação com `intval()` da cobrança com cartão salvo do técnico (`agent/post/payment.php`, `add_payment_stripe`). Rodar `openspec validate fix-payment-amount-check --strict`. Validar: sem erros.

## Workflow follow-up

- Arquivar a change (`/opsx:archive`) para levar o requisito novo para `openspec/specs/invoicing/spec.md`.
