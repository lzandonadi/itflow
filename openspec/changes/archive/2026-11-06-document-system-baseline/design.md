# Design

## Context

O conhecimento sobre o comportamento do ITFlow está em quatro fontes, da mais para a menos confiável:

1. **O código**, a fonte de verdade.
2. **Os comentários de cabeçalho** dos arquivos em `functions/`, `cron/` e `includes/`. São detalhados e foram atualizados junto com as mudanças recentes. Por exemplo, `functions/contract_hours.php`, `functions/sla.php` e `includes/cron_jobs.php` descrevem as regras quase em forma de spec.
3. **O `CONTRIBUTING.md`**, que documenta segurança, cron, backups e migrações.
4. **O `CHANGELOG.md`**, que explica o porquê de cada correção.

As specs desta change foram montadas a partir dessas fontes e conferidas pontualmente no código: status de fatura e orçamento, sequência do leitor de e-mail, multa por atraso, fechamento automático, estágios de SLA, permissões do portal e registro do cron. A verificação completa de cada spec é o trabalho das tarefas.

## Goals / Non-Goals

**Goals:**
- Specs de base para as regras com maior risco de regressão silenciosa: segurança, dinheiro, prazos e tarefas automáticas.
- Um mapa da aplicação inteira que sirva de backlog e indique onde está o risco.
- Um registro dos pontos de dependência do upstream, que o fork precisará decidir.

**Non-Goals:**
- Documentar todas as telas, campos e fluxos. As áreas fora da prioridade 1 ficam no mapa e são documentadas quando forem tocadas.
- Descrever implementação (nomes de funções, tabelas, arquivos) nas specs. Isso fica nos comentários do código e neste design.
- Corrigir bugs encontrados durante a descoberta. Divergências entre spec e código viram itens anotados para changes próprias, e a spec descreve o comportamento **atual**.
- Decidir o destino dos pontos de dependência do upstream.

## Decisions

### D1. As specs descrevem o comportamento atual, não o desejado
Esta change não altera código. Se o código diverge do que parece certo, a spec registra o que o sistema faz hoje e a divergência entra na seção "Achados" do `tasks.md`.
- *Por quê:* uma spec de base que mistura o atual com o desejado deixa de servir como contrato de regressão.

### D2. Capabilities planas, nomeadas pelo comportamento
Os nomes são `access-control`, `invoicing`, `ticket-sla` e assim por diante, sem hierarquia de domínio por enquanto.
- *Por quê:* com 10 specs, uma hierarquia seria prematura. Ela pode ser introduzida quando o mapa passar de ~25 specs, renomeando com a operação RENAMED.
- *Alternativa descartada:* uma spec por módulo de tela (`agent-invoices-page`). Isso amarra a spec à interface atual e tira o valor dela num redesign ou numa migração de stack.

### D3. Valores enumerados gravados entram nas specs
Os status de fatura e orçamento aparecem literalmente, com a regra de que são gravados e comparados assim, porque a change `add-i18n-infrastructure` depende dessa garantia. Os status de chamado são identificados por número e têm nome editável. A diferença é importante para a tradução: o nome de status de chamado é um **dado** do banco (semeado em inglês pelo setup), e não um texto do código.

### D4. "Documentar antes de mudar"
Toda change que altera uma área sem spec começa com uma tarefa que escreve a spec de base da área (ADDED com o comportamento atual) e só depois aplica o delta da mudança.

## Mapa de capabilities

Prioridade 1 = esta change. Prioridade 2 = próxima rodada, por ter risco de segurança ou dinheiro. Prioridade 3 = documentar quando for tocada.

