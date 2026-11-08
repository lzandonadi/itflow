<?php

/*
 * ITFlow - the Income list's bulk actions and export. The list holds
 * payments and other income together, so the ticked rows come as
 * payment-12 / revenue-7 (incomeBulkIds()).
 *
 * Other income is changed with write access to Financial. So is a payment,
 * with Sales at Read (incomeCanEditPayments()) - but its category and client
 * are its invoice's: Set Category sets the invoice's, which takes write access
 * to Sales too, Set Client leaves payments alone, and payments are deleted
 * from their invoice, not here.
 */

if (!defined('FROM_POST_HANDLER')) {
    header("HTTP/1.1 401 Unauthorized");
    exit;
}

if (isset($_POST['bulk_edit_income'])) {

    validateCSRFToken();

    enforceUserPermission('module_financial', 2);

    $ids = incomeBulkIds($_POST['income_ids'] ?? []);
    $field = (string) ($_POST['field'] ?? '');
    $raw = (string) ($_POST['value'] ?? '');
    $value = intval($raw);
    // A payment's category is its invoice's - setting it changes the invoice
    $can_payments = incomeCanEditPayments() && ($field !== 'category' || lookupUserPermission('module_sales') >= 2);
    $label = '';
    if ($field === 'category') {
        $categories = incomeCategories();
        if (isset($categories[$value])) {
            $label = 'the category to ' . $categories[$value]['category_name']; // i18n-ignore - the audit log
            $shown = __('the category to %s', $categories[$value]['category_name']);
            $revenue_set = "revenue_category_id = $value";
        }
    } elseif ($field === 'account') {
        $accounts = paymentAccounts();
        if (isset($accounts[$value]) && !$accounts[$value]['account_archived_at']) {
            $label = 'the account to ' . $accounts[$value]['account_name']; // i18n-ignore - the audit log
            $shown = __('the account to %s', $accounts[$value]['account_name']);
            $revenue_set = "revenue_account_id = $value";
            $payment_set = "payment_account_id = $value";
        }
    } elseif ($field === 'method') {
        if (in_array($raw, paymentMethods('in'), true)) {
            $label = 'the payment method to ' . $raw; // i18n-ignore - the audit log
            $shown = __('the payment method to %s', $raw);
            $revenue_set = "revenue_payment_method = '" . escapeSql($raw) . "'";
            $payment_set = "payment_method = '" . escapeSql($raw) . "'";
        }
    } elseif ($field === 'client') {
        if (!$value) {
            $label = 'no client'; // i18n-ignore - the audit log
            $shown = __('no client');
            $revenue_set = 'revenue_client_id = 0';
        } elseif (mysqli_num_rows(mysqli_query($mysqli, "SELECT client_id FROM clients WHERE client_id = $value " . clientScopeSql('client_id')))) {
            $label = 'the client to ' . getFieldById('clients', $value, 'client_name'); // i18n-ignore - the audit log
            $shown = __('the client to %s', getFieldById('clients', $value, 'client_name'));
            $revenue_set = "revenue_client_id = $value";
        }
    }
    if ($label === '' || (!$ids['payment'] && !$ids['revenue'])) {
        flashAlert($ids['payment'] || $ids['revenue'] ? __('Pick what to set them to') : __('Tick the income to change first'), 'error');
        redirect();
    }
    $label_sql = escapeSql($label);

    // Other income - one cleared on a reconciled statement keeps its account
    $count = 0;
    $cleared = 0;
    foreach ($ids['revenue'] as $revenue_id) {
        $row = incomeRevenueRow($revenue_id);
        if (!$row) {
            continue;
        }
        if ($field === 'account' && !reconcileCheckChange('revenue', $revenue_id, null, null, $value, $cleared_error)) {
            $cleared++;
            continue;
        }
        if ($field === 'account') {
            transferDepositRelease('revenue', $revenue_id, $value);
        }
        mysqli_query($mysqli, "UPDATE revenues SET $revenue_set WHERE revenue_id = $revenue_id");
        logAudit("Revenue", "Edit", "$session_name set $label_sql on other income " . escapeSql(i18nEnglish(fn () => incomeRevenueLabel($row))), intval($row['revenue_client_id']), $revenue_id);
        $count++;
    }

    // Payments - their own account and method; their invoice's category; never their client
    $kept = 0;
    $invoices = 0;
    if ($field === 'client' || !$can_payments) {
        $kept = count($ids['payment']);
    } elseif ($field === 'category') {
        $invoice_rows = [];
        foreach ($ids['payment'] as $payment_id) {
            $row = incomePaymentRow($payment_id);
            if ($row && $row['invoice_id']) {
                $invoice_rows[intval($row['invoice_id'])] = $row;
                $count++;
            } elseif ($row) {
                $kept++;
            }
        }
        foreach ($invoice_rows as $invoice_id => $row) {
            mysqli_query($mysqli, "UPDATE invoices SET invoice_category_id = $value WHERE invoice_id = $invoice_id");
            logAudit("Invoice", "Edit", "$session_name set $label_sql on invoice " . escapeSql($row['invoice_prefix'] . $row['invoice_number']) . " from the Income list", intval($row['invoice_client_id']), $invoice_id);
            $invoices++;
        }
    } else {
        foreach ($ids['payment'] as $payment_id) {
            $row = incomePaymentRow($payment_id);
            if (!$row) {
                continue;
            }
            if ($field === 'account' && !reconcileCheckChange('payment', $payment_id, null, null, $value, $cleared_error)) {
                $cleared++;
                continue;
            }
            if ($field === 'account') {
                transferDepositRelease('payment', $payment_id, $value);
            }
            mysqli_query($mysqli, "UPDATE payments SET $payment_set WHERE payment_id = $payment_id");
            logAudit("Payment", "Edit", "$session_name set $label_sql on the payment " . escapeSql(i18nEnglish(fn () => incomePaymentLabel($row))), intval($row['invoice_client_id']), $payment_id);
            $count++;
        }
    }
    logAudit("Income", "Bulk Edit", "$session_name set $label_sql on $count income record" . ($count === 1 ? '' : 's'));

    $notes = [];
    if ($invoices) {
        $notes[] = _n('on the %d invoice behind the payments', 'on the %d invoices behind the payments', $invoices);
    }
    if ($kept) {
        $why = $field === 'client' ? __("a payment's client is its invoice's") : ($can_payments ? __('not on an invoice') : ($field === 'category' ? __("a payment's category is its invoice's, and changing the invoice takes write access to Sales") : __('changing a payment takes write access to Financial')));
        $notes[] = _n('%1$d payment left as it was - %2$s', '%1$d payments left as they were - %2$s', $kept, $kept, $why);
    }
    if ($cleared) {
        $notes[] = _n('%d left on its account - it cleared on a reconciled statement', '%d left on their accounts - they cleared on a reconciled statement', $cleared);
    }
    flashAlert(__('Set %1$s on %2$s', escapeHtml($shown ?? $label), "<strong>$count</strong>") . ($notes ? ' - ' . escapeHtml(implode('; ', $notes)) : ''), $kept || $cleared ? 'warning' : 'success');

    redirect();

}

