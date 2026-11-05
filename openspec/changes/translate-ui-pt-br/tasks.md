# Tasks

Cada lote de migração de textos segue o mesmo roteiro:

1. **Glossário.** Registrar os termos da área em `lang/GLOSSARY.md` (D9).
2. **Conversão.** Envolver os textos em `__()`, `_x()` ou `_n()`, com frase inteira por chave (D3), marcação conforme D2 e helpers de status e prioridade (D4). `logAudit` fica em inglês (D8).
3. **Tradução.** Preencher `lang/pt_BR.php` e `lang/js/pt_BR.php`.

Cada lote é um commit revisável e só fecha quando passa em todos os itens abaixo:

- **Sintaxe:** `ddev exec php -l` sem erros em todo arquivo tocado.
- **Traduções:** `ddev exec php scripts/i18n_check.php pt_BR` com saída 0.
- **Sobras:** `--hardcoded <caminhos do lote>` sem candidatos pendentes; os falsos positivos levam `i18n-ignore`.
- **Inglês:** em `en_US`, as telas da área renderizadas sem diferença de texto visível em relação à master.
- **Português:** em pt-BR, as telas listadas na tarefa conferidas em https://itsm.ddev.site, inclusive um filtro ou uma busca da área.

## 1. Ferramentas e base comum

- [x] 1.1 Acrescentar ao `scripts/i18n_check.php` a opção `--hardcoded <caminho>...` (D7) e a comparação de tags HTML entre original e tradução (D2). Documentar a opção no `CLAUDE.md`. Verificar: `--hardcoded agent/modals/ticket` lista candidatos com arquivo:linha; uma tradução de teste com `<strong>` faltando aparece em "Placeholders differ" e é removida em seguida.
- [x] 1.2 Criar os helpers `ticketStatusLabel()`, `ticketPriorityLabel()`, `invoiceStatusLabel()` e `quoteStatusLabel()` (D4), com entradas `_x` no dicionário e os contextos no glossário. Verificar com um script descartável via `ddev exec php`: status de sistema com nome original sai traduzido, status renomeado e status personalizado "Open" saem como estão, e en_US devolve o original.
- [x] 1.3 Localizar os componentes de terceiros (D5): DataTables (`js/app.js`, `js/portal_datatables.js`), flatpickr (`js/app.js`, `js/date_filter.js`), FullCalendar (`agent/js/calendar.js`), intl-tel-input (`js/phone_inputs.js`) e TinyMCE (`js/portal.js` e os inits do agent, com o pacote em `js/vendor-lang/tinymce/pt_BR.js`). Verificar: em pt-BR, a lista de clientes mostra busca, paginação e tabela vazia em português; o seletor de data mostra meses em português e o valor postado continua `Y-m-d`; o calendário, o editor de uma resposta de chamado e o campo de telefone do contato aparecem em português; no portal do cliente não há erro de CSP no console.
- [x] 1.4 Converter a base do agent: `includes/` (header, nav lateral, top nav, footer, modais e JS compartilhados) e `agent/includes/`. Verificar: menu lateral, barra superior, busca global, notificações e rodapé em pt-BR.
- [x] 1.5 Converter a base do admin, do portal e do guest: `admin/includes/`, `client/includes/`, `guest/includes/`. Verificar: o menu do admin, o menu e o rodapé do portal (logado como Maria) e o cabeçalho de uma página guest em pt-BR.
- [ ] 1.6 Converter as mensagens comuns de `functions/` usadas por toda área: validação de CSRF, permissão negada, registro não encontrado e acesso a cliente fora do escopo (`functions/security.php`, `functions/request.php`, `functions/app.php`). Verificar: abrir um registro de outro cliente com um técnico restrito e postar sem token mostram a mensagem em pt-BR; o app log continua em inglês.

## 2. Acesso e conta

