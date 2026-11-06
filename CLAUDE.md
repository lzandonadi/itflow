# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

ITFlow is a self-hosted PSA for MSPs (ticketing, client documentation, contracts, billing, client portal). This checkout is a fork (`origin` = `lzandonadi/itflow`) of upstream `itflow-org/itflow`.

**`CONTRIBUTING.md` is the authoritative conventions document — read it before any non-trivial change.** What follows is the condensed version plus the things that take reading several files to discover.

## Stack and commands

Plain procedural PHP 8.2+, MariaDB/MySQL via `mysqli` (string-built queries, no prepared statements), Bootstrap 4 / AdminLTE, jQuery, DataTables. No framework, ORM, template engine, build step, or test suite.

- **No `composer install` / `npm install`.** Every third-party library is vendored in `libs/` (and `libs/vendor/`). Never add a runtime Composer/npm dependency, and never edit files in `libs/` (it is `-text` in `.gitattributes` — byte-for-byte upstream).
- **Local environment is DDEV** (`.ddev/config.yaml`: project `itsm`, PHP 8.4, apache-fpm — needed because ITFlow relies on `.htaccess` — MariaDB 11.8). `ddev start`, then https://itsm.ddev.site. First run goes through `/setup/` (DB host `db`, user/password/database `db`), which generates the gitignored `config.php`. Run every PHP command inside the container with `ddev exec php ...`; PHP is not installed in WSL itself. Point ITFlow's SMTP at Mailpit (`localhost:1025`, no encryption) and read mail with `ddev mailpit`. `ddev mysql` opens the DB shell.
- **Lint** (what CI runs on PRs — `overtrue/phplint`): `ddev exec php -l path/to/file.php`.
- **Schema lint** (CI, when `db.sql` changes): import `db.sql` into an empty MariaDB database.
- `index.php` routes to `/setup`, `/agent/<start page>`, `/client/`, or `/login.php`.
- **Cron:** in production, a single crontab entry `* * * * * php /path/to/itflow/cron/cron.php`. Not scheduled in DDEV; run the dispatcher or a single job by hand, e.g. `ddev exec php cron/mail_queue.php`.
- **Planning:** non-trivial work is planned with OpenSpec (`openspec/`, CLI `openspec`, slash commands `/opsx:*`); artifacts are written in pt-BR.
- **CLI scripts:** `scripts/setup_cli.php`, `scripts/update_cli.php`, `scripts/restore_cli.php`.

## Architecture

**Portals** — each is a self-contained directory with its own `includes/`, `post/`, `modals/`, `custom/`:
- `agent/` — technician app; most feature work lives here.
- `admin/` — settings, roles, mail, migrations; admin-only.
- `client/` — logged-in client portal (contacts of a client).
- `guest/` — unauthenticated, URL-key flows (view/pay invoice, view quote/ticket, shared credentials/files).
- `api/v1/<module>/{create,read,update,delete,...}.php` — key-authenticated JSON API; shared plumbing in `api/v1/*.php` (`validate_api_key.php`, `enforce_api_rbac.php`, `*_output.php`).

Root-level `includes/`, `post/`, `modals/`, `js/`, `css/` are **shared** across portals; anything inside a portal directory is scoped to it. `functions.php` is only a loader for the topical helper files in `functions/` — put new helpers in the matching topical file.

**Read pages** require their portal's `includes/inc_all*.php`, which chains `config.php` → `functions.php` → `check_login.php` → header/nav partials and sets implicit globals (`$mysqli`, `$session_user_id`, `$session_name`, and on client-scoped pages via `inc_all_client.php`, an already-`intval`'d `$client_id`). Check the include chain before adding a query for a variable that "doesn't exist".

**Write actions** go through `<portal>/post.php`, which defines `FROM_POST_HANDLER` and globs every `post/*.php` **except `*_model.php`**. Each handler file starts with `defined('FROM_POST_HANDLER') || die(...)` and is a series of `if (isset($_POST['action_name'])) { ... }` blocks. `*_model.php` files are shared create/edit field parsers — the suffix is reserved, so a handler accidentally named `*_model.php` silently never loads (unhandled POSTs are logged to App Logs as a `Request` warning). Modals post to `post.php` relative to the *page* URL, so a modal reused across portals is handled by the portal that opened it.

**Cron** — `cron/cron.php` is a dispatcher; jobs are registered in `includes/cron_jobs.php` and their schedule/enabled state lives in the `cron_jobs` table. Jobs share one PHP process: never `exit()`/`die()` (use `cronJobStop()`), never declare functions/classes in a job (shared helpers go in `functions/`), be safe to run twice a day, and check `config_enable_cron` in the job header.

**Schema changes** need both `db.sql` (fresh installs) and a new `admin/database_updates/<x.y.z>.php` named for the version it upgrades *to*, with the `FROM_DB_UPDATER` guard. The latest DB version is derived from the highest filename; never edit a historical migration. `APP_VERSION` (`includes/app_version.php`) is a separate YY.MM release number.

**`custom/` hooks** — core calls `triggerCustomAction($trigger, $entity_id)` at meaningful events; core must never depend on anything in `custom/`.

## Interface language (i18n)

The interface is translatable (`functions/i18n.php`); en_US is the default and the source language, pt_BR is the first translation (`lang/pt_BR.php` plus one file per area in `lang/pt_BR/`, merged; terms and style in `lang/GLOSSARY.md`). The language is global — `settings.config_language`, Admin > Settings > Localization — and separate from `companies.company_locale`, which only formats numbers and money. Most screens are still hardcoded English while the strings are being converted (OpenSpec change `translate-ui-pt-br`).

