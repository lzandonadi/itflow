# Spec Delta

## Purpose

Define como o ITFlow executa suas tarefas agendadas: quais existem, quando rodam, como são ligadas e desligadas e quais garantias o despachante oferece contra execução dupla e travamentos.

## ADDED Requirements

### Requirement: Uma única entrada de agendamento
O sistema SHALL executar todas as tarefas agendadas a partir de um único despachante chamado a cada minuto. O despachante decide quais tarefas estão vencidas. Adicionar uma tarefa nova não SHALL exigir alterar o agendamento do servidor.

#### Scenario: Instalação com uma linha de crontab
- **WHEN** o servidor tem apenas a linha que chama o despachante a cada minuto
- **THEN** todas as tarefas habilitadas rodam no seu horário

### Requirement: Somente tarefas registradas podem rodar
O sistema SHALL executar apenas os scripts presentes no registro de tarefas do código. O banco guarda quando e se cada tarefa roda, nunca qual arquivo roda.

#### Scenario: Linha do banco aponta para script desconhecido
- **WHEN** a tabela de tarefas contém uma linha com um nome que não está no registro
- **THEN** o despachante ignora a linha e não executa nenhum arquivo por causa dela

### Requirement: Tarefas registradas
O sistema SHALL oferecer as tarefas: fila de e-mails, leitura da caixa de suporte, SLA de chamados, triagem por IA, taxas do provedor de pagamento (por intervalo); domínios, tarefas noturnas, backup, certificados, verificação de atualização e aplicação de atualização (diárias). Backup e aplicação de atualização SHALL vir desligados.

#### Scenario: Instalação nova
- **WHEN** o despachante encontra uma tarefa pela primeira vez
- **THEN** ele cria a linha da tarefa com a agenda padrão, e backup e aplicação de atualização ficam desligados até um administrador ligar

### Requirement: Tarefa perdida roda na próxima oportunidade
O sistema SHALL registrar no banco quando cada tarefa rodou e executar na próxima chamada uma tarefa cujo horário passou sem execução (servidor desligado, execução anterior ainda em andamento), em vez de pular o dia.

#### Scenario: Servidor fora do ar no horário da tarefa noturna
- **WHEN** o servidor volta depois do horário da tarefa noturna
- **THEN** a tarefa noturna roda no próximo minuto

### Requirement: Execução reivindicada antes de rodar e bloqueada por tarefa
O sistema SHALL marcar a tarefa como reivindicada antes de executá-la, para que uma execução interrompida no meio não se repita sozinha, e SHALL bloquear cada tarefa individualmente, para que uma tarefa longa ou travada atrase só a si mesma.

#### Scenario: Backup demorado
- **WHEN** o backup ainda está rodando no minuto seguinte
- **THEN** a fila de e-mails e as demais tarefas vencidas rodam normalmente em outro processo

### Requirement: Rodar agora não executa na requisição web
O sistema SHALL tratar o "Run Now" da administração como um pedido atendido pela próxima chamada do despachante, com o mesmo bloqueio e a mesma reivindicação de uma execução agendada.

#### Scenario: Administrador clica Run Now
- **WHEN** um administrador pede para rodar a tarefa noturna agora
- **THEN** a página responde imediatamente e a tarefa roda na próxima chamada do despachante

### Requirement: Interruptor geral do cron
O sistema SHALL ter um interruptor geral, desligado por padrão, que faz toda tarefa parar logo no início quando está desligado. Religá-lo SHALL devolver exatamente a configuração por tarefa que existia antes.

#### Scenario: Cron desligado
- **WHEN** o interruptor geral está desligado e o despachante chama a fila de e-mails
- **THEN** a tarefa termina sem enviar nada

### Requirement: Tarefas diárias não repetem efeitos no mesmo dia
O sistema SHALL garantir que tarefas cujo trabalho é escolhido pela data de hoje não repitam o efeito quando rodam mais de uma vez no dia. Tarefas que não podem garantir isso SHALL ficar restritas à agenda diária.

#### Scenario: Tarefa noturna roda duas vezes
- **WHEN** a tarefa noturna roda no horário e de novo por Run Now no mesmo dia
- **THEN** multas por atraso e lembretes de vencimento não são aplicados nem enviados duas vezes
