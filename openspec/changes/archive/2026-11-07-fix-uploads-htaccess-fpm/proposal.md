# Proposal

## Why

As proteções de `uploads/` e de `scripts/` usam `php_flag engine off`, diretiva que só existe quando o PHP roda como módulo do Apache (mod_php). Com o Apache usando PHP-FPM, que é o caso do DDEV, de painéis de hospedagem e de quem liga HTTP/2, o Apache não consegue ler o `.htaccess` e responde 500 para tudo abaixo da pasta. Por isso não aparecem logo, favicon, avatares, fotos de ativos e anexos, e o download direto de backup dá 500 em vez de 403. Esse era o achado de backups da change `document-system-baseline`, e o impacto foi subestimado lá. A produção prevista (Apache com mod_php, ou nginx) não é afetada, então a prioridade é baixa. O ajuste vale pelo ambiente de desenvolvimento e para a produção não parar se o modo do PHP mudar.

## What Changes

- Os seis `.htaccess` versionados passam a aplicar `php_flag engine off` só quando o mod_php está carregado (`<IfModule mod_php.c>`). Os seis são `uploads/`, `uploads/backups/`, `uploads/tmp/`, `uploads/canned_responses/`, `uploads/expenses/` e `scripts/`. O resto das regras não muda.
- O código que regrava as proteções de uploads a cada uso de backup e a cada restauração passa a escrever o mesmo texto novo, igual byte a byte aos arquivos versionados.
- Com o Apache usando PHP-FPM, os arquivos enviados passam a ser entregues normalmente, scripts em `uploads/` passam a ser recusados com 403 e as pastas privadas e `scripts/` passam a responder 403 em vez de 500.
- Com mod_php, o comportamento fica igual ao de hoje.

## Capabilities

### New Capabilities
<!-- Nenhuma. -->

### Modified Capabilities
- `backups`: entra um requisito dizendo que as proteções da pasta de uploads valem em qualquer modo do PHP no Apache, entregando os arquivos enviados e negando scripts e pastas privadas, e que o que o sistema regrava é igual ao que vem na instalação.

## Impact

- **Arquivos:** `uploads/.htaccess`, `uploads/{backups,tmp,canned_responses,expenses}/.htaccess`, `scripts/.htaccess` e `functions/backup.php` (`BACKUP_UPLOADS_DENY_GUARD` e `backupAssertUploadsGuards()`).
- **Banco e migração:** nenhum. Instalações existentes recebem os `.htaccess` novos pela própria atualização, porque são arquivos versionados.
- **Produção com mod_php:** sem mudança de comportamento. Mesmo assim, a correção é testada também com mod_php, porque é o modo da produção prevista.
- **nginx:** não lê `.htaccess`, então não muda nada. A proteção continua dependendo da configuração do servidor, como hoje.
