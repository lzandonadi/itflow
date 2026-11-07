# Proposal

## Why

A infraestrutura de idioma (`add-i18n-infrastructure`, spec `interface-language`) já permite escolher pt-BR, mas quase todas as telas, e-mails e PDFs continuam com texto em inglês fixo no código. Por isso, ativar o português hoje deixa a interface misturada. Esta change converte os textos e entrega a tradução completa, para que uma MSP brasileira use o ITFlow e o portal do cliente inteiramente em português.

## What Changes

- **Textos visíveis passam por `__()`, `_x()` e `_n()`.** Isso vale para todo texto mostrado a pessoas nos portais agent, admin, client e guest, nas páginas de login e cadastro, nos e-mails gerados pelo sistema e nos PDFs (fatura, orçamento, contrato, exportação de cliente), com a tradução correspondente em `lang/pt_BR.php` e `lang/js/pt_BR.php`.
- **Status e prioridades.** Status de fatura e orçamento, prioridades de chamado e os status de chamado de sistema passam a ser exibidos traduzidos. O valor gravado continua em inglês. Um status de chamado renomeado pelo administrador aparece como foi digitado.
- **Componentes de terceiros.** Tabelas (busca, paginação, "nenhum registro"), seletor de data, calendário, editor de texto e campo de telefone passam a seguir o idioma da interface.
- **Verificação ampliada.** `scripts/i18n_check.php` ganha uma listagem heurística de textos ainda fixos em inglês por diretório, para medir o avanço e encontrar sobras. Também passa a conferir se a marcação HTML de uma tradução bate com a do original.
- **Glossário.** `lang/GLOSSARY.md` passa a registrar os termos de cada área à medida que os lotes são traduzidos.
- **Entrega em lotes.** O trabalho é dividido por área funcional (chamados, clientes e documentação, vendas, financeiro, contratos e projetos, relatórios, admin, portal do cliente e guest, e-mails), cada lote revisável sozinho. Em `en_US`, nenhum texto visível muda.

## Capabilities

### New Capabilities

Nenhuma.

### Modified Capabilities

- `interface-language`: novos requisitos de cobertura da tradução (interface completa em pt-BR, PDFs, componentes de terceiros, status de sistema, terminologia do glossário, inglês inalterado).

## Impact

- **Código:** praticamente todos os arquivos de interface em `agent/`, `admin/`, `client/`, `guest/`, `includes/`, `js/`, `agent/js/`, `login.php`, `join.php`, além das funções de `functions/` que montam texto para pessoas: mensagens de `flashAlert()`, e-mails, PDFs, `appNotify()` e respostas automáticas em chamados. São cerca de 240 mil linhas, com uma estimativa de 4 a 6 mil textos únicos.
- **Dicionários:** `lang/pt_BR.php` e `lang/js/pt_BR.php` crescem de algumas dezenas para milhares de entradas.
- **Bibliotecas:** nenhum arquivo em `libs/` é alterado. O pacote de idioma pt-BR do editor de texto fica fora de `libs/`.
- **Banco:** nenhuma mudança de schema. Dados semente (status, categorias, modelos) e conteúdo digitado por usuários não são traduzidos no banco.
- **Fora de alcance:** API, audit log, app log e saída de cron e CLI continuam em inglês, conforme a spec `interface-language`. O assistente de instalação (`setup/`) continua em inglês.
