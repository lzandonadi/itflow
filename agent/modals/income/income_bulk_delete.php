<?php

/*
 * Delete the other income among the ticked Income rows. A payment is taken
 * off from its invoice, so ticked payments are left alone. Posts
 * bulk_delete_income with income_ids[].
 */

require_once '../../../includes/modal_header.php';

enforceUserPermission('module_financial', 3);

$ids = incomeBulkIds($_GET['income_ids'] ?? []);
$count = count($ids['revenue']);
$payments = count($ids['payment']);
if (!$count) {
    echo json_encode(['error' => $payments ? __('A payment is deleted from its invoice - tick other income to delete it here.') : __('Tick the income to delete first.')]);
    exit;
}
$total = [];
$sql = mysqli_query($mysqli, "SELECT revenue_currency_code, SUM(revenue_amount) FROM revenues WHERE revenue_id IN (" . implode(',', $ids['revenue']) . ") AND revenue_archived_at IS NULL
    AND " . incomeRevenueSql('revenue_id') . ' ' . clientScopeSql('revenue_client_id') . " GROUP BY revenue_currency_code");
while ($row = mysqli_fetch_row($sql)) {
    $total[] = billingMoney($row[1], $row[0]);
}

ob_start();

?>
<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fas fa-fw fa-trash me-2"></i><?= escapeHtml(__('Delete %d Other Income', $count)) ?></h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>

<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <?php foreach ($ids['revenue'] as $id) { ?>
        <input type="hidden" name="income_ids[]" value="revenue-<?= $id ?>">
    <?php } ?>
    <div class="modal-body">
        <div class="d-flex gap-3 align-items-start">
            <i class="fas fa-exclamation-triangle fa-2x text-danger"></i>
            <div>
                <div class="fw-semibold"><?= escapeHtml($total ? _n('This income goes for good - %2$s.', 'These %1$d go for good - %2$s.', $count, $count, implode(__(' and '), $total)) : _n('This income goes for good.', 'These %d go for good.', $count)) ?></div>
                <div class="text-body-secondary small mt-1"><?= __('It comes out of the account balances and the reports. This can\'t be undone.') ?></div>
                <?php if ($payments) { ?>
                    <div class="text-body-secondary small mt-1"><?= escapeHtml(_n('The payment ticked with it stays - a payment is deleted from its invoice.', 'The %d payments ticked with it stay - a payment is deleted from its invoice.', $payments)) ?></div>
                <?php } ?>
            </div>
        </div>
    </div>
    <div class="modal-footer">
        <button type="submit" name="bulk_delete_income" class="btn btn-danger text-bold"><i class="fas fa-trash me-2"></i><?= escapeHtml(__('Delete %d', $count)) ?></button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fas fa-times me-2"></i><?= __('Cancel') ?></button>
    </div>
</form>

<?php
require_once '../../../includes/modal_footer.php';
