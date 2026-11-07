<?php

/*
 * The recurring invoice that bills a contract - make one (the amount each
 * time, how often, the first invoice's day, its line, category and tax, and
 * whether each is emailed by itself), or link one of the client's. Either
 * way it runs while the contract is active, and is paused while it is a
 * draft and once it ends (functions/contract_billing.php).
 */

require_once '../../../includes/modal_header.php';

enforceUserPermission('module_sales', 2);

$contract = contractRow(intval($_GET['id'] ?? 0));
if ($contract) {
    $client_id = intval($contract['contract_client_id']);
    enforceClientAccess();
}

ob_start();

$refuse = '';
if (!$contract) {
    $refuse = __('That contract is gone - it may have been deleted.');
} elseif (!contractBillingReady()) {
    $refuse = __('Linking a recurring invoice needs the database update - run it from Maintenance > Update first.');
} elseif (intval($config_module_enable_accounting) !== 1) {
    $refuse = __('Recurring invoices need accounting turned on, in Admin > Settings > Modules.');
} elseif (!contractRecurringShouldRun($contract) && $contract['contract_status'] !== 'Draft') {
    $refuse = __('An ended contract isn\'t billed - renew it first.');
} elseif (contractRecurringRow($contract)) {
    $refuse = __('It already has a recurring invoice - unlink that one first.');
}

