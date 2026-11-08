<?php

defined('FROM_POST_HANDLER') || die("Direct file access is not allowed");

if (isset($_POST['load_starter_content'])) {

    validateCSRFToken();

    require_once 'starter_content_model.php';

    $starter_content_packs = starterContentPacks();
    $requested_pack = $_POST['load_starter_content'];

    // Only registry keys are accepted - the value goes nowhere near a query, but
    // an unknown pack should say so rather than silently do nothing
    if ($requested_pack === 'all') {
        $selected_packs = array_keys($starter_content_packs);
    } elseif (isset($starter_content_packs[$requested_pack])) {
        $selected_packs = [$requested_pack];
    } else {
        flashAlert(__('Unknown starter content pack'), 'error');
        redirect();
    }

    // One pack's Add takes the items ticked under its Pick which
    // (includes/starter_content_pick.php) - the whole pack when the list wasn't sent
    $only = null;
    if ($requested_pack !== 'all' && isset($_POST['picked'][$requested_pack])) {
        $only = array_map('strval', array_filter((array) ($_POST['items'][$requested_pack] ?? []), 'is_string'));
        if (!$only) {
            flashAlert(__('Nothing is ticked under Pick which - tick what to add'), 'info');
            redirect();
        }
    }

    // Registry order is load order - categories before products, ticket
    // templates before the project templates that link to them
    $items_added = 0;
    $summary = [];
    $summary_shown = [];
    foreach ($selected_packs as $pack) {
        $pack_added = starterContentLoad($mysqli, $pack, false, $only);
        if ($pack_added) {
            $items_added = $items_added + $pack_added;
            $summary[] = "$pack_added " . $starter_content_packs[$pack]['label'];
            $summary_shown[] = "$pack_added " . __($starter_content_packs[$pack]['label']); // i18n-dynamic - starterContentPacks()
        }
    }

    if ($items_added) {
        $summary = implode(', ', $summary);
        logAudit("Starter Content", "Create", "$session_name loaded starter content - $summary");
        flashAlert(__('Added <strong>%1$s</strong> items: %2$s', $items_added, implode(', ', $summary_shown)));
    } else {
        flashAlert(__('Nothing to add - everything in that selection is already here'), 'info');
    }

    redirect();

}

if (isset($_POST['update_starter_ticket_templates'])) {

    // The starter ticket templates already here, brought up to this version
    validateCSRFToken();

    require_once 'starter_content_model.php';

    $counts = starterUpdateTicketTemplates();

    if ($counts['replaced']) {
        logAudit("Starter Content", "Edit", "$session_name brought {$counts['replaced']} starter ticket templates up to date");
        flashAlert(($counts['forms'] ? __('Brought <strong>%1$d</strong> starter ticket templates up to date - <strong>%2$d</strong> have a request form, turned off until you turn it on in their Client portal tab', $counts['replaced'], $counts['forms']) : __('Brought <strong>%d</strong> starter ticket templates up to date', $counts['replaced'])));
    } else {
        flashAlert(__('None of the starter ticket templates are here to update - add them instead'), 'info');
    }

    redirect();

}

if (isset($_POST['update_starter_canned_responses'])) {

    // The starter canned responses already here, brought up to this version
    validateCSRFToken();

    require_once 'starter_content_model.php';

    $counts = starterUpdateCannedResponses();

    if ($counts['replaced']) {
        logAudit("Starter Content", "Edit", "$session_name brought {$counts['replaced']} starter canned responses up to date");
        flashAlert(__('Brought <strong>%d</strong> starter canned responses up to date', $counts['replaced']));
    } else {
        flashAlert(__('None of the starter canned responses are here to update - add them instead'), 'info');
    }

    redirect();

}

if (isset($_POST['update_starter_document_templates'])) {

    // The starter document templates already here, brought up to this version
    validateCSRFToken();

    require_once 'starter_content_model.php';

    $counts = starterUpdateDocumentTemplates();

    if ($counts['replaced']) {
        logAudit("Starter Content", "Edit", "$session_name brought {$counts['replaced']} starter document templates up to date");
        flashAlert(__('Brought <strong>%d</strong> starter document templates up to date', $counts['replaced']));
    } else {
        flashAlert(__('None of the starter document templates are here to update - add them instead'), 'info');
    }

    redirect();

}

if (isset($_POST['update_starter_vendor_templates'])) {

    // The starter vendor templates already here, brought up to this version - the vendors made from them following
    validateCSRFToken();

    require_once 'starter_content_model.php';

    $counts = starterUpdateVendorTemplates();
    vendorTemplatesImportLogFollowed($counts);
    $followed = vendorTemplatesImportFollowedCount($counts);

    if ($counts['replaced']) {
        logAudit("Starter Content", "Edit", "$session_name brought {$counts['replaced']} starter vendor templates up to date" . ($followed ? " - $followed vendors changed with them" : ''));
        flashAlert(__('Brought <strong>%d</strong> starter vendor templates up to date', $counts['replaced']) . ($followed ? _n(' - <strong>%d</strong> vendor made from them changed with them', ' - <strong>%d</strong> vendors made from them changed with them', $followed) : ''));
    } else {
        flashAlert(__('None of the starter vendor templates are here to update - add them instead'), 'info');
    }

    redirect();

}

if (isset($_POST['update_starter_license_templates'])) {

    // The starter license templates already here, brought up to this version
    validateCSRFToken();

    require_once 'starter_content_model.php';

    $counts = starterUpdateLicenseTemplates();

    if ($counts['replaced']) {
        logAudit("Starter Content", "Edit", "$session_name brought {$counts['replaced']} starter license templates up to date");
        flashAlert(__('Brought <strong>%d</strong> starter license templates up to date', $counts['replaced']) . ($counts['vendors'] ? _n(' - with <strong>%d</strong> vendor template they come from', ' - with <strong>%d</strong> vendor templates they come from', $counts['vendors']) : ''));
    } else {
        flashAlert(__('None of the starter license templates are here to update - add them instead'), 'info');
    }

    redirect();

}

if (isset($_POST['load_demo_data'])) {

    validateCSRFToken();

    require_once 'demo_data_model.php';

    $counts = demoDataLoad($mysqli);

    if (!$counts['clients']) {
        flashAlert(__('Nothing to add - every demo client is already here'), 'info');
        redirect();
    }

    $summary = "{$counts['clients']} clients, {$counts['contacts']} contacts, {$counts['assets']} assets, {$counts['documentation']} documentation records, {$counts['tickets']} tickets, {$counts['projects']} projects, {$counts['billing']} billing records, {$counts['company']} company records";


    logAudit("Demo Data", "Create", "$session_name loaded demo data - $summary");

    if ($counts['skipped_credentials']) {
        flashAlert(__('Added demo data: %s. Credentials were skipped - the credential vault did not open for this session', $summary), 'warning');
    } else {
        flashAlert(__('Added demo data: %s', $summary));
    }

    redirect();

}

if (isset($_POST['remove_demo_data'])) {

    validateCSRFToken();

    require_once 'demo_data_model.php';

    $clients_removed = demoDataRemove($mysqli);

    if ($clients_removed) {
        logAudit("Demo Data", "Delete", "$session_name removed demo data - $clients_removed demo clients and all associated records");
        flashAlert(__('Removed <strong>%s</strong> demo clients and everything associated with them', $clients_removed), 'error');
    } else {
        flashAlert(__('No demo clients found - nothing to remove'), 'info');
    }

    redirect();

}
