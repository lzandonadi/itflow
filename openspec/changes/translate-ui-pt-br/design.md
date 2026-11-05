# Design

## Context

A motivação está em proposal.md e os requisitos em `specs/interface-language/spec.md`. A infraestrutura já existe (spec `interface-language`, `functions/i18n.php`, `js/i18n.js`, `scripts/i18n_check.php`, `lang/GLOSSARY.md`). O que falta é converter os textos.

Estado atual que molda a abordagem:

- **Tamanho.** São cerca de 240 mil linhas de PHP e JS próprios. Uma contagem bruta encontra em torno de 6.900 textos entre tags, 1.500 atributos `title` e `placeholder` e 1.250 chamadas de `flashAlert()` com texto literal, com repetição. A estimativa é de 4 a 6 mil textos únicos.
- **Onde ficam.** Cada área funcional espalha texto por página (`agent/<x>.php`), modais (`agent/modals/<x>/`), handlers (`agent/post/<x>.php`), funções (`functions/<x>.php`, incluindo e-mails e PDFs) e, às vezes, pelo portal do cliente e pelo guest. Os maiores blocos são `agent/post` (22 mil linhas), `admin/post` (15 mil), `admin/modals` (8,5 mil) e `agent/reports` (7,9 mil).
- **PDFs.** São gerados no servidor com TCPDF (`functions/billing_pdf.php`, `contract_pdf.php`, `client_export_pdf.php`), então os rótulos passam pelo `__()` do PHP como qualquer tela.
- **E-mails.** São todos montados no código. Não há modelo de e-mail gravado no banco. Os únicos textos configuráveis (`config_invoice_footer`, `config_quote_footer`, `config_login_message`) são digitados pelo usuário.
- **Status de chamado.** Os cinco status de sistema estão definidos em `ticketStatusBuiltIns()` (`functions/ticket_status.php`), com nome, descrição e texto de origem. Os nomes vêm do seed em inglês e podem ser renomeados pelo administrador.
- **Bibliotecas.** `libs/` não traz pacote pt-BR de DataTables, flatpickr, FullCalendar, TinyMCE nem intl-tel-input. Os pontos de inicialização ficam em `js/app.js` (DataTables, flatpickr), `js/portal_datatables.js`, `js/date_filter.js`, `js/portal.js` (TinyMCE), `js/phone_inputs.js` e `agent/js/calendar.js`.
- **Scripts inline.** Cerca de 31 páginas do agent têm `<script>` inline com texto. Nas páginas do agent isso é permitido, mas não no login nem no portal do cliente, cuja CSP é `default-src 'self'`.

## Goals / Non-Goals

**Goals:**

- Converter área por área. Cada lote fecha uma área funcional inteira (tela, modais, handlers, funções, e-mails, PDFs e o equivalente no portal e no guest) e cabe num PR revisável.
- Em pt-BR, cada lote termina com `i18n_check.php` sem pendências e sem sobras na varredura heurística da área. Em `en_US`, o texto fica igual ao de antes.
- Dar uma forma única para exibir status e prioridades traduzidos sem tocar no valor gravado.

**Non-Goals:**

- Traduzir dados semente já gravados (categorias, impostos, modelos de documento, status renomeados) ou conteúdo digitado por usuários.
- Traduzir o conteúdo inserido por Admin > Starter content. A tela é traduzida, mas o conteúdo que ela grava continua em inglês e pode virar uma change própria.
- Traduzir o assistente de instalação (`setup/`). Ele roda antes de existir a configuração de idioma.
- Traduzir respostas da API, audit log, app log ou saída de cron e CLI. A spec já exige que continuem em inglês.
- Mudar a redação em inglês. Uma correção de texto encontrada no caminho vai em commit separado e no changelog.
- Escolher idioma por usuário. Continua global (spec `interface-language`).

## Decisions

### D1. Lotes por área funcional, não por diretório

Cada lote cobre uma área de ponta a ponta: chamados = `agent/ticket*.php` + `agent/modals/ticket*` + `agent/post/ticket*.php` + `functions/ticket*.php` + e-mails de chamado + páginas de chamado do portal e do guest. Assim o revisor vê um fluxo completo e o glossário da área é decidido de uma vez.

