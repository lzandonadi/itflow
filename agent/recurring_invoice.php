<?php

/*
 * Recurring Invoice - an agreement billed every month, quarter or year.
 *
 * The header says whether it is running and when it next bills - and,
 * when a run was missed, whether the nightly run still bills it or it is
 * too far behind and needs Invoice Now or moving on. The template is on the left as
 * the invoices it makes will look, its lines edited in place. On the
 * right: the schedule (what it brings in a month, the period the next
 * invoice covers and the dates after, whether invoices go out by
 * themselves), its per-user and per-device lines against what the client
 * has now, what it costs us (the recurring expenses it covers, and the
 * margin left), the card it charges, the invoices it has made and whether
 * they were paid, and its history. The contract it bills, when it is
 * linked to one - it starts and stops with it (functions/contract_billing.php).
 */

// If client_id is in URI then show client Side Bar and client header
if (isset($_GET['client_id'])) {
    require_once "includes/inc_all_client.php";
    $client_url = "client_id=$client_id&";
} else {
    require_once "includes/inc_all.php";
    $client_url = '';
}

// Perms
enforceUserPermission('module_sales');

$recurring_invoice_id = intval($_GET['recurring_invoice_id'] ?? 0);
$recurring = billingRow('recurring', $recurring_invoice_id);

if (!$recurring || ($client_url && intval($recurring['client_id']) !== intval($client_id))) {
    billingNotFound('recurring', $client_url);
    require_once "../includes/footer.php";
    exit;
}

$client_id = intval($recurring['client_id']);
enforceClientAccess();

$number = billingNumber('recurring', $recurring);
$active = intval($recurring['recurring_invoice_status']) === 1;
$frequency = (string) $recurring['recurring_invoice_frequency'];
$frequency_label = recurringInvoiceFrequencies()[$frequency] ?? ucfirst($frequency);
$currency = (string) $recurring['recurring_invoice_currency_code'];
$money = function ($amount) use ($currency) {
    return escapeHtml(billingMoney($amount, $currency));
};

$items = billingItems('recurring', $recurring_invoice_id);
$totals = billingTotals('recurring', $recurring, $items);
$history = billingHistory('recurring', $recurring_invoice_id);
$generated = recurringInvoiceGenerated($recurring_invoice_id, 8);
$generated_count = intval(mysqli_fetch_row(mysqli_query($mysqli, "SELECT COUNT(*) FROM invoices WHERE invoice_recurring_invoice_id = $recurring_invoice_id"))[0]);
$upcoming = $active ? recurringInvoiceUpcoming($recurring, 4) : [];
$late = $active && $recurring['recurring_invoice_next_date'] < date('Y-m-d');
$late_days = $late ? intval(round((strtotime(date('Y-m-d')) - strtotime($recurring['recurring_invoice_next_date'])) / 86400)) : 0;
$period_word = recurringInvoicePeriodWord($frequency);
$next_period = recurringInvoicePeriod($recurring, $recurring['recurring_invoice_next_date']);
$after_next = recurringInvoiceAfter($recurring, $recurring['recurring_invoice_next_date']);
$seat_lines = recurringSeatLines($recurring_invoice_id, $client_id);
$seat_drift = count(array_filter($seat_lines, function ($line) { return $line['seat_drift']; }));
$seat_sources = recurringSeatSources();
$auto_send = intval($recurring['recurring_invoice_auto_send']) === 1;
// The contract it bills - it starts and stops with it
$for_contract = contractForRecurring($recurring_invoice_id);
$email_notify = intval($recurring['recurring_invoice_email_notify']) === 1;

$can_edit = lookupUserPermission('module_sales') >= 2;
$can_delete = lookupUserPermission('module_sales') >= 3;
$csrf = $_SESSION['csrf_token'];

