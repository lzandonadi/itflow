<?php

/*
 * ITFlow - GET/POST request handler for expenses
 *
 * New, Edit, Copy and Refund all post through expenseSave() in
 * functions/expenses.php, with the receipt as `file`. The checks there keep
 * an expense to one of our vendors, an expense category, an account and a
 * client the agent can see.
 */

defined('FROM_POST_HANDLER') || die("Direct file access is not allowed");

if (isset($_POST['add_expense'])) {

    validateCSRFToken();

    enforceUserPermission('module_financial', 2);

    $expense_id = expenseSave($_POST, 0, $error);
    if (!$expense_id) {
        flashAlert($error, 'error');
        redirect();
    }

    $receipt_error = '';
    expenseReceiptStore($expense_id, $_FILES['file'] ?? null, $receipt_error);

    $row = expenseRow($expense_id, false);
    $label = expenseLabel($row);
    $label_log = i18nEnglish(fn () => expenseLabel($row));
    $refund = intval($row['expense_refund_of_id']) > 0;

    logAudit("Expense", $refund ? "Refund" : "Create", "$session_name " . ($refund ? 'recorded a refund from ' : 'added the expense ') . escapeSql($label_log), intval($row['expense_client_id']), $expense_id); // i18n-ignore - the audit log

    if ($receipt_error) {
        flashAlert(($refund ? __('Refund %1$s added, but %2$s', '<strong>' . escapeHtml($label) . '</strong>', lcfirst(escapeHtml($receipt_error))) : __('Expense %1$s added, but %2$s', '<strong>' . escapeHtml($label) . '</strong>', lcfirst(escapeHtml($receipt_error)))), 'warning');
    } else {
        $strong = '<strong>' . escapeHtml($label) . '</strong>';
    flashAlert($row['expense_receipt'] ? ($refund ? __('Refund %s added with its receipt', $strong) : __('Expense %s added with its receipt', $strong)) : ($refund ? __('Refund %s added', $strong) : __('Expense %s added', $strong)));
    }

    redirect();

}

if (isset($_POST['edit_expense'])) {

    validateCSRFToken();

    enforceUserPermission('module_financial', 2);

    $expense_id = intval($_POST['expense_id'] ?? 0);

    if (!expenseSave($_POST, $expense_id, $error)) {
        flashAlert($error, 'error');
        redirect();
    }

    $receipt_error = '';
    if (!empty($_POST['receipt_remove'])) {
        expenseReceiptRemove($expense_id);
    } else {
        expenseReceiptStore($expense_id, $_FILES['file'] ?? null, $receipt_error);
    }

    $row = expenseRow($expense_id, false);
    $label = expenseLabel($row);

    logAudit("Expense", "Edit", "$session_name edited the expense " . escapeSql(i18nEnglish(fn () => expenseLabel($row))), intval($row['expense_client_id']), $expense_id);

    if ($receipt_error) {
        flashAlert(__('Expense %1$s saved, but %2$s', '<strong>' . escapeHtml($label) . '</strong>', lcfirst(escapeHtml($receipt_error))), 'warning');
    } else {
        flashAlert(__('Expense %s saved', '<strong>' . escapeHtml($label) . '</strong>'));
    }

    redirect();

}

// A receipt added from the expense's quick look
if (isset($_POST['expense_receipt_save'])) {

    validateCSRFToken();

    enforceUserPermission('module_financial', 2);

    $row = expenseRow(intval($_POST['expense_id'] ?? 0));
    if (!$row || $row['transfer_id']) {
        flashAlert(__('That expense no longer exists'), 'error');
        redirect();
    }
    $expense_id = intval($row['expense_id']);

    $receipt_error = '';
    if (!expenseReceiptStore($expense_id, $_FILES['file'] ?? null, $receipt_error)) {
        flashAlert($receipt_error ?: __('Choose the receipt to attach'), 'error');
        redirect();
    }

    logAudit("Expense", "Receipt", "$session_name attached a receipt to the expense " . escapeSql(i18nEnglish(fn () => expenseLabel($row))), intval($row['expense_client_id']), $expense_id);

    flashAlert(__('Receipt attached to %s', '<strong>' . escapeHtml(expenseLabel($row)) . '</strong>'));

    redirect();

}

if (isset($_GET['delete_expense'])) {

    validateCSRFToken();

    enforceUserPermission('module_financial', 3);

    $row = expenseDelete(intval($_GET['delete_expense']), $error);
    if (!$row) {
        flashAlert($error, 'error');
        redirect();
    }

    logAudit("Expense", "Delete", "$session_name deleted the expense " . escapeSql(i18nEnglish(fn () => expenseLabel($row))), intval($row['expense_client_id']));

    flashAlert(__('Expense %s deleted', '<strong>' . escapeHtml(expenseLabel($row)) . '</strong>'), 'error');

    redirect();

}

