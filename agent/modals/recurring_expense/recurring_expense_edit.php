<?php

/*
 * Edit a recurring expense - everything, and its schedule. Moving the next
 * date moves the day of the month it runs on with it. The expenses it has
 * already recorded don't change.
 */

require_once '../../../includes/modal_header.php';

enforceUserPermission('module_financial', 2);

$form_row = recurringExpenseRow(intval($_GET['id'] ?? 0));
if (!$form_row) {
    echo json_encode(['error' => __('That recurring expense no longer exists.')]);
    exit;
}

ob_start();

?>
<div class="modal-header bg-dark">
    <h5 class="modal-title text-truncate"><i class="fas fa-fw fa-edit me-2"></i><?= __('Edit Recurring Expense') ?> <span class="fw-normal text-white-50 ms-1"><?= escapeHtml(recurringExpenseLabel($form_row)) ?></span></h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <input type="hidden" name="recurring_expense_id" value="<?= intval($form_row['recurring_expense_id']) ?>">
    <div class="modal-body">
        <?php require __DIR__ . '/recurring_expense_form.php'; ?>
        <?php if (intval($form_row['made_count'])) { ?>
            <div class="form-text mt-3"><i class="fas fa-info-circle me-1"></i><?= escapeHtml(_n('The %d expense it has recorded keeps its amount - only the ones to come change.', 'The %d expenses it has recorded keep their amounts - only the ones to come change.', intval($form_row['made_count']))) ?></div>
        <?php } ?>
    </div>
    <div class="modal-footer">
        <button type="submit" name="edit_recurring_expense" class="btn btn-primary text-bold"><i class="fas fa-check me-2"></i><?= __('Save') ?></button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fas fa-times me-2"></i><?= __('Cancel') ?></button>
    </div>
</form>

<?php
require_once '../../../includes/modal_footer.php';