// What it costs us - the recurring expenses it covers (functions/expenses.php)
$can_see_costs = lookupUserPermission('module_financial') >= 1;
$can_add_costs = lookupUserPermission('module_financial') >= 2;
$costs = $can_see_costs ? recurringInvoiceCosts($recurring_invoice_id) : null;

// Cards on file the client could be charged on
$saved_methods = [];
$sql = mysqli_query($mysqli, "SELECT saved_payment_id, saved_payment_description, payment_provider_name, payment_provider_active FROM client_saved_payment_methods
    LEFT JOIN payment_providers ON payment_provider_id = saved_payment_provider_id
    WHERE saved_payment_client_id = $client_id ORDER BY saved_payment_description ASC");
while ($row = mysqli_fetch_assoc($sql)) {
    $saved_methods[intval($row['saved_payment_id'])] = $row;
}
$autopay_id = intval($recurring['recurring_payment_saved_payment_id'] ?? 0);
// An autopay that can charge: a card the client still has on file, with its provider on
// (billingAutopayUsableSql()). One without - set up before 2.2.3, or its provider turned off -
// charges nothing, and the nightly job tells the team on each due date until it is turned off
$autopay_usable = $autopay_id && isset($saved_methods[$autopay_id]) && intval($saved_methods[$autopay_id]['payment_provider_active']) === 1;
$autopay_stale = intval($recurring['recurring_payment_id'] ?? 0) > 0 && !$autopay_usable;

// Override Tab Title // No Sanitizing needed as this var will only be used in the tab title
$tab_title = $recurring['client_name'];
$page_title = $number;

$paper = [
    'type' => 'recurring',
    'row' => $recurring,
    'items' => $items,
    'totals' => $totals,
    'edit' => $can_edit,
    'notes_edit' => $can_edit,
    'taxes' => $can_edit ? billingTaxes($recurring['recurring_invoice_created_at']) : [],
    'logo_url' => billingCompany()['logo_url'],
    'csrf' => $csrf,
];

?>

<ol class="breadcrumb d-print-none">
    <li class="breadcrumb-item"><a href="client_overview.php?client_id=<?= $client_id ?>"><?= escapeHtml($recurring['client_name']) ?></a></li>
    <li class="breadcrumb-item"><a href="recurring_invoices.php?client_id=<?= $client_id ?>"><?= __('Recurring Invoices') ?></a></li>
    <li class="breadcrumb-item active"><?= escapeHtml($number) ?></li>
</ol>

<!-- What it is, whether it is running, and when it bills next -->
<div class="card mb-3 d-print-none">
    <div class="card-body d-flex flex-wrap align-items-center gap-3">
        <?= billingTile('recurring', 'lg') ?>

        <div class="flex-grow-1 min-w-0">
            <h1 class="h4 fw-semibold mb-1 d-flex flex-wrap align-items-center gap-2">
                <span><?= __('Recurring Invoice %s', escapeHtml($number)) ?></span>
                <span class="fs-6"><span class="badge rounded-pill text-bg-<?= $active ? 'success' : 'secondary' ?>"><?= $active ? __('Active') : _x('Paused', 'recurring invoice') ?></span></span>
                <?php if ($active) { ?><span class="fs-6"><?= recurringInvoiceNextPill($recurring) ?></span><?php } ?>
            </h1>
            <div class="text-body-secondary small d-flex flex-wrap column-gap-3 row-gap-1">
                <span><i class="fas fa-fw fa-building me-1"></i><a class="text-reset" href="recurring_invoices.php?client_id=<?= $client_id ?>"><?= escapeHtml($recurring['client_name']) ?></a></span>
                <?php if (trim((string) $recurring['recurring_invoice_scope']) !== '') { ?><span><i class="fas fa-fw fa-comment me-1"></i><?= escapeHtml($recurring['recurring_invoice_scope']) ?></span><?php } ?>
                <?php if ($recurring['category_name']) { ?><span><i class="fas fa-fw fa-tag me-1"></i><?= escapeHtml($recurring['category_name']) ?></span><?php } ?>
                <span><i class="fas fa-fw fa-sync-alt me-1"></i><?= escapeHtml($frequency_label) ?></span>
                <?php if (intval($recurring['recurring_invoice_quote_id'] ?? 0) && ($from_quote = billingRow('quote', intval($recurring['recurring_invoice_quote_id'])))) { ?>
                    <span><i class="fas fa-fw fa-comment-dollar me-1"></i><?= __('From %s', '<a class="text-reset" href="quote.php?quote_id=' . intval($from_quote['quote_id']) . '">' . escapeHtml(billingNumber('quote', $from_quote)) . '</a>') ?></span>
                <?php } ?>
                <?php if ($autopay_usable) { ?><span><i class="fas fa-fw fa-wallet me-1"></i><?= __('Autopay') ?></span><?php } ?>
                <?php if ($for_contract) { ?>
                    <span title="<?= __('It starts and stops with the contract') ?>" data-recurring-contract><i class="fas fa-fw fa-file-contract me-1"></i><?= __('For %s', '<a class="text-reset" href="contract.php?client_id=' . $client_id . '&contract_id=' . intval($for_contract['contract_id']) . '">' . escapeHtml($for_contract['contract_name']) . '</a>') ?></span>
                <?php } ?>
            </div>
        </div>

        <div class="d-flex flex-wrap gap-2">
            <?php if ($can_edit) { ?>
                <a class="btn btn-primary confirm-link" href="post.php?force_recurring=<?= $recurring_invoice_id ?>&run=<?= escapeHtml(urlencode($recurring['recurring_invoice_next_date'])) ?>&csrf_token=<?= $csrf ?>"
                    data-confirm-title="<?= __('Make an invoice from %s now?', escapeHtml($number)) ?>"
                    data-confirm-text="<?= escapeHtml($auto_send && $email_notify && !empty($config_recurring_auto_send_invoice) && !empty($config_smtp_provider)
                        ? __('The invoice for the run due %1$s (%2$s) is made now, dated today, and emailed. The next one moves to %3$s.', formatDate($recurring['recurring_invoice_next_date'], 'date'), $next_period['{period}'], formatDate($after_next, 'date'))
                        : __('The invoice for the run due %1$s (%2$s) is made now, dated today, as a draft to check. The next one moves to %3$s.', formatDate($recurring['recurring_invoice_next_date'], 'date'), $next_period['{period}'], formatDate($after_next, 'date'))) ?>"
                    data-confirm-button="<?= __('Invoice Now') ?>">
                    <i class="fas fa-bolt me-2"></i><?= __('Invoice Now') ?>
                </a>
                <?php if ($active) { ?>
                    <a class="btn btn-outline-secondary confirm-link" href="post.php?recurring_invoice_set_status=<?= $recurring_invoice_id ?>&status=0&csrf_token=<?= $csrf ?>"
                        data-confirm-title="<?= __('Pause %s?', escapeHtml($number)) ?>" data-confirm-text="<?= __('No invoices are made from it until it is resumed.') ?>" data-confirm-button="<?= __('Pause') ?>">
                        <i class="fas fa-pause me-2"></i><?= __('Pause') ?>
                    </a>
                <?php } else { ?>
                    <a class="btn btn-outline-success" href="post.php?recurring_invoice_set_status=<?= $recurring_invoice_id ?>&status=1&csrf_token=<?= $csrf ?>">
                        <i class="fas fa-play me-2"></i><?= __('Resume') ?>
                    </a>
                <?php } ?>
                <button type="button" class="btn btn-outline-secondary ajax-modal" data-modal-url="modals/recurring_invoice/recurring_invoice_edit.php?id=<?= $recurring_invoice_id ?>">
                    <i class="fas fa-edit me-2"></i><?= __('Edit') ?>
                </button>
            <?php } ?>
            <div class="dropdown dropstart">
                <button class="btn btn-outline-secondary" title="<?= __('More actions') ?>" type="button" data-bs-toggle="dropdown">
                    <i class="fas fa-fw fa-ellipsis-v"></i>
                </button>
                <div class="dropdown-menu">
                    <a class="dropdown-item" href="#" onclick="window.print(); return false;">
                        <i class="fas fa-fw fa-print text-secondary me-2"></i><?= __('Print') ?>
                    </a>
                    <a class="dropdown-item" href="invoices.php?client_id=<?= $client_id ?>&recurring_invoice_id=<?= $recurring_invoice_id ?>">
                        <i class="fas fa-fw fa-file-invoice-dollar text-secondary me-2"></i><?= __('Invoices Made') ?> <span class="text-body-secondary">(<?= $generated_count ?>)</span>
                    </a>
                    <?php if ($can_edit && $auto_send) { ?>
                        <div class="dropdown-divider"></div>
                        <a class="dropdown-item" href="post.php?recurring_invoice_email_notify=<?= $email_notify ? 0 : 1 ?>&recurring_invoice_id=<?= $recurring_invoice_id ?>&csrf_token=<?= $csrf ?>">
                            <i class="fas fa-fw <?= $email_notify ? 'fa-bell-slash' : 'fa-bell' ?> text-secondary me-2"></i><?= $email_notify ? __("Don't email the invoices") : __('Email the invoices') ?>
                        </a>
                    <?php } ?>
                    <?php if ($can_delete) { ?>
                        <div class="dropdown-divider"></div>
                        <a class="dropdown-item text-danger text-bold confirm-link" href="post.php?delete_recurring_invoice=<?= $recurring_invoice_id ?>&csrf_token=<?= $csrf ?>"
                            data-confirm-text="<?= __('%s goes for good, with its lines, autopay and history. The invoices it made stay.', escapeHtml($number)) ?>">
                            <i class="fas fa-fw fa-trash me-2"></i><?= __('Delete') ?>
                        </a>
                    <?php } ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php if ($late) { ?>
    <div class="alert alert-danger d-flex flex-wrap align-items-center gap-2 d-print-none">
        <i class="fas fa-exclamation-triangle"></i>
        <?php if ($late_days <= RECURRING_INVOICE_GRACE_DAYS) { ?>
            <span class="me-auto"><?= __('Its run on %s was missed - the next nightly run bills it. Invoice Now bills it straight away.', escapeHtml(formatDate($recurring['recurring_invoice_next_date'], 'date'))) ?></span>
        <?php } else { ?>
            <span class="me-auto"><?= __('Its next date, %1$s, is %2$d days gone - too far behind to bill by itself. Use Invoice Now for each run it missed, or move it on without billing them.', escapeHtml(formatDate($recurring['recurring_invoice_next_date'], 'date')), $late_days) ?></span>
        <?php } ?>
        <?php if ($can_edit) { ?>
            <a class="btn btn-sm btn-danger confirm-link" href="post.php?recurring_invoice_catch_up=<?= $recurring_invoice_id ?>&csrf_token=<?= $csrf ?>"
                data-confirm-title="<?= __('Skip the missed runs?') ?>" data-confirm-text="<?= escapeHtml(__('The next date moves on to %s. Nothing is billed for the runs it missed - use Invoice Now for those.', formatDate(recurringInvoiceCatchUp($recurring), 'date'))) ?>" data-confirm-button="<?= __('Move on') ?>">
                <i class="fas fa-forward me-1"></i><?= __('Move on to %s', escapeHtml(formatDate(recurringInvoiceCatchUp($recurring), 'date'))) ?>
            </a>
        <?php } ?>
    </div>
<?php } ?>