- **Alternativa: por diretório** (todo `agent/post` de uma vez). Gera PRs enormes e mistura vocabulários, por isso foi descartada.

A camada compartilhada (headers, menus, rodapés, componentes de terceiros) vai primeiro, porque aparece em toda tela.

### D2. Marcação HTML dentro da chave quando envolve um argumento

`flashAlert("Ticket <strong>$subject</strong> created")` vira `__('Ticket %s created', '<strong>' . escapeHtml($subject) . '</strong>')`. Quando a ênfase faz parte da frase, a marcação fica na chave, com o argumento entre as tags: `__('Ticket <strong>%s</strong> created', escapeHtml($subject))`. O tradutor vê a frase inteira e pode mover a ênfase.

`i18n_check.php` passa a comparar a sequência de tags da tradução com a do original, além dos placeholders. Os argumentos continuam escapados no ponto da chamada, como hoje. A tradução em si é código revisado e não é escapada.

- **Alternativa: proibir HTML nas chaves.** Obrigaria a quebrar frases em pedaços, o que impede a tradução correta da ordem das palavras. Descartada.

### D3. Frase inteira por chave, nunca concatenação de pedaços traduzidos

`'Delete ' . $name . '?'` vira `__('Delete %s?', $name)`. Contagens usam `_n()`. Rótulos curtos e ambíguos ("Open", "Closed", "Sent", "Draft", "Date") levam contexto com `_x()`. Os contextos usados ficam no glossário: `ticket status`, `invoice status`, `quote status`, `priority`, `button`, `column`.

### D4. Exibição de valores enumerados por helpers por domínio

Para não espalhar `_x()` com o contexto certo em centenas de lugares, cada domínio ganha um helper de exibição na sua função tópica:

- `invoiceStatusLabel()` e `quoteStatusLabel()` (`functions/billing.php`)
- `ticketPriorityLabel()` (`functions/ticket.php`)
- `ticketStatusLabel($id, $name)` (`functions/ticket_status.php`)

`ticketStatusLabel()` traduz somente quando `ticketStatusIsBuiltIn($id)` e o nome gravado é igual ao nome original de `ticketStatusBuiltIns()`. Senão, devolve o nome como está. A mesma regra vale para a descrição. `ticketStatusBadgeHtml()` passa a receber o nome já tratado. `<option value>`, filtros e comparações continuam usando o valor gravado.

- **Alternativa: traduzir o status pelo nome, sem olhar o id.** Traduziria um status personalizado que por acaso se chame "Open". Descartada.
- **Alternativa: regravar os nomes no banco ao trocar de idioma.** Viola "valores gravados nunca são traduzidos". Descartada.

### D5. Componentes de terceiros configurados pelo dicionário JS, sem tocar em `libs/`

- **DataTables:** opção `language` montada a partir de `__()` em `js/app.js` e `js/portal_datatables.js`.
- **flatpickr:** objeto `locale` (meses, dias, `firstDayOfWeek`) montado a partir de `__()` em `js/app.js` e `js/date_filter.js`. O `dateFormat` enviado não muda.
- **FullCalendar:** `locale` igual ao `<html lang>` (as datas saem do `Intl` do navegador) e `buttonText` traduzido em `agent/js/calendar.js` e no feed do guest, se houver tela.
- **intl-tel-input:** opção `i18n` só com os textos da interface. Os nomes de país vêm do `Intl.DisplayNames` do navegador, já que a biblioteca usa o idioma do navegador quando suportado. Fica confirmado no lote.
- **TinyMCE:** pacote oficial de idioma pt_BR em `js/vendor-lang/tinymce/pt_BR.js` (fora de `libs/`, com origem e versão anotadas no cabeçalho), carregado com `language` e `language_url` quando o idioma é pt-BR.
- **SweetAlert2 e o modal de confirmação:** já recebem textos do código e passam por `__()`.

Os textos desses componentes vão para `lang/js/pt_BR.php`.

- **Alternativa: vendorizar os arquivos de locale em `libs/`.** Contraria a regra de `libs/` byte a byte igual ao upstream, e várias dessas bibliotecas nem distribuem locale no build vendorizado. Descartada.