- [ ] 2.1 Converter login, MFA, redefinição de senha, convite e cadastro: `login.php`, `join.php`, `agent/user/mfa_enforcement.php`, `client/login_reset.php`, `client/join.php`, `client/signup.php`, `functions/sign_in.php`, `functions/user_invite.php`, `functions/portal_invite.php` e os e-mails de cada um. Verificar: login com senha errada, configuração de MFA, redefinição de senha pelo portal e convite de técnico em pt-BR; os e-mails no Mailpit (`ddev mailpit`) em pt-BR.
- [ ] 2.2 Converter o perfil e as preferências do técnico (`agent/user/`) e o perfil do contato no portal (`client/profile.php`). Verificar: troca de senha, tema e MFA do técnico e do contato em pt-BR.

## 3. Chamados

- [ ] 3.1 Converter a listagem e o quadro: `agent/tickets.php`, `ticket_list.php`, `ticket_kanban.php`, filtros e ações em massa (`agent/modals/ticket/` bulk) e `agent/post/ticket.php` (blocos `bulk_*`), usando `ticketStatusLabel()` e `ticketPriorityLabel()`. Verificar: filtrar por status e prioridade em pt-BR retorna os mesmos chamados que em en_US; as ações em massa de status e atribuição em pt-BR.
- [ ] 3.2 Converter o chamado aberto: `agent/ticket.php`, os demais modais de `agent/modals/ticket/`, os handlers restantes de `agent/post/ticket*.php`, `ticket_attachment.php`, tarefas, menções (`functions/ticket_mention.php`) e campos (`functions/ticket_fields.php`). Verificar: responder, resolver, fechar, reabrir, mesclar e agendar o TCK-2 em pt-BR, com a resposta automática gravada em pt-BR.
- [ ] 3.3 Converter os e-mails e as respostas automáticas de chamado (`functions/ticket.php`, `cron/ticket_email_parser.php`, `cron/nightly_tasks.php` na parte de chamados, `cron/ticket_sla.php` nas notificações). Verificar: os e-mails de chamado criado, de resposta, de resolvido e de fechado em pt-BR no Mailpit, com o `[TCK-n]` intacto no assunto; uma resposta por e-mail com esse assunto ainda entra no chamado certo (pelo parser ou pela leitura do código, se não houver IMAP).
- [ ] 3.4 Converter chamados recorrentes, modelos de chamado no agent, SLA visível ao técnico e respostas prontas: `agent/recurring_tickets.php`, `agent/modals/recurring_ticket/`, `agent/js/canned_response_*.js`, `functions/sla.php` e `functions/canned_response.php`. Verificar: criar um chamado recorrente e usar uma resposta pronta em pt-BR.
- [ ] 3.5 Converter os chamados no portal e no guest: `client/tickets.php`, `ticket.php`, `ticket_add.php`, `ticket_view_all.php`, `ticket_attachment.php`, `guest/guest_view_ticket.php` e `guest_approve_ticket_task.php`. Verificar: como Maria, abrir, responder e resolver um chamado; abrir o link guest do chamado em pt-BR.

## 4. Clientes e documentação

- [ ] 4.1 Converter clientes, contatos, locais e fornecedores: páginas `agent/clients.php`, `client_overview.php`, `contact*.php`, `location*.php`, `vendor*.php`, os modais e handlers correspondentes, `agent/client_onboarding*`, e `functions/contact.php`, `location.php`, `vendor.php` e `client_onboarding.php`. Verificar: criar um cliente pelo onboarding, editar o contato e o local, e ver a visão geral do cliente em pt-BR.
- [ ] 4.2 Converter ativos, software e licenças, redes, racks e credenciais: páginas, modais, handlers, `functions/asset*.php`, `software.php`, `network.php`, `rack.php` e `credential.php`, e o equivalente no portal (`client/assets.php`, `asset.php`, `licenses.php`). Verificar: criar um ativo com interface de rede, uma credencial e uma licença; ver os ativos no portal em pt-BR.
- [ ] 4.3 Converter documentos, arquivos, domínios, certificados, serviços, links e compartilhamentos: páginas, modais, handlers, `functions/files.php`, `domain.php`, `certificate.php`, `service.php`, `share.php` e `links.php`, o portal (`client/documents.php`, `document*.php`, `file.php`, `domains.php`, `certificates.php`) e o guest (`share.php`, `guest_download_file.php`, `guest_view_item.php`). Verificar: enviar um arquivo, criar um documento, compartilhar uma credencial por link e abrir o link guest em pt-BR.
- [ ] 4.4 Converter a exportação de dados do cliente (`functions/client_export.php` e `client_export_pdf.php`). Verificar: exportar o Cliente Teste em PDF e HTML em pt-BR, com os rótulos em português e os valores no formato regional.

