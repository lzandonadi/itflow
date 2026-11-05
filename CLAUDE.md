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
- UI strings are hardcoded English in the PHP files — there is no i18n/translation layer. Locale only affects number/currency formatting (`companies.company_locale`, `includes/settings_localization_array.php`).
- User-facing changes are described in `CHANGELOG.md` under the release heading (Upgrading / Security / New Features & Updates / Bug Fixes), written in plain language for MSP admins.
