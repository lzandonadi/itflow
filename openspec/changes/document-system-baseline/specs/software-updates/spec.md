# Spec Delta

## Purpose

Define como uma instalação descobre e aplica novas versões do código e como as mudanças de banco de cada versão são aplicadas, em ordem e com retomada segura em caso de falha.

## ADDED Requirements

### Requirement: Verificação diária de atualização
O sistema SHALL verificar uma vez por dia, no repositório git de origem da instalação, se existe uma versão mais nova do branch em uso, e SHALL guardar o resultado para a tela Maintenance > Update. Em um servidor onde o PHP não pode executar comandos externos, SHALL informar que não é possível verificar, sem interromper as demais tarefas.

#### Scenario: Nova versão no repositório de origem
- **WHEN** o branch de origem tem commits que a instalação ainda não tem
- **THEN** a tela de atualização mostra a versão disponível e os commits pendentes

#### Scenario: Servidor sem permissão de executar comandos
- **WHEN** o PHP do servidor bloqueia a execução de comandos externos
- **THEN** a verificação é registrada como impossível e a tarefa noturna continua

### Requirement: Atualização aplicada pelo cron ou pela linha de comando
O sistema SHALL aplicar uma atualização pedida na tela em Queue Update pela tarefa agendada de atualização, e não na requisição web. A mesma atualização SHALL poder ser feita pelo script de linha de comando.

#### Scenario: Atualização pela tela
- **WHEN** um administrador clica em Queue Update
- **THEN** a tarefa agendada atualiza os arquivos e aplica as migrações de banco na próxima execução

### Requirement: Migrações de banco sequenciais com retomada
O sistema SHALL aplicar em ordem crescente de versão todas as migrações de banco pendentes, avançando a versão registrada do banco após cada uma. Na primeira falha, SHALL parar com a versão na última migração concluída, e uma nova execução SHALL retomar pela que falhou.

#### Scenario: Três migrações pendentes, a segunda falha
- **WHEN** a atualização aplica três migrações e a segunda falha
- **THEN** a versão do banco fica na primeira, e a próxima execução tenta de novo a segunda e depois a terceira

### Requirement: Instalação nova nasce na versão mais recente do banco
O sistema SHALL criar o banco de uma instalação nova já no esquema mais recente, equivalente a aplicar todas as migrações.

#### Scenario: Setup de uma instalação nova
- **WHEN** o ITFlow é instalado do zero
- **THEN** a versão do banco é a da migração mais recente e não há migrações pendentes