### D6. Textos em scripts inline: PHP para o agent, dicionário JS para portal e login

Num `<script>` inline de página do agent, o texto vem do PHP com `json_encode(__('...'))`, que já fica no idioma certo. Em scripts de arquivo (`js/`, `agent/js/`) e em qualquer página sob a CSP estrita, usa-se o `__()` do JS, com a entrada em `lang/js/pt_BR.php`. `i18n_check.php` já cobre os dois casos.

### D7. Varredura heurística de textos fixos

`scripts/i18n_check.php pt_BR --hardcoded <caminho>` lista, por arquivo e linha, candidatos a texto fixo em inglês:

- nós de texto entre tags com uma palavra começando por letra
- valores de `title`, `placeholder`, `aria-label`, `data-original-title`
- literais passados a `flashAlert()`, `appNotify()`, `addToMailQueue()` e às funções de e-mail

Ficam de fora chamadas já envolvidas em `__`, `_x` ou `_n`, `value=` de `<option>` e linhas marcadas com `i18n-ignore`. É uma ferramenta de leitura: não falha a execução e não entra na saída padrão. Serve para fechar cada lote ("zero candidatos em `agent/modals/ticket`") e para a revisão final.

- **Alternativa: só `i18n_check` atual.** Ele só enxerga o que já foi convertido e não acha esquecimentos. Insuficiente.

### D8. Mensagens: `flashAlert` traduzido, `logAudit` em inglês

Os handlers costumam ter duas mensagens, uma para o audit log e outra para o `flashAlert`. Só a segunda é convertida. Quando um handler reaproveita a mesma variável `$msg` para as duas, ela é separada em duas. `appNotify()` e respostas automáticas em chamados são texto para pessoas, gerado no idioma do momento (spec `interface-language`).

### D9. Glossário cresce com os lotes

Antes de traduzir uma área, os termos dela são decididos e registrados em `lang/GLOSSARY.md`, por exemplo: Invoice = Fatura, Recurring = Recorrente, Credential = Credencial, Vendor = Fornecedor, Rack = Rack, Trip = Deslocamento, Reconcile = Conciliar, Statement = Extrato. Termos já fixados não mudam sem decisão explícita.

## Risks / Trade-offs

- **[Texto em inglês alterado sem querer durante a conversão]** → Em cada lote, renderizar as telas da área na master e na branch, em `en_US`, e comparar o texto visível (a mesma técnica da change anterior). Diferenças fora do esperado bloqueiam o lote.
- **[Escape quebrado ao mover texto para `__()`]** → Seguir a regra de sempre (argumento escapado no ponto da chamada, tradução não escapada). A revisão de cada lote procura `__(` com variável não escapada em contexto HTML. Rodar `php -l` em todo arquivo tocado.
- **[Valor gravado traduzido por engano]** (um `<option value>` ou comparação passada por `__()`) → Não usar `__()` em `value=` nem em `WHERE`. A varredura do D7 ignora `value=`. Testar, em cada lote com filtros, a filtragem com a interface em pt-BR.
- **[Dicionário grande demais por requisição]** → O arquivo PHP é carregado por requisição, mas fica no opcache. Medir o tempo da página do painel com 5 mil entradas ao fim do lote 3. Se for perceptível, dividir o dicionário por portal.
- **[Atributo `data-itflow-i18n` pesado]** → Ele só leva `lang/js`, que contém apenas os textos usados por scripts. Acompanhar o tamanho e manter abaixo de cerca de 30 KB.
- **[Divergência com o upstream]** → Esta é uma decisão já tomada do fork. Conflitos em merges futuros com o upstream crescem, e isso é aceito.
- **[Tradução inconsistente entre lotes]** → O glossário é decidido antes de cada lote (D9), e uma revisão final percorre o dicionário inteiro por termo.

## Migration Plan

Não há migração de banco. Cada lote é um commit (ou PR) independente na branch `feature/traducao-pt-br`. Com o idioma em `en_US`, nada muda para quem usa. Para reverter um lote, basta reverter o commit, e o texto volta a ser o inglês fixo.