/*
 * Set one thing on the ticked expenses: field = category, account, client
 * (0 takes the client off), method ('' clears it) or billable (yes, with
 * markup, or no), to value. Expenses already billed on an invoice keep
 * their client and stay billed.
 */
if (isset($_POST['bulk_edit_expenses'])) {

    validateCSRFToken();

    enforceUserPermission('module_financial', 2);

    $field = (string) ($_POST['field'] ?? '');
    $value = trim((string) ($_POST['value'] ?? ''));
    $expense_ids = array_unique(array_filter(array_map('intval', (array) ($_POST['expense_ids'] ?? []))));

    // What the value is, and that it can be picked
    $set = '';
    $label = '';
    if ($field === 'category') {
        $categories = expenseCategories();
        if (isset($categories[intval($value)])) {
            $set = "expense_category_id = " . intval($value);
            $label = "the category to " . $categories[intval($value)]['category_name']; // i18n-ignore - the audit log
            $shown = __('the category to %s', $categories[intval($value)]['category_name']);
        }
    } elseif ($field === 'account') {
        $accounts = paymentAccounts();
        if (isset($accounts[intval($value)])) {
            $set = "expense_account_id = " . intval($value) . ", expense_paid_by = 0";
            $label = "the account to " . $accounts[intval($value)]['account_name']; // i18n-ignore - the audit log
            $shown = __('the account to %s', $accounts[intval($value)]['account_name']);
        }
    } elseif ($field === 'client') {
        $client_name = intval($value) ? getFieldById('clients', intval($value), 'client_name') : '';
        if (!intval($value)) {
            $set = "expense_client_id = 0";
            $label = "no client"; // i18n-ignore - the audit log
            $shown = __('no client');
        } elseif ($client_name !== null && mysqli_num_rows(mysqli_query($mysqli, "SELECT client_id FROM clients WHERE client_id = " . intval($value) . " " . clientScopeSql('client_id')))) {
            $set = "expense_client_id = " . intval($value);
            $label = "the client to $client_name"; // i18n-ignore - the audit log
            $shown = __('the client to %s', $client_name);
        }
        if (!intval($value)) {
            $set .= ", expense_billable = 0";
        }
    } elseif ($field === 'billable') {
        $markup = billingNumberInput($_POST['markup'] ?? '');
        if ($value === 'no') {
            $set = "expense_billable = 0";
            $label = 'them not to bill on'; // i18n-ignore - the audit log
            $shown = __('them not to bill on');
        } elseif ($value === 'yes' && $markup !== null && $markup >= 0 && $markup <= 1000) {
            $set = "expense_billable = 1, expense_markup = $markup";
            $label = "them to bill on at " . rtrim(rtrim(number_format($markup, 2, '.', ''), '0'), '.') . "% markup"; // i18n-ignore - the audit log
            $shown = __('them to bill on at %s%% markup', rtrim(rtrim(number_format($markup, 2, '.', ''), '0'), '.'));
        } elseif ($value === 'yes') {
            flashAlert(__('The markup is a percent, from 0 to 1000'), 'error');
            redirect();
        }
    } elseif ($field === 'method') {
        if ($value === '') {
            $set = "expense_payment_method = NULL";
            $label = 'no payment method'; // i18n-ignore - the audit log
            $shown = __('no payment method');
        } elseif (in_array($value, paymentMethods('out'), true)) {
            $set = "expense_payment_method = '" . escapeSql($value) . "'";
            $label = "the payment method to $value"; // i18n-ignore - the audit log
            $shown = __('the payment method to %s', $value);
        }
    }
    if ($set === '' || !$expense_ids) {
        flashAlert($expense_ids ? __('Pick what to set them to') : __('Tick the expenses to change first'), 'error');
        redirect();
    }

    $count = 0;
    $skipped = 0;
    $cleared = 0;
    foreach ($expense_ids as $expense_id) {
        $row = expenseRow($expense_id);
        if (!$row || $row['transfer_id'] || !intval($row['expense_vendor_id'])) {
            continue;
        }
        // Cleared on a reconciled statement - it stays on its account
        if ($field === 'account' && !reconcileCheckChange('expense', $expense_id, null, null, intval($value), $cleared_error)) {
            $cleared++;
            continue;
        }
        // Billed ones keep their client and stay billed; only a client's expenses can be billed on
        if (($row['expense_invoice_id'] && in_array($field, ['client', 'billable'], true))
            || ($row['expense_reimbursement_id'] && $field === 'account')
            || ($field === 'billable' && $value === 'yes' && (!intval($row['expense_client_id']) || floatval($row['expense_amount']) <= 0))) {
            $skipped++;
            continue;
        }
        mysqli_query($mysqli, "UPDATE expenses SET $set WHERE expense_id = $expense_id");
        logAudit("Expense", "Edit", "$session_name set " . escapeSql($label) . " on the expense " . escapeSql(i18nEnglish(fn () => expenseLabel($row))), intval($row['expense_client_id']), $expense_id);
        $count++;
    }

    logAudit("Expense", "Bulk Edit", "$session_name set " . escapeSql($label) . " on $count expense" . ($count === 1 ? '' : 's')); // i18n-ignore - the audit log

    $why = $field === 'billable' && $value === 'yes' ? __('on an invoice already, or not for a client') : ($field === 'account' ? __('paid back to an agent already') : __('on an invoice already'));
    flashAlert(_n('Set %2$s on %1$s expense', 'Set %2$s on %1$s expenses', $count, "<strong>$count</strong>", escapeHtml($shown ?? $label))
        . ($skipped ? _n(' - %1$d left as it was (%2$s)', ' - %1$d left as they were (%2$s)', $skipped, $skipped, $why) : '')
        . ($cleared ? _n(' - %d left on its account (it cleared on a reconciled statement)', ' - %d left on their accounts (they cleared on a reconciled statement)', $cleared) : ''),
        $count && !$cleared ? 'success' : 'warning');

    redirect();

}

