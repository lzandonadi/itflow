# Spec Delta

## ADDED Requirements

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
