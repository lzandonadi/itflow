<?php

/*
 * ITFlow - Admin > Payment Methods: add and change a method (a rename renames
 * it on every record that used it), make it the default for payments or
 * expenses, archive it and bring it back, delete it - merging its records
 * into another when it was used - and add the methods records use that
 * aren't in the list. The work is in functions/payment_method.php.
 */

defined('FROM_POST_HANDLER') || die("Direct file access is not allowed");

if (isset($_POST['add_payment_method']) || isset($_POST['edit_payment_method'])) {

    validateCSRFToken();

    $row = isset($_POST['edit_payment_method']) ? paymentMethodRow($_POST['payment_method_id'] ?? 0) : null;
    if (isset($_POST['edit_payment_method']) && !$row) {
        flashAlert(__('That payment method is gone - it may have been deleted.'), 'error');
        redirect();
    }

    $fields = paymentMethodFromInput($_POST, $row);
    $problem = paymentMethodProblem($fields, $row ? $row['payment_method_id'] : 0);
    if ($problem !== '') {
        flashAlert(__('%s The payment method was not saved.', escapeHtml($problem)), 'error');
        redirect();
    }

    if ($row) {
        $payment_method_id = intval($row['payment_method_id']);
        [$changes, $renamed] = paymentMethodUpdate($row, $fields);
        if ($changes) {
            logAudit("Payment Method", "Edit", "$session_name changed payment method " . escapeSql($row['payment_method_name']) . ": " . escapeSql(implode('; ', $changes)), 0, $payment_method_id);
        }
        flashAlert($renamed ? _n('Payment method <strong>%1$s</strong> saved - renamed on %2$d record', 'Payment method <strong>%1$s</strong> saved - renamed on %2$d records', $renamed, escapeHtml($fields['name']), $renamed) : __('Payment method <strong>%s</strong> saved', escapeHtml($fields['name'])));
    } else {
        $payment_method_id = paymentMethodCreate($fields);
        logAudit("Payment Method", "Create", "$session_name added payment method " . escapeSql($fields['name']) . " for " . strtolower(PAYMENT_METHOD_USES[$fields['use']]['short']), 0, $payment_method_id);
        flashAlert(__('Payment method <strong>%s</strong> added', escapeHtml($fields['name'])));
    }

    redirect();

}

if (isset($_GET['default_payment_method'])) {

    validateCSRFToken();

    $row = paymentMethodRow($_GET['default_payment_method']);
    $way = ($_GET['way'] ?? '') === 'out' ? 'out' : 'in';
    $use = $row ? intval($row['payment_method_use']) : 0;
    if (!$row || !empty($row['payment_method_archived_at']) || ($way === 'in' && $use === 2) || ($way === 'out' && $use === 1)) {
        flashAlert($way === 'in' ? __("That method can't be the default for payments.") : __("That method can't be the default for expenses."), 'error');
        redirect();
    }

    paymentMethodSetDefault($way, $row['payment_method_name']);
    logAudit("Settings", "Edit", "$session_name made " . escapeSql($row['payment_method_name']) . " the default payment method for " . ($way === 'in' ? 'payments' : 'expenses'), 0, intval($row['payment_method_id']));
    flashAlert(($way === 'in' ? __('<strong>%s</strong> is now picked to start with on a new payment', escapeHtml($row['payment_method_name'])) : __('<strong>%s</strong> is now picked to start with on a new expense', escapeHtml($row['payment_method_name']))));

    redirect();

}

if (isset($_GET['archive_payment_method']) || isset($_GET['restore_payment_method'])) {

    validateCSRFToken();

    $archive = isset($_GET['archive_payment_method']);
    $row = paymentMethodRow($archive ? $_GET['archive_payment_method'] : $_GET['restore_payment_method']);
    if ($row) {
        $cleared = paymentMethodSetArchived($row, $archive);
        $no_longer = $cleared ? ' - no longer the default for ' . implode(' or ', array_map(function ($way) { return $way === 'in' ? 'payments' : 'expenses'; }, $cleared)) : ''; // i18n-ignore - the audit log
        $no_longer_shown = $cleared ? (count($cleared) > 1 ? __(' - no longer the default for payments or expenses') : (reset($cleared) === 'in' ? __(' - no longer the default for payments') : __(' - no longer the default for expenses'))) : '';
        logAudit("Payment Method", $archive ? "Archive" : "Restore", "$session_name " . ($archive ? 'archived' : 'restored') . " payment method " . escapeSql($row['payment_method_name']) . $no_longer, 0, intval($row['payment_method_id']));
        flashAlert(($archive ? __('Payment method <strong>%1$s</strong> archived - it isn\'t offered any more%2$s', escapeHtml($row['payment_method_name']), $no_longer_shown) : __('Payment method <strong>%s</strong> restored', escapeHtml($row['payment_method_name']))));
    }

    redirect();

}

if (isset($_POST['delete_payment_method'])) {

    validateCSRFToken();

    $row = paymentMethodRow($_POST['payment_method_id'] ?? 0);
    if (!$row) {
        flashAlert(__('That payment method is gone - it may have been deleted.'), 'error');
        redirect();
    }

    $name = $row['payment_method_name'];
    $move_to = intval($_POST['move_to'] ?? 0);
    [$ok, $moved, $cleared] = paymentMethodDelete($row, $move_to);
    if (!$ok) {
        flashAlert(__('Pick the method its records change to. Nothing was deleted.'), 'error');
        redirect();
    }

    $to_name = $move_to && $moved ? (paymentMethodsAll()[$move_to]['payment_method_name'] ?? '') : '';
    logAudit("Payment Method", "Delete", "$session_name deleted payment method " . escapeSql($name) . ($moved ? ", changing $moved records to " . escapeSql($to_name) : ''), 0, intval($row['payment_method_id']));
    flashAlert($moved ? _n('Payment method <strong>%1$s</strong> deleted - %2$d record changed to <strong>%3$s</strong>', 'Payment method <strong>%1$s</strong> deleted - %2$d records changed to <strong>%3$s</strong>', $moved, escapeHtml($name), $moved, escapeHtml($to_name)) : __('Payment method <strong>%s</strong> deleted', escapeHtml($name)), 'error');

    redirect();

}

if (isset($_POST['add_payment_method_names'])) {

    validateCSRFToken();

    // Methods records use that aren't in the list - only those, whatever else is sent
    $added = paymentMethodAddNames((array) ($_POST['names'] ?? []));
    if ($added) {
        logAudit("Payment Method", "Create", "$session_name added $added payment methods already used on records");
        flashAlert(($added === 1 ? __('%s payment method added - for money in and out. Change what each is for if it is only one of them.', $added) : __('%s payment methods added - for money in and out. Change what each is for if it is only one of them.', $added)));
    }

    redirect();

}
