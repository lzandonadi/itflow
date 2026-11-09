# ticket-emails Specification

## Purpose
Descreve os e-mails que o sistema envia por causa de um chamado: ao cliente, a observadores e a técnicos. Fixa quando cada um sai e as garantias que os textos de qualquer idioma precisam manter.

## Requirements

### Requirement: E-mails ao cliente cobrem o ciclo do chamado
O sistema SHALL enviar ao contato do chamado, e aos observadores quando couber, um e-mail em cada um destes momentos: chamado criado (por e-mail, pelo técnico ou por agendamento recorrente), resposta pública do técnico, chamado resolvido, chamado fechado, visita agendada ou cancelada, pessoa incluída como observadora e pedido de aprovação de tarefa.

#### Scenario: Chamado nasce de um e-mail recebido
- **WHEN** a leitura da caixa de suporte cria um chamado a partir de um e-mail
- **THEN** o remetente recebe o e-mail de confirmação com o número do chamado e a orientação de que será avisado por e-mail quando houver resposta

#### Scenario: Técnico responde publicamente
- **WHEN** um técnico envia uma resposta pública
- **THEN** o contato e os observadores recebem o texto da resposta, assinado por quem escreveu, e o chamado mostra para quem foi enviado

#### Scenario: Chamado resolvido
- **WHEN** um chamado é marcado como resolvido
- **THEN** o contato recebe o aviso de resolução, que informa em quanto tempo o chamado se fecha sozinho quando o fechamento automático está ligado

### Requirement: Contato escolhe quais e-mails de chamado recebe
O sistema SHALL deixar de enviar ao contato os e-mails de chamado criado e de chamado fechado quando ele escolheu receber só respostas, e SHALL continuar enviando os demais.

#### Scenario: Contato que só quer respostas
- **WHEN** um chamado é criado ou fechado e o contato escolheu receber só respostas
- **THEN** ele não recebe o e-mail de criação nem o de fechamento

#### Scenario: Contato que quer tudo
- **WHEN** um chamado é criado ou fechado e o contato não mudou a preferência
- **THEN** ele recebe o e-mail

### Requirement: Assunto carrega a referência do chamado
O sistema SHALL manter a referência do chamado entre colchetes (por exemplo `[TCK-11]`) no assunto de todo e-mail de chamado, em qualquer idioma, porque é por ela que a leitura da caixa liga uma resposta ao chamado.

#### Scenario: Assunto traduzido
- **WHEN** o idioma da interface é português e um e-mail de chamado é enviado
- **THEN** o assunto traz o texto em português e a referência entre colchetes no mesmo formato do inglês

#### Scenario: Aviso de chamado fechado
- **WHEN** o e-mail de chamado fechado é enviado
- **THEN** o assunto indica que não deve ser respondido e respostas a ele não reabrem o chamado

### Requirement: Confirmação de chamado por e-mail não repete o texto do remetente
O sistema SHALL, no e-mail de confirmação de um chamado criado por e-mail, informar apenas o número do chamado e a orientação de acompanhamento, sem copiar a mensagem do remetente, para que um remetente falsificado não faça o sistema entregar a terceiros o que escreveu.

#### Scenario: Confirmação em português
- **WHEN** o idioma da interface é português e um chamado é criado por e-mail
- **THEN** o título é "Recebemos o seu e-mail" e o texto é "Obrigado por entrar em contato. Um ticket de suporte (TCK-11) foi aberto para a sua solicitação. Você será notificado por e-mail quando houver uma resposta."

#### Scenario: Remetente escreveu um texto qualquer
- **WHEN** o e-mail recebido tem corpo livre
- **THEN** o corpo não aparece na confirmação enviada ao remetente

### Requirement: Resposta a chamado fechado é avisada
O sistema SHALL, quando alguém responde por e-mail a um chamado já fechado, enviar um aviso de que a resposta não chegou e de como abrir um chamado novo.

#### Scenario: Resposta a chamado fechado
- **WHEN** chega um e-mail cujo assunto traz a referência de um chamado fechado
- **THEN** o remetente recebe o aviso "Ação necessária: este chamado já está fechado" com o endereço para abrir um chamado novo

### Requirement: Textos gerados no idioma do momento
O sistema SHALL escrever os e-mails de chamado no idioma da interface vigente no momento do envio, e SHALL manter em inglês os registros técnicos (auditoria, log).

#### Scenario: Idioma em português
- **WHEN** o idioma da interface é português
- **THEN** cabeçalho, título, texto, rótulos do resumo e botões do e-mail saem em português

#### Scenario: Texto sem tradução
- **WHEN** um texto de e-mail não tem tradução no idioma escolhido
- **THEN** ele sai em inglês, sem erro
