<?php

/*
 * Renew a contract - to a new last day, another term on from its end (or
 * from today, for one that ended a while ago). An ended or cancelled
 * contract is active again.
 */

require_once '../../../includes/modal_header.php';

enforceUserPermission('module_sales', 2);

$contract = contractRow(intval($_GET['id'] ?? 0));
if ($contract) {
    $client_id = intval($contract['contract_client_id']);
    enforceClientAccess();
}

ob_start();

if (!$contract || $contract['contract_status'] === 'Draft' || !$contract['contract_end_date']) { ?>
    <div class="modal-body text-center text-secondary py-5"><?= $contract ? __('Only a contract with an end date can be renewed - a draft is made active first.') : __('That contract is gone - it may have been deleted.') ?></div>
<?php } else {
    $end = (string) $contract['contract_end_date'];
    $from = $end >= date('Y-m-d', strtotime('-1 day')) ? $end : date('Y-m-d', strtotime('-1 day'));
    // Carried on from its last day, on the day of the month it started; from today, a term from today
    $new_end = contractNextEnd($from, $contract['contract_term_months'], $from === $end ? contractAnchorDay($contract) : 0); ?>

<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fas fa-fw fa-sync-alt me-2"></i><?= __('Renew <strong>%s</strong>', escapeHtml($contract['contract_name'])) ?></h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <input type="hidden" name="contract_id" value="<?= intval($contract['contract_id']) ?>">
    <div class="modal-body">
        <p class="mb-3">
            <?= contractStateHtml($contract) ?>
            <?= escapeHtml($end >= date('Y-m-d') ? __('It ends on %s.', formatDate($end, 'date')) : __('It ended on %s.', formatDate($end, 'date'))) ?>
            <?= in_array($contract['contract_status'], ['Ended', 'Cancelled'], true) ? __('Renewing makes it active again.') : '' ?>
        </p>
        <label class="form-label"><?= __('New last day') ?></label>
        <div class="input-group">
            <span class="input-group-text"><i class="fa fa-fw fa-calendar-check"></i></span>
            <input type="date" class="form-control" name="end_date" value="<?= escapeHtml($new_end) ?>" min="<?= escapeHtml(date('Y-m-d')) ?>" required>
        </div>
        <small class="text-secondary"><?= escapeHtml(__('Another %s. Its owner is reminded again before this one ends.', contractTermLabel($contract['contract_term_months']))) ?></small>
    </div>
    <div class="modal-footer">
        <button type="submit" name="renew_contract" class="btn btn-primary text-bold"><i class="fas fa-sync-alt me-2"></i><?= __('Renew') ?></button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fas fa-times me-2"></i><?= __('Cancel') ?></button>
    </div>
</form>

<?php }
require_once '../../../includes/modal_footer.php';
