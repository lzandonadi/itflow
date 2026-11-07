# Proposal

## Why

O fork passa a ser mantido sem o upstream, mas o comportamento do ITFlow só está registrado no próprio código, nos comentários de cabeçalho, no CONTRIBUTING.md e em ~7.500 commits. Sem uma base escrita do que o sistema faz hoje, toda change futura, incluindo a tradução e uma eventual migração de stack, corre o risco de quebrar regras de negócio que ninguém lembra que existem.

## What Changes

- Primeiras specs de base em `openspec/specs/`, descrevendo comportamento **já existente** (nenhum código muda). Elas cobrem as regras de maior risco: acesso e escopo por cliente, portal do cliente, tarefas agendadas, backups, atualizações, faturamento, pagamentos online, chamados, SLA e contratos.
- Um mapa de capabilities da aplicação inteira no `design.md`, com as áreas ainda não documentadas e a prioridade de cada uma. Esse mapa vira o backlog de documentação.
- Registro dos pontos em que o fork ainda depende do upstream: autoatualização por `git fetch`, telemetria para `telemetry.itflow.org` e links para docs, fórum e serviços do itflow.org. As decisões sobre eles ficam para changes próprias.
- Uma regra de trabalho: a change que tocar uma área ainda sem spec documenta primeiro o comportamento atual dessa área.

## Capabilities

### New Capabilities
- `access-control`: papéis e níveis de permissão por módulo, área administrativa, restrição de técnicos a clientes e proteção contra requisições forjadas.
- `client-portal-access`: o que cada contato do cliente pode ver e fazer no portal, conforme seja contato principal, financeiro ou técnico.
- `scheduled-jobs`: o despachante de tarefas agendadas, a agenda de cada tarefa, o bloqueio individual, o interruptor geral e a garantia de execução repetida segura.
- `backups`: backup criptografado, retenção e restauração segura.
- `software-updates`: atualização do código pelo git e aplicação sequencial das migrações de banco.
- `invoicing`: faturas, orçamentos e faturas recorrentes, com seus status, lembretes, multas por atraso e extratos.
- `online-payments`: pagamento de faturas com cartão via Stripe, cartões salvos, cobrança automática e registro das taxas.
- `ticketing`: ciclo de vida dos chamados, fechamento automático, tempo trabalhado e abertura de chamados por e-mail.
- `ticket-sla`: metas de SLA por prioridade, horário comercial, pausa por status e alertas.
- `contracts`: contratos com vigência, renovação, assinatura online, horas pré-pagas e o que o contrato define para o cliente enquanto está em vigor.

### Modified Capabilities
<!-- Nenhuma: são as primeiras specs do projeto. -->

## Impact

- **Arquivos:** só `openspec/`. Nenhum arquivo de aplicação, banco ou dependência muda.
- **Changes em andamento:** `add-i18n-infrastructure` não depende desta. A futura `translate-ui-pt-br` usa as specs de `invoicing`, `ticketing` e `contracts` para saber quais valores gravados não podem ser traduzidos.
- **Processo:** a regra "documentar a área antes de mudá-la" passa a valer para todas as changes seguintes.
