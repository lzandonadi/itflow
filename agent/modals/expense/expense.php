<?php

/*
 * An expense's quick look - the receipt (shown right in the window when it
 * is a photo or a PDF, or attached here when there isn't one), who it was
 * paid to and for what, the account and how it was paid (or the agent who
 * paid it personally, and whether they are paid back), the client, who
 * entered it, the refund it is or the refunds against it, billing it on to
 * the client (the invoice it is on, with the margin, or what it will bill
 * at), and the stock deliveries it paid for. Opened from the expenses list, the vendor's page,
 * Transactions and search (expenses.php?open=).
 */

require_once '../../../includes/modal_header.php';

enforceUserPermission('module_financial');

$row = expenseRow(intval($_GET['id'] ?? 0));
if (!$row) {
    echo json_encode(['error' => __('That expense no longer exists.')]);
    exit;
}

$expense_id = intval($row['expense_id']);
$cleared = reconcileLock('expense', $expense_id);
$client_id = intval($row['expense_client_id']);
$can_edit = lookupUserPermission('module_financial') >= 2;
$can_delete = lookupUserPermission('module_financial') >= 3;
$is_transfer = !empty($row['transfer_id']) || !empty($row['reimbursement_out_id']);
$is_payback = !empty($row['reimbursement_out_id']);
$paid_by = intval($row['expense_paid_by']);
$to_reimburse = $paid_by && !$row['expense_reimbursement_id'] && floatval($row['expense_amount']) > 0;
$amount = floatval($row['expense_amount']);
$currency = (string) $row['expense_currency_code'];
$receipt_kind = $row['expense_receipt'] && expenseReceiptPath($row['expense_receipt']) !== '' ? expenseReceiptKind($row['expense_receipt']) : '';
$receipt_missing = $row['expense_receipt'] && $receipt_kind === '' && expenseReceiptPath($row['expense_receipt']) === '';
$receipt_url = "expense_receipt.php?expense_id=$expense_id";
// Mileage - the trips in their own vehicle this pays for (functions/trips.php)
[$mileage_trips, $mileage_miles] = tripTotalsFor('trip_expense_id', $expense_id);

// Billing it on to the client
$can_sales = lookupUserPermission('module_sales');
$billed_on = intval($row['expense_invoice_id']);
$cost = round($amount - floatval($row['refunded_amount']), 2);
$billable = !$billed_on && intval($row['expense_billable']) && $client_id && $amount > 0 && !$is_transfer;
$bill_price = expenseBillPrice($cost, $row['expense_markup']);
$can_unbill = $billed_on && $can_edit && expenseCanUnbill($row['invoice_status']);

