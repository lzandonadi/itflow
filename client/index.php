<?php
/*
 * Client Portal
 * Home - what needs this contact: tickets waiting on their reply or for them
 * to confirm, approvals, invoices past due, quotes waiting for an answer,
 * contracts waiting for a signature;
 * their open tickets, the balance, the request forms they can use,
 * renewals coming up and the devices they use.
 *
 * SCOPING: everything here is keyed to $session_client_id from the session,
 * tickets through portalTicketScopeSql() (their own, or the whole company's
 * for primary and technical contacts), and the money and IT sections are only
 * read for a contact who may see them.
 */

header("Content-Security-Policy: default-src 'self'");

require_once "includes/inc_all.php";

$scope_sql = portalTicketScopeSql();
$sees_all = portalSeesAllTickets();
$can_billing = contactCan('accounting') && $config_module_enable_accounting == 1;
$can_it = $config_module_enable_itdoc && contactCan('itdoc');
$ticket_from = "FROM tickets LEFT JOIN ticket_statuses ON ticket_status = ticket_status_id WHERE $scope_sql AND tickets.ticket_archived_at IS NULL";

// Tickets - counted, and the ones that need the contact
$tabs = portalTicketTabs();
$counts = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT
    COALESCE(SUM({$tabs['open']['where']}), 0) AS open_count,
    COALESCE(SUM({$tabs['resolved']['where']}), 0) AS resolved_count
    $ticket_from"));
$open_count = intval($counts['open_count']);
$resolved_count = intval($counts['resolved_count']);
$you_count = intval(mysqli_fetch_row(mysqli_query($mysqli, "SELECT COUNT(*) $ticket_from AND {$tabs['you']['where']}"))[0]);

