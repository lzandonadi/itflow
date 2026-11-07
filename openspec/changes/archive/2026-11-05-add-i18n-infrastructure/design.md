# Design

## Context

Hoje não existe nenhuma camada de tradução. As medições a seguir foram feitas no repositório sem contar `libs/`:

- ~3.500 textos de tela, ~1.000 atributos `placeholder` e `title`, ~580 mensagens únicas de `flashAlert`, e-mails montados em `functions/` e textos em 71 arquivos JS mais 232 PHPs com `<script>` inline.
- ~400 chamadas `date('M j, Y')` e variações, que geram nomes de mês em inglês.
- Status e prioridades são gravados e comparados como texto em inglês, por exemplo `invoice_status = 'Draft'` e `ticket_priority = 'High'`.
- `includes/inc_set_timezone.php` é carregado por todos os pontos de entrada (páginas, cron, API) e já lê `settings` para o fuso horário.
- A página Admin > Settings > Localization e o handler `edit_localization` já salvam `company_locale`, `company_currency` e `config_timezone` através dos helpers `settingsCurrent()`, `settingsWrite()`, `settingsRefuse()` e `settingsSaid()`. Os campos são registrados em `functions/settings.php`.
- `libs/vendor/symfony/translation` declara uma função `t()`, só que dentro do namespace `Symfony\Component\Translation`. Ainda assim, o nome `t` fica evitado no PHP para não gerar confusão.
- A extensão `intl` já é requisito do projeto (`numfmt_create` é usado em vários pontos).

## Goals / Non-Goals

**Goals:**
- Uma única API de tradução, barata em runtime e no padrão do projeto: PHP procedural, sem build e sem dependências novas.
- Seguro por padrão: a falta de tradução, de configuração ou de coluna no banco resulta em inglês, nunca em erro.
- Pronto para a migração em massa dos textos na change `translate-ui-pt-br`.

**Non-Goals:**
- Traduzir os textos existentes. Esta change converte apenas os textos das telas que ela própria altera.
- Idioma por técnico ou por contato do cliente. A configuração é global. A modelagem permite adicionar `user_config_language` depois sem mudar a API.
- Traduzir respostas da API, logs, saída de cron e de CLI, comentários e o assistente de setup.
- Traduzir dados digitados por usuários (nomes de status customizados, categorias, templates).
- Formatos de número e moeda, que continuam com `company_locale`.
- Arquivos de idioma das bibliotecas de front-end (flatpickr, FullCalendar, DataTables, TinyMCE), que entram na change de tradução.

## Decisions

### D1. A chave é o texto em inglês
`__('Add Ticket')` busca `'Add Ticket'` no dicionário e devolve o texto original quando não encontra.
- *Por quê:* o fallback para inglês sai de graça, o código continua legível e não é preciso inventar 6 mil nomes de chave.
- *Alternativa descartada:* chaves semânticas como `ticket.add.button`. Exigiriam nomear tudo, manter um `en_US.php` completo e deixariam o código ilegível.
- *Custo aceito:* mudar o texto em inglês invalida a tradução. O `i18n_check` acusa a diferença.

### D2. Dicionários em arrays PHP, não gettext
`lang/pt_BR.php` faz `return ['Add Ticket' => 'Adicionar chamado', ...];`. Com o opcache, o array fica em memória compartilhada.
- *Alternativa descartada:* gettext nativo. Ele exige que o locale esteja instalado no SO (`locale -a`), arquivos `.mo` compilados e guarda cache por processo do PHP-FPM, o que contraria o modelo "descompacta e roda".

### D3. API de tradução
- `__(string $text, ...$args)`: só passa por `vsprintf` quando há `$args`. Assim, `"100%"` sem argumentos não quebra. Os textos devem usar posições (`%1$s`) quando o tradutor pode precisar reordenar.
- `_x(string $text, string $context, ...$args)`: desambigua. A chave no dicionário é `"context::text"`, por exemplo `'ticket status::Open' => 'Aberto'` e `'Open' => 'Abrir'`.
- `_n(string $singular, string $plural, int $count, ...$args)`: a entrada no dicionário é uma lista `[forma_um, forma_outros]`. A regra de plural vem do CLDR (pt: um para 0 e 1; en: um só para 1).
- O retorno é texto puro. As traduções são código revisado e podem ser ecoadas direto. Os `$args` seguem as regras de escape atuais: o chamador passa `escapeHtml(...)` para a tela e `escapeSql(...)` para a query.
- Os nomes `__`, `_x` e `_n` seguem a convenção do gettext. Antes de declarar, o arquivo confirma que não existem e falha de forma explícita se outra lib já os tiver declarado.