// Refunds against it, and the stock it paid for
$refunds = [];
$sql = mysqli_query($mysqli, "SELECT expense_id, expense_date, expense_amount, expense_description FROM expenses WHERE expense_refund_of_id = $expense_id ORDER BY expense_date ASC, expense_id ASC");
while ($refund = mysqli_fetch_assoc($sql)) {
    $refunds[] = $refund;
}
$stock = [];
$sql = mysqli_query($mysqli, "SELECT stock_qty, stock_unit_cost, stock_created_at, product_id, product_name FROM product_stock
    JOIN products ON product_id = stock_product_id
    WHERE stock_expense_id = $expense_id ORDER BY stock_id ASC");
while ($move = mysqli_fetch_assoc($sql)) {
    $stock[] = $move;
}

ob_start();

?>

<div class="modal-header bg-dark" data-bs-theme="dark">
    <div class="d-flex align-items-center gap-3 min-w-0 flex-grow-1">
        <?= vendorTile($row['vendor_name'] ?: 'Transfer', 'lg') ?>
        <div class="min-w-0 flex-grow-1">
            <h5 class="modal-title d-flex flex-wrap align-items-center column-gap-2">
                <span class="fw-semibold text-break"><?= escapeHtml($is_payback ? __('Paid back to %s', $row['reimbursed_to_name'] ?: __('an agent')) : ($is_transfer ? __('Transfer out') : ($row['vendor_name'] ?: __('No vendor')))) ?></span>
                <?php if ($amount < 0) { ?><span class="badge rounded-pill text-bg-success fw-normal fs-6"><?= __('Refund') ?></span><?php } ?>
            </h5>
            <div class="small text-body-secondary mt-1"><?= escapeHtml(implode(' · ', array_filter([formatDate($row['expense_date'], 'date'), $row['category_name']]))) ?></div>
        </div>
        <div class="text-end">
            <div class="fs-4 fw-semibold itflow-money <?= $amount < 0 ? 'text-success' : 'text-white' ?>"><?= escapeHtml(billingMoney($amount, $currency)) ?></div>
            <?php if (floatval($row['expense_tax_amount']) != 0) { ?>
                <div class="small text-body-secondary">incl. <?= escapeHtml(billingMoney(abs(floatval($row['expense_tax_amount'])), $currency)) ?> tax</div>
            <?php } ?>
        </div>
    </div>
    <button type="button" class="btn-close btn-close-white ms-3" data-bs-dismiss="modal" aria-label="<?= escapeHtml(__('Close')) ?>"></button>
</div>

<div class="modal-body">

    <?php if ($is_payback) { ?>
        <div class="alert alert-info small mb-3"><i class="fas fa-hand-holding-usd me-2"></i><?= escapeHtml(__('This is money paid back to %s for what they paid personally -', $row['reimbursed_to_name'] ?: __('an agent'))) ?>
            <a href="#" class="ajax-modal" data-modal-size="lg" data-modal-url="modals/expense/expense_reimbursement.php?id=<?= intval($row['reimbursement_out_id']) ?>"><?= __('open the reimbursement') ?></a> <?= __('to see or undo it.') ?></div>
    <?php } elseif ($is_transfer) { ?>
        <div class="alert alert-info small mb-3"><i class="fas fa-exchange-alt me-2"></i><?= __('This is the out side of a transfer between accounts -') ?> <a href="#" class="ajax-modal" data-modal-size="lg" data-modal-url="modals/transfer/transfer.php?id=<?= intval($row['transfer_id']) ?>"><?= __('open the transfer') ?></a> <?= __('to change or delete it.') ?></div>
    <?php } ?>

    <!-- The receipt -->
    <?php if ($receipt_kind) { ?>
        <div class="itflow-file-preview mb-3">
            <?php if ($receipt_kind === 'image') { ?>
                <a href="<?= $receipt_url ?>&amp;action=view" target="_blank" title="<?= escapeHtml(__('Open full size')) ?>"><img src="<?= $receipt_url ?>&amp;action=view" alt="<?= escapeHtml(__('Receipt')) ?>"></a>
            <?php } else { ?>
                <iframe src="<?= $receipt_url ?>&amp;action=view#view=FitH" title="<?= escapeHtml(__('Receipt')) ?>"></iframe>
            <?php } ?>
        </div>
    <?php } elseif (!$is_transfer) { ?>
        <div class="itflow-expense-receipt-empty mb-3">
            <i class="fas fa-receipt fa-2x opacity-50 mb-2"></i>
            <div class="mb-2"><?= $receipt_missing ? __('The receipt\'s file is missing from uploads/expenses/.') : __('No receipt yet.') ?></div>
            <?php if ($can_edit) { ?>
                <form action="post.php" method="post" enctype="multipart/form-data" class="d-flex flex-wrap justify-content-center gap-2" autocomplete="off">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                    <input type="hidden" name="expense_id" value="<?= $expense_id ?>">
                    <div class="input-group" style="max-width: 26rem;">
                        <span class="input-group-text"><i class="fa fa-fw fa-paperclip"></i></span>
                        <input type="file" class="form-control" name="file" accept="image/*,application/pdf" required>
                        <button type="submit" name="expense_receipt_save" class="btn btn-primary"><i class="fas fa-upload me-1"></i><?= __('Attach') ?></button>
                    </div>
                </form>
            <?php } ?>
        </div>
    <?php } ?>

    <!-- At a glance -->
    <div class="row g-2 itflow-asset-glance">
        <div class="col-sm-6">
            <?php if (!$is_transfer && intval($row['expense_vendor_id'])) { ?>
                <div class="itflow-glance-item" title="<?= escapeHtml(__('Vendor')) ?>">
                    <i class="fas fa-fw fa-building"></i>
                    <a class="text-truncate" href="vendor.php?vendor_id=<?= intval($row['expense_vendor_id']) ?>"><?= escapeHtml($row['vendor_name']) ?></a>
                </div>
            <?php } ?>
            <div class="itflow-glance-item" title="<?= escapeHtml(__('Category')) ?>">
                <i class="fas fa-fw fa-list"></i>
                <span><?= expenseCategoryDot($row['category_color'], $row['category_icon'] ?? '') ?><?= escapeHtml($row['category_name'] ?: __('No category')) ?></span>
            </div>
            <?php if (floatval($row['expense_tax_amount']) != 0) { ?>
                <div class="itflow-glance-item" title="<?= escapeHtml(__('Tax included')) ?>">
                    <i class="fas fa-fw fa-percent"></i>
                    <span><?= escapeHtml(billingMoney(abs(floatval($row['expense_tax_amount'])), $currency)) ?> tax<?= $row['tax_name'] !== null ? ' at ' . escapeHtml(taxLabel($row)) : '' ?>, on <?= escapeHtml(billingMoney(abs($amount) - abs(floatval($row['expense_tax_amount'])), $currency)) ?></span>
                </div>
            <?php } ?>
            <?php if ($mileage_trips) { ?>
                <div class="itflow-glance-item" title="<?= escapeHtml(__('Mileage')) ?>">
                    <i class="fas fa-fw fa-car-side"></i>
                    <a class="text-truncate" href="trips.php?expense_id=<?= $expense_id ?>"><?= escapeHtml(_n('Mileage for %1$d trip, %2$s', 'Mileage for %1$d trips, %2$s', $mileage_trips, $mileage_trips, tripMilesText($mileage_miles))) ?></a>
                </div>
            <?php } ?>
            <?php if ($paid_by) { ?>
                <div class="itflow-glance-item" title="<?= escapeHtml(__('Paid personally')) ?>">
                    <i class="fas fa-fw fa-hand-holding-usd"></i>
                    <span>
                        <?= __('Paid personally by %s', escapeHtml($row['paid_by_name'] ?: __('an agent since deleted'))) ?><?= $row['expense_payment_method'] ? ' <span class="text-body-secondary">&middot; ' . escapeHtml($row['expense_payment_method']) . '</span>' : '' ?>
                        <?php if ($row['expense_reimbursement_id']) { ?>
                            <span class="d-block small"><a href="#" class="ajax-modal text-success-emphasis" data-modal-size="lg" data-modal-url="modals/expense/expense_reimbursement.php?id=<?= intval($row['expense_reimbursement_id']) ?>"><i class="fas fa-check-circle me-1"></i><?= escapeHtml($row['reimbursed_account_name'] ? __('Paid back %1$s from %2$s', formatDate($row['reimbursement_date'], 'date'), $row['reimbursed_account_name']) : __('Paid back %s', formatDate($row['reimbursement_date'], 'date'))) ?></a></span>
                        <?php } elseif ($to_reimburse) { ?>
                            <span class="d-block small text-warning-emphasis fw-semibold"><?= __('To reimburse') ?></span>
                        <?php } ?>
                    </span>
                </div>
            <?php } else { ?>
                <div class="itflow-glance-item" title="<?= escapeHtml(__('Paid from')) ?>">
                    <i class="fas fa-fw fa-piggy-bank"></i>
                    <span><?= escapeHtml($row['account_name'] ?: __('No account')) ?><?= $row['expense_payment_method'] ? ' <span class="text-body-secondary">&middot; ' . escapeHtml($row['expense_payment_method']) . '</span>' : '' ?></span>
                </div>
            <?php } ?>
            <?php if ($row['expense_reference']) { ?>
                <div class="itflow-glance-item" title="<?= escapeHtml(__('Reference')) ?>">
                    <i class="fas fa-fw fa-hashtag"></i>
                    <span class="font-monospace text-break"><?= escapeHtml($row['expense_reference']) ?></span>
                </div>
            <?php } ?>
        </div>
        <div class="col-sm-6">
            <div class="itflow-glance-item" title="<?= escapeHtml(__('Client')) ?>">
                <i class="fas fa-fw fa-user"></i>
                <?php if ($client_id) { ?>
                    <a class="text-truncate" href="client_overview.php?client_id=<?= $client_id ?>"><?= escapeHtml($row['client_name']) ?></a>
                <?php } else { ?>
                    <span class="text-body-secondary"><?= __('Not for a client') ?></span>
                <?php } ?>
            </div>
            <div class="itflow-glance-item" title="<?= escapeHtml(__('Entered')) ?>">
                <i class="fas fa-fw fa-clock"></i>
                <span><span title="<?= escapeHtml($row['expense_created_at']) ?>"><?= timeAgo($row['expense_created_at']) ?></span><?php if ($row['created_by_name']) { ?> <span class="text-body-secondary">by <?= escapeHtml($row['created_by_name']) ?></span><?php } ?></span>
            </div>
            <?= reconcileGlanceHtml($cleared) ?>
            <?php if ($row['expense_recurring_expense_id']) { ?>
                <div class="itflow-glance-item" title="<?= escapeHtml(__('Recorded by')) ?>">
                    <i class="fas fa-fw fa-redo-alt"></i>
                    <span><?= __('Recorded by') ?> <?= $row['recurring_expense_frequency'] !== null
                        ? '<a href="#" class="ajax-modal" data-modal-size="lg" data-modal-url="modals/recurring_expense/recurring_expense.php?id=' . intval($row['expense_recurring_expense_id']) . '">' . escapeHtml(__('the %s recurring expense', mb_strtolower(recurringInvoiceFrequencies()[$row['recurring_expense_frequency']] ?? _x('Monthly', 'billing frequency')))) . '</a>'
                        : __('a recurring expense since deleted') ?></span>
                </div>
            <?php } ?>
            <?php if ($billed_on) { ?>
                <div class="itflow-glance-item" title="<?= escapeHtml(__('Billed')) ?>">
                    <i class="fas fa-fw fa-file-invoice-dollar"></i>
                    <span>
                        <?php if ($row['invoice_status'] === null) { ?>
                            <?= __('Billed on an invoice since deleted') ?>
                        <?php } else { ?>
                            <?= __('Billed on') ?> <?= $can_sales ? '<a href="invoice.php?invoice_id=' . $billed_on . '">' . escapeHtml($row['invoice_prefix'] . $row['invoice_number']) . '</a>' : escapeHtml($row['invoice_prefix'] . $row['invoice_number']) ?><?= $row['invoice_status'] === 'Cancelled' ? ' <span class="text-danger-emphasis">' . escapeHtml(__('(cancelled)')) . '</span>' : '' ?>
                            <?php if ($row['billed_subtotal'] !== null) { ?>
                                <?= escapeHtml(__('at %s', billingMoney($row['billed_subtotal'], $currency))) ?>
                                <span class="text-body-secondary">&middot; <?= escapeHtml(expenseMarginText($row['billed_subtotal'], $cost)) ?></span>
                            <?php } ?>
                        <?php } ?>
                    </span>
                </div>
            <?php } elseif ($billable) { ?>
                <div class="itflow-glance-item" title="<?= escapeHtml(__('To bill')) ?>">
                    <i class="fas fa-fw fa-file-invoice-dollar"></i>
                    <span>
                        <?php if ($cost <= 0) { ?>
                            <?= __('Billable, but refunded in full') ?>
                        <?php } else { ?>
                            <span class="text-warning-emphasis fw-semibold"><?= __('To bill') ?></span> at <?= escapeHtml(billingMoney($bill_price, $currency)) ?>
                            <span class="text-body-secondary">&middot; <?= escapeHtml(rtrim(rtrim(number_format(floatval($row['expense_markup']), 2, '.', ''), '0'), '.')) ?>% markup</span>
                            <?php if ($can_sales) { ?>
                                <span class="text-body-secondary">&middot;</span> <a href="unbilled_time.php?client_id=<?= $client_id ?>"><?= __('Unbilled Time') ?></a>
                            <?php } ?>
                        <?php } ?>
                    </span>
                </div>
            <?php } ?>
            <?php if ($row['expense_refund_of_id']) { ?>
                <div class="itflow-glance-item" title="<?= escapeHtml(__('Refund of')) ?>">
                    <i class="fas fa-fw fa-undo-alt"></i>
                    <span><?= __('Money back on') ?> <a href="#" class="ajax-modal" data-modal-size="lg" data-modal-url="modals/expense/expense.php?id=<?= intval($row['expense_refund_of_id']) ?>"><?= $row['refund_of_date'] !== null ? escapeHtml(__('%1$s from %2$s', billingMoney($row['refund_of_amount'], $currency), $row['refund_of_date'])) : escapeHtml(__('an expense since deleted')) ?></a></span>
                </div>
            <?php } ?>
            <?php if ($refunds) { ?>
                <div class="itflow-glance-item" title="<?= escapeHtml(__('Refunds')) ?>">
                    <i class="fas fa-fw fa-undo-alt"></i>
                    <span>
                        <span class="text-success-emphasis"><?= escapeHtml(billingMoney($row['refunded_amount'], $currency)) ?> back</span>
                        <span class="text-body-secondary">&middot;
                            <?php foreach ($refunds as $i => $refund) { ?><?= $i ? ', ' : '' ?><a href="#" class="ajax-modal" data-modal-size="lg" data-modal-url="modals/expense/expense.php?id=<?= intval($refund['expense_id']) ?>"><?= escapeHtml($refund['expense_date']) ?></a><?php } ?>
                        </span>
                    </span>
                </div>
            <?php } ?>
        </div>
    </div>

    <?php if (trim((string) $row['expense_description']) !== '') { ?>
        <div class="mt-3">
            <div class="small fw-semibold text-uppercase text-body-secondary mb-1"><?= __('Description') ?></div>
            <div class="text-break" style="white-space: pre-line;"><?= escapeHtml($row['expense_description']) ?></div>
        </div>
    <?php } ?>

    <?php if ($stock) { ?>
        <div class="mt-3">
            <div class="small fw-semibold text-uppercase text-body-secondary mb-1"><?= __('Stock it paid for') ?></div>
            <?php foreach ($stock as $move) { ?>
                <div class="d-flex justify-content-between gap-2 small py-1 border-bottom">
                    <a class="text-truncate" href="product.php?product_id=<?= intval($move['product_id']) ?>"><i class="fas fa-fw fa-box-open text-secondary me-1"></i><?= escapeHtml(rtrim(rtrim(number_format(floatval($move['stock_qty']), 2, '.', ''), '0'), '.') . ' × ' . $move['product_name']) ?></a>
                    <span class="text-body-secondary text-nowrap"><?= $move['stock_unit_cost'] !== null ? escapeHtml(__('%s each', billingMoney($move['stock_unit_cost'], $currency))) : '' ?></span>
                </div>
            <?php } ?>
        </div>
    <?php } ?>

    <!-- Its history, from the audit log -->
    <?= auditLogHistorySection(auditLogHistory('Expense', $expense_id, 10), ['client_id' => $client_id]) ?>

</div>

<div class="modal-footer">
    <?php if ($receipt_kind) { ?>
        <a class="btn btn-outline-secondary" href="<?= $receipt_url ?>"><i class="fas fa-download me-2"></i><?= __('Receipt') ?></a>
    <?php } ?>
    <?php if ($can_edit && !$is_transfer) { ?>
        <button type="button" class="btn btn-primary text-bold ajax-modal" data-modal-size="lg" data-modal-url="modals/expense/expense_edit.php?id=<?= $expense_id ?>"><i class="fas fa-edit me-2"></i><?= __('Edit') ?></button>
        <div class="btn-group">
            <button type="button" class="btn btn-outline-secondary ajax-modal" data-modal-size="lg" data-modal-url="modals/expense/expense_copy.php?id=<?= $expense_id ?>"><i class="fas fa-copy me-2"></i><?= __('Copy') ?></button>
            <?php if ($amount > 0) { ?>
                <button type="button" class="btn btn-outline-secondary ajax-modal" data-modal-size="lg" data-modal-url="modals/expense/expense_refund.php?id=<?= $expense_id ?>"><i class="fas fa-undo-alt me-2"></i><?= __('Refund') ?></button>
            <?php } ?>
        </div>
    <?php } ?>
    <?php if ($to_reimburse && $can_edit) { ?>
        <button type="button" class="btn btn-outline-success ajax-modal" data-modal-size="lg" data-modal-url="modals/expense/expense_reimburse.php?expense_ids[]=<?= $expense_id ?>"><i class="fas fa-hand-holding-usd"></i><span class="d-none d-sm-inline ms-2"><?= __('Reimburse') ?></span></button>
    <?php } ?>
    <?php if ($can_unbill) { ?>
        <a class="btn btn-outline-secondary confirm-link" href="post.php?expense_unbill=<?= $expense_id ?>&amp;csrf_token=<?= $_SESSION['csrf_token'] ?>"
            data-confirm-text="<?= escapeHtml($row['invoice_status'] === 'Cancelled' ? __('%s is cancelled, so its line stays on it and it waits to be billed again.', $row['invoice_prefix'] . $row['invoice_number']) : __('Its line comes off %s and it waits to be billed again.', $row['invoice_prefix'] . $row['invoice_number'])) ?>"><i class="fas fa-unlink"></i><span class="d-none d-sm-inline ms-2"><?= __('Take off Invoice') ?></span></a>
    <?php } ?>
    <?php if ($can_delete && !$is_transfer && $cleared) { ?>
        <?= reconcileLockedButtonHtml($cleared) ?>
    <?php } elseif ($can_delete && !$is_transfer) { ?>
        <a class="btn btn-outline-danger confirm-link" href="post.php?delete_expense=<?= $expense_id ?>&amp;csrf_token=<?= $_SESSION['csrf_token'] ?>"
            data-confirm-text="<?= escapeHtml(($row['expense_receipt'] ? __('%s goes for good, with its receipt.', expenseLabel($row)) : __('%s goes for good.', expenseLabel($row)))
                . ($billed_on && $row['invoice_status'] !== null ? ' ' . __('Its line stays on %s.', $row['invoice_prefix'] . $row['invoice_number']) : '')
                . ($mileage_trips ? ' ' . _n('Its trip is to pay back for again.', 'Its %d trips are to pay back for again.', $mileage_trips) : '')) ?>"><i class="fas fa-trash"></i><span class="d-none d-sm-inline ms-2"><?= __('Delete') ?></span></a>
    <?php } ?>
    <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fa fa-times me-2"></i><?= __('Close') ?></button>
</div>

<?php
require_once '../../../includes/modal_footer.php';
