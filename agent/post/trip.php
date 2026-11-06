<?php

/*
 * ITFlow - trips (functions/trips.php does the work).
 *
 * New, Edit, Copy and Return Trip all post through tripSave(). Anyone logs
 * and changes their own trips; someone else's needs write access to
 * Financial, deleting them full access. Bulk Set Driver, Set Client, Set
 * Vehicle and Set Billable need write access, bulk delete full access - a
 * trip paid back for or billed is left as it was. Pay Mileage and taking a
 * trip off an invoice need write access to Financial (and Sales to take
 * one off).
 */

defined('FROM_POST_HANDLER') || die("Direct file access is not allowed");

if (isset($_POST['add_trip'])) {

    validateCSRFToken();

    $trip_id = tripSave($_POST, 0, $error);
    if (!$trip_id) {
        flashAlert(escapeHtml($error), 'error');
        redirect();
    }

    $row = tripRow($trip_id, false);
    logAudit("Trip", "Create", "$session_name logged the trip " . escapeSql(i18nEnglish(fn () => tripLabel($row))), intval($row['trip_client_id']), $trip_id);

    flashAlert(__('Trip to %1$s logged - %2$s', '<strong>' . escapeHtml($row['trip_destination']) . '</strong>', escapeHtml(tripMilesText($row['trip_miles']))));

    redirect();

}

if (isset($_POST['edit_trip'])) {

    validateCSRFToken();

    $trip_id = intval($_POST['trip_id'] ?? 0);
    if (!tripSave($_POST, $trip_id, $error)) {
        flashAlert(escapeHtml($error), 'error');
        redirect();
    }

    $row = tripRow($trip_id, false);
    logAudit("Trip", "Edit", "$session_name edited the trip " . escapeSql(i18nEnglish(fn () => tripLabel($row))), intval($row['trip_client_id']), $trip_id);

    flashAlert(__('Trip to %s saved', '<strong>' . escapeHtml($row['trip_destination']) . '</strong>'));

    redirect();

}

if (isset($_GET['delete_trip'])) {

    validateCSRFToken();

    $row = tripDelete(intval($_GET['delete_trip']), $error);
    if (!$row) {
        flashAlert(escapeHtml($error), 'error');
        redirect();
    }

    logAudit("Trip", "Delete", "$session_name deleted the trip " . escapeSql(i18nEnglish(fn () => tripLabel($row))), intval($row['trip_client_id']));

    flashAlert(__('Trip to %s deleted', '<strong>' . escapeHtml($row['trip_destination']) . '</strong>'), 'error');

    redirect();

}

/*
 * Set the driver (an agent), client (0 for none - the site and ticket go
 * with the old one), vehicle (1 their own, 0 a company one) or billable
 * (1 or 0) of the ticked trips. One paid back for keeps its driver and
 * vehicle, one billed its client and billable.
 */
