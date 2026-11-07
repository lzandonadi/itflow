# Proposal

## Why

Quando a tarefa noturna roda uma segunda vez no mesmo dia (Run Now depois da execução das 3h), cada técnico recebe de novo os avisos internos escolhidos pela data de hoje: "Pending Tickets", "Invoice Overdue", "Update", e também os de domínio, certificado, chave de API, garantia, bateria de nobreak, revisão de serviço, fatura recorrente atrasada, rascunhos de fatura e despesa recorrente atrasada. A regra do projeto diz que tarefa diária deve poder rodar duas vezes no dia sem repetir efeitos (CONTRIBUTING, regra 3 do cron; spec `scheduled-jobs`). Multas, lembretes por e-mail e extratos já respeitam isso, mas os avisos internos não. Esse era o achado de `scheduled-jobs/invoicing` da change `document-system-baseline`.

## What Changes

- Os avisos internos da tarefa noturna que dependem da data de hoje passam a ser criados no máximo uma vez por dia para cada técnico. Uma segunda execução no mesmo dia não cria de novo o aviso que o técnico já recebeu hoje, mesmo que ele já o tenha dispensado.
- Os avisos-resumo ("Pending Tickets", "Draft Invoices", "Update") contam como o mesmo aviso no dia mesmo que o número ou a versão mudem entre as execuções. Os avisos sobre um item (fatura, domínio, garantia...) contam como o mesmo quando falam do mesmo item com o mesmo texto.
- Um técnico ativado entre as duas execuções recebe na segunda os avisos do dia que ainda não recebeu.
- Os avisos que nascem de um acontecimento e não se repetem sozinhos continuam como estão. São eles: multa aplicada, fatura paga por cobrança automática, despesa recorrente registrada, contrato encerrado, white label desligado, e os avisos das outras tarefas diárias (problema de certificado, backup, atualização aplicada).
- Os e-mails a clientes não mudam. Lembretes de vencimento, extratos e multas já são enviados ou aplicados uma vez por dia.

## Capabilities

### New Capabilities
<!-- Nenhuma. -->

### Modified Capabilities
- `scheduled-jobs`: o requisito "Tarefas diárias não repetem efeitos no mesmo dia" ganha o cenário dos avisos internos da tarefa noturna.

## Impact

- **Código:** um helper novo em `functions/logging.php`, ao lado de `appNotify()`, e as chamadas dos avisos com data de hoje em `cron/nightly_tasks.php` passando a usá-lo.
- **Banco:** nenhuma mudança de esquema. A checagem consulta a própria tabela `notifications`.
- **Usuários:** nada muda numa execução por dia. Numa segunda execução no mesmo dia, deixam de aparecer avisos repetidos.