<?= taxDocWarning('recurring', $recurring_invoice_id, $client_id, $items, true, $can_edit) ?>

<div class="row g-3">

    <!-- The invoice it makes -->
    <div class="col-xl-8 itflow-paper-col">
        <?php require "../includes/billing_paper.php"; ?>
    </div>

    <div class="col-xl-4 d-print-none">

        <div class="card mb-3">
            <div class="card-header">
                <h3 class="card-title mb-0"><i class="fas fa-fw fa-calendar-alt text-secondary me-2"></i><?= __('Schedule') ?></h3>
            </div>
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-end mb-3">
                    <div>
                        <div class="itflow-billing-stat-label"><?= ['month' => __('Each month'), 'quarter' => __('Each quarter'), 'year' => __('Each year')][$period_word] ?></div>
                        <div class="itflow-billing-stat-value"><?= $money($totals['total']) ?></div>
                    </div>
                    <?php if ($frequency !== 'month') { ?>
                        <div class="text-end small text-body-secondary"><?= __('%s a month', $money(recurringInvoiceMonthly($totals['total'], $frequency))) ?></div>
                    <?php } ?>
                </div>
                <dl class="itflow-asset-dl mb-0">
                    <dt><?= _x('Next', 'recurring run') ?></dt>
                    <dd>
                        <?php if ($active) { ?>
                            <?= escapeHtml(formatDate($recurring['recurring_invoice_next_date'], 'date')) ?>
                            <div class="small text-body-secondary"><?= __('For %s', escapeHtml($next_period['{period}'])) ?></div>
                            <?php if (count($upcoming) > 1 && !$late) { ?>
                                <div class="small text-body-secondary"><?= __('then %s', escapeHtml(implode(', ', array_map(function ($d) { return formatDate($d, 'date'); }, array_slice($upcoming, 1))))) ?></div>
                            <?php } ?>
                        <?php } else { ?>
                            <span class="text-body-secondary"><?= $for_contract && $for_contract['contract_status'] === 'Draft' ? __('Paused - it starts when %s is active', escapeHtml($for_contract['contract_name'])) : __('Paused - nothing until it is resumed') ?></span>
                        <?php } ?>
                    </dd>
                    <dt><?= _x('Last', 'recurring run') ?></dt><dd><?= $recurring['recurring_invoice_last_sent'] ? escapeHtml(formatDate($recurring['recurring_invoice_last_sent'], 'date')) : '<span class="text-body-secondary">' . __('Not yet') . '</span>' ?></dd>
                    <?php $margin_html = billingMarginHtml('recurring', $items, $currency, $period_word); if ($margin_html) { ?>
                        <dt><?= __('Margin') ?></dt><dd><?= $margin_html ?></dd>
                    <?php } ?>
                    <?php $reprice_doc = $can_edit && empty($recurring['recurring_invoice_archived_at']) ? productRepriceCount(['kind' => 'recurring', 'doc_id' => $recurring_invoice_id]) : null; if ($reprice_doc && $reprice_doc['lines']) { ?>
                        <dt><?= __('Prices') ?></dt>
                        <dd><a class="text-warning-emphasis" href="products_reprice.php?kind=recurring&amp;doc_id=<?= $recurring_invoice_id ?>"><i class="fas fa-tags me-1"></i><?= _n('%d line at an old price', '%d lines at an old price', intval($reprice_doc['lines'])) ?></a></dd>
                    <?php } ?>
                    <dt><?= __('Sending') ?></dt>
                    <dd>
                        <?php if (!$auto_send) { ?>
                            <?= __('Made as a draft for you to check and send') ?>
                        <?php } elseif (empty($config_recurring_auto_send_invoice)) { ?>
                            <?= __('Made as a draft') ?><div class="small text-body-secondary"><?= __('Emailing the invoices recurring invoices make is off in Settings &gt; Notifications') ?></div>
                        <?php } elseif ($email_notify && empty($config_smtp_provider)) { ?>
                            <?= __('Made as a draft') ?><div class="small text-body-secondary"><?= __("Outgoing mail isn't set up") ?></div>
                        <?php } elseif ($email_notify) { ?>
                            <?= __('Emailed to the primary and billing contacts as it is made') ?>
                        <?php } else { ?>
                            <?= __('Made as a draft') ?><div class="small text-body-secondary"><?= __('Emailing is off for this one') ?></div>
                        <?php } ?>
                    </dd>
                    <dt><?= __('Since') ?></dt><dd title="<?= escapeHtml(formatDate($recurring['recurring_invoice_created_at'], 'datetime')) ?>"><?= escapeHtml(formatDate($recurring['recurring_invoice_created_at'], 'date')) ?></dd>
                </dl>
            </div>
        </div>

        <?php if ($seat_lines) { ?>
            <!-- Per user, per device: what it bills and what the client has now -->
            <div class="card mb-3">
                <div class="card-header d-flex align-items-center">
                    <h3 class="card-title mb-0 me-auto"><i class="fas fa-fw fa-users text-secondary me-2"></i><?= __('Seats') ?></h3>
                    <?php if ($can_edit && $seat_drift) { ?>
                        <a class="btn btn-sm btn-outline-warning" href="post.php?recurring_invoice_sync=<?= $recurring_invoice_id ?>&csrf_token=<?= $csrf ?>"><i class="fas fa-sync-alt me-1"></i><?= __('Update now') ?></a>
                    <?php } ?>
                </div>
                <div class="list-group list-group-flush">
                    <?php foreach ($seat_lines as $seat) {
                        $source = $seat_sources[$seat['item_seat_source']] ?? ['icon' => 'fa-users', 'unit' => 'seat']; ?>
                        <div class="list-group-item d-flex align-items-center gap-2">
                            <span class="itflow-asset-icon itflow-asset-icon-sm itflow-billing-tile itflow-billing-tile-recurring" aria-hidden="true"><i class="fas fa-fw <?= $source['icon'] ?>"></i></span>
                            <div class="flex-grow-1 min-w-0">
                                <div class="text-truncate"><?= escapeHtml($seat['item_name']) ?></div>
                                <div class="small text-body-secondary text-truncate"><?= escapeHtml($seat['seat_label']) ?></div>
                            </div>
                            <div class="text-end text-nowrap">
                                <div class="fw-semibold"><?= billingQuantity($seat['item_quantity']) ?></div>
                                <?php if ($seat['seat_count'] === null) { ?>
                                    <div class="small text-danger-emphasis"><?= __('License gone') ?></div>
                                <?php } elseif ($seat['seat_drift']) { ?>
                                    <div class="small text-warning-emphasis"><?= __('now %s', billingQuantity($seat['seat_quantity'])) ?></div>
                                <?php } else { ?>
                                    <div class="small text-body-secondary"><?= __('up to date') ?></div>
                                <?php } ?>
                            </div>
                        </div>
                    <?php } ?>
                </div>
                <div class="card-footer small text-body-secondary"><?= $seat_drift ? __('Counted again each time an invoice is made - the next one bills the new counts.') : __('Counted again each time an invoice is made.') ?></div>
            </div>
        <?php } ?>

        <?php if ($costs) { ?>
            <!-- What it costs us: the recurring expenses it covers, and the margin left -->
            <div class="card mb-3">
                <div class="card-header d-flex align-items-center">
                    <h3 class="card-title mb-0 me-auto"><i class="fas fa-fw fa-shopping-cart text-secondary me-2"></i><?= __('Costs') ?> <span class="badge rounded-pill bg-secondary-subtle text-secondary-emphasis fw-normal ms-1"><?= count($costs['rows']) ?></span></h3>
                    <?php if ($can_add_costs) { ?>
                        <button type="button" class="btn btn-sm btn-outline-primary ajax-modal" data-modal-size="lg" data-modal-url="modals/recurring_expense/recurring_expense_add.php?recurring_invoice_id=<?= $recurring_invoice_id ?>" title="<?= __('A recurring expense this covers') ?>">
                            <i class="fas fa-plus me-1"></i><?= __('Add') ?>
                        </button>
                    <?php } ?>
                </div>
                <?php if ($costs['rows']) { ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($costs['rows'] as $cost) { ?>
                            <div class="list-group-item d-flex align-items-center gap-2">
                                <?= vendorTile($cost['vendor_name'] ?: __('Recurring'), 'sm') ?>
                                <div class="flex-grow-1 min-w-0">
                                    <a class="text-truncate d-block ajax-modal" href="#" data-modal-size="lg" data-modal-url="modals/recurring_expense/recurring_expense.php?id=<?= intval($cost['recurring_expense_id']) ?>"><?= escapeHtml($cost['vendor_name'] ?: __('No vendor')) ?></a>
                                    <div class="small text-body-secondary text-truncate"><?= escapeHtml(implode(' · ', array_filter([$cost['recurring_expense_description'], $cost['category_name']]))) ?: '&nbsp;' ?></div>
                                </div>
                                <div class="text-end text-nowrap small">
                                    <div class="itflow-money <?= $cost['running'] ? '' : 'text-body-secondary text-decoration-line-through' ?>"><?= $money($cost['monthly']) ?></div>
                                    <div class="text-body-secondary"><?= $cost['running'] ? __('a month') : escapeHtml(ucfirst(recurringExpenseState($cost))) ?></div>
                                </div>
                            </div>
                        <?php } ?>
                    </div>
                    <div class="card-footer small">
                        <div class="d-flex justify-content-between gap-2">
                            <span class="text-body-secondary"><?= __('Bills %1$s a month before tax &middot; costs %2$s', $money($costs['revenue']), $money($costs['cost'])) ?></span>
                            <span class="fw-semibold text-nowrap <?= $costs['margin'] < 0 ? 'text-danger-emphasis' : 'text-success-emphasis' ?>"><?= $money($costs['margin']) ?><?= $costs['percent'] !== null ? ' &middot; ' . $costs['percent'] . '%' : '' ?></span>
                        </div>
                    </div>
                <?php } else { ?>
                    <div class="card-body small text-body-secondary">
                        <?= __('No costs against it yet. Add the recurring expenses it pays for - the licenses, hosting or backup it resells - to see the margin it leaves each month.') ?>
                    </div>
                <?php } ?>
            </div>
        <?php } ?>

        <div class="card mb-3">
            <div class="card-header">
                <h3 class="card-title mb-0"><i class="fas fa-fw fa-wallet text-secondary me-2"></i><?= __('Autopay') ?></h3>
            </div>
            <div class="card-body">
                <?php if ($autopay_stale) { ?>
                    <div class="alert alert-warning small d-flex flex-wrap align-items-center gap-2 mb-3" role="alert">
                        <span class="flex-grow-1"><i class="fas fa-fw fa-exclamation-triangle me-1"></i><?= __('Its autopay cannot charge anything - it has no usable card on file. The invoices it makes stay open until they are paid, and the team is told on each due date.') ?></span>
                        <?php if ($can_edit) { ?>
                            <form action="post.php" method="post" class="m-0">
                                <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                                <input type="hidden" name="set_recurring_payment" value="1">
                                <input type="hidden" name="recurring_invoice_id" value="<?= $recurring_invoice_id ?>">
                                <input type="hidden" name="saved_payment_id" value="0">
                                <button type="submit" class="btn btn-sm btn-outline-secondary"><i class="fas fa-fw fa-power-off me-1"></i><?= __('Turn off') ?></button>
                            </form>
                        <?php } ?>
                    </div>
                <?php } ?>
                <?php if ($saved_methods) { ?>
                    <form action="post.php" method="post">
                        <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                        <input type="hidden" name="set_recurring_payment" value="1">
                        <input type="hidden" name="recurring_invoice_id" value="<?= $recurring_invoice_id ?>">
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-fw fa-credit-card"></i></span>
                            <select class="form-select" name="saved_payment_id" onchange="this.form.submit()" <?= $can_edit ? '' : 'disabled' ?> aria-label="<?= __('Card to charge') ?>">
                                <option value="0"><?= __('Off - the client pays each invoice') ?></option>
                                <?php foreach ($saved_methods as $saved_id => $method) { ?>
                                    <option value="<?= $saved_id ?>" <?= $autopay_id === $saved_id ? 'selected' : '' ?>><?= escapeHtml($method['saved_payment_description']) ?><?= $method['payment_provider_name'] ? ' (' . escapeHtml($method['payment_provider_name']) . (intval($method['payment_provider_active']) ? '' : ', ' . __('turned off')) . ')' : '' ?></option>
                                <?php } ?>
                            </select>
                        </div>
                    </form>
                    <div class="form-text"><?= $autopay_usable ? __('Charged on the due date of each invoice it makes.') : __('Charge a card on file on the due date of each invoice it makes.') ?></div>
                <?php } else { ?>
                    <div class="small text-body-secondary"><?= __('No card on file for %s. Once they save one paying an invoice online, it can be charged here automatically.', escapeHtml($recurring['client_name'])) ?></div>
                <?php } ?>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header d-flex align-items-center">
                <h3 class="card-title mb-0 me-auto"><i class="fas fa-fw fa-file-invoice-dollar text-secondary me-2"></i><?= __('Invoices Made') ?> <span class="badge rounded-pill bg-secondary-subtle text-secondary-emphasis fw-normal ms-1"><?= $generated_count ?></span></h3>
                <?php if ($generated_count > count($generated)) { ?>
                    <a class="small" href="invoices.php?client_id=<?= $client_id ?>&recurring_invoice_id=<?= $recurring_invoice_id ?>"><?= __('All') ?></a>
                <?php } ?>
            </div>
            <?php if ($generated) { ?>
                <div class="list-group list-group-flush">
                    <?php foreach ($generated as $made) {
                        $made_state = invoiceState($made, $made['paid']); ?>
                        <a class="list-group-item list-group-item-action d-flex align-items-center gap-2" href="invoice.php?invoice_id=<?= intval($made['invoice_id']) ?>">
                            <div class="flex-grow-1 min-w-0">
                                <div class="fw-semibold"><?= escapeHtml($made['invoice_prefix'] . $made['invoice_number']) ?> <span class="fw-normal text-body-secondary small ms-1"><?= escapeHtml(formatDate($made['invoice_date'], 'date')) ?></span></div>
                                <div class="small"><?= invoiceStatusBadge($made['invoice_status'], $made_state['state'] === 'overdue') ?></div>
                            </div>
                            <span class="itflow-money small"><?= escapeHtml(billingMoney($made['invoice_amount'], $made['invoice_currency_code'])) ?></span>
                        </a>
                    <?php } ?>
                </div>
            <?php } else { ?>
                <div class="card-body small text-body-secondary"><?= $active ? __('None yet - the first is made on %s.', escapeHtml(formatDate($recurring['recurring_invoice_next_date'], 'date'))) : __('None yet.') ?></div>
            <?php } ?>
        </div>

        <div class="card mb-3">
            <div class="card-header">
                <h3 class="card-title mb-0"><i class="fas fa-fw fa-history text-secondary me-2"></i><?= __('History') ?></h3>
            </div>
            <?= billingHistoryHtml($history) ?>
        </div>

    </div>
</div>

<script src="/js/inline_notes.js"></script>
<script src="/libs/SortableJS/Sortable.min.js"></script>
<script src="/js/billing_items.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    itflowBillingItems({
        products: <?= $can_edit ? getProductsForAutocomplete($mysqli, intval($recurring['client_id'])) : '[]' ?>,
        csrf: <?= json_encode($csrf) ?>,
        currency: <?= json_encode($currency) ?>,
        locale: <?= json_encode(str_replace('_', '-', (string) $session_company_locale)) ?>
    });
});
</script>

<?php require_once "../includes/footer.php";