if (isset($_POST['bulk_edit_trips'])) {

    validateCSRFToken();

    enforceUserPermission('module_financial', 2);

    $field = (string) ($_POST['field'] ?? '');
    $value = intval($_POST['value'] ?? 0);
    $set = '';
    $label = '';
    $skip = '';
    if ($field === 'driver') {
        $drivers = tripDrivers();
        if (isset($drivers[$value])) {
            $set = "trip_user_id = $value";
            $label = 'the driver to ' . $drivers[$value]['user_name']; // i18n-ignore - the audit log
            $shown = __('the driver to %s', $drivers[$value]['user_name']);
            $skip = 'trip_expense_id = 0';
        }
    } elseif ($field === 'client') {
        $skip = 'trip_invoice_id = 0';
        if (!$value) {
            $set = 'trip_client_id = 0, trip_location_id = 0, trip_ticket_id = 0, trip_billable = 0';
            $label = 'no client'; // i18n-ignore - the audit log
            $shown = __('no client');
        } elseif (mysqli_num_rows(mysqli_query($mysqli, "SELECT client_id FROM clients WHERE client_id = $value " . clientScopeSql('client_id')))) {
            // The site and ticket first - the client after, as MySQL sets them in order
            $set = "trip_location_id = IF(trip_client_id = $value, trip_location_id, 0), trip_ticket_id = IF(trip_client_id = $value, trip_ticket_id, 0), trip_client_id = $value";
            $label = 'the client to ' . getFieldById('clients', $value, 'client_name'); // i18n-ignore - the audit log
            $shown = __('the client to %s', getFieldById('clients', $value, 'client_name'));
        }
    } elseif ($field === 'vehicle') {
        $set = 'trip_own_vehicle = ' . ($value ? 1 : 0);
        $label = $value ? 'their own vehicle' : 'a company vehicle'; // i18n-ignore - the audit log
        $shown = $value ? __('their own vehicle') : __('a company vehicle');
        $skip = 'trip_expense_id = 0';
    } elseif ($field === 'billable') {
        $set = 'trip_billable = ' . ($value ? 'IF(trip_client_id > 0, 1, 0)' : 0);
        $label = $value ? 'billable' : 'not billable'; // i18n-ignore - the audit log
        $shown = $value ? __('billable') : __('not billable');
        $skip = 'trip_invoice_id = 0';
    }
    $trip_ids = array_unique(array_filter(array_map('intval', (array) ($_POST['trip_ids'] ?? []))));
    if ($set === '' || !$trip_ids) {
        flashAlert($trip_ids ? __('Pick what to set them to') : __('Tick the trips to change first'), 'error');
        redirect();
    }

    $count = 0;
    $kept = 0;
    foreach ($trip_ids as $trip_id) {
        $row = tripRow($trip_id);
        if (!$row) {
            continue;
        }
        mysqli_query($mysqli, "UPDATE trips SET $set WHERE trip_id = $trip_id" . ($skip !== '' ? " AND $skip" : ''));
        if ($skip !== '' && !mysqli_affected_rows($mysqli) && !mysqli_num_rows(mysqli_query($mysqli, "SELECT trip_id FROM trips WHERE trip_id = $trip_id AND $skip"))) {
            $kept++;
            continue;
        }
        logAudit("Trip", "Edit", "$session_name set " . escapeSql($label) . " on the trip " . escapeSql(i18nEnglish(fn () => tripLabel($row))), intval($row['trip_client_id']), $trip_id);
        $count++;
    }
    logAudit("Trip", "Bulk Edit", "$session_name set " . escapeSql($label) . " on $count trip" . ($count === 1 ? '' : 's')); // i18n-ignore - the audit log

    flashAlert(_n('Set %2$s on %1$s trip', 'Set %2$s on %1$s trips', $count, "<strong>$count</strong>", escapeHtml($shown ?? $label))
        . ($kept ? ($skip === 'trip_expense_id = 0' ? _n(' - %d was left as it was, paid back for already', ' - %d were left as they were, paid back for already', $kept) : _n(' - %d was left as it was, billed already', ' - %d were left as they were, billed already', $kept)) : ''), $kept ? 'warning' : 'success');

    redirect();

}

if (isset($_POST['bulk_delete_trips'])) {

    validateCSRFToken();

    enforceUserPermission('module_financial', 3);

    $count = 0;
    $kept = 0;
    foreach (array_unique(array_filter(array_map('intval', (array) ($_POST['trip_ids'] ?? [])))) as $trip_id) {
        $row = tripDelete($trip_id, $delete_error);
        if (!$row) {
            $kept += $delete_error !== '' && $delete_error !== __('That trip no longer exists, or is not one you can see') ? 1 : 0;
            continue;
        }
        logAudit("Trip", "Delete", "$session_name deleted the trip " . escapeSql(i18nEnglish(fn () => tripLabel($row))), intval($row['trip_client_id']));
        $count++;
    }
    logAudit("Trip", "Bulk Delete", "$session_name deleted $count trip" . ($count === 1 ? '' : 's')); // i18n-ignore - the audit log

    flashAlert(_n('%s trip deleted', '%s trips deleted', $count, "<strong>$count</strong>") . ($kept ? _n(' - %d paid back for or billed was kept', ' - %d paid back for or billed were kept', $kept) : ''), 'error');

    redirect();

}