## 5. Vendas

- [ ] 5.1 Converter orçamentos: `agent/quotes.php`, `quote.php`, modais, handlers, `guest/guest_view_quote.php`, `client/quotes.php` e `quote.php`, usando `quoteStatusLabel()`. Verificar: criar, enviar e aprovar um orçamento pelo link guest em pt-BR; filtrar por status.
- [ ] 5.2 Converter faturas, faturas recorrentes e produtos: `agent/invoice*.php`, `recurring_invoice*.php`, `product*.php`, modais e handlers, `functions/billing.php`, `products.php` e `tax.php`, usando `invoiceStatusLabel()`. Verificar: criar uma fatura, adicionar itens, marcar como enviada (o banco grava `Sent`), criar uma recorrente e reajustar preços em pt-BR.
- [ ] 5.3 Converter os PDFs e e-mails de cobrança (`functions/billing_pdf.php` e `billing_email.php`) e os lembretes e multas de `cron/nightly_tasks.php`. Verificar: baixar o PDF da fatura, do orçamento e do extrato em pt-BR e conferir os rótulos; os e-mails de fatura e de lembrete em pt-BR no Mailpit.
- [ ] 5.4 Converter o pagamento no portal e no guest: `client/invoices.php`, `invoice.php`, `unpaid_invoices.php`, `pay.php`, `payments.php`, `receipt.php`, `statement.php`, `recurring_invoice*.php`, `saved_payment_methods.php` e `pdf.php`, `guest/guest_view_invoice.php`, `guest_pay_*`, e `functions/billing_pay.php`. Verificar: como Maria, ver as faturas, o extrato e o recibo; abrir a fatura pelo link guest em pt-BR (sem Stripe real, só as telas).

## 6. Financeiro

- [ ] 6.1 Converter pagamentos, receitas, despesas e despesas recorrentes, incluindo as importações: páginas, modais, handlers, `functions/payments.php`, `income*.php`, `expense*.php` e `payment_method.php`. Verificar: registrar um pagamento, uma despesa com recibo e importar um CSV de receitas em pt-BR.
- [ ] 6.2 Converter contas, transferências, transações, conciliação e deslocamentos: páginas, modais, handlers, `functions/accounts.php`, `reconcile.php`, `trips.php` e `payment_provider.php`. Verificar: criar uma transferência, abrir a conciliação e registrar um deslocamento em pt-BR.

## 7. Contratos, projetos e agenda

- [ ] 7.1 Converter contratos: `agent/contract*.php`, modais, handlers, `functions/contract*.php` (incluindo o PDF, a assinatura e as horas), `guest/guest_view_contract.php` e `client/contract*.php`. Verificar: criar um contrato, assinar pelo link guest e consumir horas pré-pagas em pt-BR; baixar o PDF do contrato.
- [ ] 7.2 Converter projetos e o calendário: `agent/project*.php`, `calendar.php`, os modais e handlers, `agent/js/calendar.js`, `functions/project.php` e `calendar.php`, e `guest/guest_calendar_feed.php` (só os textos para pessoas). Verificar: criar um projeto com chamados e um evento no calendário em pt-BR.

## 8. Painel, busca e relatórios

