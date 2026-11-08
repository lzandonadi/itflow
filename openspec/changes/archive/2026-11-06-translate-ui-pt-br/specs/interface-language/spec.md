# Spec Delta

## ADDED Requirements

### Requirement: Interface completa em português
Com o idioma da interface em pt-BR, o sistema SHALL apresentar em português todo texto que ele próprio gera e mostra a pessoas (rótulos, menus, mensagens, ajuda, e-mails e documentos) em todos os portais e nas páginas de acesso. Conteúdo digitado por usuários, dados semente gravados e saídas técnicas não são traduzidos.

#### Scenario: Navegação do técnico
- **WHEN** o idioma é pt-BR e um técnico percorre o menu lateral, abre uma lista, um registro e o modal de edição de qualquer módulo
- **THEN** nenhum texto gerado pelo sistema aparece em inglês

#### Scenario: Mensagem após uma ação
- **WHEN** o idioma é pt-BR e um técnico cria, edita ou exclui um registro
- **THEN** a mensagem de confirmação ou de erro aparece em português e o audit log registra a ação em inglês

#### Scenario: Conteúdo digitado pelo usuário
- **WHEN** o idioma é pt-BR e um chamado tem o assunto "Printer offline"
- **THEN** o assunto aparece exatamente como foi digitado

### Requirement: Documentos PDF no idioma da interface
O sistema SHALL gerar fatura, orçamento, contrato e exportação de dados do cliente em PDF com os rótulos no idioma da interface. Números e valores monetários continuam seguindo o formato regional da empresa.

#### Scenario: Fatura em PDF
- **WHEN** o idioma é pt-BR e uma fatura é baixada em PDF pelo técnico, pelo portal do cliente ou pelo link guest
- **THEN** rótulos como número, data, vencimento, itens, subtotal e total aparecem em português e os valores seguem o formato regional configurado

### Requirement: Componentes de terceiros no idioma da interface
O sistema SHALL exibir no idioma da interface os textos dos componentes de terceiros que mostram texto próprio. São eles as tabelas (busca, paginação, contagem, tabela vazia), o seletor de data (nomes de mês e de dia), o calendário (botões e cabeçalhos), o editor de texto rico (menus e botões) e o campo de telefone (nomes de país e textos de ajuda).

#### Scenario: Tabela de listagem
- **WHEN** o idioma é pt-BR e uma listagem com tabela pesquisável é aberta
- **THEN** o campo de busca, a paginação, a contagem de registros e a mensagem de tabela vazia aparecem em português

#### Scenario: Seletor de data
- **WHEN** o idioma é pt-BR e um técnico abre o seletor de data de um formulário
- **THEN** os nomes de mês e de dia da semana aparecem em português e o valor enviado no formulário mantém o formato numérico de sempre

### Requirement: Status de sistema exibidos no idioma da interface
O sistema SHALL exibir no idioma da interface os status de chamado criados pela instalação enquanto conservarem o nome original. Um status renomeado ou criado pelo administrador SHALL aparecer como foi digitado. O nome gravado nunca é alterado.

#### Scenario: Status original
- **WHEN** o idioma é pt-BR e um chamado está no status de sistema "Open" com o nome original
- **THEN** a tela mostra o status traduzido e filtros e relatórios continuam usando o mesmo status

#### Scenario: Status renomeado
- **WHEN** o administrador renomeou o status "On Hold" para "Aguardando peça"
- **THEN** o status aparece como "Aguardando peça" em qualquer idioma

### Requirement: Terminologia consistente com o glossário
O sistema SHALL usar em pt-BR os termos fixados no glossário do projeto em todas as telas, e-mails e documentos: Ticket = Chamado, Agent/Technician = Técnico, Quote = Orçamento e Asset = Ativo, além dos demais termos registrados nele.

#### Scenario: Termo fixado
- **WHEN** o idioma é pt-BR e qualquer tela, e-mail ou PDF menciona tickets, quotes, assets ou technicians
- **THEN** o texto usa "chamado", "orçamento", "ativo" e "técnico", e os identificadores gravados (como o prefixo do número do chamado) permanecem como estão

### Requirement: Inglês inalterado pela conversão
A conversão dos textos para traduzíveis SHALL manter idêntico, em `en_US`, o texto que cada tela, e-mail e documento já mostrava, salvo correções de texto registradas no changelog.

#### Scenario: Tela convertida em inglês
- **WHEN** o idioma é en_US e uma tela já convertida é aberta
- **THEN** os textos visíveis são os mesmos de antes da conversão
