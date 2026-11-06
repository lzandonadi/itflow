<?php

/*
 * Stock for a product that tracks it. ?id= and ?mode= picks where it opens:
 *   receive    it came in - how many, at what unit cost, on which expense
 *   count      what is on the shelf now - the difference is recorded
 *   write_off  damaged, lost, used by us or sent back
 * Posts product_stock_save.
 */

require_once '../../../includes/modal_header.php';

enforceUserPermission('module_sales', 2);

$product = productRow(intval($_GET['id'] ?? 0));
if (!$product) {
    exit(json_encode(['error' => __('That product no longer exists.')]));
}
if (!intval($product['product_track_stock'])) {
    exit(json_encode(['error' => __('%s doesn\'t track stock. Turn on Track stock in Edit to count it.', escapeHtml($product['product_name']))]));
}
$product_id = intval($product['product_id']);
$on_hand = floatval($product['product_stock']);
$vendor_id = intval($product['product_vendor_id']);
$mode = in_array($_GET['mode'] ?? '', ['receive', 'count', 'write_off'], true) ? $_GET['mode'] : 'receive';
$currency = (string) $product['product_currency_code'];
$cost = floatval($product['product_cost']) > 0 ? number_format(floatval($product['product_cost']), 2, '.', '') : '';
$level = floatval($product['product_reorder_level']);

