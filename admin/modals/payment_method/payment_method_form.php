<?php

/*
 * A payment method's fields - for payment_method_add.php and
 * payment_method_edit.php, which set $payment_method (a paymentMethodRow(),
 * or null when new). The server checks them again (paymentMethodFromInput(),
 * paymentMethodProblem()).
 */

isset($mysqli) || die("Direct file access is not allowed");

$m = $payment_method ?? null;
$m_name = (string) ($m['payment_method_name'] ?? '');
$m_use = $m ? intval($m['payment_method_use']) : 0;
$m_online = $m && paymentMethodIsOnline($m_name);
$m_usage = $m ? paymentMethodUsageFor($m_name) : ['total' => 0];

?>

<div class="mb-3">
    <label class="form-label"><?= __('Name') ?> <strong class="text-danger">*</strong></label>
    <div class="input-group">
        <span class="input-group-text"><i class="fa fa-fw fa-credit-card"></i></span>
        <input type="text" class="form-control" name="name" maxlength="200" placeholder="<?= escapeHtml(__('Check, ACH, Cash, Company Amex')) ?>" value="<?= escapeHtml($m_name) ?>" required<?= $m_online ? ' readonly' : ($m ? '' : ' autofocus') ?>>
    </div>
    <?php if ($m_online) { ?>
        <small class="text-secondary"><i class="fas fa-fw fa-lock me-1"></i><?= __('Card payments clients make online are recorded as %s, so its name stays.', escapeHtml(PAYMENT_METHOD_ONLINE)) ?></small>
    <?php } elseif ($m && $m_usage['total']) { ?>
        <small class="text-secondary"><?= __('Renaming it renames it on the %s that used it.', escapeHtml(paymentMethodUsageText($m_usage))) ?></small>
    <?php } ?>
</div>

<div class="mb-3">
    <label class="form-label"><?= __('Description') ?></label>
    <div class="input-group">
        <span class="input-group-text"><i class="fa fa-fw fa-align-left"></i></span>
        <input type="text" class="form-control" name="description" maxlength="250" placeholder="<?= escapeHtml(__('Which account, card or bank - what it\'s for')) ?>" value="<?= escapeHtml($m['payment_method_description'] ?? '') ?>">
    </div>
</div>

<div class="mb-1">
    <label class="form-label"><?= __('Offered for') ?></label>
    <div class="input-group">
        <span class="input-group-text"><i class="fa fa-fw fa-exchange-alt"></i></span>
        <select class="form-select" name="use">
            <?php foreach (PAYMENT_METHOD_USES as $value => $use) { ?>
                <option value="<?= $value ?>"<?= $value === $m_use ? ' selected' : '' ?>><?= escapeHtml(paymentMethodUseLabel($value)['label']) ?></option>
            <?php } ?>
        </select>
    </div>
    <small class="text-secondary"><?= __('Which pickers offer it - money in is payments from clients and other income; money out is expenses, recurring expenses, reimbursements and mileage claims. Transfers between accounts offer every method.') ?></small>
</div>
