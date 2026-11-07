# Design

## Context

Ver proposal.md, "Why". Os `.htaccess` em questão:

| Arquivo | Conteúdo hoje | Quem regrava |
|---|---|---|
| `uploads/.htaccess` | `Options -ExecCGI -Indexes`, `php_flag engine off`, `RemoveHandler`/`RemoveType` de scripts e `<FilesMatch>` negando `.php`, `.phtml`, `.phar`, `.phps`, `.cgi`, `.pl`, `.sh` | `backupAssertUploadsGuards()` (`functions/backup.php:229`), a cada restauração |
| `uploads/backups/.htaccess` | `Require all denied`, `Options -ExecCGI -Indexes`, `php_flag engine off` (`BACKUP_UPLOADS_DENY_GUARD`, `functions/backup.php:23`) | `backupHardenStorageDir()` a cada uso de `backupStorageDir()`, e `backupAssertUploadsGuards()` |
| `uploads/{tmp,canned_responses,expenses}/.htaccess` | igual a `BACKUP_UPLOADS_DENY_GUARD` | `backupAssertUploadsGuards()` |
| `scripts/.htaccess` | igual a `BACKUP_UPLOADS_DENY_GUARD` | ninguém (só o arquivo versionado) |

No DDEV o Apache tem `proxy_fcgi_module` e nenhum módulo PHP. Hoje todas as URLs abaixo de `/uploads/` e de `/scripts/` respondem 500, e o log mostra `Invalid command 'php_flag'`. Com PHP-FPM, `php_flag engine off` não teria efeito nem se o Apache aceitasse a diretiva. O que impede executar scripts nos uploads é o `<FilesMatch> Require all denied`, e nas pastas privadas é o `Require all denied`. Hoje essas regras também não chegam a valer, porque o arquivo inteiro falha.

## Goals / Non-Goals

**Goals:**
- Os seis `.htaccess` funcionando com mod_php e com PHP-FPM, com o mesmo resultado de segurança nos dois.
- Os arquivos versionados iguais, byte a byte, ao que o código escreve, para que backup e restauração não deixem a árvore do git suja nem mudem o comportamento.

**Non-Goals:**
- Proteção no nginx, que não lê `.htaccess`. Continua sendo papel da configuração do servidor, como já diz o comentário de `backupHardenStorageDir()`.
- Mudar quais pastas são privadas ou quais extensões são negadas.
- Mandar a correção para o upstream. O fork não acompanha o upstream; se for o caso, fica para depois.
- Corrigir o texto do achado na change arquivada além de apontar esta change: a linha errada (20 em vez de 23) e o impacto subestimado ficam registrados aqui.

## Decisions

### D1. Envolver a diretiva em `<IfModule mod_php.c>`, sem removê-la
- *Por quê:* com mod_php, `php_flag engine off` é uma segunda barreira (o PHP não roda nem num arquivo que escape do `FilesMatch`), e mantê-la deixa a produção com mod_php exatamente como está. `mod_php.c` é o identificador do módulo do PHP 8, e o projeto exige PHP 8.2 ou mais novo.
- *Alternativa descartada:* remover `php_flag`. Funciona no FPM, mas tira uma barreira da produção prevista.
- *Alternativa descartada:* `<IfModule php_module>`. Também funciona no PHP 8. Ficou `mod_php.c` por ser a forma já citada no achado e na documentação do Apache para módulos pelo nome do arquivo-fonte. As duas são equivalentes.

### D2. Uma constante para o trecho, usada nos dois textos
O trecho `<IfModule mod_php.c>\n  php_flag engine off\n</IfModule>\n` vira uma constante em `functions/backup.php`, usada por `BACKUP_UPLOADS_DENY_GUARD` e por `$wanted` em `backupAssertUploadsGuards()`.
- *Por quê:* os dois textos precisam mudar juntos, e o defeito existia justamente nos dois.
- *Alternativa descartada:* editar as duas strings à mão. Funciona, mas deixa o mesmo trecho em dois lugares.

### D3. Os arquivos versionados são gerados a partir do código
Na implementação, os cinco `.htaccess` de `uploads/` são reescritos com a saída das próprias funções, e não editados à mão. `scripts/.htaccess` recebe o mesmo texto de `BACKUP_UPLOADS_DENY_GUARD`. Uma conferência com `cmp` garante a igualdade.
- *Por quê:* se os arquivos e o código divergirem em um byte, o primeiro backup muda o arquivo versionado e o `git status` fica sujo. Isso pode atrapalhar a atualização, que trabalha sobre a árvore do git.

## Risks / Trade-offs

- **[Risco] Erro no `.htaccess` derruba a produção com 500.** É o mesmo tipo de defeito que esta change corrige, só que no modo da produção. → Testar também com mod_php, num contêiner temporário com Apache e mod_php (imagem `php:8.4-apache`, ou a `phpmyadmin:5.2` já baixada, que usa mod_php), servindo uma cópia de `uploads/` e `scripts/`.
- **[Risco] Instalações em que o código já regravou os arquivos.** O conteúdo regravado é igual ao versionado de hoje. A atualização traz o arquivo novo, e o próximo backup ou restauração grava o mesmo texto novo, sem conflito.
- **[Trade-off] Com PHP-FPM, `php_flag` continua sem efeito.** A proteção contra scripts depende só do `FilesMatch`, que agora passa a valer de fato, o que é melhor que hoje, quando nada vale e a pasta fica bloqueada por acidente.

## Migration Plan

Sem migração. A atualização de código entrega os `.htaccess` novos. Para desfazer, basta reverter o commit, e o próximo backup ou restauração volta a escrever o texto antigo.
