# Spec Delta

## Purpose

Define como o ITFlow cria, guarda, retém e restaura backups da instalação, com as garantias de que um backup vazado não pode ser aberto e de que uma restauração não destrói a instalação nem abre brechas no servidor.

## ADDED Requirements

### Requirement: Backups criptografados com chave fora do backup
O sistema SHALL gerar backups como arquivos compactados criptografados com AES-256. A chave é única por instalação e guardada só na configuração local do servidor, nunca no banco, no nome do arquivo ou em URL.

#### Scenario: Backup vazado
- **WHEN** alguém obtém um arquivo de backup sem acesso à configuração do servidor
- **THEN** o conteúdo não pode ser aberto com nada que o próprio backup contenha

### Requirement: Backup gerado fora da requisição web
O sistema SHALL registrar o pedido de backup feito pela administração como pendente e gerá-lo na tarefa agendada de backup, nunca dentro da requisição web.

#### Scenario: Backup manual de uma instalação grande
- **WHEN** um administrador pede um backup em Maintenance > Backup
- **THEN** a página responde na hora e o backup aparece como pendente até a tarefa agendada concluí-lo

### Requirement: Restauração valida antes de destruir
O sistema SHALL verificar a chave e descompactar o arquivo antes de apagar qualquer tabela, e SHALL gravar antes um dump do banco atual para reverter. Se a importação falhar, o banco anterior SHALL ser recolocado.

#### Scenario: Chave errada
- **WHEN** uma restauração é feita com a chave errada
- **THEN** a restauração para antes de tocar no banco e a instalação continua como estava

#### Scenario: Importação falha no meio
- **WHEN** a importação de uma tabela falha durante a restauração
- **THEN** o banco anterior é recolocado

### Requirement: Restauração não altera as proteções do servidor
O sistema SHALL ignorar, ao restaurar, os arquivos que mudam como o servidor web trata uma pasta, como `.htaccess`, `.user.ini` e arquivos PHP nos uploads. As proteções da pasta de uploads SHALL ser regravadas depois de toda restauração, e a pasta de backups SHALL ser preservada.

#### Scenario: Backup adulterado com .htaccess
- **WHEN** um arquivo de backup traz um `.htaccess` dentro dos uploads
- **THEN** a restauração não grava esse arquivo e as proteções originais dos uploads continuam valendo

### Requirement: Retenção nunca apaga o último backup completo
O sistema SHALL aplicar a retenção na tarefa noturna, separada da tarefa de backup, nunca removendo o backup completo mais recente. Arquivos de backup encontrados em disco sem registro no banco SHALL ser adotados, e não apagados.

#### Scenario: Backup da noite falha
- **WHEN** o backup da noite falha
- **THEN** o backup completo anterior continua guardado

### Requirement: Restauração pelo setup só em instalação vazia
O sistema SHALL oferecer a restauração na tela de setup apenas enquanto não existir nenhum usuário cadastrado.

#### Scenario: Setup acessado em instalação em uso
- **WHEN** alguém abre a restauração do setup em uma instalação que já tem usuários
- **THEN** a restauração não está disponível
