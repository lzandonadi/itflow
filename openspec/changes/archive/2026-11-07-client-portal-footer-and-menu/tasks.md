# Tasks

As conferências no DDEV (https://itsm.ddev.site) rodam dentro de um snapshot do banco (`ddev snapshot --name antes-portal-layout`), recolocado e apagado ao final. Elas usam:
- a contato Maria (`maria@cliente-teste.local`, contato principal do Cliente Teste) para o portal;
- o administrador Lucas (usuário 1) para a tela de configurações.

As senhas temporárias valem só dentro do snapshot. Cookie jars e capturas ficam no scratchpad e são apagados ao final.

## 1. Spec de base

- [x] 1.1 Conferir no código os requisitos de base de `specs/client-portal-layout/spec.md` que não mudam:
  - cabeçalho: logo, ou iniciais e nome; "Novo chamado" a partir de 576 px; conta; cabeçalho fixo;
  - navegação no celular: barra de abas com Faturamento só para quem tem acesso, e menu lateral.

  Corrigir a delta se algo divergir. Validar: `openspec validate client-portal-footer-and-menu --strict`.

## 2. Banco

- [x] 2.1 Em `db.sql`, acrescentar `config_client_portal_footer_text varchar(200) NOT NULL DEFAULT ''` depois de `config_client_portal_signup`.

  Criar `admin/database_updates/3.5.7.php` no modelo da `3.5.6.php`:
  - cabeçalho dizendo para que serve;
  - guarda `FROM_DB_UPDATER`;
  - checagem em `information_schema` antes do `ALTER TABLE settings ADD COLUMN ... AFTER config_client_portal_signup`;
  - nenhum texto preenchido.

  Antes, conferir que a 3.5.7 ainda é a próxima versão em disco. Validar:
  - `ddev exec php -l admin/database_updates/3.5.7.php`;
  - importar o `db.sql` num banco vazio, como faz o CI;
  - rodar a atualização no DDEV (Admin > Atualizações de banco, ou `ddev exec php scripts/update_cli.php` com a opção de banco) e conferir a coluna com `ddev mysql -e "show columns from settings like 'config_client_portal_footer_text'"`;
  - rodar a atualização de novo não dá erro.

## 3. Leitura e rodapé

- [x] 3.1 Em `functions/portal.php`, criar `portalFooterTextReady()` (versão do banco >= 3.5.7, no modelo de `portalInvitesReady()`) e `portalFooterText()` (o texto salvo, ou `''` se não estiver pronto). Em `client/includes/footer.php`, mostrar `escapeHtml(portalFooterText())` quando não vazio, senão o bloco de hoje (`if (!$config_whitelabel_enabled)` com `__('Powered by ITFlow')`). Validar:
  - `ddev exec php -l` nos dois arquivos;
  - com a coluna vazia, o rodapé de `client/index.php` (curl como Maria) continua com "Desenvolvido com ITFlow".

## 4. Configuração no Admin

- [x] 4.1 Em `admin/settings_client_portal.php`, quando `portalFooterTextReady()`, acrescentar a seção "Rodapé" com `settingsInput('config_client_portal_footer_text', ...)` (`maxlength` 200, rótulo e ajuda do design D3) e ler o valor salvo para o campo.

  Em `admin/post/settings_client_portal.php`, no `edit_client_portal_settings`:
  - quando pronto, incluir a coluna;
  - acima de 200 caracteres, `settingsRefuse()` com a mensagem no campo.

  Em `functions/settings.php`, registrar o campo para a busca de configurações. Em `lang/pt_BR/settings.php`, acrescentar o rótulo, a ajuda, a mensagem de erro e as palavras de busca.

  Validar:
  - `php -l` nos arquivos;
  - `ddev exec php scripts/i18n_check.php pt_BR` sem pendências;
  - `--hardcoded` no escopo oficial (`agent admin client guest includes js functions login.php join.php`) em 0.

## 5. Menu

- [x] 5.1 Em `css/itflow_custom.css`, no `@media (min-width: 992px)` junto das regras `.itflow-portal-nav`, dar ao `.itflow-portal-nav` `flex: 1 1 auto; justify-content: center; min-width: 0` (design D4). Nada de estilo inline nem mudança na marcação, salvo se o teste mostrar necessidade. Validar: conferência visual na tarefa 6.3.

## 6. Verificação

- [x] 6.1 Admin, com curl como Lucas, no snapshot:
  - a seção "Rodapé" aparece em `admin/settings_client_portal.php`;
  - salvar "Presença Digital, Automação e Inteligência Artificial, Produtividade e Integração" com token CSRF grava a coluna e cria uma linha no audit log;
  - o mesmo POST sem token dá 403 e não muda nada;
  - um texto de 201 caracteres é recusado com o erro no campo e não grava;
  - um técnico sem acesso ao Admin não consegue salvar (pedido não tratado, sem mudança).
- [x] 6.2 Portal, com curl como Maria:
  - em `client/index.php`, `client/tickets.php` e `client/invoices.php`, o rodapé tem o nome da empresa e a frase salva, e não tem "Desenvolvido com ITFlow";
  - com `config_whitelabel_enabled = 1`, posto dentro do snapshot, o rodapé fica igual;
  - salvar `<b>teste & "aspas"</b>` mostra o texto escapado (o `escapeSql()` tira a marcação ao salvar, então conferir o que foi gravado e o que aparece);
  - com o campo esvaziado pela tela, o rodapé volta a "Desenvolvido com ITFlow";
  - com o campo vazio e o white-label ligado, só aparece o nome da empresa;
  - a tela de login continua igual.
- [x] 6.3 Visual, com o Chrome headless (`google-chrome --headless --screenshot --ignore-certificate-errors`):
  - salvar o HTML autenticado de `client/index.php`, com a frase configurada, num arquivo do scratchpad com `<base href="https://itsm.ddev.site/">`;
  - capturar a 1280×800, 992×800 e 390×844;
  - a 1280 e a 992: o menu está centralizado entre a logo e os botões, sem encostar na logo e sem transbordar;
  - a 390: o menu de cima some, a barra de abas aparece e a frase do rodapé quebra de linha sem cortar.

  Se o Chrome headless não renderizar a página, registrar isso, e o usuário confere no navegador. Ao final:
  - recolocar e apagar o snapshot;
  - remover as capturas e os cookie jars;
  - configurar a frase de novo no DDEV pela tela, porque a restauração apaga o que foi salvo durante o teste. A coluna continua, porque a atualização de banco roda antes do snapshot.

## 7. Fechamento

- [x] 7.1 Entradas em `CHANGELOG.md`:
  - em "New Features & Updates": texto do rodapé do portal configurável em Admin > Configurações > Portal do cliente, e menu do portal centralizado no cabeçalho;
  - em "Upgrading": a atualização de banco 3.5.7 acrescenta a configuração, vazia; preencha o texto depois de atualizar.

  Validar: leitura das entradas.
- [x] 7.2 `openspec validate client-portal-footer-and-menu --strict` sem erros. Revisar o diff, que deve tocar só:
  - `db.sql` e `admin/database_updates/3.5.7.php`;
  - `functions/portal.php`, `functions/settings.php`, `client/includes/footer.php` e `css/itflow_custom.css`;
  - `admin/settings_client_portal.php` e `admin/post/settings_client_portal.php`;
  - `lang/pt_BR/settings.php` e `CHANGELOG.md`.

## Workflow follow-up

- Arquivar a change (`/opsx:archive`), criando `openspec/specs/client-portal-layout/spec.md`.
- Em produção, depois de atualizar o código e rodar a atualização de banco 3.5.7: preencher Admin > Configurações > Portal do cliente > Rodapé com "Presença Digital, Automação e Inteligência Artificial, Produtividade e Integração".
