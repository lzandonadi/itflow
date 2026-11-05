<?php

/*
 * Portuguese (Brazil) - the part of the dictionary the front end shows, keyed by
 * the English (js/i18n.js: __('Delete this ticket?')). Only what scripts use goes
 * here: it travels with every page (i18nHtmlAttributes(), functions/i18n.php).
 * Same rules as lang/pt_BR.php.
 */

return [

    // Confirm dialog (js/confirm_modal.js)
    'Are you sure?' => 'Tem certeza?',
    'Yes' => 'Sim',
    'Cancel' => 'Cancelar',

    // Tables (DataTables - itflowI18n.dataTables())
    ': Activate to sort' => ': ative para ordenar',
    ': Activate to remove sorting' => ': ative para remover a ordenação',
    ': Activate to invert sorting' => ': ative para inverter a ordenação',
    'First' => 'Primeira',
    'Last' => 'Última',
    'Next' => 'Próxima',
    'Previous' => 'Anterior',
    'No data available in table' => 'Nenhum registro',
    'entries' => 'registros',
    'entry' => 'registro',
    'Showing _START_ to _END_ of _TOTAL_ _ENTRIES-TOTAL_' => 'Mostrando _START_ a _END_ de _TOTAL_ _ENTRIES-TOTAL_',
    'Showing 0 to 0 of 0 _ENTRIES-TOTAL_' => 'Mostrando 0 a 0 de 0 _ENTRIES-TOTAL_',
    '(filtered from _MAX_ total _ENTRIES-MAX_)' => '(filtrado de _MAX_ _ENTRIES-MAX_ no total)',
    'All' => 'Todos',
    '_MENU_ _ENTRIES_ per page' => '_MENU_ _ENTRIES_ por página',
    'Loading...' => 'Carregando...',
    'Search:' => 'Buscar:',
    'No matching records found' => 'Nenhum registro encontrado',

    // Date pickers (flatpickr - itflowI18n.localizeFlatpickr()); month and day names come from the browser
    ' to ' => ' até ',
    'Wk' => 'Sem',
    'Scroll to increment' => 'Role para alterar',
    'Click to toggle' => 'Clique para alternar',
    'Year' => 'Ano',
    'Month' => 'Mês',
    'Hour' => 'Hora',
    'Minute' => 'Minuto',

    // Calendar (FullCalendar - itflowI18n.fullCalendar())
    'Today' => 'Hoje',
    'Prev' => 'Anterior',
    'Prev year' => 'Ano anterior',
    'Next year' => 'Próximo ano',
    'Week' => 'Semana',
    'Day' => 'Dia',
    'List' => 'Lista',
    'Close' => 'Fechar',
    'Events' => 'Eventos',
    'All-day' => 'Dia inteiro',
    'Timed' => 'Com horário',
    'more' => 'mais',
    'No events to display' => 'Nenhum evento para exibir',
    'This %s' => '%s atual',
    'Previous $0' => '$0 anterior',
    'Next $0' => '$0 seguinte',
    'Change view' => 'Mudar a visualização',
    '$0 view' => 'Visualização de $0',
    'Go to $0' => 'Ir para $0',
    'Show 1 more event' => 'Mostrar mais 1 evento',
    'Show %d more events' => 'Mostrar mais %d eventos',

    // Phone fields (intl-tel-input - itflowI18n.phoneInput()); country names come from the browser
    'Change country for phone number, currently selected ${countryName} (${dialCode})' => 'Alterar o país do telefone, selecionado agora: ${countryName} (${dialCode})',
    'Select country for phone number' => 'Selecione o país do telefone',
    'List of countries' => 'Lista de países',
    'Search' => 'Buscar',
    'Clear search' => 'Limpar busca',
    'No results found' => 'Nenhum resultado encontrado',
    '1 result found' => '1 resultado encontrado',
    '%d results found' => '%d resultados encontrados',
];
