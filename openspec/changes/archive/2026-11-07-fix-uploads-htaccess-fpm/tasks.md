# Tasks

Os testes com PHP-FPM usam o DDEV (https://itsm.ddev.site). Os testes com mod_php usam um contêiner temporário com Apache e mod_php, apagado ao final. Os testes de backup e restauração rodam dentro de um snapshot do banco (`ddev snapshot --name antes-<tarefa>`), recolocado ao final, e qualquer backup criado para o teste é apagado.

## 1. Código

- [x] 1.1 Em `functions/backup.php`, criar a constante com o trecho `<IfModule mod_php.c>` / `php_flag engine off` / `</IfModule>` e usá-la em `BACKUP_UPLOADS_DENY_GUARD` e em `$wanted` de `backupAssertUploadsGuards()`. Atualizar o comentário dos dois pontos para explicar por que a diretiva fica condicionada. Validar: `ddev exec php -l functions/backup.php`.

## 2. Arquivos versionados

- [x] 2.1 Reescrever `uploads/.htaccess` com o `$wanted` novo; `uploads/backups/.htaccess`, `uploads/tmp/.htaccess`, `uploads/canned_responses/.htaccess`, `uploads/expenses/.htaccess` e `scripts/.htaccess` com o `BACKUP_UPLOADS_DENY_GUARD` novo. Gerar o texto a partir das constantes do código, com um script descartável rodado por `ddev exec`. Validar: `cmp` de cada arquivo contra a saída do código, e `git diff` mostrando só a troca da linha `php_flag` pelo bloco `<IfModule>`.

## 3. Verificação

- [x] 3.1 PHP-FPM (DDEV), com `curl`. Esperado:
  - `uploads/settings/UtbncfX07YLE6B2Q1YWUdcc1OF9Zgnpe.png` → 200;
  - `uploads/index.php` e um `.php` criado para o teste em `uploads/clients/` (apagado depois) → 403;
  - `uploads/` → 403 (sem listagem);
  - um `.zip` em `uploads/backups/`, `uploads/tmp/x` e `scripts/update_cli.php` → 403;
  - nenhuma linha `Invalid command` nova em `ddev logs -s web`.

  Conferir também no navegador que o logo aparece no login, no portal do cliente e no menu do técnico.
- [x] 3.2 mod_php: subir um contêiner temporário com Apache e mod_php servindo uma cópia de `uploads/` e `scripts/`, com `AllowOverride All`, e repetir os pedidos da 3.1 (os mesmos códigos esperados). Conferir que um `.php` que escape do `FilesMatch` não é executado: copiar para os uploads um arquivo com extensão liberada pelo `FilesMatch` e manipulador de PHP forçado (por exemplo, `.php5`) e verificar que o código não roda, por causa do `php_flag`. Validar: códigos HTTP e saída do teste; apagar o contêiner e a cópia.
- [x] 3.3 Regravação: num snapshot, fazer um backup pela tela e rodar `backupAssertUploadsGuards()` por um script descartável (o que uma restauração faz ao final). Validar: `git status --short uploads scripts` vazio; o logo continua com 200. Apagar o backup criado e recolocar o snapshot.

## 4. Fechamento

- [x] 4.1 Entrada em `CHANGELOG.md`, em Bug Fixes, para administradores: em servidores Apache com PHP-FPM, nada da pasta de uploads era servido (logo, avatares, fotos, anexos) por causa de uma diretiva que só o mod_php aceita; com mod_php ou nginx nada muda. Validar: leitura da entrada.
- [x] 4.2 Atualizar o achado de backups em `openspec/changes/archive/2026-11-06-document-system-baseline/tasks.md` com o nome desta change. Rodar `openspec validate fix-uploads-htaccess-fpm --strict`. Validar: sem erros.

## Workflow follow-up

- Arquivar a change (`/opsx:archive`) para levar o requisito novo para `openspec/specs/backups/spec.md`.
