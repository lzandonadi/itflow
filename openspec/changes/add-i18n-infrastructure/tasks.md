# Tasks

## 1. Ambiente de validação

- [x] 1.1 Criar o ambiente DDEV (`itsm`, PHP 8.4, apache-fpm, MariaDB 11.8) em https://itsm.ddev.site; verificado com `ddev exec php -m` (intl, mysqli, gd, curl, mbstring, xml, openssl, fileinfo)
- [x] 1.2 Confirmar no ICU do container as saídas dos skeletons do D7 em en_US e pt_BR; tabela do design.md ajustada
- [ ] 1.3 Concluir o `/setup` em https://itsm.ddev.site (banco `db`, usuário `db`, senha `db`, host `db`) e apontar o SMTP para o Mailpit (`localhost:1025`, sem criptografia); verificar o login e um e-mail de teste chegando em `ddev mailpit`

## 2. Núcleo de tradução

- [ ] 2.1 Criar `functions/i18n.php` com `i18nLanguages()`, `i18nLocale()` (resolução preguiçosa com `try/catch`, D4), `i18nSetLocale()`, `i18nHtmlLang()` e o carregamento do dicionário com cache estático; verificar com `ddev exec php -l`
- [ ] 2.2 Implementar `__()`, `_x()` e `_n()` (D3): `vsprintf` só com argumentos, captura de `ValueError` com fallback para o inglês, regra de plural CLDR pt/en e declaração protegida contra nome já existente; verificar com `ddev exec php` cobrindo tradução encontrada, fallback, contexto, plural 0/1/2 e placeholder faltando
- [ ] 2.3 Carregar `functions/i18n.php` no `functions.php`; verificar com `ddev exec php -l functions.php` e abrindo `/login.php` e `/agent/` sem erros no log do PHP
- [ ] 2.4 Criar `lang/pt_BR.php` (array vazio ou com os textos desta change) e `lang/GLOSSARY.md` com os quatro termos fixados e as convenções de estilo (D10); verificar com `ddev exec php -l lang/pt_BR.php`

## 3. Configuração do idioma

- [ ] 3.1 Adicionar `config_language varchar(10) NOT NULL DEFAULT 'en_US'` à tabela `settings` no `db.sql`; verificar importando o `db.sql` em um banco vazio do MariaDB
- [ ] 3.2 Criar `admin/database_updates/3.5.6.php` (guard `FROM_DB_UPDATER`, re-executável, verifica a coluna em `information_schema` antes do `ALTER`); verificar rodando Admin > Update Database em uma base 3.5.5 e rodando de novo sem erro
- [ ] 3.3 Verificar o cenário "código antes da migração": com a base ainda em 3.5.5, abrir login, agent e a tela de update do banco e confirmar que tudo carrega em inglês, sem exceção
- [ ] 3.4 Registrar o campo `config_language` em `functions/settings.php` e renomear o rótulo de `company_locale` para "Number and currency format" (D5); verificar que a busca de configurações encontra o campo
- [ ] 3.5 Adicionar o select "Interface language" em `admin/settings_localization.php`, com os nomes de cada idioma escritos no próprio idioma; verificar visualmente a página em claro e escuro
- [ ] 3.6 Estender `edit_localization` em `admin/post/settings_localization.php` para validar contra `i18nLanguages()`, gravar com `settingsWrite('settings', ...)` e incluir no `settingsSaid()`; verificar salvando pt_BR (o audit log registra), enviando um valor inválido pelo DevTools (recusado com erro no campo) e com `ddev exec php -l`

## 4. Superfícies de exibição

- [ ] 4.1 Trocar `lang="en"` por `i18nHtmlLang()` em `includes/header.php`, `client/includes/header.php`, `guest/includes/guest_header.php` e `login.php`; verificar com `ddev exec php -l` e inspecionando o HTML com o idioma em pt_BR e em en_US
- [ ] 4.2 Converter para `__()` os textos que esta change altera ou cria (página de Localization e mensagens de erro do handler) e traduzi-los em `lang/pt_BR.php`; verificar que a página aparece em pt-BR com o idioma em pt_BR e idêntica à de hoje em en_US
- [ ] 4.3 Converter um texto do `cron/mail_queue.php` ou de um e-mail de notificação simples para provar o idioma no cron; verificar rodando `ddev exec php cron/<job>.php` com pt_BR e conferindo o e-mail na fila

## 5. Datas

- [ ] 5.1 Implementar `formatDate()` em `functions/format.php` com os estilos do D7, aceitando timestamp, `DateTimeInterface` e string do MySQL, e devolvendo string vazia para valor nulo ou inválido; verificar com `ddev exec php` para cada estilo em en_US e pt_BR
- [ ] 5.2 Aplicar `formatDate()` em uma tela piloto (por exemplo, a lista de chamados) para validar o padrão de substituição; verificar a tela nos dois idiomas
- [ ] 5.3 Documentar no `CLAUDE.md` a regra "`date()` só para formatos numéricos e de gravação; `formatDate()` para exibir"

## 6. Ponte JavaScript

- [ ] 6.1 Criar `lang/js/pt_BR.php` e `i18nJsDictionary()` (vazio em en_US); verificar com `ddev exec php -l`
- [ ] 6.2 Emitir `window.itflowI18n` nos três headers e no `login.php`, com `JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE`, antes dos scripts do projeto; verificar no DevTools que o objeto existe e que um texto com `</script>` no dicionário não quebra a página
- [ ] 6.3 Criar `js/i18n.js` com `__()` (placeholders `%s` e `%1$s`, fallback) e carregá-lo nos footers e no login antes dos demais scripts do projeto; verificar no console com `__('texto existente')`, `__('texto inexistente')` e um caso com placeholder
- [ ] 6.4 Converter um texto JS piloto (por exemplo, uma confirmação SweetAlert) e traduzi-lo; verificar nos dois idiomas

## 7. Verificação de traduções

- [ ] 7.1 Criar `scripts/i18n_check.php` (D9) com saída `arquivo:linha`, órfãs, chamadas não literais, divergência de placeholders e código de saída 1 quando faltar tradução; verificar rodando `ddev exec php scripts/i18n_check.php pt_BR` com um texto propositalmente sem tradução (sai com 1) e depois traduzido (sai com 0)
- [ ] 7.2 Documentar no `CLAUDE.md` a API (`__`/`_x`/`_n`, JS `__`), a regra de nunca traduzir valores gravados, o uso de `formatDate()` e o comando `ddev exec php scripts/i18n_check.php pt_BR`; verificar que o comando documentado roda como descrito

## 8. Integração

- [ ] 8.1 Em uma instalação nova (`db.sql`) e em uma atualizada (3.5.5 → 3.5.6), percorrer login, dashboard, chamado, portal do cliente e uma página guest em en_US (sem nenhuma diferença visual em relação à master) e depois em pt_BR (`lang` correto e textos piloto traduzidos)
- [ ] 8.2 Rodar `ddev exec php -l` em todos os arquivos alterados e `ddev exec php scripts/i18n_check.php pt_BR` sem pendências

## Workflow follow-up

- Abrir PR da branch `feature/traducao-pt-br` com a infraestrutura antes de iniciar a change `translate-ui-pt-br`.
- Arquivar a change (`/opsx:archive`) depois do merge, promovendo a spec `interface-language` para `openspec/specs/`.
