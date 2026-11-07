# interface-language Specification

## Purpose

Define em que idioma o ITFlow apresenta sua interface, e-mails e documentos. Cobre como o administrador escolhe esse idioma e as garantias de que a escolha nunca altera dados gravados nem quebra uma instalação sem tradução completa.

## Requirements

### Requirement: Inglês como idioma padrão
O sistema SHALL apresentar a interface em inglês (`en_US`) quando nenhum outro idioma estiver configurado, inclusive em instalações novas, instalações atualizadas e instalações cuja atualização de banco ainda não rodou.

#### Scenario: Instalação nova
- **WHEN** o ITFlow é instalado a partir do `db.sql` ou do setup
- **THEN** o idioma da interface é `en_US` e todas as telas aparecem em inglês

#### Scenario: Código atualizado antes da migração do banco
- **WHEN** os arquivos foram atualizados mas a migração que cria a configuração de idioma ainda não rodou
- **THEN** todas as telas, inclusive a de atualização do banco, carregam normalmente em inglês, sem erro

### Requirement: Administrador escolhe o idioma da interface
O sistema SHALL permitir que um administrador escolha o idioma da interface em Admin > Settings > Localization, entre os idiomas suportados. A escolha vale para a instalação inteira e fica registrada no audit log como as demais alterações de configuração.

#### Scenario: Troca para português
- **WHEN** um administrador seleciona "Português (Brasil)" e salva
- **THEN** a próxima página carregada por qualquer usuário aparece em pt-BR e o audit log registra a alteração

#### Scenario: Idioma inválido
- **WHEN** a requisição de salvar traz um idioma que não está na lista de suportados
- **THEN** nada é gravado e a página volta indicando o campo com erro

#### Scenario: Usuário sem permissão de admin
- **WHEN** um técnico que não é administrador tenta salvar a configuração de idioma
- **THEN** a alteração é recusada como qualquer outra configuração administrativa

### Requirement: Idioma da interface independe do formato regional
O sistema SHALL tratar o idioma da interface separadamente do formato regional da empresa (`company_locale`), que continua controlando apenas a formatação de números e moeda.

#### Scenario: Idioma e formato diferentes
- **WHEN** o idioma da interface é pt-BR e o formato regional continua en_US
- **THEN** os textos aparecem em português e os valores monetários continuam no formato en_US

### Requirement: Fallback para inglês em textos sem tradução
O sistema SHALL exibir o texto original em inglês sempre que não houver tradução para o idioma configurado, sem erro e sem mostrar chaves internas.

#### Scenario: Texto ainda não traduzido
- **WHEN** o idioma é pt-BR e uma tela usa um texto que não está no dicionário pt-BR
- **THEN** a tela mostra o texto em inglês e todo o resto da página continua traduzido

### Requirement: Idioma aplicado em todos os pontos de entrada com texto
O sistema SHALL aplicar o idioma configurado em toda superfície que apresenta texto a pessoas: área do técnico, admin, portal do cliente, páginas guest, login e e-mails gerados por tarefas agendadas. Cada página HTML SHALL declarar o idioma em `<html lang>`.

#### Scenario: Portal do cliente
- **WHEN** o idioma é pt-BR e um contato acessa o portal do cliente
- **THEN** o portal aparece em pt-BR e o HTML declara `lang="pt-BR"`

#### Scenario: E-mail enviado pelo cron
- **WHEN** o idioma é pt-BR e uma tarefa agendada gera um e-mail com texto já traduzível
- **THEN** o e-mail sai em pt-BR

### Requirement: Saídas técnicas permanecem em inglês
O sistema SHALL manter em inglês as saídas voltadas a máquinas ou diagnóstico: respostas da API, audit log, app log, saída de cron e de scripts CLI.

#### Scenario: Resposta da API
- **WHEN** o idioma é pt-BR e uma integração consulta a API v1
- **THEN** as mensagens e os campos da resposta são idênticos aos da instalação em inglês

### Requirement: Valores gravados nunca são traduzidos
O sistema SHALL traduzir valores enumerados gravados no banco (status de fatura, prioridade e status de chamado, entre outros) apenas na exibição. O valor gravado, comparado ou enviado em formulários permanece o original em inglês.

#### Scenario: Salvar fatura com interface em português
- **WHEN** o idioma é pt-BR e um técnico marca uma fatura como enviada
- **THEN** a tela mostra o status traduzido e o banco grava `invoice_status = 'Sent'`

#### Scenario: Troca de idioma com dados existentes
- **WHEN** o idioma é trocado de en-US para pt-BR e depois de volta
- **THEN** nenhum registro do banco é alterado e filtros e relatórios retornam os mesmos resultados

### Requirement: Textos gerados ficam no idioma do momento da criação
O sistema SHALL gerar no idioma configurado no momento da criação os textos que grava para pessoas lerem depois (respostas automáticas em chamados, notificações, e-mails), sem retraduzir o histórico quando o idioma muda.

#### Scenario: Histórico anterior à troca
- **WHEN** existem respostas automáticas gravadas em inglês e o idioma passa a ser pt-BR
- **THEN** as respostas antigas continuam em inglês e as novas são gravadas em pt-BR

### Requirement: Datas exibidas no idioma da interface
O sistema SHALL escrever nomes de mês e de dia da semana no idioma da interface em toda data exibida por meio do formatador de datas do sistema.

#### Scenario: Data em português
- **WHEN** o idioma é pt-BR e uma tela exibe a data 2026-11-05 no formato médio
- **THEN** a data aparece como "5 de nov. de 2026", e não como "Nov 5, 2026"

### Requirement: Textos do JavaScript seguem o idioma
O sistema SHALL disponibilizar aos scripts do front-end as traduções que eles exibem, com o mesmo fallback para inglês.

#### Scenario: Confirmação em JavaScript
- **WHEN** o idioma é pt-BR e um script exibe uma confirmação cujo texto está no dicionário JS
- **THEN** a confirmação aparece em pt-BR

### Requirement: Verificação de traduções
O projeto SHALL oferecer uma verificação por linha de comando que lista os textos traduzíveis usados no código sem tradução em um idioma e as traduções que não são mais usadas.

#### Scenario: Texto novo sem tradução
- **WHEN** um texto traduzível é adicionado ao código e não existe no dicionário pt-BR
- **THEN** a verificação lista esse texto com o arquivo e a linha onde aparece e termina com código de saída diferente de zero

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