$needs_you = [];
$sql = mysqli_query($mysqli, "SELECT " . portalTicketColumnsSql() . " $ticket_from AND ({$tabs['you']['where']} OR {$tabs['resolved']['where']})
    ORDER BY COALESCE(tickets.ticket_updated_at, tickets.ticket_created_at) DESC LIMIT 6");
while ($row = mysqli_fetch_assoc($sql)) {
    $resolved = !empty($row['ticket_resolved_at']);
    $needs_you[] = [
        'href' => 'ticket.php?id=' . intval($row['ticket_id']),
        'icon' => $resolved ? 'fa-check' : 'fa-reply',
        'tone' => $resolved ? 'is-info' : '',
        'title' => $row['ticket_subject'],
        'line' => ($resolved ? __('Resolved - is it fixed? Close it, or reopen it') : __('We replied - waiting on you')) . ' · ' . $row['ticket_prefix'] . $row['ticket_number'],
    ];
}

// Tasks on their tickets waiting for the client's approval
$sql = mysqli_query($mysqli, "SELECT tickets.ticket_id, ticket_prefix, ticket_number, task_name FROM task_approvals
    JOIN tasks ON task_id = approval_task_id
    JOIN tickets ON tickets.ticket_id = task_ticket_id
    LEFT JOIN ticket_statuses ON ticket_status = ticket_status_id
    WHERE $scope_sql AND approval_scope = 'client' AND approval_status = 'pending' AND task_completed_at IS NULL AND tickets.ticket_closed_at IS NULL
    ORDER BY approval_id DESC LIMIT 4");
while ($row = mysqli_fetch_assoc($sql)) {
    $needs_you[] = [
        'href' => 'ticket.php?id=' . intval($row['ticket_id']),
        'icon' => 'fa-clipboard-check',
        'tone' => '',
        'title' => 'Approve: ' . $row['task_name'],
        'line' => __('Waiting for your approval · %s', $row['ticket_prefix'] . $row['ticket_number']),
    ];
}

// Money - what is owed, and what is past due
$balance = 0.0;
$overdue = 0.0;
$overdue_count = 0;
$next_due = null;
$client_currency = $session_company_currency;
if ($can_billing) {
    $client_currency = mysqli_fetch_row(mysqli_query($mysqli, "SELECT client_currency_code FROM clients WHERE client_id = $session_client_id"))[0] ?: $session_company_currency;
    foreach (billingClientOpenInvoices($session_client_id) as $invoice) {
        $left = round(floatval($invoice['invoice_amount']) - floatval($invoice['paid']), 2);
        $balance += $left;
        if ($invoice['invoice_due'] < date('Y-m-d')) {
            $overdue += $left;
            $overdue_count++;
            $needs_you[] = [
                'href' => 'invoice.php?id=' . intval($invoice['invoice_id']),
                'icon' => 'fa-file-invoice-dollar',
                'tone' => 'is-danger',
                'title' => __('Invoice %s is past due', $invoice['invoice_prefix'] . $invoice['invoice_number']),
                'line' => __('%1$s left - due %2$s', numfmt_format_currency($currency_format, $left, $client_currency), formatDate($invoice['invoice_due'])),
            ];
        } elseif ($next_due === null || $invoice['invoice_due'] < $next_due) {
            $next_due = $invoice['invoice_due'];
        }
    }

    // Quotes waiting for their answer
    $sql = mysqli_query($mysqli, "SELECT quote_id, quote_prefix, quote_number, quote_scope, quote_amount, quote_currency_code, quote_expire FROM quotes
        WHERE quote_client_id = $session_client_id AND " . portalQuoteWaitingSql() . " ORDER BY quote_expire ASC LIMIT 4");
    while ($row = mysqli_fetch_assoc($sql)) {
        $needs_you[] = [
            'href' => 'quote.php?id=' . intval($row['quote_id']),
            'icon' => 'fa-comment-dollar',
            'tone' => '',
            'title' => __('Quote %s is waiting for your answer', $row['quote_prefix'] . $row['quote_number']),
            'line' => trim($row['quote_scope'] . ' · ' . billingMoney($row['quote_amount'], $row['quote_currency_code']), ' ·') . ' · ' . __('good until %s', formatDate($row['quote_expire'], 'date_short')),
        ];
    }

    // Contracts waiting for their signature
    if (contractsReady() && contractSignaturesReady()) {
        $sql = mysqli_query($mysqli, "SELECT contract_id, contract_name, contract_type FROM contracts WHERE " . portalContractScopeSql() . " AND " . portalContractWaitingSql() . " ORDER BY contract_name ASC LIMIT 4");
        while ($row = mysqli_fetch_assoc($sql)) {
            $needs_you[] = [
                'href' => 'contract.php?id=' . intval($row['contract_id']),
                'icon' => 'fa-file-signature',
                'tone' => '',
                'title' => __('%s is waiting for your signature', $row['contract_name']),
                'line' => __('%s contract - read it and sign it here', contractTypeLabel($row['contract_type'])),
            ];
        }
    }
}
$pay_href = $can_billing && billingStripeProvider() ? 'unpaid_invoices.php' : 'invoices.php';

// Open tickets, newest first
$recent = mysqli_query($mysqli, "SELECT " . portalTicketColumnsSql() . ", contact_name FROM tickets
    LEFT JOIN ticket_statuses ON ticket_status = ticket_status_id
    LEFT JOIN contacts ON contact_id = tickets.ticket_contact_id
    WHERE $scope_sql AND tickets.ticket_archived_at IS NULL AND {$tabs['open']['where']}
    ORDER BY COALESCE(tickets.ticket_updated_at, tickets.ticket_created_at) DESC LIMIT 5");

// Renewals coming up in the next 45 days, and anything that lapsed in the last 30
$renewals = [];
if ($can_it) {
    $window = "BETWEEN CURDATE() - INTERVAL 30 DAY AND CURDATE() + INTERVAL 45 DAY";
    $sources = [
        ['domains.php', 'fa-globe', __('Domain'), "SELECT domain_name, domain_expire, 0 FROM domains WHERE domain_client_id = $session_client_id AND domain_archived_at IS NULL AND domain_expire $window"],
        ['domains.php?tab=certificates', 'fa-lock', __('Certificate'), "SELECT certificate_name, certificate_expire, 0 FROM certificates WHERE certificate_client_id = $session_client_id AND certificate_archived_at IS NULL AND certificate_expire $window"],
        ['licenses.php', 'fa-cube', __('License'), "SELECT software_name, software_expire, 0 FROM software WHERE software_client_id = $session_client_id AND software_archived_at IS NULL AND software_expire $window"],
        ['asset.php?id=', 'fa-shield-alt', __('Warranty'), "SELECT asset_name, asset_warranty_expire, asset_id FROM assets WHERE asset_client_id = $session_client_id AND asset_archived_at IS NULL AND asset_warranty_expire $window"],
    ];
    foreach ($sources as [$href, $icon, $kind, $query]) {
        $sql = mysqli_query($mysqli, $query);
        while ($row = mysqli_fetch_row($sql)) {
            $renewals[] = ['href' => $href . (intval($row[2]) ? intval($row[2]) : ''), 'icon' => $icon, 'kind' => $kind, 'name' => $row[0], 'date' => $row[1]];
        }
    }
    usort($renewals, function ($a, $b) { return strcmp($a['date'], $b['date']); });
}

// The devices this contact uses
$devices = [];
$sql = mysqli_query($mysqli, "SELECT asset_id, asset_name, asset_type, asset_make, asset_model, asset_warranty_expire, asset_uri_client FROM assets
    WHERE asset_contact_id = $session_contact_id AND asset_client_id = $session_client_id AND asset_archived_at IS NULL ORDER BY asset_name ASC LIMIT 6");
while ($row = mysqli_fetch_assoc($sql)) {
    $devices[] = $row;
}

// The request forms they can use - ticket templates offered in the portal
$request_forms = ticketTemplatePortalForms($session_client_id, $sees_all);

// How to reach us
$company = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT company_phone, company_phone_country_code, company_email, company_website FROM companies WHERE company_id = 1"));

$hour = intval(date('G'));
$greeting = $hour < 12 ? __('Good morning') : ($hour < 18 ? __('Good afternoon') : __('Good evening'));
$first_name = strtok(trim((string) $contact['contact_name']), ' ') ?: '';
?>

<div class="itflow-portal-page-head">
    <div>
        <h1><?= escapeHtml(rtrim("$greeting, $first_name", ', ')) ?></h1>
        <p><?= escapeHtml($session_client_name) ?></p>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <?php if ($can_billing && $balance > 0) { ?>
            <a class="btn btn-outline-primary" href="<?= $pay_href ?>"><i class="fas fa-<?= $pay_href === 'unpaid_invoices.php' ? 'credit-card' : 'file-invoice-dollar' ?> me-2"></i><?= $pay_href === 'unpaid_invoices.php' ? __('Pay invoices') : __('Invoices') ?></a>
        <?php } ?>
    </div>
</div>

<?php foreach (portalAnnouncementsFor($session_client_id) as $announcement) {
    $tone_key = isset(PORTAL_ANNOUNCEMENT_TONES[$announcement['announcement_tone']]) ? $announcement['announcement_tone'] : 'info';
    $tone = PORTAL_ANNOUNCEMENT_TONES[$tone_key]; ?>
    <section class="itflow-portal-announcement is-<?= $tone_key ?> mb-3" aria-label="<?= escapeHtml(__($tone['label'])) /* i18n-dynamic */ ?>">
        <span class="itflow-portal-announcement-icon" aria-hidden="true"><i class="fas <?= $tone['icon'] ?>"></i></span>
        <div class="min-w-0">
            <h2 class="itflow-portal-announcement-title"><?= escapeHtml($announcement['announcement_title']) ?></h2>
            <?php if (trim((string) $announcement['announcement_body']) !== '') { ?>
                <div class="itflow-portal-announcement-body"><?= portalAnnouncementBodyHtml($announcement['announcement_body']) ?></div>
            <?php } ?>
            <div class="small text-body-secondary mt-1"><?= escapeHtml(__($tone['label'])) /* i18n-dynamic */ ?> · <?= escapeHtml(formatDate($announcement['announcement_starts_at'] ?: $announcement['announcement_created_at'])) ?></div>
        </div>
    </section>
<?php } ?>

<div class="row g-3 mb-4">
    <div class="col-6 col-lg-3">
        <a class="itflow-portal-stat" href="tickets.php">
            <span class="itflow-portal-stat-icon"><i class="fas fa-life-ring"></i></span>
            <span>
                <span class="itflow-portal-stat-value d-block"><?= $open_count ?></span>
                <span class="itflow-portal-stat-label"><?= $sees_all ? ($open_count === 1 ? __('Open ticket') : __('Open tickets')) : ($open_count === 1 ? __('Open ticket of yours') : __('Open tickets of yours')) ?></span>
            </span>
        </a>
    </div>
    <div class="col-6 col-lg-3">
        <a class="itflow-portal-stat<?= $you_count ? ' itflow-portal-stat-alert' : '' ?>" href="tickets.php?tab=you">
            <span class="itflow-portal-stat-icon"><i class="fas fa-reply"></i></span>
            <span>
                <span class="itflow-portal-stat-value d-block"><?= $you_count ?></span>
                <span class="itflow-portal-stat-label"><?= __('Waiting on you') ?></span>
            </span>
        </a>
    </div>
    <?php if ($can_billing) { ?>
        <div class="col-6 col-lg-3">
            <a class="itflow-portal-stat<?= $overdue > 0 ? ' itflow-portal-stat-danger' : '' ?>" href="<?= $balance > 0 ? $pay_href : 'invoices.php' ?>">
                <span class="itflow-portal-stat-icon"><i class="fas fa-file-invoice-dollar"></i></span>
                <span class="min-w-0">
                    <span class="itflow-portal-stat-value d-block"><?= numfmt_format_currency($currency_format, $balance, $client_currency) ?></span>
                    <span class="itflow-portal-stat-label">
                        <?php if ($overdue > 0) { ?>
                            <?= __('%s past due', numfmt_format_currency($currency_format, $overdue, $client_currency)) ?>
                        <?php } elseif ($balance > 0 && $next_due) { ?>
                            <?= __('Due %s', formatDate($next_due, 'date_short')) ?>
                        <?php } else { ?>
                            <?= __('Nothing owed') ?>
                        <?php } ?>
                    </span>
                </span>
            </a>
        </div>
    <?php } ?>
    <?php if ($can_it) { ?>
        <div class="col-6 col-lg-3">
            <a class="itflow-portal-stat" href="#renewals">
                <span class="itflow-portal-stat-icon"><i class="fas fa-calendar-alt"></i></span>
                <span>
                    <span class="itflow-portal-stat-value d-block"><?= count($renewals) ?></span>
                    <span class="itflow-portal-stat-label"><?= count($renewals) === 1 ? __('Renewal coming up') : __('Renewals coming up') ?></span>
                </span>
            </a>
        </div>
    <?php } ?>
</div>

<div class="row g-4">
    <div class="col-lg-8">

        <section class="itflow-portal-panel mb-4">
            <div class="itflow-portal-panel-head">
                <h2><i class="fas fa-bell"></i><?= __('Needs you') ?></h2>
            </div>
            <?php if (!$needs_you) { ?>
                <?= emptyStateHtml(['title' => __('You\'re all caught up'), 'text' => __('Nothing is waiting on you right now.'), 'icon' => 'fa-check', 'filtered' => false]) ?>
            <?php } else { ?>
                <ul class="itflow-portal-list">
                    <?php foreach (array_slice($needs_you, 0, 8) as $item) { ?>
                        <li>
                            <a class="itflow-portal-todo" href="<?= escapeHtml($item['href']) ?>">
                                <span class="itflow-portal-todo-icon <?= $item['tone'] ?>"><i class="fas <?= $item['icon'] ?>"></i></span>
                                <span class="itflow-portal-todo-main">
                                    <div><?= escapeHtml($item['title']) ?></div>
                                    <div><?= escapeHtml($item['line']) ?></div>
                                </span>
                                <i class="fas fa-chevron-right text-body-secondary small"></i>
                            </a>
                        </li>
                    <?php } ?>
                </ul>
            <?php } ?>
        </section>

        <section class="itflow-portal-panel">
            <div class="itflow-portal-panel-head">
                <h2><i class="fas fa-life-ring"></i><?= $sees_all ? __('Open tickets') : __('Your open tickets') ?></h2>
                <a href="tickets.php"><?= __('All tickets') ?></a>
            </div>
            <?php if (!mysqli_num_rows($recent)) { ?>
                <?= emptyStateHtml(['title' => __('No open tickets'), 'text' => __('Something not working? Raise a ticket and we will pick it up.'), 'icon' => 'fa-life-ring', 'filtered' => false,
                    'actions' => [['label' => __('New ticket'), 'href' => 'ticket_add.php']]]) ?>
            <?php } else { ?>
                <ul class="itflow-portal-list">
                    <?php while ($row = mysqli_fetch_assoc($recent)) {
                        $updated = $row['ticket_updated_at'] ?: $row['ticket_created_at']; ?>
                        <li>
                            <a class="itflow-portal-ticket" href="ticket.php?id=<?= intval($row['ticket_id']) ?>">
                                <span class="itflow-portal-ticket-main">
                                    <span class="itflow-portal-ticket-subject d-block"><?= escapeHtml($row['ticket_subject']) ?></span>
                                    <span class="itflow-portal-ticket-meta">
                                        <span><?= escapeHtml($row['ticket_prefix'] . $row['ticket_number']) ?></span>
                                        <?php if ($sees_all && !empty($row['contact_name'])) { ?><span><i class="fas fa-user me-1"></i><?= escapeHtml($row['contact_name']) ?></span><?php } ?>
                                    </span>
                                </span>
                                <span class="itflow-portal-ticket-side">
                                    <?= portalTicketStateHtml($row) ?>
                                    <small title="<?= escapeHtml($updated) ?>"><?= timeAgo($updated) ?></small>
                                </span>
                            </a>
                        </li>
                    <?php } ?>
                </ul>
            <?php } ?>
        </section>

    </div>
    <div class="col-lg-4">

        <?php if ($request_forms) { ?>
            <section class="itflow-portal-panel mb-4">
                <div class="itflow-portal-panel-head">
                    <h2><i class="fas fa-clipboard-list"></i><?= __('Ask for something') ?></h2>
                    <?php if (count($request_forms) > 5) { ?><a href="ticket_add.php"><?= __('All %d', count($request_forms)) ?></a><?php } ?>
                </div>
                <ul class="itflow-portal-list">
                    <?php foreach (array_slice($request_forms, 0, 5) as $row) { ?>
                        <li>
                            <a class="itflow-portal-todo" href="ticket_add.php?form=<?= intval($row['ticket_template_id']) ?>">
                                <span class="itflow-portal-tile itflow-portal-tile-sm"><i class="fas fa-fw <?= ticketTemplatePortalIcon($row) ?>"></i></span>
                                <span class="itflow-portal-todo-main">
                                    <div><?= escapeHtml(ticketTemplatePortalTitle($row)) ?></div>
                                    <?php if (trim((string) $row['ticket_template_portal_description']) !== '') { ?><div><?= escapeHtml($row['ticket_template_portal_description']) ?></div><?php } ?>
                                </span>
                                <i class="fas fa-chevron-right text-body-secondary small"></i>
                            </a>
                        </li>
                    <?php } ?>
                </ul>
            </section>
        <?php } ?>

        <?php if ($devices) { ?>
            <section class="itflow-portal-panel mb-4">
                <div class="itflow-portal-panel-head">
                    <h2><i class="fas fa-desktop"></i><?= __('Your devices') ?></h2>
                    <?php if ($can_it) { ?><a href="assets.php"><?= __('All devices') ?></a><?php } ?>
                </div>
                <ul class="itflow-portal-list">
                    <?php foreach ($devices as $device) {
                        $warranty = assetWarranty($device['asset_warranty_expire']);
                        $what = trim($device['asset_make'] . ' ' . $device['asset_model']) ?: $device['asset_type'];
                        ?>
                        <li>
                            <a class="itflow-portal-todo" href="asset.php?id=<?= intval($device['asset_id']) ?>">
                                <span class="itflow-portal-todo-icon is-info"><i class="fas fa-<?= getAssetIcon($device['asset_type']) ?>"></i></span>
                                <span class="itflow-portal-todo-main">
                                    <div><?= escapeHtml($device['asset_name']) ?></div>
                                    <div>
                                        <?= escapeHtml($what) ?>
                                        <?php if ($warranty && $warranty['tone'] !== 'success') { ?>
                                            · <span class="text-<?= $warranty['tone'] ?>-emphasis"><?= escapeHtml(__('Warranty %s', mb_strtolower($warranty['short']))) ?></span>
                                        <?php } ?>
                                    </div>
                                </span>
                                <i class="fas fa-chevron-right text-body-secondary small"></i>
                            </a>
                        </li>
                    <?php } ?>
                </ul>
            </section>
        <?php } ?>

        <?php if ($can_it) { ?>
            <section class="itflow-portal-panel mb-4" id="renewals">
                <div class="itflow-portal-panel-head">
                    <h2><i class="fas fa-calendar-alt"></i><?= __('Coming up') ?></h2>
                </div>
                <?php if (!$renewals) { ?>
                    <?= emptyStateHtml(['title' => __('Nothing renewing soon'), 'text' => __('Domains, certificates, licenses and warranties due in the next 45 days show here.'), 'icon' => 'fa-check', 'filtered' => false]) ?>
                <?php } else { ?>
                    <ul class="itflow-portal-list">
                        <?php foreach (array_slice($renewals, 0, 8) as $item) {
                            $lapsed = $item['date'] < date('Y-m-d'); ?>
                            <li>
                                <a class="itflow-portal-todo" href="<?= escapeHtml($item['href']) ?>">
                                    <span class="itflow-portal-todo-icon<?= $lapsed ? ' is-danger' : '' ?>"><i class="fas <?= $item['icon'] ?>"></i></span>
                                    <span class="itflow-portal-todo-main">
                                        <div><?= escapeHtml($item['name']) ?></div>
                                        <div><?= escapeHtml($item['kind']) ?> · <?= escapeHtml($lapsed ? __('expired %s', formatDate($item['date'])) : __('renews %s', formatDate($item['date']))) ?> (<?= timeAgo($item['date']) ?>)</div>
                                    </span>
                                    <i class="fas fa-chevron-right text-body-secondary small"></i>
                                </a>
                            </li>
                        <?php } ?>
                    </ul>
                <?php } ?>
            </section>
        <?php } ?>

        <?php if (!empty($company['company_phone']) || !empty($company['company_email'])) { ?>
            <section class="itflow-portal-panel">
                <div class="itflow-portal-panel-head">
                    <h2><i class="fas fa-headset"></i><?= __('Need us fast?') ?></h2>
                </div>
                <div class="itflow-portal-panel-body">
                    <p class="text-body-secondary small mb-2"><?= __('For anything urgent, call us - and raise a ticket so it is on record.') ?></p>
                    <?php if (!empty($company['company_phone'])) { ?>
                        <div class="mb-1"><i class="fas fa-fw fa-phone me-2 text-body-secondary"></i><a href="tel:<?= escapeHtml(preg_replace('/[^0-9+]/', '', ($company['company_phone_country_code'] ? '+' . $company['company_phone_country_code'] : '') . $company['company_phone'])) ?>"><?= escapeHtml(formatPhoneNumber($company['company_phone'], $company['company_phone_country_code'])) ?></a></div>
                    <?php } ?>
                    <?php if (!empty($company['company_email'])) { ?>
                        <div><i class="fas fa-fw fa-envelope me-2 text-body-secondary"></i><a href="mailto:<?= escapeHtml($company['company_email']) ?>"><?= escapeHtml($company['company_email']) ?></a></div>
                    <?php } ?>
                </div>
            </section>
        <?php } ?>

    </div>
</div>

<?php require_once "includes/footer.php"; ?>
