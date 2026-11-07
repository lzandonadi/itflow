<?php

/*
 * Invoice - one invoice, the way an MSP works it.
 *
 * The header says where it stands - its status, whether it is due or late
 * and by how long - and has the next thing to do with it: send a draft,
 * take a payment on one that is owed. The invoice itself is on the left,
 * as the client sees it, with its lines edited in place while it is open.
 * On the right: what has been paid and what is left, the payments, the
 * hardware sold on it and whether it is on the client's assets yet, the
 * tickets and expenses billed on it (the expenses with what they cost us and
 * the margin) and its history.
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

$invoice_id = intval($_GET['invoice_id'] ?? 0);
$invoice = billingRow('invoice', $invoice_id);

if (!$invoice || ($client_url && intval($invoice['client_id']) !== intval($client_id))) {
    billingNotFound('invoice', $client_url);
    require_once "../includes/footer.php";
    exit;
}

$client_id = intval($invoice['client_id']);
enforceClientAccess();

$number = billingNumber('invoice', $invoice);
$status = (string) $invoice['invoice_status'];
$currency = (string) $invoice['invoice_currency_code'];
$money = function ($amount) use ($currency) {
    return escapeHtml(billingMoney($amount, $currency));
};

$items = billingItems('invoice', $invoice_id);
$totals = billingTotals('invoice', $invoice, $items);
$paid = invoicePaid($invoice_id);
$state = invoiceState($invoice, $paid);
$payments = invoicePayments($invoice_id);
$tickets = invoiceTickets($invoice_id);
$history = billingHistory('invoice', $invoice_id);

$can_edit = lookupUserPermission('module_sales') >= 2;
$can_delete = lookupUserPermission('module_sales') >= 3;
// The money on it is accounting's too - Financial, with Sales at Read (billingMoneyCan())
$can_take_payment = billingMoneyCan('take') && invoiceCanPay($invoice, $state);
$can_edit_payments = billingMoneyCan('edit');
$can_delete_payments = billingMoneyCan('delete');
$can_remind = billingMoneyCan('remind');
$can_edit_lines = billingCanEditItems('invoice', $invoice);
$mail_ready = !empty($config_smtp_provider) && billingEmailableContacts('invoice', $client_id) > 0;
$saved_card = $can_take_payment && in_array($status, ['Sent', 'Viewed'], true) && invoiceHasSavedCard($client_id);
$unbilled_tickets = $can_edit && $can_edit_lines && $config_module_enable_ticketing ? count(billingUnbilledTickets($client_id)) : 0;
// Expenses billed on it, against what they cost us (functions/expenses.php)
$can_see_expenses = lookupUserPermission('module_financial') >= 1;
$can_bill_expenses = $can_edit && $can_edit_lines && lookupUserPermission('module_financial') >= 2;
$can_unbill_expenses = $can_edit && lookupUserPermission('module_financial') >= 2 && expenseCanUnbill($status);
$billed_expenses = $can_see_expenses ? expenseOnInvoice($invoice_id) : [];
$unbilled_expenses = $can_bill_expenses ? count(expenseUnbilled($client_id)) : 0;
// Trips billed on it (functions/trips.php) - the driving out to the client
$billed_trips = $can_see_expenses ? tripsOnInvoice($invoice_id) : [];
$unbilled_trips = $can_bill_expenses ? count(tripUnbilled($client_id)) : 0;
$can_unbill_trips = $can_edit && tripCanUnbill($status);
$guest_url = billingGuestUrl('invoice', $invoice);
// Hardware sold on it, and how much of it is on the client's assets (functions/products.php)
$can_see_assets = lookupUserPermission('module_support') >= 1;
$hardware = $can_see_assets ? productInvoiceHardware($invoice_id) : [];
$hardware_units = array_sum(array_column($hardware, 'units'));
$hardware_to_add = $status === 'Cancelled' ? 0 : array_sum(array_column($hardware, 'to_add'));
$can_add_assets = $hardware_to_add > 0 && lookupUserPermission('module_support') >= 2;
// Payment reminders - how many have gone, and when the next one goes (functions/billing_email.php)
$owed = in_array($state['state'], ['open', 'overdue'], true) && $status !== 'Draft';
$reminder_rules = billingReminderRules();
$reminders_sent = $owed ? mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT COUNT(*) AS sent, MAX(history_created_at) AS last FROM history
    WHERE history_invoice_id = $invoice_id AND (history_description LIKE 'Reminder emailed%' OR history_description LIKE 'Cron Emailed Overdue Invoice%')")) : null;
$client_reminders = intval(getFieldById('clients', $client_id, 'client_reminders') ?? 1);
$reminder_next = $owed && $client_reminders ? billingReminderNext($invoice, $reminder_rules, billingReminderAutopay($invoice)) : null;
$csrf = $_SESSION['csrf_token'];

// Made by a recurring invoice?
$recurring = intval($invoice['invoice_recurring_invoice_id'])
    ? mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT recurring_invoice_id, recurring_invoice_prefix, recurring_invoice_number FROM recurring_invoices WHERE recurring_invoice_id = " . intval($invoice['invoice_recurring_invoice_id'])))
    : null;
// Made from a quote?
$from_quote = intval($invoice['invoice_quote_id'] ?? 0)
    ? mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT quote_id, quote_prefix, quote_number FROM quotes WHERE quote_id = " . intval($invoice['invoice_quote_id'])))
    : null;

$progress = $state['paid'] > 0 && $totals['total'] > 0 ? min(100, round($state['paid'] / $totals['total'] * 100)) : 0;

// Override Tab Title // No Sanitizing needed as this var will only be used in the tab title
$tab_title = $invoice['client_name'];
$page_title = $number;

$paper = [
    'type' => 'invoice',
    'row' => $invoice,
    'items' => $items,
    'totals' => $totals,
    'paid' => $state['paid'],
    'balance' => $state['balance'],
    'edit' => $can_edit_lines,
    'ai' => $can_edit_lines && aiReady('Billing'),
    'notes_edit' => $can_edit,
    'taxes' => $can_edit_lines ? billingTaxes($invoice['invoice_created_at']) : [],
    'logo_url' => billingCompany()['logo_url'],
    'csrf' => $csrf,
];

?>

<ol class="breadcrumb d-print-none">
    <li class="breadcrumb-item"><a href="client_overview.php?client_id=<?= $client_id ?>"><?= escapeHtml($invoice['client_name']) ?></a></li>
    <li class="breadcrumb-item"><a href="invoices.php?client_id=<?= $client_id ?>"><?= __('Invoices') ?></a></li>
    <li class="breadcrumb-item active"><?= escapeHtml($number) ?></li>
</ol>

<!-- What it is, where it stands, and the next thing to do -->
<div class="card mb-3 d-print-none">
    <div class="card-body d-flex flex-wrap align-items-center gap-3">
        <?= billingTile('invoice', 'lg') ?>

        <div class="flex-grow-1 min-w-0">
            <h1 class="h4 fw-semibold mb-1 d-flex flex-wrap align-items-center gap-2">
                <span><?= _x('Invoice %s', 'invoice title', escapeHtml($number)) ?></span>
                <span class="fs-6"><?= invoiceStatusBadge($status) ?></span>
                <span class="fs-6"><?= invoiceDuePill($state) ?></span>
            </h1>
            <div class="text-body-secondary small d-flex flex-wrap column-gap-3 row-gap-1">
                <span><i class="fas fa-fw fa-building me-1"></i><a class="text-reset" href="invoices.php?client_id=<?= $client_id ?>"><?= escapeHtml($invoice['client_name']) ?></a></span>
                <?php if (trim((string) $invoice['invoice_scope']) !== '') { ?><span><i class="fas fa-fw fa-comment me-1"></i><?= escapeHtml($invoice['invoice_scope']) ?></span><?php } ?>
                <?php if ($invoice['category_name']) { ?><span><i class="fas fa-fw fa-tag me-1"></i><?= escapeHtml($invoice['category_name']) ?></span><?php } ?>
                <?php if ($recurring) { ?>
                    <span><i class="fas fa-fw fa-redo-alt me-1"></i><?= __('From %s', '<a class="text-reset" href="recurring_invoice.php?recurring_invoice_id=' . intval($recurring['recurring_invoice_id']) . '">' . escapeHtml($recurring['recurring_invoice_prefix'] . $recurring['recurring_invoice_number']) . '</a>') ?></span>
                <?php } ?>
                <?php if ($from_quote) { ?>
                    <span><i class="fas fa-fw fa-comment-dollar me-1"></i><?= __('From %s', '<a class="text-reset" href="quote.php?quote_id=' . intval($from_quote['quote_id']) . '">' . escapeHtml($from_quote['quote_prefix'] . $from_quote['quote_number']) . '</a>') ?></span>
                <?php } ?>
                <span title="<?= escapeHtml($invoice['invoice_created_at']) ?>"><i class="far fa-fw fa-clock me-1"></i><?= _x('Created %s', 'invoice', escapeHtml(timeAgo($invoice['invoice_created_at']))) ?></span>
            </div>
        </div>

        <div class="d-flex flex-wrap gap-2">
            <?php if ($can_edit && invoiceCanMarkSent($status)) { ?>
                <div class="btn-group">
                    <?php if ($mail_ready) { ?>
                        <button type="button" class="btn btn-primary ajax-modal" data-modal-size="lg" data-modal-url="modals/invoice/invoice_email.php?invoice_id=<?= $invoice_id ?>">
                            <i class="fas fa-paper-plane me-2"></i><?= __('Send') ?>
                        </button>
                        <button type="button" class="btn btn-primary dropdown-toggle dropdown-toggle-split" data-bs-toggle="dropdown" aria-label="<?= __('More ways to send') ?>"></button>
                        <div class="dropdown-menu dropdown-menu-end">
                            <button type="submit" class="dropdown-item confirm-link" form="quickSendInvoice" name="invoice_id" value="<?= $invoice_id ?>"
                                data-confirm-title="<?= __('Send %s now?', escapeHtml($number)) ?>" data-confirm-text="<?= escapeHtml(__("It goes to the client's primary and billing contacts.")) ?>" data-confirm-button="<?= __('Send') ?>">
                                <i class="fas fa-fw fa-bolt text-secondary me-2"></i><?= __('Quick Send') ?>
                            </button>
                            <a class="dropdown-item ajax-modal" href="#" data-modal-url="modals/invoice/invoice_mark_sent.php?invoice_id=<?= $invoice_id ?>">
                                <i class="fas fa-fw fa-check text-secondary me-2"></i><?= __('Mark Sent') ?><span class="text-body-secondary">...</span>
                            </a>
                        </div>
                    <?php } else { ?>
                        <button type="button" class="btn btn-primary ajax-modal" data-modal-url="modals/invoice/invoice_mark_sent.php?invoice_id=<?= $invoice_id ?>">
                            <i class="fas fa-check me-2"></i><?= __('Mark Sent') ?>
                        </button>
                    <?php } ?>
                </div>
            <?php } ?>

            <?php if ($can_take_payment) { ?>
                <div class="btn-group">
                    <button type="button" class="btn btn-success ajax-modal" data-modal-url="modals/payment/payment_add.php?id=<?= $invoice_id ?>">
                        <i class="fas fa-credit-card me-2"></i><?= __('Add Payment') ?>
                    </button>
                    <?php if ($saved_card) { ?>
                        <button type="button" class="btn btn-success dropdown-toggle dropdown-toggle-split" data-bs-toggle="dropdown" aria-label="<?= __('More ways to pay') ?>"></button>
                        <div class="dropdown-menu dropdown-menu-end">
                            <a class="dropdown-item ajax-modal" href="#" data-modal-url="modals/payment/payment_saved_method_add.php?id=<?= $invoice_id ?>">
                                <i class="fas fa-fw fa-wallet text-secondary me-2"></i><?= __('Charge Saved Card') ?>
                            </a>
                        </div>
                    <?php } ?>
                </div>
            <?php } ?>

            <?php if ($can_edit && $status !== 'Draft' && $mail_ready && in_array($status, invoiceOpenStatuses(), true)) { ?>
                <button type="button" class="btn btn-outline-primary ajax-modal" data-modal-size="lg" data-modal-url="modals/invoice/invoice_email.php?invoice_id=<?= $invoice_id ?>">
                    <i class="fas fa-paper-plane me-2"></i><?= __('Send Again') ?>
                </button>
            <?php } ?>

            <?php if ($can_edit && invoiceCanMarkNonBillable($invoice)) { ?>
                <a class="btn btn-outline-dark confirm-link" href="post.php?mark_invoice_non-billable=<?= $invoice_id ?>&csrf_token=<?= $csrf ?>"
                    data-confirm-text="<?= __('Nothing is owed on it - it is closed off as non-billable.') ?>">
                    <i class="fas fa-ban me-2"></i><?= __('Non-Billable') ?>
                </a>
            <?php } ?>

            <?php if ($can_edit) { ?>
                <button type="button" class="btn btn-outline-secondary ajax-modal" data-modal-url="modals/invoice/invoice_edit.php?id=<?= $invoice_id ?>">
                    <i class="fas fa-edit me-2"></i><?= __('Edit') ?>
                </button>
            <?php } ?>

            <div class="dropdown dropstart">
                <button class="btn btn-outline-secondary" title="<?= __('More actions') ?>" type="button" data-bs-toggle="dropdown">
                    <i class="fas fa-fw fa-ellipsis-v"></i>
                </button>
                <div class="dropdown-menu">
                    <a class="dropdown-item" href="post.php?export_invoice_pdf=<?= $invoice_id ?>&csrf_token=<?= $csrf ?>" target="_blank">
                        <i class="fas fa-fw fa-file-pdf text-secondary me-2"></i><?= __('Download PDF') ?>
                    </a>
                    <a class="dropdown-item" href="#" onclick="window.print(); return false;">
                        <i class="fas fa-fw fa-print text-secondary me-2"></i><?= __('Print') ?>
                    </a>
                    <a class="dropdown-item" href="post.php?export_invoice_packing_slip=<?= $invoice_id ?>&csrf_token=<?= $csrf ?>" target="_blank">
                        <i class="fas fa-fw fa-box-open text-secondary me-2"></i><?= __('Packing Slip') ?>
                    </a>
                    <div class="dropdown-divider"></div>
                    <a class="dropdown-item clipboardjs" href="#" data-clipboard-text="<?= escapeHtml($guest_url) ?>">
                        <i class="fas fa-fw fa-link text-secondary me-2"></i><?= __('Copy Client Link') ?>
                    </a>
                    <a class="dropdown-item" href="<?= escapeHtml($guest_url) ?>" target="_blank" rel="noopener">
                        <i class="fas fa-fw fa-external-link-alt text-secondary me-2"></i><?= __('View as Client') ?>
                    </a>
                    <?php if ($can_remind && $mail_ready && $owed && $state['balance'] > 0) { ?>
                        <a class="dropdown-item ajax-modal" href="#" data-modal-size="lg" data-modal-url="modals/invoice/invoice_email.php?invoice_id=<?= $invoice_id ?>&amp;kind=reminder">
                            <i class="fas fa-fw fa-bell text-secondary me-2"></i><?= __('Send Reminder') ?><span class="text-body-secondary">...</span>
                        </a>
                    <?php } ?>
                    <?php if ($can_edit) { ?>
                        <?php if ($status !== 'Draft' && $mail_ready && !in_array($status, invoiceOpenStatuses(), true)) { ?>
                            <a class="dropdown-item ajax-modal" href="#" data-modal-size="lg" data-modal-url="modals/invoice/invoice_email.php?invoice_id=<?= $invoice_id ?>">
                                <i class="fas fa-fw fa-paper-plane text-secondary me-2"></i><?= __('Send Again') ?><span class="text-body-secondary">...</span>
                            </a>
                        <?php } ?>
                        <div class="dropdown-divider"></div>
                        <a class="dropdown-item ajax-modal" href="#" data-modal-url="modals/invoice/invoice_copy.php?id=<?= $invoice_id ?>">
                            <i class="fas fa-fw fa-copy text-secondary me-2"></i><?= __('Copy') ?>
                        </a>
                        <a class="dropdown-item ajax-modal" href="#" data-modal-url="modals/invoice/invoice_recurring_add.php?invoice_id=<?= $invoice_id ?>">
                            <i class="fas fa-fw fa-redo-alt text-secondary me-2"></i><?= __('Make Recurring') ?>
                        </a>
                        <?php if (invoiceCanCancel($status)) { ?>
                            <div class="dropdown-divider"></div>
                            <a class="dropdown-item text-danger confirm-link" href="post.php?cancel_invoice=<?= $invoice_id ?>&csrf_token=<?= $csrf ?>"
                                data-confirm-text="<?= __('%s is cancelled - nothing more is owed on it. It stays on record.', escapeHtml($number)) ?>">
                                <i class="fas fa-fw fa-times me-2"></i><?= __('Cancel Invoice') ?>
                            </a>
                        <?php } ?>
                    <?php } ?>
                    <?php if ($can_delete) { ?>
                        <a class="dropdown-item text-danger text-bold confirm-link" href="post.php?delete_invoice=<?= $invoice_id ?>&csrf_token=<?= $csrf ?>"
                            data-confirm-text="<?= __('%s goes for good, with its lines, payments and history. Its tickets become unbilled again, and its expenses and trips are to bill again.', escapeHtml($number)) ?>">
                            <i class="fas fa-fw fa-trash me-2"></i><?= __('Delete') ?>
                        </a>
                    <?php } ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?= taxDocWarning('invoice', $invoice_id, $client_id, $items, billingLinesOpen('invoice', $status), $can_edit) ?>

<div class="row g-3">

    <!-- The invoice, as the client sees it -->
    <div class="col-xl-8 itflow-paper-col">
        <?php require "../includes/billing_paper.php"; ?>
    </div>

    <!-- Money, tickets and what has happened -->
    <div class="col-xl-4 d-print-none">

        <div class="card mb-3">
            <div class="card-header">
                <h3 class="card-title mb-0"><i class="fas fa-fw fa-coins text-secondary me-2"></i><?= __('Payment') ?></h3>
            </div>
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-end mb-2">
                    <div>
                        <div class="itflow-billing-stat-label"><?= $state['state'] === 'paid' ? __('Paid in full') : ($state['state'] === 'overdue' ? _x('Overdue', 'invoice balance') : __('Balance')) ?></div>
                        <div class="itflow-billing-stat-value <?= $state['state'] === 'overdue' ? 'text-danger-emphasis' : '' ?>"><?= $money($state['state'] === 'paid' ? $state['paid'] : $state['balance']) ?></div>
                    </div>
                    <div class="text-end small text-body-secondary"><?= __('of %s', $money($totals['total'])) ?></div>
                </div>
                <div class="progress itflow-billing-progress mb-3" role="progressbar" aria-label="<?= _x('Paid', 'invoice amount') ?>" aria-valuenow="<?= $progress ?>" aria-valuemin="0" aria-valuemax="100">
                    <div class="progress-bar bg-success" style="width: <?= $progress ?>%"></div>
                </div>
                <dl class="itflow-asset-dl mb-0">
                    <dt><?= _x('Paid', 'invoice amount') ?></dt><dd><?= $money($state['paid']) ?><?= $payments ? ' <span class="text-body-secondary">' . _n('in %d payment', 'in %d payments', count($payments)) . '</span>' : '' ?></dd>
                    <dt><?= __('Due') ?></dt>
                    <dd>
                        <?= escapeHtml(formatDate($invoice['invoice_due'])) ?>
                        <?php if (in_array($state['state'], ['open', 'overdue'], true)) { ?>
                            <div class="small <?= $state['days'] < 0 ? 'text-danger-emphasis' : 'text-body-secondary' ?>">
                                <?= $state['days'] < 0 ? _n('%d day late', '%d days late', -$state['days']) : ($state['days'] === 0 ? __('Today') : _n('In %d day', 'In %d days', $state['days'])) ?>
                            </div>
                        <?php } ?>
                    </dd>
                    <dt><?= __('Terms') ?></dt><dd><?php $invoice_terms = contractNetTerms(intval($invoice['client_id']), (string) $invoice['invoice_date']); echo $invoice_terms ? _n('%d day', '%d days', $invoice_terms) : __('Due on receipt'); ?></dd>
                    <?php if ($owed) { ?>
                        <dt><?= __('Reminders') ?></dt>
                        <dd>
                            <?= intval($reminders_sent['sent']) ? _n('%1$d sent, the last %2$s', '%1$d sent, the last %2$s', intval($reminders_sent['sent']), intval($reminders_sent['sent']), escapeHtml(billingEmailDate($reminders_sent['last']))) : __('None sent yet') ?>
                            <div class="small text-body-secondary">
                                <?php if (!$reminder_rules['enabled']) { ?>
                                    <?= __('Off - set in Invoice Settings') ?>
                                <?php } elseif (!$client_reminders) { ?>
                                    <?= __('Off for %s', escapeHtml($invoice['client_name'])) ?>
                                <?php } elseif ($reminder_next) { ?>
                                    <?= __('Next %1$s - %2$s', escapeHtml(billingEmailDate($reminder_next['date'])), escapeHtml(billingReminderWhen($reminder_next['days']))) ?>
                                <?php } else { ?>
                                    <?= __('No more on the schedule') ?>
                                <?php } ?>
                            </div>
                        </dd>
                    <?php } ?>
                    <?php $margin_html = billingMarginHtml('invoice', $items, $currency); if ($margin_html) { ?>
                        <dt><?= __('Margin') ?></dt><dd><?= $margin_html ?></dd>
                    <?php } ?>
                    <?php $reprice_doc = $can_edit && $status === 'Draft' ? productRepriceCount(['kind' => 'invoice', 'doc_id' => $invoice_id]) : null; if ($reprice_doc && $reprice_doc['lines']) { ?>
                        <dt><?= __('Prices') ?></dt>
                        <dd><a class="text-warning-emphasis" href="products_reprice.php?kind=invoice&amp;doc_id=<?= $invoice_id ?>"><i class="fas fa-tags me-1"></i><?= _n('%d line at an old price', '%d lines at an old price', intval($reprice_doc['lines'])) ?></a></dd>
                    <?php } ?>
                    <?php if ($status === 'Draft') { ?>
                        <dt><?= __('Client') ?></dt><dd class="text-body-secondary"><?= __("Not sent yet - the client can't see it") ?></dd>
                    <?php } ?>
                </dl>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header d-flex align-items-center">
                <h3 class="card-title mb-0 me-auto"><i class="fas fa-fw fa-credit-card text-secondary me-2"></i><?= __('Payments') ?> <span class="badge rounded-pill bg-secondary-subtle text-secondary-emphasis fw-normal ms-1"><?= count($payments) ?></span></h3>
                <?php if ($can_take_payment) { ?>
                    <button type="button" class="btn btn-sm btn-outline-success ajax-modal" data-modal-url="modals/payment/payment_add.php?id=<?= $invoice_id ?>"><i class="fas fa-plus me-1"></i><?= __('Add') ?></button>
                <?php } ?>
            </div>
            <?php if ($payments) { ?>
                <div class="list-group list-group-flush">
                    <?php foreach ($payments as $payment) {
                        $payment_id = intval($payment['payment_id']); ?>
                        <div class="list-group-item d-flex align-items-center gap-2">
                            <div class="flex-grow-1 min-w-0">
                                <div class="fw-semibold itflow-money"><?= escapeHtml(billingMoney($payment['payment_amount'], $payment['payment_currency_code'])) ?></div>
                                <div class="small text-body-secondary text-truncate">
                                    <?= escapeHtml(implode(' · ', array_filter([formatDate($payment['payment_date']), $payment['payment_method'], $payment['payment_reference'], $payment['account_name']]))) ?>
                                </div>
                            </div>
                            <?php if ($can_edit_payments || $can_delete_payments) { ?>
                                <div class="dropdown dropstart">
                                    <button class="btn btn-sm btn-link text-body-secondary" type="button" data-bs-toggle="dropdown" title="<?= __('Payment actions') ?>"><i class="fas fa-fw fa-ellipsis-v"></i></button>
                                    <div class="dropdown-menu">
                                        <?php if ($can_edit_payments) { ?>
                                            <a class="dropdown-item ajax-modal" href="#" data-modal-url="modals/payment/payment_edit.php?id=<?= $payment_id ?>">
                                                <i class="fas fa-fw fa-edit text-secondary me-2"></i><?= __('Edit') ?>
                                            </a>
                                        <?php } ?>
                                        <?php if ($can_delete_payments) { ?>
                                            <?php if ($payment['payment_method'] === 'Stripe') { ?>
                                                <a class="dropdown-item text-warning confirm-link" href="post.php?refund_payment_stripe=<?= $payment_id ?>&csrf_token=<?= $csrf ?>"
                                                    data-confirm-text="<?= escapeHtml(__("The payment is refunded to the client's card through Stripe.")) ?>">
                                                    <i class="fas fa-fw fa-undo me-2"></i><?= __('Refund through Stripe') ?>
                                                </a>
                                            <?php } ?>
                                            <?php if ($can_edit_payments) { ?>
                                                <div class="dropdown-divider"></div>
                                            <?php } ?>
                                            <a class="dropdown-item text-danger confirm-link" href="post.php?delete_payment=<?= $payment_id ?>&csrf_token=<?= $csrf ?>"
                                                data-confirm-text="<?= __('The payment comes off the invoice and its account. No money moves.') ?>">
                                                <i class="fas fa-fw fa-trash me-2"></i><?= __('Delete') ?>
                                            </a>
                                        <?php } ?>
                                    </div>
                                </div>
                            <?php } ?>
                        </div>
                    <?php } ?>
                </div>
            <?php } else { ?>
                <div class="card-body small text-body-secondary"><?= __('No payments yet.') ?></div>
            <?php } ?>
        </div>

        <?php if ($hardware) { ?>
            <div class="card mb-3">
                <div class="card-header d-flex align-items-center">
                    <h3 class="card-title mb-0 me-auto"><i class="fas fa-fw fa-laptop text-secondary me-2"></i><?= __('Hardware') ?> <span class="badge rounded-pill bg-secondary-subtle text-secondary-emphasis fw-normal ms-1"><?= $hardware_units ?></span></h3>
                    <?php if ($can_add_assets) { ?>
                        <button type="button" class="btn btn-sm btn-outline-primary ajax-modal" data-modal-size="xl" data-modal-url="modals/invoice/invoice_assets.php?invoice_id=<?= $invoice_id ?>">
                            <i class="fas fa-plus me-1"></i><?= __('Add to Assets') ?> <span class="badge rounded-pill text-bg-primary ms-1" title="<?= __('Not on their assets yet') ?>"><?= $hardware_to_add ?></span>
                        </button>
                    <?php } ?>
                </div>
                <div class="list-group list-group-flush">
                    <?php foreach ($hardware as $line) {
                        $line_done = count($line['assets']); ?>
                        <div class="list-group-item">
                            <div class="d-flex align-items-center gap-2">
                                <?= productAssetIcon($line['product_asset_type'], 'sm') ?>
                                <div class="flex-grow-1 min-w-0">
                                    <div class="fw-semibold text-truncate"><?= escapeHtml($line['item_name']) ?></div>
                                    <div class="small text-body-secondary"><?= escapeHtml(assetTypeLabel($line['product_asset_type'])) ?> &middot; <?= __('%s sold', escapeHtml(billingQuantity($line['item_quantity']))) ?></div>
                                </div>
                                <?php if ($line['units'] && $line_done >= $line['units']) { ?>
                                    <span class="badge rounded-pill bg-success-subtle text-success-emphasis fw-normal text-nowrap"><i class="fas fa-check me-1"></i><?= __('On assets') ?></span>
                                <?php } elseif ($status !== 'Cancelled' && $line['units']) { ?>
                                    <span class="badge rounded-pill bg-warning-subtle text-warning-emphasis fw-normal text-nowrap" title="<?= escapeHtml(__("On the client's assets")) ?>"><?= __('%1$d of %2$d', $line_done, $line['units']) ?></span>
                                <?php } ?>
                            </div>
                            <?php if ($line['assets']) { ?>
                                <ul class="list-unstyled small mb-0 mt-1 itflow-hw-assets">
                                    <?php foreach ($line['assets'] as $line_asset) {
                                        $line_asset_name = escapeHtml($line_asset['asset_name']); ?>
                                        <li class="text-truncate">
                                            <?php if (hasClientAccess($line_asset['asset_client_id'])) { ?>
                                                <a href="asset.php?client_id=<?= intval($line_asset['asset_client_id']) ?>&asset_id=<?= intval($line_asset['asset_id']) ?>"><?= $line_asset_name ?></a>
                                            <?php } else { ?>
                                                <?= $line_asset_name ?>
                                            <?php } ?>
                                            <?php if ($line_asset['asset_serial']) { ?><span class="font-monospace text-body-secondary ms-1"><?= escapeHtml($line_asset['asset_serial']) ?></span><?php } ?>
                                            <?php if ($line_asset['asset_archived_at']) { ?><span class="badge rounded-pill text-bg-secondary fw-normal ms-1"><?= __('Archived') ?></span><?php } ?>
                                            <?php if (intval($line_asset['asset_client_id']) !== $client_id) { ?><span class="text-body-secondary ms-1"><?= __('(moved to another client)') ?></span><?php } ?>
                                        </li>
                                    <?php } ?>
                                </ul>
                            <?php } ?>
                        </div>
                    <?php } ?>
                </div>
                <?php if ($status === 'Cancelled') { ?>
                    <div class="card-footer small text-body-secondary"><?= __('Cancelled - nothing on it was sold.') ?></div>
                <?php } elseif ($hardware_to_add && !$can_add_assets) { ?>
                    <div class="card-footer small text-body-secondary"><?= _n("%1\$d not on %2\$s's assets yet.", "%1\$d not on %2\$s's assets yet.", $hardware_to_add, $hardware_to_add, escapeHtml($invoice['client_name'])) ?></div>
                <?php } ?>
            </div>
        <?php } ?>

        <?php if ($tickets || $unbilled_tickets) { ?>
            <div class="card mb-3">
                <div class="card-header d-flex align-items-center">
                    <h3 class="card-title mb-0 me-auto"><i class="fas fa-fw fa-life-ring text-secondary me-2"></i><?= __('Tickets') ?> <span class="badge rounded-pill bg-secondary-subtle text-secondary-emphasis fw-normal ms-1"><?= count($tickets) ?></span></h3>
                    <?php if ($unbilled_tickets) { ?>
                        <button type="button" class="btn btn-sm btn-outline-primary ajax-modal" data-modal-url="modals/invoice/invoice_add_ticket.php?invoice_id=<?= $invoice_id ?>">
                            <i class="fas fa-plus me-1"></i><?= __('Add') ?> <span class="badge rounded-pill text-bg-primary ms-1" title="<?= __('Unbilled') ?>"><?= $unbilled_tickets ?></span>
                        </button>
                    <?php } ?>
                </div>
                <?php if ($tickets) { ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($tickets as $ticket) {
                            $ticket_id = intval($ticket['ticket_id']); ?>
                            <div class="list-group-item d-flex align-items-center gap-2">
                                <div class="flex-grow-1 min-w-0">
                                    <a class="text-truncate d-block" href="ticket.php?ticket_id=<?= $ticket_id ?>"><span class="font-monospace small me-1"><?= escapeHtml($ticket['ticket_prefix'] . $ticket['ticket_number']) ?></span><?= escapeHtml($ticket['ticket_subject']) ?></a>
                                    <div class="small text-body-secondary"><i class="far fa-clock me-1"></i><?= __('%s worked', billingDuration($ticket['seconds'])) ?></div>
                                </div>
                                <?php if ($can_edit) { ?>
                                    <a class="btn btn-sm btn-link text-body-secondary confirm-link" title="<?= __('Take off the invoice') ?>" href="post.php?remove_ticket_from_invoice&invoice_id=<?= $invoice_id ?>&ticket_id=<?= $ticket_id ?>&csrf_token=<?= $csrf ?>"
                                        data-confirm-text="<?= escapeHtml(__("The ticket becomes unbilled again. The invoice's lines don't change.")) ?>">
                                        <i class="fas fa-fw fa-unlink"></i>
                                    </a>
                                <?php } ?>
                            </div>
                        <?php } ?>
                    </div>
                <?php } else { ?>
                    <div class="card-body small text-body-secondary"><?= _n('%1$d unbilled ticket for %2$s could go on this invoice.', '%1$d unbilled tickets for %2$s could go on this invoice.', $unbilled_tickets, $unbilled_tickets, escapeHtml($invoice['client_name'])) ?></div>
                <?php } ?>
            </div>
        <?php } ?>

        <?php if ($billed_expenses || $unbilled_expenses) {
            $expenses_cost = array_sum(array_column($billed_expenses, 'cost'));
            $expenses_billed = array_sum(array_map(function ($e) { return floatval($e['billed']); }, $billed_expenses)); ?>
            <div class="card mb-3">
                <div class="card-header d-flex align-items-center">
                    <h3 class="card-title mb-0 me-auto"><i class="fas fa-fw fa-shopping-cart text-secondary me-2"></i><?= __('Expenses') ?> <span class="badge rounded-pill bg-secondary-subtle text-secondary-emphasis fw-normal ms-1"><?= count($billed_expenses) ?></span></h3>
                    <?php if ($unbilled_expenses) { ?>
                        <button type="button" class="btn btn-sm btn-outline-primary ajax-modal" data-modal-size="lg" data-modal-url="modals/invoice/invoice_add_expenses.php?invoice_id=<?= $invoice_id ?>">
                            <i class="fas fa-plus me-1"></i><?= __('Add') ?> <span class="badge rounded-pill text-bg-primary ms-1" title="<?= __('To bill') ?>"><?= $unbilled_expenses ?></span>
                        </button>
                    <?php } ?>
                </div>
                <?php if ($billed_expenses) { ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($billed_expenses as $expense) {
                            $expense_id = intval($expense['expense_id']);
                            [$line_name] = expenseBillLine($expense); ?>
                            <div class="list-group-item">
                                <div class="d-flex align-items-start gap-2">
                                    <div class="flex-grow-1 min-w-0">
                                        <a class="text-truncate d-block" href="expenses.php?open=<?= $expense_id ?>" title="<?= escapeHtml($line_name) ?>"><?= escapeHtml($line_name) ?></a>
                                        <div class="small text-body-secondary text-truncate"><?= escapeHtml(implode(' · ', array_filter([$expense['vendor_name'], formatDate($expense['expense_date'])]))) ?></div>
                                    </div>
                                    <?php if ($can_unbill_expenses) { ?>
                                        <a class="btn btn-sm btn-link text-body-secondary confirm-link p-0" title="<?= __('Take off the invoice') ?>" href="post.php?expense_unbill=<?= $expense_id ?>&csrf_token=<?= $csrf ?>"
                                            data-confirm-text="<?= $status === 'Cancelled' ? __('The invoice is cancelled, so its line stays on it and the expense waits to be billed again.') : __('Its line comes off the invoice and the expense waits to be billed again.') ?>">
                                            <i class="fas fa-fw fa-unlink"></i>
                                        </a>
                                    <?php } ?>
                                </div>
                                <div class="d-flex justify-content-between gap-2 small mt-1">
                                    <span class="itflow-money text-nowrap"><span class="text-body-secondary"><?= __('Cost %s', $money($expense['cost'])) ?> &rarr;</span> <?= $expense['billed'] !== null ? $money($expense['billed']) : '<span class="text-body-secondary">' . __('line gone') . '</span>' ?></span>
                                    <?php if ($expense['billed'] !== null) { ?><span class="text-nowrap <?= $expense['billed'] < $expense['cost'] ? 'text-danger-emphasis' : 'text-body-secondary' ?>"><?= escapeHtml(expenseMarginText($expense['billed'], $expense['cost'])) ?></span><?php } ?>
                                </div>
                            </div>
                        <?php } ?>
                    </div>
                    <div class="card-footer small d-flex justify-content-between gap-2">
                        <span class="text-body-secondary"><?= __('Cost %1$s &middot; billed %2$s', $money($expenses_cost), $money($expenses_billed)) ?></span>
                        <span class="fw-semibold <?= $expenses_billed < $expenses_cost ? 'text-danger-emphasis' : '' ?>"><?= escapeHtml(expenseMarginText($expenses_billed, $expenses_cost) ?: __('No charge')) ?></span>
                    </div>
                <?php } else { ?>
                    <div class="card-body small text-body-secondary"><?= _n('%1$d expense to bill to %2$s could go on this invoice.', '%1$d expenses to bill to %2$s could go on this invoice.', $unbilled_expenses, $unbilled_expenses, escapeHtml($invoice['client_name'])) ?></div>
                <?php } ?>
            </div>
        <?php } ?>

        <?php if ($billed_trips || $unbilled_trips) {
            $trips_billed = array_sum(array_map(function ($t) { return floatval($t['billed']); }, $billed_trips));
            $trips_miles = array_sum(array_map(function ($t) { return floatval($t['trip_miles']); }, $billed_trips)); ?>
            <div class="card mb-3">
                <div class="card-header d-flex align-items-center">
                    <h3 class="card-title mb-0 me-auto"><i class="fas fa-fw fa-route text-secondary me-2"></i><?= __('Travel') ?> <span class="badge rounded-pill bg-secondary-subtle text-secondary-emphasis fw-normal ms-1"><?= count($billed_trips) ?></span></h3>
                    <?php if ($unbilled_trips) { ?>
                        <button type="button" class="btn btn-sm btn-outline-primary ajax-modal" data-modal-size="lg" data-modal-url="modals/invoice/invoice_add_trips.php?invoice_id=<?= $invoice_id ?>">
                            <i class="fas fa-plus me-1"></i><?= __('Add') ?> <span class="badge rounded-pill text-bg-primary ms-1" title="<?= __('To bill') ?>"><?= $unbilled_trips ?></span>
                        </button>
                    <?php } ?>
                </div>
                <?php if ($billed_trips) { ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($billed_trips as $trip) {
                            $trip_id = intval($trip['trip_id']); ?>
                            <div class="list-group-item">
                                <div class="d-flex align-items-start gap-2">
                                    <div class="flex-grow-1 min-w-0">
                                        <a class="text-truncate d-block ajax-modal" href="#" data-modal-size="lg" data-modal-url="modals/trip/trip.php?id=<?= $trip_id ?>" title="<?= escapeHtml($trip['trip_purpose']) ?>"><?= escapeHtml($trip['trip_purpose']) ?></a>
                                        <div class="small text-body-secondary text-truncate"><?= escapeHtml(implode(' · ', array_filter([formatDate($trip['trip_date']), $trip['driver_name'], tripMilesText($trip['trip_miles'])]))) ?></div>
                                    </div>
                                    <?php if ($can_unbill_trips) { ?>
                                        <a class="btn btn-sm btn-link text-body-secondary confirm-link p-0" title="<?= __('Take off the invoice') ?>" href="post.php?trip_unbill=<?= $trip_id ?>&csrf_token=<?= $csrf ?>"
                                            data-confirm-text="<?= $status === 'Cancelled' ? __('The invoice is cancelled, so its line stays on it and the trip waits to be billed again.') : __('Its line comes off the invoice and the trip waits to be billed again.') ?>">
                                            <i class="fas fa-fw fa-unlink"></i>
                                        </a>
                                    <?php } ?>
                                </div>
                                <div class="small mt-1 text-end itflow-money"><?= $trip['billed'] !== null ? $money($trip['billed']) : '<span class="text-body-secondary">' . __('line gone') . '</span>' ?></div>
                            </div>
                        <?php } ?>
                    </div>
                    <div class="card-footer small d-flex justify-content-between gap-2">
                        <span class="text-body-secondary"><?= __('%s driven', escapeHtml(tripMilesText($trips_miles))) ?></span>
                        <span class="fw-semibold"><?= $money($trips_billed) ?></span>
                    </div>
                <?php } else { ?>
                    <div class="card-body small text-body-secondary"><?= _n('%1$d trip to bill to %2$s could go on this invoice.', '%1$d trips to bill to %2$s could go on this invoice.', $unbilled_trips, $unbilled_trips, escapeHtml($invoice['client_name'])) ?></div>
                <?php } ?>
            </div>
        <?php } ?>

        <div class="card mb-3">
            <div class="card-header">
                <h3 class="card-title mb-0"><i class="fas fa-fw fa-history text-secondary me-2"></i><?= __('History') ?></h3>
            </div>
            <?= billingHistoryHtml($history) ?>
        </div>

    </div>
</div>

<?php if ($can_edit && $mail_ready) { ?>
    <!-- Quick Send buttons post this, carrying the invoice id as their own name/value -->
    <form id="quickSendInvoice" action="post.php" method="post" class="d-none">
        <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
        <input type="hidden" name="email_invoice" value="1">
        <input type="hidden" name="quick_send" value="1">
    </form>
<?php } ?>

<script src="/js/inline_notes.js"></script>
<script src="/libs/SortableJS/Sortable.min.js"></script>
<script src="/js/billing_items.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    itflowBillingItems({
        products: <?= $can_edit_lines ? getProductsForAutocomplete($mysqli, intval($invoice['client_id'])) : '[]' ?>,
        csrf: <?= json_encode($csrf) ?>,
        currency: <?= json_encode($currency) ?>,
        locale: <?= json_encode(str_replace('_', '-', (string) $session_company_locale)) ?>
    });
});
</script>

<?php require_once "../includes/footer.php";
