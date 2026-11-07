<?php
/*
 * Client Portal
 * Pay invoices - everything still owed, ticked to pay, paid together in one
 * card payment: with a saved card there and then, or a new card on the
 * checkout page (pay.php). ?select=ID,ID ticks just those - the Pay button
 * on an invoice. The total and the button follow the ticks (js/portal.js).
 *
 * SCOPING: billingClientOpenInvoices() keys the list to $session_client_id,
 * and post.php (portal_pay) checks the choice again before any charge.
 */

header("Content-Security-Policy: default-src 'self'");

require_once "includes/inc_all.php";

portalBillingGate();

$provider = billingStripeProvider();
$open = billingClientOpenInvoices($session_client_id);
$cards = $provider ? billingSavedCards($session_client_id) : [];
$usable = array_values(array_filter($cards, function ($card) {
    return !$card['card']['expired'];
}));

// What is ticked to start with - all of it, or what the link asked for
$select = isset($_GET['select']) ? array_filter(array_map('intval', explode(',', (string) $_GET['select']))) : null;
$ticked = [];
$total_cents = 0;
$currency = '';
foreach ($open as $i => $row) {
    $cents = (int) round((floatval($row['invoice_amount']) - floatval($row['paid'])) * 100);
    $open[$i]['cents'] = $cents;
    $currency = $currency ?: strtoupper((string) $row['invoice_currency_code']);
    // One payment can carry so many invoices - the oldest are ticked first
    if ($select === null ? count($ticked) < BILLING_PAY_MAX_INVOICES : in_array(intval($row['invoice_id']), $select, true)) {
        $ticked[intval($row['invoice_id'])] = true;
        $total_cents += $cents;
    }
}
$limit_cents = $provider ? (int) round(floatval($provider['payment_provider_threshold']) * 100) : 0;
$locale = str_replace('_', '-', (string) ($session_company_locale ?: 'en_US'));
$pay_token = bin2hex(random_bytes(16));
?>

<div class="itflow-portal-page-head">
    <div>
        <h1><?= escapeHtml(__('Pay invoices')) ?></h1>
        <p><?= escapeHtml(__('Tick what to pay - it goes through as one card payment.')) ?></p>
    </div>
</div>

