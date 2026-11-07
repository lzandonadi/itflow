# Spec Delta

## MODIFIED Requirements

### Requirement: Tarefas diárias não repetem efeitos no mesmo dia
O sistema SHALL garantir que tarefas cujo trabalho é escolhido pela data de hoje não repitam o efeito quando rodam mais de uma vez no dia. Tarefas que não podem garantir isso (tarefa noturna, backup e aplicação de atualização) SHALL rodar no máximo uma vez por dia pela agenda, mesmo que a linha do banco peça um intervalo menor.

#### Scenario: Tarefa noturna roda duas vezes
- **WHEN** a tarefa noturna roda no horário e de novo por Run Now no mesmo dia
- **THEN** multas por atraso e lembretes de vencimento não são aplicados nem enviados duas vezes

#### Scenario: Avisos internos numa segunda execução
- **WHEN** a tarefa noturna roda de novo no mesmo dia e um técnico já recebeu hoje um aviso escolhido pela data (fatura vencida, chamados aguardando atribuição, atualização disponível, domínio ou garantia vencendo, entre outros), tenha ou não dispensado o aviso
- **THEN** o técnico não recebe esse aviso outra vez; um aviso-resumo conta como o mesmo no dia mesmo que o número que ele traz tenha mudado

#### Scenario: Técnico ativado entre as execuções
- **WHEN** um técnico é ativado depois da primeira execução do dia e a tarefa noturna roda de novo
- **THEN** ele recebe os avisos do dia que ainda não tinha recebido, e os outros técnicos não recebem nada repetido