if (isset($_POST['bulk_delete_income'])) {

    validateCSRFToken();

    enforceUserPermission('module_financial', 3);

    $ids = incomeBulkIds($_POST['income_ids'] ?? []);
    $count = 0;
    $cleared = 0;
    foreach ($ids['revenue'] as $revenue_id) {
        $row = incomeRevenueDelete($revenue_id, $delete_error);
        if (!$row) {
            $cleared += reconcileLock('revenue', $revenue_id) ? 1 : 0;
            continue;
        }
        logAudit("Revenue", "Delete", "$session_name deleted other income " . escapeSql(i18nEnglish(fn () => incomeRevenueLabel($row))), intval($row['revenue_client_id']));
        $count++;
    }
    logAudit("Income", "Bulk Delete", "$session_name deleted $count other income record" . ($count === 1 ? '' : 's'));

    $kept = count($ids['payment']);
    flashAlert(__('%s other income deleted', "<strong>$count</strong>") . ($kept ? _n(' - %d payment was kept; a payment is deleted from its invoice', ' - %d payments were kept; a payment is deleted from its invoice', $kept) : '')
        . ($cleared ? _n(' - %d kept, cleared on a reconciled statement', ' - %d kept, cleared on a reconciled statement', $cleared) : ''), 'error');

    redirect();

}

if (isExportRequest('export_income')) {

    validateCSRFToken();

    enforceUserPermission('module_financial');

    $format = resolveExportFormat($_POST['export_income']);

    // The same filters as the Income list, over the income this agent may see
    $src = $_POST;
    if (!empty($_POST['client_id'])) {
        $src['client'] = intval($_POST['client_id']);
    }
    $where = incomeFiltersSql($src, $filter_summary);
    $client_id = intval($src['client'] ?? 0);
    if ($client_id) {
        enforceClientAccess();
    }

    $sql = mysqli_query($mysqli, "SELECT income.* FROM (" . incomeUnionSql() . ") AS income
        WHERE $where
        ORDER BY income.income_date ASC, income.income_created_at ASC, income.income_type ASC, income.income_id ASC");

    $num_rows = mysqli_num_rows($sql);

    guardExportHasRows($num_rows, __('income entries'));

    if ($num_rows > 0) {

        guardExportPdfRowCount($format, $num_rows);

        $export = beginExport('income', $format, ($client_id ? getFieldById('clients', $client_id, 'client_name') : $session_company_name) . '-Income', __('Income'), summarizeExportFilters($filter_summary));

        while ($row = mysqli_fetch_assoc($sql)) {
            $is_payment = $row['income_type'] === 'payment';
            $type = fn () => $is_payment ? __('Invoice payment') : __('Other income');
            $row['income_type'] = $format === 'csv' ? i18nEnglish($type) : $type(); // CSV values stay English
            $row['income_invoice'] = $is_payment && $row['invoice_number'] !== null ? $row['invoice_prefix'] . $row['invoice_number'] : '';
            $row['income_source'] = trim(strtok((string) $row['income_description'], "\n"));
            $row['income_category'] = (string) $row['category_name'];
            $row['income_client'] = (string) $row['client_name'];
            $row['income_account'] = (string) $row['account_name'];
            $row['income_online'] = intval($row['income_online']) ? 'Yes' : '';
            addExportRow($export, $row);
        }

        finishExport($export);
    }

    logAudit("Income", "Export", "$session_name exported $num_rows income record(s) to a " . strtoupper($format) . " file", $client_id);

    exit;

}