if ($refuse !== '') { ?>
    <div class="modal-body text-center text-secondary py-5"><?= escapeHtml($refuse) ?></div>
<?php } else {
    $contract_id = intval($contract['contract_id']);
    $choices = contractRecurringChoices($client_id, $contract_id);
    $draft = $contract['contract_status'] === 'Draft';
    $taxes = billingTaxes();
    $tab = 'contractRecurring' . bin2hex(random_bytes(3)); ?>

<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fas fa-fw fa-redo-alt me-2"></i><?= __('Recurring invoice for <strong>%s</strong>', escapeHtml($contract['contract_name'])) ?></h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
<div class="modal-body pb-0">
    <ul class="nav nav-pills nav-justified mb-3" role="tablist">
        <li class="nav-item"><a class="nav-link active" data-bs-toggle="pill" href="#<?= $tab ?>Make" role="tab"><i class="fas fa-fw fa-plus me-1"></i><?= __('Make one') ?></a></li>
        <li class="nav-item"><a class="nav-link<?= $choices ? '' : ' disabled' ?>" data-bs-toggle="pill" href="#<?= $tab ?>Link" role="tab"><i class="fas fa-fw fa-link me-1"></i><?= $choices ? escapeHtml(__('Link one (%d)', count($choices))) : __('Link one') ?></a></li>
    </ul>
    <div class="small text-body-secondary mb-3">
        <?= $draft ? __('It runs while the contract is active - until then it is paused, and is paused once the contract ends or its cancellation takes effect.') : __('It runs while the contract is active, and is paused once the contract ends or its cancellation takes effect.') ?>
    </div>
</div>
<div class="tab-content">
    <div class="tab-pane fade show active" id="<?= $tab ?>Make" role="tabpanel">
        <form action="post.php" method="post" autocomplete="off">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            <input type="hidden" name="contract_id" value="<?= $contract_id ?>">
            <div class="modal-body pt-0">
                <div class="row g-3 mb-3">
                    <div class="col-sm-6">
                        <label class="form-label"><?= __('Amount each time') ?> <strong class="text-danger">*</strong></label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fa fa-fw fa-dollar-sign"></i></span>
                            <input type="text" inputmode="decimal" class="form-control" name="amount" placeholder="1,500.00" required autofocus>
                        </div>
                    </div>
                    <div class="col-sm-6">
                        <label class="form-label"><?= __('How often') ?></label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fa fa-fw fa-sync-alt"></i></span>
                            <select class="form-select" name="frequency">
                                <?php foreach (recurringInvoiceFrequencies() as $key => $label) { ?>
                                    <option value="<?= $key ?>"<?= $key === 'month' ? ' selected' : '' ?>><?= escapeHtml($label) ?></option>
                                <?php } ?>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="row g-3 mb-3">
                    <div class="col-sm-6">
                        <label class="form-label"><?= __('First invoice') ?> <strong class="text-danger">*</strong></label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fa fa-fw fa-calendar-day"></i></span>
                            <input type="date" class="form-control" name="start" max="2999-12-31" value="<?= escapeHtml(contractRecurringFirstDate($contract)) ?>" required>
                        </div>
                    </div>
                    <div class="col-sm-6">
                        <label class="form-label"><?= __('Category') ?></label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fa fa-fw fa-tag"></i></span>
                            <select class="form-select" name="category">
                                <option value="0"><?= __('- Category -') ?></option>
                                <?= categoryOptionsHtml('Income') ?>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label"><?= __('Line') ?> <strong class="text-danger">*</strong></label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fa fa-fw fa-cube"></i></span>
                        <input type="text" class="form-control" name="item_name" maxlength="200" value="<?= escapeHtml($contract['contract_name']) ?>" required>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label"><?= __('Description') ?></label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fa fa-fw fa-align-left"></i></span>
                        <input type="text" class="form-control" name="description" maxlength="1000" value="<?= escapeHtml(contractTypeLabel($contract['contract_type'])) ?> - {period}">
                    </div>
                    <div class="form-text"><?= __('{month}, {quarter}, {year} and {period} become the dates each invoice covers.') ?></div>
                </div>
                <div class="row g-3 align-items-end">
                    <div class="col-sm-6">
                        <label class="form-label"><?= __('Tax') ?></label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fa fa-fw fa-percent"></i></span>
                            <select class="form-select" name="tax_id">
                                <?= taxOptionsHtml($taxes, taxDefaultFor($client_id, 'goods')) ?>
                            </select>
                        </div>
                    </div>
                    <div class="col-sm-6">
                        <div class="form-check form-switch mb-2">
                            <input class="form-check-input" type="checkbox" role="switch" name="auto_send" value="1" id="<?= $tab ?>AutoSend" checked>
                            <label class="form-check-label" for="<?= $tab ?>AutoSend"><?= __('Email each invoice by itself') ?></label>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="submit" name="make_contract_recurring" class="btn btn-primary text-bold"><i class="fas fa-check me-2"></i><?= __('Make it') ?></button>
                <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fas fa-times me-2"></i><?= __('Cancel') ?></button>
            </div>
        </form>
    </div>
    <div class="tab-pane fade" id="<?= $tab ?>Link" role="tabpanel">
        <form action="post.php" method="post" autocomplete="off">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            <input type="hidden" name="contract_id" value="<?= $contract_id ?>">
            <div class="modal-body pt-0">
                <label class="form-label"><?= __('Recurring invoice') ?></label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fa fa-fw fa-redo-alt"></i></span>
                    <select class="form-select" name="recurring_invoice_id" required>
                        <?php foreach ($choices as $choice_id => $choice) { ?>
                            <option value="<?= $choice_id ?>"><?= escapeHtml(billingNumber('recurring', $choice) . (trim((string) $choice['recurring_invoice_scope']) !== '' ? ' - ' . $choice['recurring_invoice_scope'] : '')
                                . ' (' . contractRecurringAmountText(['recurring_invoice_amount' => $choice['recurring_invoice_amount'], 'recurring_invoice_currency_code' => $session_company_currency, 'recurring_invoice_frequency' => $choice['recurring_invoice_frequency']]) . ', ' . (intval($choice['recurring_invoice_status']) ? _x('active', 'recurring invoice') : _x('paused', 'recurring invoice')) . ')') ?></option>
                        <?php } ?>
                    </select>
                </div>
                <div class="form-text"><?= escapeHtml(__('%s\'s recurring invoices that aren\'t another contract\'s.', $contract['client_name'])) ?> <?= $draft ? __('An active one is paused until the contract is active.') : __('A paused one starts now, its missed runs skipped.') ?></div>
            </div>
            <div class="modal-footer">
                <button type="submit" name="link_contract_recurring" class="btn btn-primary text-bold"><i class="fas fa-link me-2"></i><?= __('Link it') ?></button>
                <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fas fa-times me-2"></i><?= __('Cancel') ?></button>
            </div>
        </form>
    </div>
</div>

<?php }
require_once '../../../includes/modal_footer.php';
