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
