<?php

/*
 * Delete the ticked expenses, with their receipts. Transfers between
 * accounts are left alone.
 */

require_once '../../../includes/modal_header.php';

enforceUserPermission('module_financial', 3);

$expense_ids = array_values(array_unique(array_filter(array_map('intval', (array) ($_GET['expense_ids'] ?? [])))));
$count = count($expense_ids);
if (!$count) {
    echo json_encode(['error' => __('Tick the expenses to delete first.')]);
    exit;
}

ob_start();

?>
<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fas fa-fw fa-trash me-2"></i><?= escapeHtml(_n('Delete %d Expense', 'Delete %d Expenses', $count)) ?></h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>

<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <?php foreach ($expense_ids as $expense_id) { ?>
        <input type="hidden" name="expense_ids[]" value="<?= $expense_id ?>">
    <?php } ?>
    <div class="modal-body">
        <div class="d-flex gap-3 align-items-start">
            <i class="fas fa-exclamation-triangle fa-2x text-danger"></i>
            <div>
                <div class="fw-semibold"><?= escapeHtml(_n('This expense goes for good, with its receipt.', 'These %d expenses go for good, with their receipts.', $count)) ?></div>
                <div class="text-body-secondary small mt-1"><?= __('They come out of the account balances and the reports. Stock deliveries that named them stay, unlinked. This can\'t be undone.') ?></div>
            </div>
        </div>
    </div>
    <div class="modal-footer">
        <button type="submit" name="bulk_delete_expenses" class="btn btn-danger text-bold"><i class="fas fa-trash me-2"></i><?= escapeHtml(__('Delete %d', $count)) ?></button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fas fa-times me-2"></i><?= __('Cancel') ?></button>
    </div>
</form>

<?php
require_once '../../../includes/modal_footer.php';
