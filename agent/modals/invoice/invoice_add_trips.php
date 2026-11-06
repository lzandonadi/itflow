<?php

/*
 * Add Trips to an invoice - the client's billable trips that aren't on an
 * invoice yet, each at what the trip charge and rate make it, the price
 * ready to change. Each goes on as a line of its own. ?invoice_id=
 */

require_once '../../../includes/modal_header.php';

enforceUserPermission('module_sales', 2);
enforceUserPermission('module_financial', 2);

$invoice_id = intval($_GET['invoice_id'] ?? 0);
$invoice = billingRow('invoice', $invoice_id);
if (!$invoice) {
    exit(json_encode(['error' => __('That invoice no longer exists.')]));
}
$client_id = intval($invoice['client_id']);
enforceClientAccess();
if (!billingCanEditItems('invoice', $invoice)) {
    exit(json_encode(['error' => __("%s can't take more lines - it is paid, cancelled or non-billable.", billingNumber('invoice', $invoice))]));
}

$trips = tripUnbilled($client_id);
$currency = (string) $invoice['invoice_currency_code'];
$taxes = billingTaxes();

ob_start();

?>
<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fas fa-fw fa-route me-2"></i><?= __('Add Trips') ?> <span class="fw-normal text-white-50 ms-1"><?= escapeHtml(billingNumber('invoice', $invoice)) ?></span></h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
<form action="post.php" method="post" autocomplete="off" id="invoiceAddTrips">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <input type="hidden" name="invoice_id" value="<?= $invoice_id ?>">

    <div class="modal-body p-0">
        <?php if ($trips) { ?>
            <div class="px-3 py-2 small text-body-secondary border-bottom">
                <?= tripBillingOn() ? __('The driving out to %1$s to bill, at %2$s. Change a price before adding - each goes on as a line of its own.', escapeHtml($invoice['client_name']), escapeHtml(tripBillRuleText())) : __('The driving out to %s to bill, with no trip charge or rate set. Change a price before adding - each goes on as a line of its own.', escapeHtml($invoice['client_name'])) ?>
            </div>
            <div class="list-group list-group-flush" style="max-height: 22rem; overflow-y: auto;">
                <?php foreach ($trips as $trip_id => $trip) { ?>
                    <div class="list-group-item d-flex align-items-center gap-3" data-add-trip-row>
                        <input class="form-check-input flex-shrink-0 mt-0" type="checkbox" name="trip_ids[]" value="<?= $trip_id ?>" id="addTrip<?= $trip_id ?>" checked data-add-trip>
                        <label class="flex-grow-1 min-w-0 mb-0" for="addTrip<?= $trip_id ?>">
                            <span class="d-block text-truncate"><?= escapeHtml($trip['trip_purpose']) ?></span>
                            <span class="small text-body-secondary d-block text-truncate">
                                <?= escapeHtml(formatDate($trip['trip_date'])) ?> &middot; <?= escapeHtml($trip['location_name'] ?? $trip['trip_destination']) ?> &middot; <?= escapeHtml(tripMilesText($trip['trip_miles'])) ?><?= intval($trip['round_trip']) ? ' ' . __('round trip') : '' ?>
                            </span>
                        </label>
                        <div class="flex-shrink-0" style="width: 8rem;">
                            <div class="input-group input-group-sm">
                                <span class="input-group-text"><i class="fa fa-fw fa-dollar-sign"></i></span>
                                <input type="text" class="form-control text-end" name="trip_price[<?= $trip_id ?>]" value="<?= number_format($trip['price'], 2, '.', '') ?>" inputmode="decimal" aria-label="<?= __('Price to bill') ?>" data-add-trip-price>
                            </div>
                        </div>
                    </div>
                <?php } ?>
            </div>
            <div class="p-3 border-top">
                <div class="row g-3">
                    <div class="col-sm-6">
                        <label class="form-label"><?= __('Tax') ?></label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fa fa-fw fa-percent"></i></span>
                            <select class="form-select" name="tax_id" data-add-trip-tax>
                                <?= taxOptionsHtml($taxes, taxDefaultFor($client_id, 'trip')) ?>
                            </select>
                        </div>
                    </div>
                </div>
            </div>
        <?php } else { ?>
            <div class="itflow-asset-empty">
                <i class="fas fa-route fa-2x mb-2 opacity-50"></i>
                <div><?= __("No trips to bill - %s's billable trips are all on an invoice.", escapeHtml($invoice['client_name'])) ?></div>
                <div class="small mt-1"><?= __("Switch on Bill the client in a trip's Edit window, or several at once with Bulk Action &rsaquo; Set Billable on %s.", '<a href="trips.php?client_id=' . $client_id . '">' . __('their trips') . '</a>') ?></div>
            </div>
        <?php } ?>
    </div>
    <div class="modal-footer">
        <?php if ($trips) { ?>
            <span class="me-auto small text-body-secondary" data-add-trip-summary></span>
            <button type="submit" name="invoice_add_trips" class="btn btn-primary text-bold" data-add-trip-submit><i class="fas fa-check me-2"></i><?= __('Add') ?></button>
        <?php } ?>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fas fa-times me-2"></i><?= $trips ? __('Cancel') : __('Close') ?></button>
    </div>
</form>

<script>
(function () {
    var form = document.getElementById('invoiceAddTrips');
    if (!form || form.dataset.wired || !form.querySelector('[data-add-trip]')) {
        return;
    }
    form.dataset.wired = '1';
    var currency = <?= json_encode($currency) ?>;
    var t = <?= json_encode(['one' => _n('%d trip', '%d trips', 1), 'other' => _n('%d trip', '%d trips', 2), 'tick' => __('Tick the trips to add')], JSON_HEX_TAG | JSON_HEX_AMP) ?>;
    var tax = form.querySelector('[data-add-trip-tax]');
    var summary = form.querySelector('[data-add-trip-summary]');
    var submit = form.querySelector('[data-add-trip-submit]');
    function number(value) {
        var n = Number(String(value || '').replace(/[\s,$]/g, ''));
        return isNaN(n) ? 0 : n;
    }
    function money(amount) {
        try {
            return new Intl.NumberFormat(undefined, { style: 'currency', currency: currency || 'USD' }).format(amount);
        } catch (e) {
            return amount.toFixed(2);
        }
    }
    function update() {
        var count = 0;
        var price = 0;
        form.querySelectorAll('[data-add-trip-row]').forEach(function (row) {
            var box = row.querySelector('[data-add-trip]');
            var input = row.querySelector('[data-add-trip-price]');
            input.disabled = !box.checked;
            if (box.checked) {
                count++;
                price += number(input.value);
            }
        });
        var option = tax.options[tax.selectedIndex];
        price = Math.round(price * 100) / 100;
        var total = price + Math.round(price * Number(option ? option.getAttribute('data-percent') || 0 : 0)) / 100;
        summary.textContent = count ? (count === 1 ? t.one : t.other).replace('%d', count) + ' · ' + money(total) : t.tick;
        submit.disabled = count === 0;
    }
    form.addEventListener('change', update);
    form.addEventListener('input', update);
    update();
})();
</script>

<?php
require_once '../../../includes/modal_footer.php';