/*
 * Pay Mileage - the driver's ticked trips in their own vehicle become a
 * mileage expense for each client, paid personally by them at the rate
 * typed; paid back from an account there and then when asked.
 */
if (isset($_POST['trip_claim'])) {

    validateCSRFToken();

    enforceUserPermission('module_financial', 2);

    $user_id = intval($_POST['user_id'] ?? 0);
    $result = tripClaim($user_id, array_unique(array_filter(array_map('intval', (array) ($_POST['trip_ids'] ?? [])))), [
        'rate' => (string) ($_POST['rate'] ?? ''),
        'date' => (string) ($_POST['date'] ?? ''),
        'by' => stripslashes($session_name),
    ], $error);
    if (!$result) {
        flashAlert(escapeHtml($error), 'error');
        redirect();
    }

    $what = _n("%2\$s's mileage - %1\$d trip, %3\$s at %4\$s: %5\$s", "%2\$s's mileage - %1\$d trips, %3\$s at %4\$s: %5\$s", $result['count'], $result['count'], '<strong>' . escapeHtml($result['user_name']) . '</strong>',
        escapeHtml(tripDistanceText($result['miles'])), escapeHtml(tripRateText($result['rate'])), '<strong>' . escapeHtml(billingMoney($result['amount'], $result['currency'])) . '</strong>');
    if (empty($_POST['pay_now'])) {
        flashAlert(__('%s to pay back - in To reimburse on the expenses', $what));
        redirect();
    }
    $paid = expenseReimburse($user_id, $result['expense_ids'], [
        'account_id' => intval($_POST['account'] ?? 0),
        'date' => (string) ($_POST['date'] ?? ''),
        'payment_method' => (string) ($_POST['payment_method'] ?? ''),
        'reference' => (string) ($_POST['reference'] ?? ''),
        'by' => stripslashes($session_name),
    ], $pay_error);
    if (!$paid) {
        flashAlert(__('%1$s to pay back - not paid back yet: %2$s', $what, escapeHtml($pay_error)), 'warning');
        redirect();
    }
    flashAlert(__('%s, paid back', $what));

    redirect();

}

/* Take a trip off the invoice it was billed on - it is to bill again. */
if (isset($_GET['trip_unbill'])) {

    validateCSRFToken();

    enforceUserPermission('module_financial', 2);
    enforceUserPermission('module_sales', 2);

    $trip_id = intval($_GET['trip_unbill']);
    $row = tripRow($trip_id);
    $number = $row ? tripUnbill($trip_id, $error) : '';
    if (!$row || $number === '') {
        flashAlert(escapeHtml($row ? $error : __('That trip no longer exists')), 'error');
        redirect();
    }

    logAudit("Trip", "Unbill", "$session_name took the trip " . escapeSql(i18nEnglish(fn () => tripLabel($row))) . " off invoice " . escapeSql($number), intval($row['trip_client_id']), $trip_id);
    if ($row['invoice_status'] !== null) {
        billingLog('invoice', intval($row['trip_invoice_id']), $row['invoice_status'], 'Trip taken off by ' . stripslashes($session_name) . ' - ' . i18nEnglish(fn () => tripLabel($row))); // i18n-ignore - invoice history stays English
    }

    flashAlert(__('Trip to %1$s taken off %2$s - it is to bill again', '<strong>' . escapeHtml($row['trip_destination']) . '</strong>', escapeHtml($number)));

    redirect();

}

