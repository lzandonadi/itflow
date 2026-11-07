# client-portal-layout Specification

## Purpose
Define o que o cabeçalho, a navegação e o rodapé do portal do cliente mostram em cada tamanho de tela, e onde cada coisa fica, para que mudanças de layout tenham um contrato.

## Requirements

### Requirement: Cabeçalho do portal
O sistema SHALL mostrar no topo de toda página logada do portal a logo da empresa, ou as iniciais e o nome dela quando não há logo, com link para o início do portal. À direita SHALL ficar o menu da conta e, a partir de 576 px de largura, o botão "Novo chamado". O cabeçalho SHALL ficar fixo no topo ao rolar a página.

#### Scenario: Empresa sem logo
- **WHEN** a empresa não tem logo cadastrada e um contato abre o portal
- **THEN** o cabeçalho mostra as iniciais e o nome da empresa no lugar da logo, com link para o início

### Requirement: Menu do portal centralizado em telas largas
Em telas largas (a partir de 992 px), o sistema SHALL mostrar o menu das seções do portal no cabeçalho, centralizado no espaço entre a logo e os botões da direita, com uma folga visível em relação à logo. O menu SHALL mostrar só as seções que o contato pode acessar.

#### Scenario: Contato num computador
- **WHEN** um contato abre o portal numa janela larga
- **THEN** o menu aparece no meio do cabeçalho, afastado da logo, e os botões da direita continuam encostados à direita

#### Scenario: Logo larga
- **WHEN** a logo da empresa ocupa a largura máxima permitida
- **THEN** o menu continua inteiro e sem sobrepor a logo nem os botões

### Requirement: Navegação do portal no celular
Em telas estreitas (abaixo de 992 px), o sistema SHALL esconder o menu do cabeçalho e oferecer a navegação por uma barra de abas fixa embaixo da tela (Início, Chamados, Novo, Faturamento quando o contato tem acesso, Menu) e por um menu lateral com todas as seções e a conta.

#### Scenario: Contato no celular
- **WHEN** um contato abre o portal num celular
- **THEN** o menu do cabeçalho não aparece, e a barra de abas embaixo e o menu lateral levam a todas as seções

### Requirement: Rodapé do portal
O sistema SHALL mostrar no rodapé de toda página logada do portal o nome da empresa e, ao lado, o texto do rodapé configurado pelo administrador, exatamente como foi salvo, sem tradução e com ou sem white-label. Sem texto configurado, o rodapé SHALL mostrar "Powered by ITFlow" traduzido, só quando o white-label está desligado.

#### Scenario: Texto configurado
- **WHEN** o texto do rodapé está configurado como "Presença Digital, Automação e Inteligência Artificial, Produtividade e Integração" e um contato abre qualquer página do portal
- **THEN** o rodapé mostra o nome da empresa e esse texto, e não mostra "Powered by ITFlow" em nenhum idioma

#### Scenario: Texto configurado com white-label ligado
- **WHEN** o texto do rodapé está configurado e o white-label está ligado
- **THEN** o rodapé mostra o nome da empresa e o texto configurado

#### Scenario: Sem texto configurado
- **WHEN** o texto do rodapé está vazio e o white-label está desligado
- **THEN** o rodapé mostra o nome da empresa e "Powered by ITFlow" no idioma da interface ("Desenvolvido com ITFlow" em português)

#### Scenario: Sem texto e com white-label
- **WHEN** o texto do rodapé está vazio e o white-label está ligado
- **THEN** o rodapé mostra só o nome da empresa

#### Scenario: Texto com marcação
- **WHEN** o texto configurado contém caracteres especiais como `<`, `&` ou aspas
- **THEN** o portal os mostra como texto, sem interpretá-los como HTML

### Requirement: Texto do rodapé editado pelo administrador
O sistema SHALL permitir a um administrador definir ou apagar o texto do rodapé do portal em Admin > Configurações > Portal do cliente, com até 200 caracteres de texto puro. Cada mudança SHALL ser registrada no audit log. Um texto acima do limite SHALL ser recusado sem salvar nada.

#### Scenario: Administrador define o texto
- **WHEN** um administrador salva "Presença Digital, Automação e Inteligência Artificial, Produtividade e Integração" no campo "Texto do rodapé"
- **THEN** a configuração é salva, o audit log registra a mudança e o rodapé do portal passa a mostrar o texto

#### Scenario: Administrador apaga o texto
- **WHEN** um administrador esvazia o campo e salva
- **THEN** o rodapé do portal volta a mostrar "Powered by ITFlow", conforme o white-label

#### Scenario: Texto longo demais
- **WHEN** um pedido de salvamento traz um texto com mais de 200 caracteres
- **THEN** nada é salvo e a página aponta o erro no campo