### D4. Resolução do idioma: preguiçosa e tolerante a falhas
`i18nLocale()` resolve o idioma uma vez por processo e guarda em static:
1. Se já foi definido por `i18nSetLocale()`, usa esse valor.
2. Se não, havendo `$mysqli`, executa `SELECT config_language FROM settings WHERE company_id = 1` dentro de `try/catch (mysqli_sql_exception)`. Isso cobre a janela entre atualizar os arquivos e rodar a migração.
3. Se o valor não estiver em `i18nLanguages()`, ou em qualquer falha, usa `en_US`.

O idioma não fica em `inc_set_timezone.php`, porque um erro de coluna inexistente ali derrubaria o fuso horário e todas as páginas. A resolução preguiçosa também cobre o cron (um processo, um idioma) e telas sem banco, como o setup. A API não chama `__()`.

`i18nLanguages()` devolve a lista fechada de idiomas suportados: `['en_US' => 'English (US)', 'pt_BR' => 'Português (Brasil)']`. Ela é declarada no código e não é descoberta pelos arquivos em `lang/`, para que um arquivo solto não vire uma opção válida.

### D5. Configuração e tela
- `settings.config_language VARCHAR(10) NOT NULL DEFAULT 'en_US'`, criada no `db.sql` e na migração `3.5.6.php`. A migração é re-executável e verifica antes se a coluna já existe, no padrão do `3.5.5.php`.
- O campo `config_language` é registrado em `functions/settings.php` (página `localization`, seção "Language and currency"). O antigo "Language" (`company_locale`) é renomeado na tela para "Number and currency format", para deixar claro que são coisas diferentes.
- `edit_localization` valida o valor contra `i18nLanguages()`, grava com `settingsWrite('settings', ...)` e entra no mesmo `settingsSaid()` do audit. O handler admin herda o gate de admin do dispatcher.
- O nome de cada idioma aparece no próprio idioma ("Português (Brasil)"), para ser reconhecível mesmo com a interface no idioma errado.

### D6. `<html lang>`
`i18nHtmlLang()` devolve o código BCP 47 (`pt-BR`, `en-US`). É usado em `includes/header.php`, `client/includes/header.php`, `guest/includes/guest_header.php` e `login.php`.

### D7. Datas: `formatDate()`
`formatDate($value, string $style = 'date')` aceita timestamp, `DateTimeInterface` ou string de data do MySQL. Ele usa `IntlDatePatternGenerator` (PHP 8.1+) sobre um *skeleton* por estilo, e por isso cada idioma recebe a ordem e a pontuação naturais:

| estilo | substitui | skeleton | en_US | pt_BR |
|---|---|---|---|---|
| `date` | `M j, Y` | `yMMMd` | Nov 5, 2026 | 5 de nov. de 2026 |
| `date_short` | `M j` | `MMMd` | Nov 5 | 5 de nov. |
| `datetime` | `M j, Y g:i A` | `yMMMdjmm` | Nov 5, 2026, 3:04 PM | 5 de nov. de 2026, 15:04 |
| `datetime_short` | `M j, g:i A` | `MMMdjmm` | Nov 5, 3:04 PM | 5 de nov., 15:04 |
| `time` | `g:i A` | `jmm` | 3:04 PM | 15:04 |
| `month` | `M` | `MMM` | Nov | nov. |
| `month_year` | `F Y` | `yMMMM` | November 2026 | novembro de 2026 |
| `date_long` | `F j, Y` | `yMMMMd` | November 5, 2026 | 5 de novembro de 2026 |

Saídas conferidas no ICU do ambiente DDEV (PHP 8.4). O fuso é o `date_default_timezone_get()`, já definido por `inc_set_timezone.php`. Formatos numéricos usados em inputs, CSV ou SQL (`Y-m-d`, `H:i`) **não** mudam e continuam com `date()`. O helper existe para exibir datas, nunca para gravá-las.

