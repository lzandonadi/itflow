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
 * numbered form - and its HTML tags. php scripts/i18n_check.php pt_BR lists what
 * is missing.
 *
 * This file holds what is shared by every area; each area has its own file in
 * lang/pt_BR/ (tickets.php, billing.php...), loaded after this one and merged.
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

    // Stored values as shown - invoiceStatusLabel(), quoteStatusLabel(), ticketPriorityLabel(), ticketStatusLabel()
    'invoice status::Draft' => 'Rascunho',
    'invoice status::Sent' => 'Enviada',
    'invoice status::Viewed' => 'Visualizada',
    'invoice status::Partial' => 'Parcial',
    'invoice status::Paid' => 'Paga',
    'invoice status::Overdue' => 'Vencida',
    'invoice status::Cancelled' => 'Cancelada',
    'invoice status::Non-Billable' => 'Não faturável',
    'quote status::Draft' => 'Rascunho',
    'quote status::Sent' => 'Enviado',
    'quote status::Viewed' => 'Visualizado',
    'quote status::Accepted' => 'Aceito',
    'quote status::Declined' => 'Recusado',
    'quote status::Invoiced' => 'Faturado',
    'quote status::Expired' => 'Expirado',
    'ticket priority::Low' => 'Baixa',
    'ticket priority::Medium' => 'Média',
    'ticket priority::High' => 'Alta',
    'ticket priority::Urgent' => 'Urgente',
    'ticket status::New' => 'Novo',
    'ticket status::Open' => 'Aberto',
    'ticket status::On Hold' => 'Em espera',
    'ticket status::Resolved' => 'Resolvido',
    'ticket status::Closed' => 'Fechado',
    'Just come in - nobody has picked it up yet.' => 'Acabou de chegar - ninguém o assumiu ainda.',
    'Set when a ticket comes in. Assigning it or replying opens it.' => 'Definido quando um chamado chega. Atribuí-lo ou respondê-lo o abre.',
    'Being worked on.' => 'Em atendimento.',
    'Where a ticket goes when it is picked up, and when a client replies.' => 'Para onde o chamado vai quando é assumido e quando o cliente responde.',
    'Waiting on someone else - the client, a vendor or a part.' => 'Aguardando outra pessoa - o cliente, um fornecedor ou uma peça.',
    'Set by hand. The SLA\'s resolution clock is paused here.' => 'Definido manualmente. O prazo de solução do SLA fica pausado aqui.',
    'Fixed, and waiting to be closed - a reply from the client reopens it.' => 'Solucionado e aguardando fechamento - uma resposta do cliente o reabre.',
    'Set by Resolve, which tells the client. Closes by itself after the days set in Admin > Settings > Tickets.' => 'Definido por Resolver, que avisa o cliente. Fecha sozinho após os dias definidos em Admin > Configurações > Chamados.',
    'Finished - it can\'t be replied to.' => 'Encerrado - não pode mais ser respondido.',
    'Set by Close, or when a resolved ticket closes by itself.' => 'Definido por Fechar, ou quando um chamado resolvido fecha sozinho.',

    // Due and expiry pills, billable (functions/billing.php, functions/ticket_fields.php)
    'Past its due date' => 'Passou do vencimento',
    '%d day overdue' => ['%d dia em atraso', '%d dias em atraso'],
    'Due today' => 'Vence hoje',
    'Due in %d day' => ['Vence em %d dia', 'Vence em %d dias'],
    'Expired %d day ago' => ['Expirou há %d dia', 'Expirou há %d dias'],
    'Expires today' => 'Expira hoje',
    'Expires in %d day' => ['Expira em %d dia', 'Expira em %d dias'],
    'Billable' => 'Faturável',
    'Not billable' => 'Não faturável',

    // Tickets - list and board (agent/ticket_list.php, agent/ticket_kanban.php)
    'Today' => 'Hoje',

    // Ticket emails (functions/ticket.php)
    'Ticket closed - [%1$s] - %2$s | (do not reply)' => 'Chamado fechado - [%1$s] - %2$s | (não responda)',

    // Permission and access refusals (functions/auth.php, functions/security.php, client/functions.php, agent/ajax.php)
    'Not for your role' => 'Não disponível para o seu perfil de acesso',
    'Back' => 'Voltar',
    "Only an admin can do that - your role, %s, isn't an admin role." => 'Só um administrador pode fazer isso - o seu perfil de acesso, %s, não é de administrador.',
    "Only an admin can do that - your role isn't an admin role." => 'Só um administrador pode fazer isso - o seu perfil de acesso não é de administrador.',
    "Your role, %1\$s, can't open %2\$s." => 'O seu perfil de acesso, %1$s, não pode abrir %2$s.',
    "Your role can't open %s." => 'O seu perfil de acesso não pode abrir %s.',
    'Your role, %1$s, can only view %2$s, not change it.' => 'O seu perfil de acesso, %1$s, só pode ver %2$s, sem alterar.',
    'Your role can only view %s, not change it.' => 'O seu perfil de acesso só pode ver %s, sem alterar.',
    "Your role, %1\$s, can't delete in %2\$s - that takes Full access." => 'O seu perfil de acesso, %1$s, não pode excluir em %2$s - isso exige acesso Total.',
    "Your role can't delete in %s - that takes Full access." => 'O seu perfil de acesso não pode excluir em %s - isso exige acesso Total.',
    'An admin can change what your role allows in Admin > Roles.' => 'Um administrador pode mudar o que o seu perfil permite em Administração > Perfis de acesso.',
    'Access Denied.' => 'Acesso negado.',
    'Access Denied - You do not have permission to access that client!' => 'Acesso negado - você não tem permissão para acessar esse cliente!',
    "You don't have access to %s's tickets." => 'Você não tem acesso aos chamados de %s.',
    "You don't have access to that client's tickets." => 'Você não tem acesso aos chamados desse cliente.',
    "You don't have access to %s." => 'Você não tem acesso a %s.',
    "You don't have access to that client's documents." => 'Você não tem acesso aos documentos desse cliente.',
    "That part of the portal isn't open to you" => 'Essa parte do portal não está liberada para você',
    '403 Forbidden' => '403 Acesso negado',
    'CSRF token verification failed. Your session may have expired.' => 'Falha na verificação do token CSRF. Sua sessão pode ter expirado.',
    '<a href="/">Return to ITFlow</a> and try again.' => '<a href="/">Volte ao ITFlow</a> e tente de novo.',

    // Role modules, as named in the refusals above and in Admin > Roles (functions/roles.php roleModuleInfo())
    'Support' => 'Suporte',
    'Sales' => 'Vendas',
    'Financial' => 'Financeiro',
    'AI' => 'IA',

];
