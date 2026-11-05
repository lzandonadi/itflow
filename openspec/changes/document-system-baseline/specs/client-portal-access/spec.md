# Spec Delta

## Purpose

Define o que cada contato de um cliente pode ver e fazer no portal do cliente, conforme seja contato principal, financeiro ou técnico, e como funciona a visualização do portal por um técnico.

## ADDED Requirements

### Requirement: Contato principal tem acesso completo
O sistema SHALL dar ao contato principal do cliente acesso a todas as áreas do portal, incluindo a gestão dos demais contatos e do acesso deles ao portal.

#### Scenario: Contato principal gerencia contatos
- **WHEN** o contato principal abre a área de contatos
- **THEN** ele pode adicionar contatos e alterar o acesso deles ao portal

### Requirement: Acesso por perfil de contato
O sistema SHALL liberar as áreas do portal para os demais contatos conforme o perfil: o financeiro vê faturas, orçamentos, faturas recorrentes e cartões salvos; o técnico vê ativos, certificados, domínios, documentos, arquivos e os contatos da empresa. Apenas o contato principal SHALL gerenciar contatos.

#### Scenario: Contato técnico tenta abrir faturas
- **WHEN** um contato que é só técnico abre a página de faturas do portal
- **THEN** ele volta à página inicial do portal com um aviso de que aquela área não está aberta para ele, sem perder a sessão

#### Scenario: Área desconhecida
- **WHEN** o portal verifica uma área que não tem regra definida
- **THEN** o acesso é negado

### Requirement: Visualização como contato
O sistema SHALL permitir que um técnico veja o portal como um contato, sem alterar nem registrar nada em nome dele. A parte financeira só aparece se o papel do técnico permitir.

#### Scenario: Técnico sem acesso financeiro vê o portal
- **WHEN** um técnico sem permissão financeira usa "View as contact" com um contato principal
- **THEN** o portal aparece sem a área financeira
