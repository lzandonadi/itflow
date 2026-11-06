<?php

/*
 * Add Tickets to an invoice - the client's billable tickets that are
 * resolved or closed and not on an invoice yet. Each comes with the hours
 * its time bills (rounded the way Admin > Settings > Invoice says), ready to
 * change, and goes on as a line at the rate - or, with Bill their time off,
 * goes on without a line, for work already on the invoice. ?invoice_id=
 */

require_once '../../../includes/modal_header.php';

enforceUserPermission('module_sales', 2);

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

$tickets = billingUnbilledTickets($client_id);
$rules = billingTimeRules();
// One rate for them all, or each its own - a contract's after-hours rate, or a change of contract, makes more than one
$rates = array_unique(array_map(function ($t) { return sprintf('%.2f', $t['rate']); }, $tickets));
$rate = count($rates) === 1 ? floatval(reset($rates)) : ($tickets ? null : contractClientRate($client_id)['rate']);
$currency = (string) $invoice['invoice_currency_code'];
$taxes = billingTaxes();

ob_start();

?>
<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fas fa-fw fa-life-ring me-2"></i><?= __('Add Tickets') ?> <span class="fw-normal text-white-50 ms-1"><?= escapeHtml(billingNumber('invoice', $invoice)) ?></span></h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
<form action="post.php" method="post" autocomplete="off" id="invoiceAddTickets">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <input type="hidden" name="invoice_id" value="<?= $invoice_id ?>">

    <div class="modal-body p-0">
        <?php if ($tickets) { ?>
            <div class="px-3 py-2 small text-body-secondary border-bottom">
                <?= __("%1\$s's billable tickets, resolved or closed, that aren't on an invoice. The hours are the time logged, rounded %2\$s - change them before adding.", escapeHtml($invoice['client_name']), escapeHtml(billingTimeRulesText($rules))) ?>
            </div>
            <div class="list-group list-group-flush" style="max-height: 20rem; overflow-y: auto;">
                <?php foreach ($tickets as $ticket_id => $ticket) { ?>
                    <div class="list-group-item d-flex align-items-center gap-3" data-rate="<?= floatval($ticket['rate']) ?>">
                        <input class="form-check-input flex-shrink-0 mt-0" type="checkbox" name="ticket_ids[]" value="<?= $ticket_id ?>" id="addTicket<?= $ticket_id ?>" data-add-ticket>
                        <label class="flex-grow-1 min-w-0 mb-0" for="addTicket<?= $ticket_id ?>">
                            <span class="d-block text-truncate"><span class="font-monospace small text-body-secondary me-2"><?= escapeHtml($ticket['ticket_prefix'] . $ticket['ticket_number']) ?></span><?= escapeHtml($ticket['ticket_subject']) ?></span>
                            <span class="small text-body-secondary">
                                <i class="far fa-clock me-1"></i><?= $ticket['seconds'] ? __('%s logged', billingDuration($ticket['seconds'])) : '<span class="text-warning-emphasis">' . __('No time logged') . '</span>' ?>
                                &middot; <?= __('done %s', escapeHtml(formatDate($ticket['ticket_resolved_at'] ?: $ticket['ticket_closed_at']))) ?>
                                <?= $ticket['prepaid'] > 0 ? '&middot; ' . __('less %s prepaid', escapeHtml(contractHoursText($ticket['prepaid']))) : '' ?>
                                <?php if ($rate === null) { ?>
                                    &middot; <span title="<?= escapeHtml(ucfirst($ticket['rate_note'])) ?>"><?= in_array($ticket['rate_source'], ['after_hours', 'default_after_hours'], true) ? '<i class="fas fa-moon me-1"></i>' : '' ?><?= __('%s/hr', escapeHtml(billingMoney($ticket['rate'], $currency))) ?></span>
                                <?php } ?>
                            </span>
                        </label>
                        <div class="input-group input-group-sm flex-shrink-0" style="width: 6.5rem;" data-add-ticket-hours>
                            <input type="text" class="form-control text-end" name="hours[<?= $ticket_id ?>]" value="<?= billingQuantity($ticket['hours']) ?>" inputmode="decimal" aria-label="<?= __('Hours to bill') ?>">
                            <span class="input-group-text"><?= _x('h', 'hours') ?></span>
                        </div>
                    </div>
                <?php } ?>
            </div>
            <div class="p-3 border-top">
                <div class="form-check form-switch mb-3">
                    <input class="form-check-input" type="checkbox" role="switch" name="bill_time" value="1" id="addTicketsBill" checked data-add-ticket-bill>
                    <label class="form-check-label" for="addTicketsBill"><?= __('Bill their time - add lines at the hourly rate') ?></label>
                </div>
                <div class="row g-3" data-add-ticket-lines>
                    <div class="col-12">
                        <label class="form-label"><?= __('Line') ?></label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fa fa-fw fa-cube"></i></span>
                            <input type="text" class="form-control" name="item_name" maxlength="200" value="<?= escapeHtml($rules['item_name']) ?>" required>
                        </div>
                    </div>
                    <div class="col-sm-4">
                        <label class="form-label"><?= __('Rate') ?></label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fa fa-fw fa-dollar-sign"></i></span>
                            <input type="text" class="form-control text-end" name="price" inputmode="decimal" value="<?= $rate !== null ? number_format($rate, 2, '.', '') : '' ?>" placeholder="<?= __('Each its own') ?>" data-add-ticket-rate>
                        </div>
                        <?php if ($rate === null) { ?>
                            <div class="form-text"><?= __("Each ticket at its own rate - a contract's sets some. Type one to bill them all at it.") ?></div>
                        <?php } ?>
                    </div>
                    <div class="col-sm-4">
                        <label class="form-label"><?= __('Tax') ?></label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fa fa-fw fa-percent"></i></span>
                            <select class="form-select" name="tax_id" data-add-ticket-tax>
                                <?= taxOptionsHtml($taxes, taxDefaultFor($client_id, 'time')) ?>
                            </select>
                        </div>
                    </div>
                    <div class="col-sm-4">
                        <label class="form-label"><?= __('Lines') ?></label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fa fa-fw fa-list"></i></span>
                            <select class="form-select" name="lines">
                                <option value="each"><?= __('One each') ?></option>
                                <option value="one"><?= __('One for all') ?></option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="form-text" data-add-ticket-nolines hidden><?= __('The tickets go on the invoice and stop being unbilled - nothing is added to its lines. For work already billed on it by hand.') ?></div>
            </div>
        <?php } else { ?>
            <div class="itflow-asset-empty">
                <i class="fas fa-life-ring fa-2x mb-2 opacity-50"></i>
                <div><?= __("No unbilled tickets - %s's billable tickets are all on an invoice, or still open.", escapeHtml($invoice['client_name'])) ?></div>
            </div>
        <?php } ?>
    </div>
    <div class="modal-footer">
        <?php if ($tickets) { ?>
            <span class="me-auto small text-body-secondary" data-add-ticket-summary></span>
            <button type="submit" name="add_ticket_to_invoice" class="btn btn-primary text-bold" disabled data-add-ticket-submit><i class="fas fa-check me-2"></i><?= __('Add') ?></button>
        <?php } ?>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fas fa-times me-2"></i><?= $tickets ? __('Cancel') : __('Close') ?></button>
    </div>
