<?php

/*
 * Portuguese (Brazil) - what ITFlow shows, keyed by the English it replaces
 * (functions/i18n.php). Terms and style follow lang/GLOSSARY.md.
 *
 *   'Add Ticket' => 'Adicionar chamado',            // __('Add Ticket')
 *   'ticket status::Open' => 'Aberto',              // _x('Open', 'ticket status')
 *   '%d ticket' => ['%d chamado', '%d chamados'],   // _n('%d ticket', '%d tickets', $n)
 *
 * Keep the placeholders of the English (%s, %d, %1$s) - reorder them with the
 * numbered form. php scripts/i18n_check.php pt_BR lists what is missing here.
 */

return [

    // Settings pages - frame and saving (functions/settings.php)
    'Settings' => 'Configurações',
    'Unsaved changes' => 'Alterações não salvas',
    'Undo changes' => 'Desfazer alterações',
    'Save' => 'Salvar',
    'Nothing changed on %s' => 'Nada foi alterado em %s',
    '%1$s saved - %2$s' => '%1$s: alterações salvas - %2$s',
    '%d change' => ['%d alteração', '%d alterações'],
    'Not saved - one thing to put right' => ['Não salvo - um item para corrigir', 'Não salvo - %d itens para corrigir'],

    // Admin > Settings > Localization
    'Localization' => 'Localização',
    'The language ITFlow is shown in, how numbers and money are written, the currency you bill in, and your timezone.' => 'O idioma em que o ITFlow é exibido, como números e valores são escritos, a moeda em que você cobra e o seu fuso horário.',
    'Language and currency' => 'Idioma e moeda',
    'The language ITFlow is shown in, how numbers and money are written - on screen, on invoices and in emails - and the currency new clients, invoices and quotes start in.' => 'O idioma em que o ITFlow é exibido, como números e valores são escritos - na tela, nas faturas e nos e-mails - e a moeda com que novos clientes, faturas e orçamentos começam.',
    'Interface language' => 'Idioma da interface',
    'For everyone - agents, the client portal and the emails ITFlow sends. What is already saved stays in the language it was written in.' => 'Para todos - técnicos, portal do cliente e os e-mails que o ITFlow envia. O que já está salvo continua no idioma em que foi escrito.',
    'Number and currency format' => 'Formato de números e moeda',
    '- Format -' => '- Formato -',
    'Currency' => 'Moeda',
    '- Currency -' => '- Moeda -',
    'What is already entered keeps the currency it was entered in - nothing is converted.' => 'O que já foi lançado mantém a moeda em que foi lançado - nada é convertido.',
    'Timezone' => 'Fuso horário',
    '- Timezone -' => '- Fuso horário -',
    'The clock ITFlow keeps - when tickets are due, when cron runs the nightly jobs, and the times on everything.' => 'O relógio do ITFlow - quando os chamados vencem, quando o cron roda as tarefas noturnas e o horário de tudo.',
    'Looks like' => 'Fica assim',
    'Pick the language from the list.' => 'Escolha o idioma na lista.',
    'Pick the format from the list.' => 'Escolha o formato na lista.',
    'Pick the currency from the list.' => 'Escolha a moeda na lista.',
    'Pick the timezone from the list.' => 'Escolha o fuso horário na lista.',

    // Tickets - list and board (agent/ticket_list.php, agent/ticket_kanban.php)
    'Today' => 'Hoje',

    // Ticket emails (functions/ticket.php)
    'Ticket closed - [%1$s] - %2$s | (do not reply)' => 'Chamado fechado - [%1$s] - %2$s | (não responda)',

];
