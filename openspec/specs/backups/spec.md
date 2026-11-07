# backups Specification

## Purpose
Define como o ITFlow cria, guarda, retém e restaura backups da instalação, com as garantias de que um backup vazado não pode ser aberto e de que uma restauração não destrói a instalação nem abre brechas no servidor.

## Requirements

### Requirement: Backups criptografados com chave fora do backup
O sistema SHALL gerar backups como arquivos compactados criptografados com AES-256. A chave é única por instalação e guardada só na configuração local do servidor, nunca no banco, no nome do arquivo ou em URL.

#### Scenario: Backup vazado
- **WHEN** alguém obtém um arquivo de backup sem acesso à configuração do servidor
- **THEN** o conteúdo não pode ser aberto com nada que o próprio backup contenha

### Requirement: Tipos de backup
O sistema SHALL oferecer três tipos de backup: completo (banco e arquivos enviados), só banco e chave mestra. A tarefa agendada gera só os dois primeiros; a chave mestra exige um administrador logado.

#### Scenario: Backup agendado
- **WHEN** a tarefa de backup roda no horário
- **THEN** ela gera um backup completo, uma vez por dia, mesmo que seja chamada de novo no mesmo dia

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
O sistema SHALL aplicar a retenção na tarefa noturna, separada da tarefa de backup, por idade e por quantidade (por padrão, 30 dias e 5 backups), nunca removendo o backup completo mais recente. Um backup ainda marcado como em andamento depois de seis horas SHALL ser dado como concluído se o arquivo existir, ou como falho se não existir. Arquivos de backup encontrados em disco sem registro no banco SHALL ser adotados, e não apagados.

#### Scenario: Backup da noite falha
- **WHEN** o backup da noite falha
- **THEN** o backup completo anterior continua guardado

### Requirement: Arquivos de backup não são servidos pela web
O sistema SHALL impedir que os arquivos de backup sejam baixados diretamente pelo endereço da pasta onde ficam.

#### Scenario: Endereço do arquivo adivinhado
- **WHEN** alguém pede pelo navegador o caminho de um arquivo de backup
- **THEN** o arquivo não é entregue

### Requirement: Restauração pelo setup só em instalação vazia
O sistema SHALL oferecer a restauração na tela de setup apenas enquanto não existir nenhum usuário cadastrado.

#### Scenario: Setup acessado em instalação em uso
- **WHEN** alguém abre a restauração do setup em uma instalação que já tem usuários
- **THEN** a restauração não está disponível e o setup indica a restauração pela linha de comando

### Requirement: Proteções dos uploads valem em qualquer modo do PHP no Apache
O sistema SHALL escrever proteções da pasta de uploads que o Apache aceite tanto com o PHP como módulo quanto com o PHP-FPM. Os arquivos enviados SHALL ser entregues normalmente, scripts dentro dos uploads SHALL ser recusados sem executar, e as pastas privadas (backups, temporários, respostas prontas, despesas) SHALL ser negadas. As proteções que o sistema regrava em backups e restaurações SHALL ser iguais às que vêm na instalação.

#### Scenario: Logo da empresa com PHP-FPM
- **WHEN** o Apache roda o PHP via PHP-FPM e o navegador pede o logo da empresa salvo nos uploads
- **THEN** a imagem é entregue

#### Scenario: Script colocado nos uploads
- **WHEN** alguém pede pelo navegador um arquivo `.php` dentro dos uploads, com o PHP como módulo ou via PHP-FPM
- **THEN** o pedido é recusado com 403 e o script não é executado

#### Scenario: Endereço de backup com PHP-FPM
- **WHEN** o Apache roda o PHP via PHP-FPM e alguém pede pelo navegador o caminho de um arquivo de backup
- **THEN** o pedido é recusado com 403, e não com erro do servidor

#### Scenario: Proteções regravadas
- **WHEN** um backup é feito ou uma restauração termina
- **THEN** as proteções gravadas nos uploads são idênticas às que acompanham o código, e os arquivos enviados continuam sendo entregues