// Recent expenses to link - the product's vendor's first. Expenses are Financial's,
// so only for a role that can see them, and only the clients' it can open
$expenses = [];
$can_expenses = lookupUserPermission('module_financial') >= 1;
if ($can_expenses) {
    $sql = mysqli_query($mysqli, "SELECT expense_id, expense_description, expense_date, expense_amount, expense_currency_code, expense_vendor_id, vendor_name
        FROM expenses
        LEFT JOIN vendors ON vendor_id = expense_vendor_id
        WHERE expense_archived_at IS NULL AND expense_date >= CURDATE() - INTERVAL 1 YEAR " . clientScopeSql('expense_client_id') . "
        ORDER BY (expense_vendor_id = $vendor_id AND $vendor_id > 0) DESC, expense_date DESC, expense_id DESC
        LIMIT 150");
    while ($row = mysqli_fetch_assoc($sql)) {
        $expenses[] = $row;
    }
}

$modes = [
    'receive' => ['label' => __('Receive'), 'icon' => 'fa-dolly', 'note' => __('It came in')],
    'count' => ['label' => __('Count'), 'icon' => 'fa-clipboard-check', 'note' => __('What is on the shelf')],
    'write_off' => ['label' => __('Write off'), 'icon' => 'fa-minus-circle', 'note' => __('Damaged, lost, used')],
];

ob_start();

?>
<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fas fa-fw fa-warehouse me-2"></i><?= __('Stock') ?> <span class="fw-normal text-white-50 ms-1"><?= escapeHtml($product['product_name']) ?></span></h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
<form action="post.php" method="post" autocomplete="off" id="productStock<?= $product_id ?>" data-on-hand="<?= $on_hand ?>" data-level="<?= $level ?>">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <input type="hidden" name="product_id" value="<?= $product_id ?>">

    <div class="modal-body">
        <div class="d-flex align-items-center gap-3 mb-3 p-3 rounded bg-body-tertiary">
            <?= productTile('product') ?>
            <div class="small flex-grow-1">
                <div><?= $product['product_location'] ? __('<strong>%1$s</strong> on the shelf at %2$s', escapeHtml(billingQuantity($on_hand)), escapeHtml($product['product_location'])) : __('<strong>%s</strong> on the shelf', escapeHtml(billingQuantity($on_hand))) ?><?= $level > 0 ? ' &middot; ' . __('reorder at %s', escapeHtml(billingQuantity($level))) : '' ?></div>
                <div class="text-body-secondary" data-stock-after>&nbsp;</div>
            </div>
        </div>

        <div class="btn-group w-100 itflow-product-type mb-3" role="group" aria-label="<?= escapeHtml(__('What happened')) ?>">
            <?php foreach ($modes as $mode_key => $m) { ?>
                <input type="radio" class="btn-check" name="mode" value="<?= $mode_key ?>" id="productStock<?= $product_id . $mode_key ?>" autocomplete="off" <?= $mode === $mode_key ? 'checked' : '' ?> data-stock-mode>
                <label class="btn btn-outline-secondary text-start d-flex align-items-center gap-2" for="productStock<?= $product_id . $mode_key ?>">
                    <i class="fas fa-fw <?= $m['icon'] ?> fa-lg"></i>
                    <span>
                        <span class="fw-semibold d-block"><?= $m['label'] ?></span>
                        <span class="small opacity-75 d-none d-sm-block"><?= $m['note'] ?></span>
                    </span>
                </label>
            <?php } ?>
        </div>

        <div class="row g-3">
            <!-- Receive -->
            <div class="col-sm-5" data-stock-for="receive">
                <label class="form-label"><?= __('Came in') ?> <strong class="text-danger">*</strong></label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fas fa-fw fa-plus"></i></span>
                    <input type="text" class="form-control text-end" name="qty" inputmode="decimal" placeholder="<?= escapeHtml(__('How many')) ?>" required data-stock-qty>
                </div>
            </div>
            <div class="col-sm-7" data-stock-for="receive">
                <label class="form-label"><?= __('Unit cost') ?></label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fas fa-fw fa-shopping-cart"></i></span>
                    <input type="text" class="form-control text-end" name="unit_cost" inputmode="decimal" value="<?= $cost ?>" placeholder="0.00" data-stock-cost>
                </div>
            </div>
            <div class="col-12" data-stock-for="receive">
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" role="switch" name="update_cost" value="1" id="productStockCost<?= $product_id ?>" data-stock-update-cost>
                    <label class="form-check-label" for="productStockCost<?= $product_id ?>"><?= __('Make this the product\'s cost') ?></label>
                    <div class="form-text"><?= $cost !== '' ? __('Its cost is %s - margins and the stock\'s value use it.', escapeHtml(billingMoney($cost, $currency))) : __('Its cost is not set - margins and the stock\'s value use it.') ?></div>
                </div>
            </div>
            <?php if ($can_expenses) { ?>
            <div class="col-12" data-stock-for="receive">
                <label class="form-label"><?= __('Bought on') ?></label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fas fa-fw fa-receipt"></i></span>
                    <select class="form-select select2" name="expense">
                        <option value="0"><?= __('- An expense from the last year -') ?></option>
                        <?php foreach ($expenses as $expense) { ?>
                            <option value="<?= intval($expense['expense_id']) ?>"><?= escapeHtml(formatDate($expense['expense_date'], 'date') . ' - ' . ($expense['vendor_name'] ? $expense['vendor_name'] . ' - ' : '') . mb_strimwidth((string) $expense['expense_description'], 0, 60, '...') . ' - ' . billingMoney($expense['expense_amount'], $expense['expense_currency_code'])) ?></option>
                        <?php } ?>
                    </select>
                </div>
            </div>
            <?php } ?>

            <!-- Count -->
            <div class="col-sm-6" data-stock-for="count">
                <label class="form-label"><?= __('On the shelf') ?> <strong class="text-danger">*</strong></label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fas fa-fw fa-clipboard-check"></i></span>
                    <input type="text" class="form-control text-end" name="counted" inputmode="decimal" placeholder="<?= escapeHtml(__('What you counted')) ?>" required data-stock-counted>
                </div>
            </div>

            <!-- Write off -->
            <div class="col-sm-5" data-stock-for="write_off">
                <label class="form-label"><?= __('How many') ?> <strong class="text-danger">*</strong></label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fas fa-fw fa-minus"></i></span>
                    <input type="text" class="form-control text-end" name="qty" inputmode="decimal" placeholder="<?= escapeHtml(__('Off the shelf')) ?>" required data-stock-qty>
                </div>
            </div>
            <div class="col-sm-7" data-stock-for="write_off">
                <label class="form-label"><?= __('Why') ?></label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fas fa-fw fa-question-circle"></i></span>
                    <select class="form-select" name="reason">
                        <?php foreach (productWriteOffReasons() as $reason) { ?>
                            <option value="<?= escapeHtml($reason) ?>"><?= escapeHtml(productWriteOffReasonLabel($reason)) ?></option>
                        <?php } ?>
                    </select>
                </div>
            </div>

            <div class="col-12">
                <label class="form-label"><?= __('Note') ?></label>
                <div class="input-group">
                    <span class="input-group-text align-items-start pt-2"><i class="fas fa-fw fa-sticky-note"></i></span>
                    <textarea class="form-control" name="note" rows="2" placeholder="<?= escapeHtml(__('Order number, serials, who - kept with the move')) ?>"></textarea>
                </div>
            </div>
        </div>
    </div>

    <div class="modal-footer">
        <button type="submit" name="product_stock_save" class="btn btn-primary text-bold"><i class="fas fa-check me-2"></i><span data-stock-submit><?= __('Save') ?></span></button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fas fa-times me-2"></i><?= __('Cancel') ?></button>
    </div>
</form>

<script>
(function () {
    var form = document.getElementById('productStock<?= $product_id ?>');
    if (!form || form.dataset.wired) {
        return;
    }
    form.dataset.wired = '1';
    var onHand = Number(form.dataset.onHand);
    var level = Number(form.dataset.level);
    var after = form.querySelector('[data-stock-after]');
    var submit = form.querySelector('[data-stock-submit]');
    var t = <?= json_encode([
        'receive' => __('Receive'),
        'count' => __('Save Count'),
        'write_off' => __('Write Off'),
        'save' => __('Save'),
        'only' => __('Only %s on the shelf'),
        'after' => __('%s on the shelf after this'),
        'same' => __('The same as ITFlow has'),
        'diff' => __('%s from what ITFlow has'),
        'level' => __(' - at or under the reorder level'),
    ], JSON_HEX_TAG | JSON_HEX_AMP) ?>;
    var labels = { receive: t.receive, count: t.count, write_off: t.write_off };

    function number(input) {
        var text = String(input ? input.value : '').replace(/[\s,]/g, '');
        return text === '' ? NaN : Number(text);
    }
    function fmt(n) {
        return String(Math.round(n * 100) / 100);
    }
    function update() {
        var mode = (form.querySelector('[data-stock-mode]:checked') || {}).value;
        form.querySelectorAll('[data-stock-for]').forEach(function (section) {
            var on = section.dataset.stockFor === mode;
            section.hidden = !on;
            section.querySelectorAll('input, select, textarea').forEach(function (input) {
                input.disabled = !on;
            });
        });
        submit.textContent = labels[mode] || t.save;
        var text = '';
        var section = form.querySelector('[data-stock-for="' + mode + '"]');
        if (mode === 'receive' || mode === 'write_off') {
            var q = number(section.querySelector('[data-stock-qty]'));
            if (q > 0) {
                var next = mode === 'receive' ? onHand + q : onHand - q;
                text = next < 0 ? t.only.replace('%s', fmt(onHand)) : t.after.replace('%s', fmt(next));
            }
        } else if (mode === 'count') {
            var c = number(form.querySelector('[data-stock-counted]'));
            if (!isNaN(c) && c >= 0) {
                var diff = Math.round((c - onHand) * 100) / 100;
                text = diff === 0 ? t.same : t.diff.replace('%s', (diff > 0 ? '+' : '') + fmt(diff));
            }
        }
        if (text && level > 0 && mode !== 'receive') {
            var left = mode === 'count' ? number(form.querySelector('[data-stock-counted]')) : onHand - number(section.querySelector('[data-stock-qty]'));
            if (left <= level) {
                text += t.level;
            }
        }
        after.innerHTML = text ? text.replace(/&/g, '&amp;').replace(/</g, '&lt;') : '&nbsp;';
    }
    form.addEventListener('input', update);
    form.addEventListener('change', update);
    update();
})();
</script>

<?php
require_once '../../../includes/modal_footer.php';