| Área | Capability prevista | Prioridade | Fontes principais |
|---|---|---|---|
| Permissões, papéis e escopo por cliente | `access-control` | 1 | `functions/roles.php`, `functions/auth.php`, CONTRIBUTING §3–4 |
| Portal do cliente: acesso | `client-portal-access` | 1 | `client/functions.php`, `functions/portal.php` |
| Cron | `scheduled-jobs` | 1 | `includes/cron_jobs.php`, `cron/cron.php`, CONTRIBUTING "Cron" |
| Backups | `backups` | 1 | `functions/backup.php`, CONTRIBUTING "Backups" |
| Atualização e migrações | `software-updates` | 1 | `functions/app.php`, `admin/database_updates.php`, `scripts/update_cli.php` |
| Faturas, orçamentos, recorrentes | `invoicing` | 1 | `functions/billing*.php`, `cron/nightly_tasks.php` |
| Pagamento online | `online-payments` | 1 | `functions/billing_pay.php`, `functions/payment_provider.php`, `cron/payment_provider_fees.php` |
| Chamados | `ticketing` | 1 | `functions/ticket*.php`, `cron/ticket_email_parser.php`, `setup/seed_data.php` |
| SLA | `ticket-sla` | 1 | `functions/sla.php`, `cron/ticket_sla.php` |
| Contratos | `contracts` | 1 | `functions/contract*.php` |
| Credenciais (criptografia, 2FA, registro de visualização) | `credentials` | 2 | `functions/credential.php`, `functions/security.php` |
| Links compartilhados com expiração | `secure-shares` | 2 | `functions/share.php`, `guest/` |
| Autenticação de técnicos e contatos (senha, MFA, sessões, convites) | `authentication` | 2 | `functions/sign_in.php`, `functions/user_invite.php`, `functions/portal_invite.php` |
| API v1 | `rest-api` | 2 | `api/v1/` |
| Fila e envio de e-mail, configuração SMTP/IMAP/OAuth | `email-delivery` | 2 | `functions/mail_queue.php`, `functions/email.php`, `admin/settings_mail.php` |
| Contabilidade: contas, transações, transferências, conciliação, importação | `accounting` | 2 | `functions/accounts.php`, `functions/reconcile.php`, `functions/*_import.php` |
| Despesas e receitas (incluindo recorrentes) | `expenses-income` | 2 | `functions/expenses.php`, `functions/income.php` |
| Tempo não faturado, viagens | `unbilled-work` | 2 | `functions/billing.php`, `functions/trips.php` |
| Audit log, app log, encaminhamento de log | `audit-logging` | 2 | `functions/audit_log.php`, `functions/app_log.php`, `functions/log_forward.php` |
| Clientes, contatos, locais | `client-records` | 3 | `functions/contact.php`, `functions/location.php` |
| Onboarding de cliente | `client-onboarding` | 3 | `functions/client_onboarding.php` |
| Ativos, interfaces, racks, redes | `asset-inventory` | 3 | `functions/asset*.php`, `functions/rack.php`, `functions/network.php` |
| Software e licenças | `software-licenses` | 3 | `functions/software*.php` |
| Fornecedores | `vendors` | 3 | `functions/vendor*.php` |
| Domínios e certificados (verificação diária) | `domain-monitoring` | 3 | `functions/domain.php`, `functions/certificate.php` |
| Documentos, arquivos, templates | `documentation` | 3 | `functions/document_template.php`, `functions/files.php` |
| Serviços (revisões) | `services` | 3 | `functions/service.php` |
| Chamados recorrentes, templates, respostas prontas, menções | `ticket-automation` | 3 | `functions/ticket_template.php`, `functions/canned_response.php`, `functions/ticket_mention.php` |
| Projetos | `projects` | 3 | `functions/project.php` |
| Calendário e iCal | `calendar` | 3 | `functions/calendar.php` |
| Produtos e estoque | `products` | 3 | `functions/products.php` |
| Relatórios e dashboard | `reporting` | 3 | `functions/reports.php`, `functions/dashboard.php` |
| Busca global, notificações | `navigation` | 3 | `functions/search.php`, `functions/notifications.php` |
| IA (provedores, triagem, resumos) | `ai-assist` | 3 | `functions/ai*.php`, `cron/ai_triage.php` |
| Configurações, campos e links personalizados, tags, categorias, impostos | `configuration` | 3 | `functions/settings.php`, `functions/custom_link.php`, `functions/tags.php`, `functions/tax.php` |
| Setup e saúde do sistema | `installation` | 3 | `setup/`, `functions/system_health.php` |
| Idioma da interface | `interface-language` | — | change `add-i18n-infrastructure` |

## Pontos de dependência do upstream

Comportamentos que ainda apontam para o projeto ITFlow original. Esta change só os registra. Cada um pede uma decisão em uma change própria.

| Ponto | Comportamento atual | Impacto no fork |
|---|---|---|
| Autoatualização | `git fetch` do `origin`, comparado com o branch em uso (`software-updates`) | Instalações do fork passam a se atualizar a partir do repositório do fork. É preciso definir o branch de release e garantir que nada chegue lá sem revisão |
| Telemetria | O setup e a tarefa noturna enviam dados a `telemetry.itflow.org` quando habilitada | Dados de instalações do fork chegam ao upstream. Avaliar desligar ou remover |
| Whitelabel | As chaves são validadas com a chave pública do itflow.org | O fork não consegue emitir chaves |
| Links de ajuda | Footer, setup, saúde do sistema e mensagens de erro apontam para docs, fórum e serviços do itflow.org, além do link "Docs" semeado no setup | A documentação do upstream vai divergir do fork |
| Licença | AGPL-3.0 | Qualquer oferta do sistema via rede exige disponibilizar o código-fonte do fork |

## Risks / Trade-offs

- **[Risco] Spec errada passa por documentação confiável.** → Mitigação: cada tarefa confere a spec contra o código antes de marcar como concluída, e os pontos não confirmados ficam marcados como achados.
- **[Risco] As specs ficam desatualizadas sem testes automatizados.** → Mitigação: a regra D4 obriga atualizar a spec na mesma change que muda o comportamento. Os cenários foram escritos para servirem de roteiro de teste manual e de base para testes automatizados no futuro.
- **[Trade-off] Cobertura parcial.** Só 10 das ~36 áreas ficam documentadas nesta change. Aceito, porque o mapa deixa explícito o que falta e em que ordem.

## Open Questions

- Se as specs de prioridade 2 entram em uma segunda change de documentação ou só pela regra D4, conforme as áreas forem tocadas. A decisão pode esperar o fim desta change.
