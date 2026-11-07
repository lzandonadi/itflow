# Proposal

## Why

Todos os textos de interface do ITFlow estão escritos em inglês diretamente no PHP e no JS, sem nenhuma camada de tradução. O fork passa a ser mantido de forma independente e precisa atender usuários e clientes finais em português do Brasil. Por isso, a interface precisa ser traduzível, mantendo o inglês como padrão. Esta change cria apenas a base de tradução. A migração dos textos fica na change `translate-ui-pt-br`, que depende desta.

## What Changes

- Nova camada de tradução em `functions/i18n.php`:
  - `__()` traduz um texto, `_x()` traduz com contexto e `_n()` trata plural.
  - A chave é o próprio texto em inglês, e o fallback devolve o inglês quando falta tradução.
- Dicionários em arquivos PHP por idioma, em `lang/<locale>.php`. O `en_US` é o idioma de origem e não tem arquivo. Nesta change o `pt_BR` nasce com o conjunto mínimo usado pela própria infraestrutura.
- Nova configuração global `settings.config_language`, com padrão `en_US`. A mudança de schema vai para o `db.sql` e para a migração `admin/database_updates/3.5.6.php`.
- Novo campo "Idioma da interface" em Admin > Settings > Localization, separado de `company_locale`, que continua controlando apenas o formato de números e moeda.
- O idioma configurado vale para todos os pontos de entrada que exibem texto: agent, admin, portal do cliente, guest, login e e-mails gerados pelo cron. O atributo `<html lang>` passa a refletir esse idioma.
- Novo helper `formatDate()`, baseado em `IntlDateFormatter`. Ele escreve datas com nomes de mês e dia no idioma da interface e substitui progressivamente as chamadas `date('M j, Y')` e similares.
- Ponte de tradução para JavaScript: um dicionário reduzido é injetado nos headers, e um helper `__()` fica disponível em `js/i18n.js`.
- Script de linha de comando `scripts/i18n_check.php`, que lista textos usados em `__()`, `_x()` e `_n()` sem tradução e traduções que não são mais usadas.
- Novo glossário pt-BR em `lang/GLOSSARY.md`, com os termos já fixados (Ticket = Chamado, Agent/Technician = Técnico, Quote = Orçamento, Asset = Ativo).

## Capabilities

### New Capabilities
- `interface-language`: idioma em que a interface, os e-mails e os documentos são apresentados. Cobre a seleção do idioma pelo admin, a tradução com fallback para inglês, a formatação de datas no idioma escolhido e a garantia de que valores gravados no banco nunca são traduzidos.

### Modified Capabilities
<!-- Nenhuma: não existem specs anteriores no projeto. -->

## Impact

- **Código novo:** `functions/i18n.php` (carregado por `functions.php`), `lang/pt_BR.php`, `lang/js/pt_BR.php`, `lang/GLOSSARY.md`, `js/i18n.js`, `scripts/i18n_check.php`.
- **Código alterado:**
  - `functions.php`, `functions/settings.php` (registro do campo) e `functions/format.php` (`formatDate`).
  - `admin/settings_localization.php` e `admin/post/settings_localization.php`.
  - `includes/header.php`, `client/includes/header.php`, `guest/includes/guest_header.php`, `login.php` (`<html lang>` e dicionário JS).
- **Banco:** a coluna `settings.config_language` é adicionada no `db.sql` e na migração `3.5.6`. Nenhum dado existente muda.
- **Dependências:** nenhuma nova. A base usa a extensão `intl`, que o ITFlow já exige.
- **Compatibilidade:** em uma instalação sem configuração, ou antes da migração rodar, tudo continua em inglês exatamente como hoje.
- **Fora desta change:** traduzir os textos existentes, que fica para a change `translate-ui-pt-br`.
