# Tasks

Cada tarefa de verificação segue o mesmo roteiro. Primeiro, ler o código das fontes listadas no mapa do design.md para a capability. Depois, confirmar ou corrigir cada requisito e cenário, acrescentar as regras críticas que faltarem e anotar em **Achados** toda divergência entre o comportamento atual e o esperado. A tarefa está concluída quando todos os cenários foram conferidos no código. Os que puderem ser reproduzidos devem ser conferidos também em https://itsm.ddev.site.

## 1. Preparação

- [x] 1.1 Concluir o setup do ambiente DDEV e carregar dados de exemplo (clientes, contatos, chamados, faturas, contrato) para reproduzir os cenários; verificar logando como admin e como técnico restrito a um cliente

## 2. Plataforma e segurança

- [x] 2.1 Verificar e completar `access-control`: níveis por módulo, gate do admin, `clientScopeSql`/`enforceClientAccess`, CSRF, audit; reproduzir no navegador "ID de outro cliente na URL" e "técnico só com leitura"
- [x] 2.2 Verificar e completar `client-portal-access`: perfis de contato, "View as contact", MFA do portal; reproduzir com um contato só técnico abrindo faturas
- [x] 2.3 Verificar e completar `scheduled-jobs`: registro, reivindicação, bloqueio, Run Now, interruptor geral, `interval_safe`; verificar com `ddev exec php cron/cron.php` e com o interruptor desligado
- [x] 2.4 Verificar e completar `backups`: criptografia, pendente via cron, validações da restauração, retenção; verificar gerando um backup com `ddev exec php cron/backup.php` e restaurando com `scripts/restore_cli.php` em um snapshot (`ddev snapshot`)
- [x] 2.5 Verificar e completar `software-updates`: verificação, fila, migrações sequenciais com retomada; verificar o comportamento de retomada lendo `admin/database_updates.php` e rodando a tela de update em uma base atualizada

## 3. Dinheiro

- [x] 3.1 Verificar e completar `invoicing`: transições de status (quem muda para Viewed, Overdue e Cancelled), regras de numeração, recorrentes, multas, lembretes, extratos; verificar rodando `ddev exec php cron/nightly_tasks.php` com uma fatura vencida há 30 dias
- [ ] 3.2 Verificar e completar `online-payments`: pagamento múltiplo, autopay, recusa, taxas; verificar lendo `functions/billing_pay.php`, o bloco de autopay de `cron/nightly_tasks.php` e `cron/payment_provider_fees.php` (sem Stripe real no DDEV)
- [ ] 3.3 Verificar e completar `contracts`: ciclo de vida, condições em vigor, assinatura, horas pré-pagas; reproduzir uma assinatura completa pelo link guest e um consumo parcial de horas

## 4. Atendimento

- [ ] 4.1 Verificar e completar `ticketing`: status, reabertura, fechamento automático, tempo trabalhado, leitura de e-mail; reproduzir fechamento automático com `ddev exec php cron/nightly_tasks.php` e um e-mail com número no assunto (IMAP opcional; senão, por leitura do código)
- [ ] 4.2 Verificar e completar `ticket-sla`: origem do SLA, horário comercial, pausa, estágios e percentual de aviso; reproduzir com um SLA de minutos e `ddev exec php cron/ticket_sla.php`

## 5. Fechamento

- [ ] 5.1 Revisar com o usuário as 10 specs e os achados; verificar que nenhuma spec traz requisito marcado como não confirmado
- [ ] 5.2 Documentar no `CLAUDE.md` a regra "documentar antes de mudar" (D4) e onde ficam as specs; verificar que o texto aponta para `openspec/specs/`
- [ ] 5.3 Rodar `openspec validate document-system-baseline --strict` sem erros

## Achados

<!-- Divergências encontradas durante a verificação: comportamento atual × esperado, arquivo:linha, e se merece change própria. -->

- **backups: download direto de backup responde 500, não 403.** O guarda de `uploads/` (`BACKUP_UPLOADS_DENY_GUARD`, `functions/backup.php:20`, e `uploads/.htaccess`) usa `php_flag engine off`, que o Apache só entende com mod_php. Com PHP-FPM (DDEV, e a maioria das instalações novas) a diretiva derruba o `.htaccess` inteiro com erro 500. O arquivo continua protegido, mas por acidente: as demais regras do mesmo `.htaccess` (`Require all denied`, `-Indexes`) não chegam a ser aplicadas por causa do erro. Merece change própria: envolver `php_flag` em `<IfModule mod_php.c>`.
- **invoicing: cancelar e marcar Non-Billable não verificam o status atual.** `agent/post/invoice.php:213` e `:246` gravam o novo status sem olhar o anterior, então uma fatura Paid ou Partial pode ser cancelada por um link montado à mão, ficando com os pagamentos registrados. A tela só mostra os botões nos status certos (`agent/invoice.php:202` e `:254`: Non-Billable em Sent/Viewed com valor zero, Cancelar fora de Cancelled/Paid/Non-Billable), então a falha é só do handler. Merece change própria: recusar no handler.
- **scheduled-jobs/invoicing: a segunda execução da tarefa noturna no mesmo dia repete notificações internas.** Reproduzido com Run Now duas vezes: "Pending Tickets", "Invoice Overdue" e "Update" foram criadas de novo para cada técnico. A multa, o histórico e o e-mail de lembrete ao cliente não se repetiram, então o requisito "não repetem efeitos" vale para o cliente e não para as notificações internas. Impacto baixo (só ruído). Merece change própria se o Run Now da noturna for usado com frequência.

## Workflow follow-up

- Arquivar a change (`/opsx:archive`) para promover as 10 specs para `openspec/specs/`.
- Abrir changes próprias para os pontos de dependência do upstream (autoatualização, telemetria, whitelabel, links) e para os achados que pedirem correção.
- Decidir se a prioridade 2 do mapa vira uma segunda change de documentação.