/*
 * Take a billed expense off its invoice - the line comes off and it waits
 * to be billed again. Not once the invoice is paid or cancelled.
 */
if (isset($_GET['expense_unbill'])) {

    validateCSRFToken();

    enforceUserPermission('module_financial', 2);
    enforceUserPermission('module_sales', 2);

    $expense_id = intval($_GET['expense_unbill']);
    $row = expenseRow($expense_id);
    $number = $row ? expenseUnbill($expense_id, $error) : '';
    if (!$row || $number === '') {
        flashAlert($row ? $error : __('That expense no longer exists'), 'error');
        redirect();
    }

    logAudit("Expense", "Unbill", "$session_name took the expense " . escapeSql(i18nEnglish(fn () => expenseLabel($row))) . " off invoice " . escapeSql($number), intval($row['expense_client_id']), $expense_id);
    if ($row['invoice_status'] !== null) {
        billingLog('invoice', intval($row['expense_invoice_id']), $row['invoice_status'], 'Expense taken off by ' . stripslashes($session_name) . ' - ' . i18nEnglish(fn () => expenseLabel($row))); // i18n-ignore - invoice history stays English
    }

    flashAlert(__('Expense %1$s taken off %2$s - it is to bill again', '<strong>' . escapeHtml(expenseLabel($row)) . '</strong>', escapeHtml($number)));

    redirect();

}

if (isset($_POST['bulk_delete_expenses'])) {

    validateCSRFToken();

    enforceUserPermission('module_financial', 3);

    $count = 0;
    $cleared = 0;
    foreach (array_unique(array_filter(array_map('intval', (array) ($_POST['expense_ids'] ?? [])))) as $expense_id) {
        $row = expenseDelete($expense_id, $delete_error);
        if (!$row) {
            $cleared += reconcileLock('expense', $expense_id) ? 1 : 0;
            continue;
        }
        logAudit("Expense", "Delete", "$session_name deleted the expense " . escapeSql(i18nEnglish(fn () => expenseLabel($row))), intval($row['expense_client_id']));
        $count++;
    }

    logAudit("Expense", "Bulk Delete", "$session_name deleted $count expense" . ($count === 1 ? '' : 's'));

    flashAlert(_n('Deleted %s expense', 'Deleted %s expenses', $count, "<strong>$count</strong>") . ($cleared ? _n(' - %d kept, cleared on a reconciled statement', ' - %d kept, cleared on a reconciled statement', $cleared) : ''), 'error');

    redirect();

}

/*
 * Pay an agent back for what they paid personally - the ticked expenses
 * (expense_ids[]) of one agent (user_id), from an account, on a date.
 */
