<?php

// Set Page Title 

// Get the current page name without the .php extension
$page_title = basename($_SERVER['PHP_SELF'], '.php');

// The pages whose file name doesn't make a good title; the rest are the file name, words capitalized
// i18n-ignore-start - English titles, shown through __()
$page_titles = [
    'ai_models' => 'AI Models',
    'ai_providers' => 'AI Providers',
    'ai_usage' => 'AI Usage',
    'api_keys' => 'API Keys',
    'client_overview' => 'Client Overview',
    'client_ticket_time_detail' => 'Ticket Time by Client',
    'contract_document' => 'Contract Document',
    'expense_by_vendor' => 'Expenses by Vendor',
    'expense_import' => 'Import Expenses',
    'expense_summary' => 'Expense Summary',
    'global_search' => 'Search',
    'guest_approve_ticket_task' => 'Approve Task',
    'guest_calendar_feed' => 'Calendar',
    'guest_pay_invoice_stripe' => 'Pay Invoice',
    'guest_pay_setup_stripe_customer' => 'Save a Card',
    'guest_view_contract' => 'Contract',
    'guest_view_invoice' => 'Invoice',
    'guest_view_quote' => 'Quote',
    'guest_view_ticket' => 'Ticket',
    'identity_providers' => 'Identity Providers',
    'income_by_client' => 'Income by Client',
    'income_import' => 'Import Income',
    'index' => 'Home',
    'outstanding_balances' => 'Outstanding Balances',
    'products_count' => 'Count Stock',
    'products_reprice' => 'Update Prices',
    'profit_loss' => 'Profit and Loss',
    'quote_pipeline' => 'Quote Pipeline',
    'recurring_by_client' => 'Recurring by Client',
    'saved_payment_methods' => 'Saved Cards',
    'settings_client_portal' => 'Client Portal Settings',
    'settings_company' => 'Company Settings',
    'settings_default' => 'Defaults',
    'settings_invoice' => 'Invoice Settings',
    'settings_localization' => 'Localization',
    'settings_logs' => 'Log Settings',
    'settings_mail' => 'Mail Settings',
    'settings_module' => 'Modules',
    'settings_notification' => 'Notification Settings',
    'settings_project' => 'Project Settings',
    'settings_quote' => 'Quote Settings',
    'settings_security' => 'Security Settings',
    'settings_telemetry' => 'Telemetry',
    'settings_theme' => 'Theme',
    'settings_ticket' => 'Ticket Settings',
    'shares' => 'Shared Links',
    'sign_ins' => 'Sign-ins',
    'sla' => 'SLAs',
    'sla_by_client' => 'SLA by Client',
    'sla_summary' => 'SLA Summary',
    'software_templates' => 'License Templates',
    'tax_rates' => 'Taxes',
    'tax_summary' => 'Tax Summary',
    'ticket_aging' => 'Ticket Aging',
    'ticket_by_client' => 'Tickets by Client',
    'ticket_summary' => 'Ticket Summary',
    'tickets_unbilled' => 'Unbilled Tickets',
    'time_by_tech' => 'Time by Agent',
    'user_activity' => 'Activity',
    'user_canned_responses' => 'My Canned Responses',
    'user_details' => 'My Details',
    'user_preferences' => 'Preferences',
    'user_security' => 'Security',
];
// i18n-ignore-end
$page_title = $page_titles[$page_title] ?? ucwords(str_replace('_', ' ', $page_title));

// In English for SQL input such as logging
$page_title_sanitized = escapeSql($page_title);

// In the interface language, safe for output
$page_title = escapeHtml(__($page_title)); // i18n-dynamic - the table above and the file names

$tab_title = $session_company_name;