<?php if (!$provider) { ?>

    <section class="itflow-portal-panel">
        <?= emptyStateHtml(['title' => __('Card payments are not set up'), 'text' => __('To pay, use the payment details on each invoice.'), 'icon' => 'fa-credit-card', 'filtered' => false,
            'actions' => [['label' => __('Invoices to pay'), 'href' => 'invoices.php?tab=open', 'icon' => 'fa-file-invoice-dollar']]]) ?>
    </section>

<?php } elseif (!$open) { ?>

    <section class="itflow-portal-panel">
        <?= emptyStateHtml(['title' => __('Nothing to pay'), 'text' => __('You are all paid up - thank you.'), 'icon' => 'fa-check', 'filtered' => false,
            'actions' => [['label' => __('Your payments'), 'href' => 'payments.php', 'icon' => 'fa-receipt']]]) ?>
    </section>

<?php } else { ?>

    <form action="post.php" method="post" data-portal-pay data-portal-once
        data-pay-currency="<?= escapeHtml($currency) ?>" data-pay-locale="<?= escapeHtml($locale) ?>" data-pay-limit="<?= $limit_cents ?>" data-pay-max="<?= BILLING_PAY_MAX_INVOICES ?>">
        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
        <input type="hidden" name="pay_token" value="<?= $pay_token ?>">

        <div class="row g-4">
            <div class="col-lg-8">
                <section class="itflow-portal-panel">
                    <div class="itflow-portal-panel-head">
                        <h2><i class="fas fa-file-invoice-dollar"></i><?= escapeHtml(__('Still to pay')) ?></h2>
                        <div class="form-check mb-0 small">
                            <input class="form-check-input" type="checkbox" id="payAll" data-pay-all<?= count($ticked) === count($open) ? ' checked' : '' ?>>
                            <label class="form-check-label" for="payAll"><?= escapeHtml(__('All')) ?></label>
                        </div>
                    </div>
                    <ul class="itflow-portal-list">
                        <?php foreach ($open as $row) {
                            $id = intval($row['invoice_id']);
                            $state = portalInvoiceState($row, $row['paid']); ?>
                            <li>
                                <label class="itflow-portal-ticket itflow-portal-payrow" for="payInvoice<?= $id ?>">
                                    <input class="form-check-input" type="checkbox" id="payInvoice<?= $id ?>" name="invoice_ids[]" value="<?= $id ?>" data-pay-cents="<?= intval($row['cents']) ?>"<?= isset($ticked[$id]) ? ' checked' : '' ?>>
                                    <span class="itflow-portal-ticket-main">
                                        <span class="itflow-portal-ticket-subject d-block"><?= escapeHtml($row['invoice_prefix'] . $row['invoice_number']) ?><?php if (trim((string) $row['invoice_scope']) !== '') { ?><span class="fw-normal text-body-secondary"> · <?= escapeHtml($row['invoice_scope']) ?></span><?php } ?></span>
                                        <span class="itflow-portal-ticket-meta">
                                            <span><?= escapeHtml(_x('Due %s', 'invoice due', formatDate($row['invoice_due'], 'date'))) ?></span>
                                            <?php if ($state['paid'] > 0) { ?><span><?= escapeHtml(__('%1$s of %2$s paid', billingMoney($state['paid'], $row['invoice_currency_code']), billingMoney($row['invoice_amount'], $row['invoice_currency_code']))) ?></span><?php } ?>
                                            <a href="invoice.php?id=<?= $id ?>"><?= escapeHtml(__('View')) ?></a>
                                        </span>
                                    </span>
                                    <span class="itflow-portal-ticket-side">
                                        <span class="itflow-portal-amount itflow-money"><?= escapeHtml(billingMoney($state['balance'], $row['invoice_currency_code'])) ?></span>
                                        <?= portalStateBadge($state) ?>
                                    </span>
                                </label>
                            </li>
                        <?php } ?>
                    </ul>
                </section>
            </div>

            <div class="col-lg-4">
                <section class="itflow-portal-panel itflow-portal-paybox">
                    <div class="itflow-portal-panel-head">
                        <h2><i class="fas fa-credit-card"></i><?= escapeHtml(__('Pay with')) ?></h2>
                        <a href="saved_payment_methods.php"><?= escapeHtml(__('Your cards')) ?></a>
                    </div>
                    <div class="itflow-portal-panel-body">
                        <div class="itflow-portal-methods">
                            <?php foreach ($cards as $i => $card) {
                                $expired = $card['card']['expired']; ?>
                                <label class="itflow-portal-method<?= $expired ? ' is-disabled' : '' ?>">
                                    <input class="form-check-input" type="radio" name="method" value="<?= intval($card['saved_payment_id']) ?>"<?= $expired ? ' disabled' : '' ?><?= $usable && intval($usable[0]['saved_payment_id']) === intval($card['saved_payment_id']) ? ' checked' : '' ?>>
                                    <i class="<?= escapeHtml($card['card']['icon']) ?> fa-fw fa-lg"></i>
                                    <span class="min-w-0">
                                        <span class="d-block text-truncate"><?= escapeHtml($card['card']['label']) ?></span>
                                        <small class="<?= $expired ? 'text-danger-emphasis' : 'text-body-secondary' ?>"><?= escapeHtml($card['card']['exp'] !== '' ? ($expired ? _x('Expired %s', 'card', $card['card']['exp']) : __('Expires %s', $card['card']['exp'])) : __('Saved card')) ?></small>
                                    </span>
                                </label>
                            <?php } ?>
                            <label class="itflow-portal-method">
                                <input class="form-check-input" type="radio" name="method" value="new"<?= $usable ? '' : ' checked' ?>>
                                <i class="fas fa-plus-circle fa-fw fa-lg text-body-secondary"></i>
                                <span>
                                    <span class="d-block"><?= escapeHtml(__('A new card')) ?></span>
                                    <small class="text-body-secondary"><?= escapeHtml(__('Enter the card on the next page')) ?></small>
                                </span>
                            </label>
                        </div>
                    </div>
                    <div class="itflow-portal-paybar">
                        <div class="d-flex justify-content-between align-items-baseline mb-2">
                            <span class="text-body-secondary" data-pay-count><?= escapeHtml(_n('%d invoice', '%d invoices', count($ticked))) ?></span>
                            <span class="itflow-portal-big-amount itflow-money" data-pay-total><?= escapeHtml(billingMoney($total_cents / 100, $currency)) ?></span>
                        </div>
                        <div class="alert alert-warning small py-2" data-pay-limit-note<?= $limit_cents > 0 && $total_cents >= $limit_cents ? '' : ' hidden' ?>>
                            <?= escapeHtml(__('Card payments are limited to under %s at a time - untick some invoices, or pay another way.', billingMoney($limit_cents / 100, $currency))) ?>
                        </div>
                        <button type="submit" class="btn btn-success btn-lg w-100" name="portal_pay" data-pay-button<?= !$ticked ? ' disabled' : '' ?>>
                            <i class="fas fa-lock me-2"></i><span data-pay-label><?= escapeHtml($usable ? __('Pay %s', billingMoney($total_cents / 100, $currency)) : __('Continue to pay %s', billingMoney($total_cents / 100, $currency))) ?></span>
                        </button>
                        <p class="small text-body-secondary text-center mt-2 mb-0"><?= escapeHtml(__('Paid securely through Stripe - your card details never reach us.')) ?></p>
                    </div>
                </section>
            </div>
        </div>
    </form>

<?php } ?>

<?php
require_once "includes/footer.php";