if (isset($_POST['expense_reimburse'])) {

    validateCSRFToken();

    enforceUserPermission('module_financial', 2);

    $user_id = intval($_POST['user_id'] ?? 0);
    $result = expenseReimburse($user_id, array_unique(array_filter(array_map('intval', (array) ($_POST['expense_ids'] ?? [])))), [
        'account_id' => intval($_POST['account'] ?? 0),
        'date' => (string) ($_POST['date'] ?? ''),
        'payment_method' => (string) ($_POST['payment_method'] ?? ''),
        'reference' => (string) ($_POST['reference'] ?? ''),
        'by' => stripslashes($session_name),
    ], $error);
    if (!$result) {
        flashAlert(escapeHtml($error), 'error');
        redirect();
    }

    flashAlert(_n('%2$s paid back %3$s for %1$d expense', '%2$s paid back %3$s for %1$d expenses', $result['count'], $result['count'], '<strong>' . escapeHtml($result['user_name']) . '</strong>', escapeHtml(billingMoney($result['amount'], $result['currency']))));

    redirect();

}

/* Undo a reimbursement - its money out of the account goes, and the expenses are to reimburse again. */
if (isset($_GET['expense_reimbursement_delete'])) {

    validateCSRFToken();

    enforceUserPermission('module_financial', 3);

    $row = reimbursementDelete(intval($_GET['expense_reimbursement_delete']), $error);
    if (!$row) {
        flashAlert(escapeHtml($error), 'error');
        redirect();
    }

    $what = billingMoney($row['reimbursement_amount'], $row['reimbursement_currency_code']) . ' paid back to ' . $row['user_name'] . ' on ' . $row['reimbursement_date']; // i18n-ignore - the audit log
    logAudit("Expense", "Reimbursement Undo", "$session_name undid the " . escapeSql($what));

    flashAlert(__('Undone: %s - its expenses are to reimburse again', '<strong>' . escapeHtml($what) . '</strong>'), 'error');

    redirect();

}

if (isExportRequest('export_expenses')) {

    validateCSRFToken();

    // Exports are reads - see CONTRIBUTING.md
    enforceUserPermission('module_financial');

    $format = resolveExportFormat($_POST['export_expenses']);

    // The same filters as the expenses list (functions/expenses.php)
    $where = expenseFiltersSql($_POST, $filter_summary);

    $sql = mysqli_query($mysqli, "SELECT expenses.*, vendor_name, category_name, account_name, client_name, user_name, invoice_prefix, invoice_number,
            (SELECT payer.user_name FROM users AS payer WHERE payer.user_id = expense_paid_by) AS paid_by_name,
            (SELECT reimbursement_date FROM reimbursements WHERE reimbursement_id = expense_reimbursement_id) AS reimbursement_date
        FROM expenses " . expenseJoinsSql() . "
        LEFT JOIN invoices ON invoice_id = expense_invoice_id
        WHERE $where
        ORDER BY expense_date ASC, expense_id ASC");

    $num_rows = mysqli_num_rows($sql);

    guardExportHasRows($num_rows, __('expenses'));

    if ($num_rows > 0) {

        guardExportPdfRowCount($format, $num_rows);

        $export = beginExport('expenses', $format, "$session_company_name-Expenses", __('Expenses'), summarizeExportFilters($filter_summary));

        while ($row = mysqli_fetch_assoc($sql)) {
            $row['expense_receipt_display'] = $row['expense_receipt'] ? 'Yes' : 'No';
            $row['expense_tax_rate'] = intval($row['expense_tax_id']) ? taxLabel(taxRow($row['expense_tax_id'])) : (floatval($row['expense_tax_amount']) != 0 ? 'Other' : '');
            $row['expense_before_tax'] = round(floatval($row['expense_amount']) - floatval($row['expense_tax_amount']), 2);
            $markup = rtrim(rtrim(number_format(floatval($row['expense_markup']), 2, '.', ''), '0'), '.') . '%';
            $row['expense_billing_display'] = $row['expense_invoice_id'] ? "Billed, $markup markup" : (intval($row['expense_billable']) ? "To bill, $markup markup" : ''); // i18n-ignore - CSV values stay English
            $row['expense_billed_on'] = $row['expense_invoice_id'] && $row['invoice_number'] !== null ? $row['invoice_prefix'] . $row['invoice_number'] : '';
            $row['expense_paid_by_display'] = intval($row['expense_paid_by']) ? ($row['paid_by_name'] ?: __('An agent')) . ($row['reimbursement_date'] ? ', paid back ' . $row['reimbursement_date'] : ', to reimburse') : '';
            addExportRow($export, $row);
        }

        finishExport($export);
    }

    logAudit("Expense", "Export", "$session_name exported $num_rows expense(s) to a " . strtoupper($format) . " file");

    exit;

}