</form>

<script>
(function () {
    var form = document.getElementById('invoiceAddTickets');
    if (!form || form.dataset.wired) {
        return;
    }
    form.dataset.wired = '1';
    var currency = <?= json_encode($currency) ?>;
    var t = <?= json_encode(['one' => _n('%d ticket', '%d tickets', 1), 'other' => _n('%d ticket', '%d tickets', 2), 'h' => _x('h', 'hours'), 'tick' => __('Tick the tickets to add')], JSON_HEX_TAG | JSON_HEX_AMP) ?>;
    var bill = form.querySelector('[data-add-ticket-bill]');
    var lines = form.querySelector('[data-add-ticket-lines]');
    var noLines = form.querySelector('[data-add-ticket-nolines]');
    var rate = form.querySelector('[data-add-ticket-rate]');
    var tax = form.querySelector('[data-add-ticket-tax]');
    var summary = form.querySelector('[data-add-ticket-summary]');
    var submit = form.querySelector('[data-add-ticket-submit]');
    if (!bill) {
        return;
    }
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
        var hours = 0;
        var amount = 0;
        var typed = rate.value.trim() !== '';
        form.querySelectorAll('[data-add-ticket]').forEach(function (box) {
            var row = box.closest('.list-group-item');
            var input = row.querySelector('[data-add-ticket-hours] input');
            input.disabled = !bill.checked;
            if (box.checked) {
                count++;
                hours += number(input.value);
                // The rate typed, or the ticket's own
                amount += Math.round(number(input.value) * (typed ? number(rate.value) : Number(row.getAttribute('data-rate') || 0)) * 100) / 100;
            }
        });
        lines.hidden = !bill.checked;
        lines.querySelectorAll('input, select').forEach(function (input) {
            input.disabled = !bill.checked;
        });
        noLines.hidden = bill.checked;
        var option = tax.options[tax.selectedIndex];
        amount += Math.round(amount * Number(option ? option.getAttribute('data-percent') || 0 : 0)) / 100;
        hours = Math.round(hours * 100) / 100;
        summary.textContent = count ? (count === 1 ? t.one : t.other).replace('%d', count) + (bill.checked ? ' · ' + hours + ' ' + t.h + ' · ' + money(amount) : '') : t.tick;
        submit.disabled = count === 0;
    }
    form.addEventListener('change', update);
    form.addEventListener('input', update);
    update();
})();
</script>

<?php
require_once '../../../includes/modal_footer.php';