- [ ] 8.1 Converter o painel, a busca global, as notificações e o histórico: `agent/dashboard.php`, `global_search.php`, `audit_log.php` (só a moldura, os registros ficam em inglês), `functions/dashboard.php`, `search.php` e `notifications.php`, e `client/index.php` e `activity.php`. Verificar: painel do técnico e painel do portal em pt-BR; buscar um contato pela busca global.
- [ ] 8.2 Converter os relatórios (`agent/reports/` e `functions/reports.php`), incluindo as exportações CSV. Os cabeçalhos do CSV seguem o idioma, e os valores gravados e as colunas de identificador não mudam. Verificar: abrir cada relatório em pt-BR e exportar um CSV.
- [ ] 8.3 Converter as funções de IA visíveis ao técnico (`functions/ai*.php` e `cron/ai_triage.php` na parte que grava texto para pessoas). Verificar: em pt-BR, os rótulos e mensagens das ações de IA em um chamado e em um documento (sem provedor real, só as telas e as mensagens de erro).

## 9. Admin

- [ ] 9.1 Converter as páginas de configuração: `admin/settings*.php`, handlers, os rótulos e textos de ajuda do registro em `functions/settings.php` e `admin/modules.php`. Verificar: abrir cada página de configuração em pt-BR, salvar uma alteração e ver o audit log em inglês.
- [ ] 9.2 Converter usuários, perfis de acesso, provedores de identidade, chaves de API, logs e logins: `admin/users.php`, `roles.php`, `identity_providers.php`, `api_keys.php`, `audit_logs.php`, `app_logs.php` e `sign_ins.php` (só a moldura), modais, handlers, e `functions/roles.php` e `api_keys.php`. Verificar: criar um técnico, um perfil de acesso e uma chave de API em pt-BR.
- [ ] 9.3 Converter catálogos e modelos: status de chamado (com `ticketStatusLabel()`), categorias, tags, impostos, SLA, links personalizados, modelos de chamado, projeto, documento, contrato, fornecedor e software, respostas prontas, métodos e provedores de pagamento, com páginas, modais, handlers e as funções `*_template*.php` correspondentes. Verificar: renomear um status de sistema e ver o nome digitado em todas as telas; criar uma categoria e um modelo de documento em pt-BR.
- [ ] 9.4 Converter manutenção e primeiros passos: `admin/cron.php`, `backup*.php`, `update.php`, `database_updates.php` (só a tela), `mail_queue.php`, `mail_preview.php`, `debug.php`, `getting_started*` e `starter_content*.php` (só a tela, o conteúdo gravado fica em inglês), e as funções `functions/backup.php`, `mail_queue.php`, `email_preview.php`, `system_health.php` e `getting_started.php`. Verificar: rodar um job em Run Now, abrir a fila de e-mail e o Get Started em pt-BR; a saída do `cron.php` continua em inglês.

## 10. Fechamento

- [ ] 10.1 Rodar `--hardcoded` no projeto inteiro e resolver ou marcar com `i18n-ignore` cada candidato restante. Verificar: a varredura de `agent admin client guest includes js functions login.php join.php` sai vazia.
- [ ] 10.2 Revisar o dicionário inteiro contra o glossário, termo a termo (Chamado, Técnico, Orçamento, Ativo e os termos de cada lote). Ajustar as divergências e as entradas sem uso. Verificar: `i18n_check.php pt_BR` sem "Unused" e uma busca por `[Tt]icket` nas traduções sem ocorrências fora de identificadores.
- [ ] 10.3 Percorrer em pt-BR os roteiros dos cenários da spec (técnico, admin, portal e guest, PDFs, componentes, status de sistema) e, em en_US, a comparação com a master das telas principais de cada área. Medir o tempo do painel com o dicionário completo (risco do design). Verificar: os cenários de `specs/interface-language/spec.md` conferidos e as diferenças em en_US só as esperadas.
- [ ] 10.4 Descrever a tradução no `CHANGELOG.md` (New Features & Updates, para administradores) e atualizar a seção i18n do `CLAUDE.md` com os helpers de status, a regra de marcação nas chaves e `--hardcoded`. Verificar: a leitura do texto e `openspec validate translate-ui-pt-br --strict` sem erros.

## Workflow follow-up

- Arquivar a change (`/opsx:archive`) para incorporar os novos requisitos à spec `interface-language`.
- Avaliar changes próprias para: conteúdo inicial (starter content e seed) em pt-BR, escolha de idioma no setup e idioma por usuário ou por contato.