/* An invoice's Add Trips - the client's ticked trips to bill, at the prices typed. */
if (isset($_POST['invoice_add_trips'])) {

    validateCSRFToken();

    enforceUserPermission('module_sales', 2);
    enforceUserPermission('module_financial', 2);

    $invoice_id = intval($_POST['invoice_id'] ?? 0);
    $invoice = billingRow('invoice', $invoice_id);
    if (!$invoice) {
        flashAlert(__('That invoice no longer exists.'), 'error');
        redirect();
    }
    $client_id = intval($invoice['client_id']);
    enforceClientAccess();

    $prices = [];
    foreach (array_unique(array_filter(array_map('intval', (array) ($_POST['trip_ids'] ?? [])))) as $trip_id) {
        $prices[$trip_id] = $_POST['trip_price'][$trip_id] ?? null;
    }
    $result = tripBillInvoice($client_id, $prices, [
        'invoice_id' => $invoice_id,
        'tax_id' => intval($_POST['tax_id'] ?? 0),
        'by' => stripslashes($session_name),
    ], $error);
    if (!$result) {
        flashAlert(escapeHtml($error), 'error');
        redirect("invoice.php?invoice_id=$invoice_id");
    }

    flashAlert(_n('%2$s billed - %3$s for %4$s', '%2$s billed - %3$s for %4$s', $result['trips'], $result['trips'], '<strong>' . escapeHtml(_n('%d trip', '%d trips', $result['trips'])) . '</strong>', escapeHtml(billingMoney($result['amount'], $invoice['invoice_currency_code'])), escapeHtml(tripDistanceText($result['miles']))));

    redirect("invoice.php?invoice_id=$invoice_id#items");

}

if (isExportRequest('export_trips')) {

    validateCSRFToken();

    $format = resolveExportFormat($_POST['export_trips']);

    // The same filters as the trips list, over the trips this agent may see
    $src = $_POST;
    if (!empty($_POST['client_id'])) {
        $src['client'] = intval($_POST['client_id']);
    }
    $where = tripFiltersSql($src, $filter_summary);
    $client_id = intval($src['client'] ?? 0);
    if ($client_id) {
        enforceClientAccess();
    }

    $sql = mysqli_query($mysqli, "SELECT trips.*, client_name, driver.user_name AS driver_name, location_name, ticket_prefix, ticket_number, ticket_subject,
            claim.expense_id AS claim_expense_id, reimbursement_date, invoice_prefix, invoice_number
        FROM trips " . tripJoinsSql() . "
        LEFT JOIN expenses AS claim ON trips.trip_expense_id > 0 AND claim.expense_id = trips.trip_expense_id
        LEFT JOIN reimbursements ON reimbursement_id = claim.expense_reimbursement_id
        LEFT JOIN invoices ON trips.trip_invoice_id > 0 AND invoice_id = trips.trip_invoice_id
        WHERE $where
        ORDER BY trips.trip_date ASC, trips.trip_id ASC");

    $num_rows = mysqli_num_rows($sql);

    guardExportHasRows($num_rows, __('trips'));

    if ($num_rows > 0) {

        guardExportPdfRowCount($format, $num_rows);

        $export = beginExport('trips', $format, ($client_id ? getFieldById('clients', $client_id, 'client_name') : $session_company_name) . '-Trips', __('Trips'), summarizeExportFilters($filter_summary));

        while ($row = mysqli_fetch_assoc($sql)) {
            $row['user_name'] = $row['driver_name'];
            // CSV values stay English, so the file reads the same in every language
            $labels = fn () => [
                intval($row['round_trip']) ? __('Yes') : __('No'),
                intval($row['trip_own_vehicle']) ? _x('Own', 'vehicle') : _x('Company', 'vehicle'),
                !intval($row['trip_own_vehicle']) ? '' : ($row['claim_expense_id'] === null ? __('To pay back') : ($row['reimbursement_date'] !== null ? __('Paid back %s', $row['reimbursement_date']) : __('On a mileage expense'))),
                $row['invoice_number'] !== null ? __('Billed on %s', $row['invoice_prefix'] . $row['invoice_number']) : (intval($row['trip_billable']) ? __('To bill') : ''),
            ];
            [$row['round_trip_display'], $row['vehicle_display'], $row['paid_back_display'], $row['billing_display']] = $format === 'csv' ? i18nEnglish($labels) : $labels();
            $row['trip_one_way'] = number_format(tripOneWay($row), 1, '.', '');
            $row['ticket_display'] = $row['ticket_number'] !== null ? ticketReference($row) . ' - ' . $row['ticket_subject'] : '';
            addExportRow($export, $row);
        }

        finishExport($export);
    }

    logAudit("Trip", "Export", "$session_name exported $num_rows trip(s) to a " . strtoupper($format) . " file", $client_id);

    exit;

}