*Ressalva:* em en_US a saída do ICU difere levemente do `date()` atual: `datetime` ganha uma vírgula antes da hora. É aceitável e fica documentado no glossário.

### D8. Ponte JS
- `lang/js/pt_BR.php` é um subconjunto, só com os textos usados por JS. Ele **não** inclui o dicionário inteiro, para não colocar centenas de KB em cada página.
- O dicionário vai num atributo `data-itflow-i18n` do `<html>` (JSON passado por `escapeHtml`), e **não** num `<script>` inline. O login e o portal do cliente servem `Content-Security-Policy: default-src 'self'`, que bloqueia scripts inline, e a área do técnico trata os inline existentes como dívida a não aumentar. Os headers já usam esse padrão para passar o país ao intl-tel-input. Em en_US o atributo vale `{}`.
- `js/i18n.js` lê o atributo, expõe `__(text, ...args)` com substituição `%s`/`%1$s` e fallback para o texto original e é carregado no `<head>`, antes de todos os scripts do projeto.

### D9. `scripts/i18n_check.php`
Um script CLI (a pasta `scripts/` já nega acesso web) que:
- Varre `*.php` e `*.js` fora de `libs/` procurando chamadas `__('...')`, `_x('...', '...')` e `_n('...', '...')` com literal string.
- Compara o resultado com `lang/<locale>.php` e `lang/js/<locale>.php`.
- Lista o que falta, com `arquivo:linha`, e as traduções órfãs. Sai com código 1 se faltar alguma tradução.
- Também avisa sobre chamadas com argumento não literal (`__($var)`), que precisam de revisão manual, e sobre texto com `%` cuja tradução tem uma quantidade diferente de placeholders.

### D10. Glossário
`lang/GLOSSARY.md` traz os termos fixados (Ticket → Chamado, Agent/Technician → Técnico, Quote → Orçamento, Asset → Ativo) e as convenções de estilo pt-BR:
- Botões no infinitivo ("Adicionar chamado").
- Caixa de sentença.
- Sem tradução de nomes próprios e de produto (Stripe, Microsoft 365).

O documento é ampliado ao longo da change de tradução.

## Risks / Trade-offs

- **[Risco] Traduzir um valor do banco por engano.** Por exemplo, `__($status)` usado como valor de `<option>` ou dentro de uma comparação faz o banco gravar "Rascunho". → Mitigação: está na spec, no glossário e no `CLAUDE.md`. O `i18n_check` lista chamadas com argumento variável para revisão. A tradução de enumerados é feita só por `_x()` com contexto explícito, na exibição.
- **[Risco] Placeholder quebrado na tradução.** `vsprintf` lança `ValueError` quando faltam argumentos. → Mitigação: o `i18n_check` compara a quantidade de placeholders. Em runtime, `__()` captura `ValueError` e devolve o texto em inglês formatado.
- **[Risco] Conflito de nome `__`/`_n`/`_x` com uma lib futura.** → Mitigação: a declaração é protegida e falha de forma explícita, o que torna o conflito visível no primeiro request, e não silencioso.
- **[Trade-off] Chave = texto em inglês.** Uma correção de digitação no inglês perde a tradução até ser reaplicada. Aceito em troca do fallback gratuito.
- **[Trade-off] Idioma global.** Uma instalação com técnicos de idiomas diferentes não é atendida. Fica fora do escopo e pode ser adicionado depois sem mudar a API.
- **[Risco] Custo de desempenho.** Um array de ~6k entradas é carregado por request. → Com o opcache, o custo de inclusão é desprezível. Sem opcache, são alguns ms, o que é aceitável.

## Migration Plan

1. O deploy dos arquivos funciona antes da migração: a coluna ausente resulta em `en_US` (D4).
2. `admin/database_updates/3.5.6.php` adiciona `config_language` com default `en_US`. Nenhuma instalação muda de idioma sozinha.
3. O admin troca o idioma em Localization quando quiser.
4. **Rollback:** voltar o campo para English (US) restaura a interface original. Remover a coluna não é necessário, porque o código antigo a ignora.
