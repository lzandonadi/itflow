<?php
/*
 * Client Portal
 * Cards & autopay - the cards saved with Stripe (each saying which
 * recurring invoices it pays), adding one in Stripe's own form, and autopay:
 * which card, if any, pays each recurring invoice on its due date.
 *
 * The cards are read from what was saved with them (billingSavedCards()) -
 * the page doesn't ask Stripe about each one every time it opens.
 *
 * SCOPING: every query is keyed to $session_client_id; the handlers in
 * post.php (create_stripe_customer, create_stripe_checkout, stripe_save_card,
 * delete_saved_payment, set_recurring_payment) check the same.
 */

$portal_stripe = true; // Stripe's script, for adding a card - see includes/header.php

require_once "includes/inc_all.php";

portalBillingGate();

$provider = billingStripeProvider();

if (!$provider) { ?>
    <div class="itflow-portal-page-head">
        <div>
            <h1><?= escapeHtml(__('Cards & autopay')) ?></h1>
        </div>
    </div>
    <section class="itflow-portal-panel">
        <?= emptyStateHtml(['title' => __('Card payments are not set up'), 'text' => __('To pay, use the payment details on each invoice.'), 'icon' => 'fa-credit-card', 'filtered' => false]) ?>
    </section>
    <?php
    require_once "includes/footer.php";
    exit;
}

