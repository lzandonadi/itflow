# client-portal-access Specification

## Purpose
Define o que cada contato de um cliente pode ver e fazer no portal do cliente, conforme seja contato principal, financeiro ou técnico, e como funciona a visualização do portal por um técnico.

## Requirements

### Requirement: Contato principal tem acesso completo
O sistema SHALL dar ao contato principal do cliente acesso a todas as áreas do portal, incluindo a gestão dos demais contatos e do acesso deles ao portal.

#### Scenario: Contato principal gerencia contatos
- **WHEN** o contato principal abre a área de contatos
- **THEN** ele pode adicionar contatos e alterar o acesso deles ao portal

### Requirement: Acesso por perfil de contato
O sistema SHALL liberar as áreas do portal para os demais contatos conforme o perfil: o financeiro vê faturas, orçamentos, faturas recorrentes, pagamentos, extrato, contratos e cartões salvos; o técnico vê ativos, certificados, domínios, documentos, arquivos e os contatos da empresa. Apenas o contato principal SHALL gerenciar contatos.

#### Scenario: Contato técnico tenta abrir faturas
- **WHEN** um contato que é só técnico abre a página de faturas do portal
- **THEN** ele volta à página inicial do portal com um aviso de que aquela área não está aberta para ele, sem perder a sessão

#### Scenario: Área desconhecida
- **WHEN** o portal verifica uma área que não tem regra definida
- **THEN** o acesso é negado

#### Scenario: Módulo desligado
- **WHEN** o módulo de contabilidade está desligado e o contato principal abre a página de faturas
- **THEN** ele volta à página inicial do portal

### Requirement: Chamados visíveis por contato
O sistema SHALL mostrar a cada contato os chamados que ele abriu. O contato principal e os contatos técnicos SHALL ver todos os chamados da empresa.

#### Scenario: Contato comum abre chamado de um colega
- **WHEN** um contato que não é principal nem técnico abre um chamado de outro contato da empresa pela URL
- **THEN** o chamado não é exibido

### Requirement: Dispositivos visíveis por contato
O sistema SHALL mostrar ao contato principal e aos técnicos todos os dispositivos da empresa. Os demais contatos SHALL ver apenas os dispositivos atribuídos a eles.

#### Scenario: Contato comum vê o próprio notebook
- **WHEN** um contato sem perfil técnico abre a lista de dispositivos
- **THEN** a lista mostra só os dispositivos atribuídos a ele

### Requirement: Sessão do portal encerrada quando o acesso muda
O sistema SHALL encerrar a sessão aberta de um contato quando o acesso dele ao portal é desligado, quando a conta é desativada ou arquivada e quando a senha é trocada em outro lugar.

#### Scenario: Acesso desligado com sessão aberta
- **WHEN** um técnico desliga o acesso ao portal de um contato que está com o portal aberto
- **THEN** a próxima página que o contato abre o leva para o login

### Requirement: Verificação em duas etapas obrigatória
O sistema SHALL, quando o administrador exige verificação em duas etapas no portal, deixar um contato com senha local que ainda não a configurou abrir apenas a tela de configuração ou sair. Contatos que entram pela Microsoft não são afetados.

#### Scenario: Contato sem a verificação configurada
- **WHEN** a exigência está ligada e um contato sem verificação configurada entra e abre a página de faturas
- **THEN** ele é levado para a configuração da verificação em duas etapas

### Requirement: Visualização como contato
O sistema SHALL permitir que um técnico veja o portal como um contato, sem alterar nem registrar nada em nome dele. Exige que o portal esteja ligado, que o papel do técnico tenha leitura em clientes e suporte e que o técnico tenha acesso ao cliente do contato. A parte financeira só aparece se o papel tiver leitura em vendas e financeiro.

#### Scenario: Técnico sem acesso financeiro vê o portal
- **WHEN** um técnico sem permissão financeira usa "View as contact" com um contato principal
- **THEN** o portal aparece sem a área financeira

#### Scenario: Técnico tenta enviar algo vendo como contato
- **WHEN** um técnico, vendo o portal como contato, envia um formulário do portal
- **THEN** nada é alterado e o portal avisa que nada pode ser mudado nesse modo; só o extrato em PDF pode ser baixado

#### Scenario: Técnico restrito a outro cliente
- **WHEN** um técnico restrito ao cliente A tenta ver o portal como um contato do cliente B
- **THEN** a visualização não é aberta
