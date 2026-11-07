<?php
/*
 * Client Portal
 * A recurring invoice - what it bills each month, quarter or year, line by
 * line, when the next invoice comes, autopay for it, and the invoices it
 * has made.
 *
 * SCOPING: it must be the session's client's, and active.
 */

header("Content-Security-Policy: default-src 'self'");

require_once "includes/inc_all.php";

portalBillingGate();

$recurring = billingRow('recurring', intval($_GET['id'] ?? 0), false);
if (!$recurring || intval($recurring['client_id']) !== $session_client_id || intval($recurring['recurring_invoice_status']) !== 1) { ?>
    <a class="itflow-portal-back" href="recurring_invoices.php"><i class="fas fa-chevron-left"></i><?= escapeHtml(__('Recurring invoices')) ?></a>
    <section class="itflow-portal-panel">
        <?= emptyStateHtml(['title' => __("That recurring invoice isn't here"), 'text' => __('It may have ended, or the link is not quite right.'), 'icon' => 'fa-redo-alt', 'filtered' => false,
            'actions' => [['label' => __('Recurring invoices'), 'href' => 'recurring_invoices.php', 'icon' => 'fa-arrow-left']]]) ?>
    </section>
    <?php
    require_once "includes/footer.php";
    exit;
}

$recurring_id = intval($recurring['recurring_invoice_id']);
$currency = (string) $recurring['recurring_invoice_currency_code'];
$title = trim((string) $recurring['recurring_invoice_scope']) !== '' ? $recurring['recurring_invoice_scope'] : billingNumber('recurring', $recurring);
$items = billingItems('recurring', $recurring_id);
$totals = billingTotals('recurring', $recurring, $items);
$company = billingCompany();
$cards_on = billingStripeProvider() !== null;
$cards = $cards_on ? billingSavedCards($session_client_id) : [];
$usable = array_filter($cards, function ($card) {
    return !$card['card']['expired'];
});
$autopay = intval($recurring['recurring_payment_saved_payment_id']);
$made = array_filter(recurringInvoiceGenerated($recurring_id, 8), function ($row) {
    return $row['invoice_status'] !== 'Draft';
});

$paper = [
    'type' => 'recurring',
    'row' => $recurring,
    'items' => $items,
    'totals' => $totals,
    'logo_url' => $company['logo_url'],
    'client' => true,
];
?>

<a class="itflow-portal-back" href="recurring_invoices.php"><i class="fas fa-chevron-left"></i><?= escapeHtml(__('Recurring invoices')) ?></a>

<div class="itflow-portal-page-head">
    <div>
        <h1><?= escapeHtml($title) ?></h1>
        <p><?= escapeHtml(recurringInvoiceFrequencies()[$recurring['recurring_invoice_frequency']] ?? ucfirst((string) $recurring['recurring_invoice_frequency'])) ?> · <?= escapeHtml(billingNumber('recurring', $recurring)) ?></p>
    </div>
</div>

<div class="row g-4">

    <div class="col-lg-4 order-lg-2">

        <section class="itflow-portal-panel mb-4">
            <div class="itflow-portal-panel-body">
                <div class="itflow-billing-stat-label"><?= escapeHtml(__('Each %s', recurringInvoicePeriodWord($recurring['recurring_invoice_frequency']))) ?></div>
                <div class="itflow-portal-big-amount itflow-money"><?= escapeHtml(billingMoney($recurring['recurring_invoice_amount'], $currency)) ?></div>
                <p class="small text-body-secondary mb-0"><?= escapeHtml(__('The next invoice comes %s.', formatDate($recurring['recurring_invoice_next_date'], 'date'))) ?></p>
            </div>
            <?php if ($cards_on) { ?>
                <div class="itflow-portal-panel-body border-top">
                    <form action="post.php" method="post">
                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                        <input type="hidden" name="set_recurring_payment" value="1">
                        <input type="hidden" name="recurring_invoice_id" value="<?= $recurring_id ?>">
                        <label class="form-label fw-semibold" for="autopayCard"><?= escapeHtml(__('Autopay')) ?></label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fa fa-fw fa-credit-card"></i></span>
                            <select class="form-select" id="autopayCard" name="saved_payment_id" data-autosubmit<?= !$usable && !$autopay ? ' disabled' : '' ?>>
                                <option value="0"><?= escapeHtml(__("Off - I'll pay each invoice")) ?></option>
                                <?php foreach ($cards as $card) {
                                    if ($card['card']['expired'] && intval($card['saved_payment_id']) !== $autopay) {
                                        continue;
                                    } ?>
                                    <option value="<?= intval($card['saved_payment_id']) ?>"<?= intval($card['saved_payment_id']) === $autopay ? ' selected' : '' ?>><?= escapeHtml($card['card']['expired'] ? __('Pay with %s (expired)', $card['card']['label']) : __('Pay with %s', $card['card']['label'])) ?></option>
                                <?php } ?>
                            </select>
                        </div>
                        <div class="form-text">
                            <?php if ($usable) { ?>
                                <?= escapeHtml(__("Charged on each invoice's due date, with a receipt by email.")) ?>
                            <?php } else { ?>
                                <?= __('<a href="%s">Add a card</a> to have these paid automatically.', 'saved_payment_methods.php') ?>
                            <?php } ?>
                        </div>
                    </form>
                </div>
            <?php } ?>
        </section>

        <section class="itflow-portal-panel mb-4">
            <div class="itflow-portal-panel-head">
                <h2><i class="fas fa-file-invoice-dollar"></i><?= escapeHtml(__('Invoices from it')) ?></h2>
            </div>
            <?php if (!$made) { ?>
                <?= emptyStateHtml(['title' => __('None yet'), 'text' => escapeHtml(__('The first one comes %s.', formatDate($recurring['recurring_invoice_next_date'], 'date'))), 'icon' => 'fa-file-invoice-dollar', 'filtered' => false]) ?>
            <?php } else { ?>
                <ul class="itflow-portal-list">
                    <?php foreach ($made as $row) {
                        $state = portalInvoiceState($row, $row['paid']); ?>
                        <li>
                            <a class="itflow-portal-todo" href="invoice.php?id=<?= intval($row['invoice_id']) ?>">
                                <span class="itflow-portal-todo-main">
                                    <div><?= escapeHtml($row['invoice_prefix'] . $row['invoice_number']) ?></div>
                                    <div><?= escapeHtml(formatDate($row['invoice_date'], 'date')) ?> · <?= escapeHtml(billingMoney($row['invoice_amount'], $row['invoice_currency_code'])) ?></div>
                                </span>
                                <?= portalStateBadge($state) ?>
                            </a>
                        </li>
                    <?php } ?>
                </ul>
            <?php } ?>
        </section>

        <p class="small text-body-secondary px-1">
            <?php $ask = escapeHtml(portalAskUrl(__('Change to %s', $title))); ?>
            <?= !empty($company['phone_display']) ? __('Something to change? <a href="%1$s">Ask us</a> or call %2$s.', $ask, escapeHtml($company['phone_display'])) : __('Something to change? <a href="%s">Ask us</a>.', $ask) ?>
        </p>

    </div>

    <div class="col-lg-8 order-lg-1 itflow-paper-col">
        <?php require $_SERVER['DOCUMENT_ROOT'] . '/includes/billing_paper.php'; ?>
    </div>

</div>

<?php
require_once "includes/footer.php";