$customer = billingStripeCustomer($session_client_id);
$cards = billingSavedCards($session_client_id);
$usable = array_values(array_filter($cards, function ($card) {
    return !$card['card']['expired'];
}));
$recurring = mysqli_query($mysqli, "SELECT recurring_invoice_id, recurring_invoice_prefix, recurring_invoice_number, recurring_invoice_scope, recurring_invoice_amount,
        recurring_invoice_currency_code, recurring_invoice_frequency, recurring_invoice_next_date, recurring_payment_saved_payment_id
    FROM recurring_invoices
    LEFT JOIN recurring_payments ON recurring_payment_recurring_invoice_id = recurring_invoice_id
    WHERE recurring_invoice_client_id = $session_client_id AND recurring_invoice_status = 1
    ORDER BY recurring_invoice_next_date ASC, recurring_invoice_id ASC");
$company = billingCompany();
$autostart = isset($_GET['add']) && $customer !== '';
?>

<div class="itflow-portal-page-head">
    <div>
        <h1><?= escapeHtml(__('Cards & autopay')) ?></h1>
        <p><?= escapeHtml(__('Save a card to pay invoices in a click, and have recurring invoices paid on their own.')) ?></p>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-7">
        <section class="itflow-portal-panel">
            <div class="itflow-portal-panel-head">
                <h2><i class="fas fa-credit-card"></i><?= escapeHtml(__('Your cards')) ?></h2>
            </div>
            <?php if (!$cards) { ?>
                <?= emptyStateHtml(['title' => __('No cards saved'), 'text' => __('Save one to pay in a click, or to turn on autopay.'), 'icon' => 'fa-credit-card', 'filtered' => false]) ?>
            <?php } else { ?>
                <ul class="itflow-portal-list">
                    <?php foreach ($cards as $card) {
                        $c = $card['card']; ?>
                        <li class="itflow-portal-todo">
                            <span class="itflow-portal-card-brand" aria-hidden="true"><i class="<?= escapeHtml($c['icon']) ?>"></i></span>
                            <span class="itflow-portal-todo-main">
                                <div><?= escapeHtml($c['label']) ?></div>
                                <div>
                                    <?php if ($c['expired']) { ?>
                                        <span class="text-danger-emphasis"><?= escapeHtml(_x('Expired %s', 'card', $c['exp'])) ?></span>
                                    <?php } elseif ($c['exp'] !== '') { ?>
                                        <?= escapeHtml(__('Expires %s', $c['exp'])) ?>
                                    <?php } ?>
                                    <?php if ($card['autopay']) { ?>
                                        · <?= escapeHtml(_n('Pays %d recurring invoice automatically', 'Pays %d recurring invoices automatically', $card['autopay'])) ?>
                                    <?php } ?>
                                </div>
                            </span>
                            <a class="btn btn-sm btn-outline-danger confirm-link" href="post.php?delete_saved_payment=<?= intval($card['saved_payment_id']) ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>"
                                data-confirm-title="<?= escapeHtml(__('Remove %s?', $c['label'])) ?>"
                                data-confirm-text="<?= escapeHtml($card['autopay'] ? __('Autopay with this card stops - those invoices will need paying by hand.') : __('You can save it again any time.')) ?>"
                                data-confirm-button="<?= escapeHtml(__('Remove')) ?>" data-confirm-icon="warning"><i class="fas fa-trash-alt me-1"></i><?= escapeHtml(__('Remove')) ?></a>
                        </li>
                    <?php } ?>
                </ul>
            <?php } ?>
            <div class="itflow-portal-panel-body border-top">
                <?php if ($customer !== '') { ?>
                    <button type="button" class="btn btn-outline-primary" data-stripe-add-card<?= $autostart ? ' hidden' : '' ?>><i class="fas fa-plus me-2"></i><?= escapeHtml(__('Add a card')) ?></button>
                    <div data-stripe-add-card-holder data-stripe-key="<?= escapeHtml($provider['payment_provider_public_key']) ?>"<?= $autostart ? ' data-autostart' : '' ?> hidden>
                        <p class="small text-body-secondary"><?= escapeHtml(__('By saving a card you agree %s may charge it for invoices you pay with it, and for the recurring invoices you set to autopay.', $company['company_name'] ?? __('we'))) ?></p>
                        <div class="text-body-secondary small" data-stripe-loading><span class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span><?= escapeHtml(__('Loading Stripe...')) ?></div>
                        <div class="alert alert-warning small mb-0" data-stripe-unavailable hidden><?= escapeHtml(__("Stripe's form did not load - check your connection and reload the page.")) ?></div>
                        <div id="checkout"></div>
                    </div>
                <?php } else { ?>
                    <form action="post.php" method="post" data-portal-once>
                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                        <p class="small text-body-secondary"><?= escapeHtml(__('By saving a card you agree %s may charge it for invoices you pay with it, and for the recurring invoices you set to autopay. Stripe keeps the card details, under its privacy policy and terms.', $company['company_name'] ?? __('we'))) ?></p>
                        <button type="submit" class="btn btn-outline-primary" name="create_stripe_customer"><i class="fas fa-plus me-2"></i><?= escapeHtml(__('Add a card')) ?></button>
                    </form>
                <?php } ?>
            </div>
        </section>
    </div>

    <div class="col-lg-5">
        <section class="itflow-portal-panel">
            <div class="itflow-portal-panel-head">
                <h2><i class="fas fa-sync-alt"></i><?= escapeHtml(__('Autopay')) ?></h2>
            </div>
            <?php if (!mysqli_num_rows($recurring)) { ?>
                <?= emptyStateHtml(['title' => __('Nothing to autopay'), 'text' => __('Recurring invoices - monthly services and the like - can be paid automatically once you have one.'), 'icon' => 'fa-sync-alt', 'filtered' => false]) ?>
            <?php } else { ?>
                <div class="itflow-portal-panel-body">
                    <p class="small text-body-secondary mb-0"><?= escapeHtml(__("Autopay charges the card on each invoice's due date, and we email you a receipt every time.")) ?><?= !$usable ? ' ' . escapeHtml(__('Add a card to turn it on.')) : '' ?></p>
                </div>
                <ul class="itflow-portal-list border-top">
                    <?php while ($row = mysqli_fetch_assoc($recurring)) {
                        $rid = intval($row['recurring_invoice_id']);
                        $on = intval($row['recurring_payment_saved_payment_id']); ?>
                        <li class="itflow-portal-panel-body">
                            <div class="d-flex justify-content-between gap-2 mb-2">
                                <a class="fw-semibold text-body text-decoration-none text-truncate" href="recurring_invoice.php?id=<?= $rid ?>"><?= escapeHtml(trim((string) $row['recurring_invoice_scope']) !== '' ? $row['recurring_invoice_scope'] : $row['recurring_invoice_prefix'] . $row['recurring_invoice_number']) ?></a>
                                <span class="itflow-money small text-body-secondary"><?= escapeHtml(billingMoney($row['recurring_invoice_amount'], $row['recurring_invoice_currency_code'])) ?> / <?= escapeHtml(recurringInvoicePeriodWord($row['recurring_invoice_frequency'])) ?></span>
                            </div>
                            <form action="post.php" method="post">
                                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                <input type="hidden" name="set_recurring_payment" value="1">
                                <input type="hidden" name="recurring_invoice_id" value="<?= $rid ?>">
                                <div class="input-group input-group-sm">
                                    <span class="input-group-text"><i class="fa fa-fw fa-credit-card"></i></span>
                                    <select class="form-select" name="saved_payment_id" aria-label="<?= escapeHtml(__('Autopay for this recurring invoice')) ?>" data-autosubmit<?= !$usable && !$on ? ' disabled' : '' ?>>
                                        <option value="0"><?= escapeHtml(__("Off - I'll pay each invoice")) ?></option>
                                        <?php foreach ($cards as $card) {
                                            if ($card['card']['expired'] && intval($card['saved_payment_id']) !== $on) {
                                                continue;
                                            } ?>
                                            <option value="<?= intval($card['saved_payment_id']) ?>"<?= intval($card['saved_payment_id']) === $on ? ' selected' : '' ?>><?= escapeHtml($card['card']['expired'] ? __('Pay with %s (expired)', $card['card']['label']) : __('Pay with %s', $card['card']['label'])) ?></option>
                                        <?php } ?>
                                    </select>
                                </div>
                            </form>
                        </li>
                    <?php } ?>
                </ul>
            <?php } ?>
        </section>
    </div>
</div>

<?php
require_once "includes/footer.php";