- **PHP:** `__('Add Ticket')`, `__('Ticket %s resolved', $ref)` (vsprintf; use `%1$s` where order may change), `_x('Open', 'ticket status')` (entry `'ticket status::Open'`), `_n('%d ticket', '%d tickets', $n)` (entry `[one, other]`). The key is the English text; a missing entry falls back to English. Return values are plain text — arguments still get `escapeHtml()`/`escapeSql()` at the call site as usual.
- **JS:** `__('Are you sure?')` from `js/i18n.js`, reading the dictionary in `<html data-itflow-i18n>` (from `lang/js/<locale>.php`, only strings scripts use). No inline `<script>` — the sign-in pages and client portal send `default-src 'self'`. A page with its own `<html>` that loads project JS uses `<html <?= i18nHtmlAttributes() ?>>` and `<script src="/js/i18n.js">` in `<head>`.
- **Third-party widgets** (DataTables, flatpickr, FullCalendar, intl-tel-input, TinyMCE) get their language from `itflowI18n.<widget>()` in `js/i18n.js` (in `js/app.js`, `itflowI18nOptions('<widget>')`), which returns nothing in English. TinyMCE's pt-BR pack lives in `js/vendor-lang/tinymce/`, outside `libs/`.
- **Never translate stored or compared values** (`invoice_status = 'Sent'`, `<option value>`, ticket status names from the DB) — translate only where shown, with `_x()` and a context. Audit log, app log, API responses and CLI/cron output stay English; text written to the DB for people (system ticket replies, notifications, emails) is generated in the language of the moment. When one text goes both to people and to a log, build the log's copy with `i18nEnglish(fn () => ...)`. `escapeSql()` strips tags: translated text with HTML written to the DB (a link in a system ticket note) goes through `mysqli_real_escape_string()`.
- **Check:** `ddev exec php scripts/i18n_check.php pt_BR` lists missing translations (file:line), placeholder or HTML-tag mismatches, the same English translated differently in two files, unused entries and non-literal calls; exits 1 on missing/mismatch. Mark a reviewed dynamic call (`__($label)`) with an `i18n-dynamic` comment on its line.
- **Find leftovers:** `ddev exec php scripts/i18n_check.php pt_BR --hardcoded agent/modals/ticket agent/ticket.php` lists what still looks like fixed English (text between tags, title/placeholder/aria-label/alt/data-placeholder/data-confirm-*, sentences in PHP and JS strings). It is a heuristic; mark a false positive's line with `i18n-ignore`.
- **Lists' empty states:** pass `$empty_state['thing']` translated and plural (`__('recurring tickets')`) - `emptyStateHtml()` builds "No %s yet" / "No %s match" / "No archived %s" from it, worded in pt_BR to take any plural; give `title`/`match_title` only for a better sentence.
- **Dates for display go through `formatDate($value, $style)`** (`date`, `date_short`, `datetime`, `datetime_short`, `datetime_weekday`, `date_weekday`, `time`, `weekday_short`, `month`, `month_long`, `month_year`, `month_year_short`, `date_long`, `day_long`) so month and day names follow the interface language. Keep `date()` only for numeric formats that are stored, posted, exported or compared (`Y-m-d`, `H:i`) — `formatDate()` output is not stable text (ICU punctuation, U+202F before AM/PM).

## Non-negotiable rules

1. **SQL:** every interpolated value is `intval()` (unquoted) or `escapeSql()` (inside quotes) — including values read back from the DB and arguments to `logAudit()`, `appNotify()`, `logHistory()`, `logTicketHistory()`, which build INSERTs internally. Fetch helpers like `getFieldById()` return raw values; the caller escapes.
2. **CSRF:** `validateCSRFToken()` (no argument) is the first line of every state-changing block.
3. **Permissions:** in `agent/post/` blocks, `enforceUserPermission('module_x', 1|2|3)` (read/write/delete). Modules: `module_client`, `module_support`, `module_sales`, `module_financial`, `module_credential`, `module_reporting`. Admin handlers inherit the gate from `admin/post.php`; the client portal uses `enforceContactCan(...)`.
4. **Client scoping:** `enforceClientAccess()` after loading a single record; append `clientScopeSql('<resource>_client_id')` to every list query (on the resource's own column, needs a `WHERE`). The old `$access_permission_query` global is gone — using it silently disables scoping.
5. **Output:** `escapeHtml()` at the point the row is read into variables (ints via `intval`), then echo raw. Don't double-escape.
6. **No new `exec`/`shell_exec`/`eval`.**

## Conventions

- Columns are prefixed with the entity name (`ticket_id`, `client_name`; `calendar_events` → `event_*`, `invoice_items` → `item_*`). Select explicit columns, not `*` (except `api/v1/*/read.php`, where the row is the output contract). Use `SELECT 1` for count-only queries.
- After a state change: `logAudit(...)`, optionally `appNotify()` / `triggerCustomAction()`, then `flashAlert($msg, $type)` and `redirect()`.
- Only technician-typed time goes in `ticket_reply_time_worked`; system-generated replies record `'00:00:00'`.
- When changing a single action, check for a parallel `bulk_*` implementation and update it too.
- Old helper names were removed in 2026 and fatal if called (`sanitizeInput` → `escapeSql`, `nullable_htmlentities` → `escapeHtml`, `logAction` → `logAudit`, `flash_alert` → `flashAlert`, `customAction` → `triggerCustomAction`; full list in CONTRIBUTING.md).
- 4-space indent, LF line endings (enforced by `.editorconfig`/`.gitattributes`). Match surrounding code; don't reformat untouched code.

- User-facing changes are described in `CHANGELOG.md` under the release heading (Upgrading / Security / New Features & Updates / Bug Fixes), written in plain language for MSP admins.
