# access-control Specification

## Purpose
Define quem pode abrir e alterar o quê na área do técnico e na administração: papéis com níveis por módulo, papel de administrador, restrição de técnicos a determinados clientes e proteção das ações contra requisições forjadas.

## Requirements

### Requirement: Níveis de permissão por módulo
O sistema SHALL controlar o acesso do técnico por papel, com um nível por módulo: nenhum, leitura, modificação (adicionar, editar, arquivar e o trabalho do dia a dia) ou completo (inclui excluir). Os módulos são clientes, suporte, vendas, financeiro, credenciais, relatórios e IA. Um papel sem nível em um módulo não abre esse módulo. Os níveis valem para todas as formas de acesso: telas, janelas, chamadas internas da interface e API.

#### Scenario: Técnico só com leitura em vendas
- **WHEN** um técnico cujo papel tem leitura em vendas tenta editar um orçamento
- **THEN** a ação é recusada com uma mensagem que diz que o papel só pode ver vendas, e nada é gravado

#### Scenario: Exclusão exige nível completo
- **WHEN** um técnico com nível de modificação em suporte tenta excluir um chamado
- **THEN** a exclusão é recusada

#### Scenario: Exportação conta como leitura
- **WHEN** um técnico com leitura no módulo exporta a lista em CSV ou PDF
- **THEN** a exportação é permitida

### Requirement: Papel de administrador
O sistema SHALL dar ao papel de administrador acesso a todos os módulos, a todos os clientes e à área de administração (usuários, papéis, configurações). Nenhuma ação da área de administração SHALL ser executada para quem não é administrador.

#### Scenario: Técnico comum envia formulário de administração
- **WHEN** um técnico sem papel de administrador envia diretamente uma requisição de alteração de configuração
- **THEN** nenhum handler de administração é executado, a configuração não muda e a requisição fica registrada no app log como não tratada

### Requirement: Restrição de técnicos a clientes
O sistema SHALL permitir restringir um técnico a clientes. Um técnico restrito não SHALL ver nem alterar registros de clientes fora do seu conjunto, em listas, buscas, exportações, na API ou abrindo um registro pelo ID na URL.

#### Scenario: ID de outro cliente na URL
- **WHEN** um técnico restrito ao cliente A abre a URL de um ativo do cliente B
- **THEN** o acesso é negado, ele volta para a lista de clientes e a tentativa fica registrada no audit log

#### Scenario: Chave de API de um técnico restrito
- **WHEN** uma chave de API pertencente a um técnico restrito ao cliente A lista ativos
- **THEN** a resposta traz apenas ativos do cliente A e de nenhum cliente, mesmo que a chamada peça outro cliente pelo filtro

#### Scenario: Lista de despesas
- **WHEN** um técnico restrito ao cliente A abre a lista de despesas
- **THEN** a lista mostra apenas despesas do cliente A e despesas sem cliente

### Requirement: Listas de clientes liberados e bloqueados
O sistema SHALL definir o conjunto de clientes de um técnico por duas listas. Quando há clientes liberados, o técnico só acessa esses. Os clientes bloqueados ele nunca acessa, mesmo que também estejam liberados. Sem nenhuma das listas, acessa todos. Administradores não são restringidos.

#### Scenario: Cliente bloqueado e liberado ao mesmo tempo
- **WHEN** um cliente está nas duas listas de um técnico
- **THEN** o técnico não acessa esse cliente

#### Scenario: Técnico sem listas
- **WHEN** um técnico não tem clientes liberados nem bloqueados
- **THEN** ele acessa todos os clientes

### Requirement: Registros sem cliente continuam visíveis
O sistema SHALL manter visíveis para técnicos restritos os registros que não pertencem a nenhum cliente, porque a restrição separa dados de clientes e um registro sem cliente não é dado de nenhum cliente.

#### Scenario: Despesa interna
- **WHEN** um técnico restrito abre a lista de despesas e existe uma despesa sem cliente
- **THEN** a despesa sem cliente aparece na lista

### Requirement: Proteção contra requisições forjadas
O sistema SHALL recusar toda ação que altera estado, seja por formulário ou por link, quando ela não trouxer o token anti-CSRF válido da sessão.

#### Scenario: Formulário vindo de outro site
- **WHEN** uma página externa envia um POST de exclusão para o ITFlow usando a sessão do técnico, sem o token
- **THEN** a resposta é 403 com a explicação de que o token não foi verificado, e nada é alterado

### Requirement: Ações de alteração deixam rastro
O sistema SHALL registrar no audit log as alterações de estado feitas por técnicos e administradores, com o tipo, a ação, a descrição, o cliente e o registro afetado.

#### Scenario: Chamado resolvido
- **WHEN** um técnico resolve um chamado
- **THEN** o audit log ganha uma entrada com o técnico, o chamado e o cliente
