<?php

/*
 * ITFlow - POST handler for the app logs: exporting them to CSV or PDF, and
 * clearing them, with the filters the export and clear windows post - the
 * same ones the list uses (appLogFiltersSql(), functions/app_log.php). Both
 * are in the audit log.
 */

defined('FROM_POST_HANDLER') || die("Direct file access is not allowed");

if (isExportRequest('export_app_logs')) {

    validateCSRFToken();

    enforceAdminPermission();

    $format = resolveExportFormat($_POST['export_app_logs']);

    $filter_summary = [];
    $where = appLogFiltersSql($_POST, $filter_summary);

    $num_rows = intval(mysqli_fetch_row(mysqli_query($mysqli, "SELECT COUNT(*) FROM app_logs WHERE $where"))[0]);

    if ($num_rows > 0) {

        guardExportPdfRowCount($format, $num_rows);

        $export = beginExport('app_logs', $format, "$session_company_name-App-Logs", __('App Logs'), summarizeExportFilters(appLogFiltersShown($filter_summary)));

        $sql = mysqli_query($mysqli, "SELECT app_log_id, app_log_category, app_log_type, app_log_details, app_log_created_at FROM app_logs
            WHERE $where
            ORDER BY app_log_created_at DESC, app_log_id DESC");
        while ($row = mysqli_fetch_assoc($sql)) {
            $row['app_log_type'] = i18nEnglish(fn () => appLogLevels()[appLogLevel($row['app_log_type'])]['one']);
            addExportRow($export, $row);
        }

        finishExport($export);

        logAudit("App Log", "Export", "$session_name exported $num_rows app log " . ($num_rows === 1 ? 'entry' : 'entries') . " to a " . strtoupper($format) . " file");

        exit;
    }

    flashAlert(__('No app log entries match - nothing to export.'), 'error');

    redirect();

}

if (isset($_POST['clear_app_logs'])) {

    validateCSRFToken();

    enforceAdminPermission();

    // The ones shown, with the list's filters - or every one
    $all = ($_POST['scope'] ?? '') !== 'shown';
    $summary = [];
    $where = $all ? '1 = 1' : i18nEnglish(function () use (&$summary) {
        return appLogFiltersSql($_POST, $summary);
    });
    if (!$all && !$summary) {
        $all = true;
    }

    mysqli_query($mysqli, "DELETE FROM app_logs WHERE $where");
    $cleared = mysqli_affected_rows($mysqli);

    if ($cleared > 0) {
        $what = i18nEnglish(fn () => lcfirst(appLogFiltersText($summary)));
        logAudit("App Log", "Delete", "$session_name cleared $cleared app log " . ($cleared === 1 ? 'entry' : 'entries') . ($all ? ' - all of them' : ' - ' . escapeSql($what))); // i18n-ignore - SQL or the audit log
        flashAlert(_n('Cleared %d app log entry', 'Cleared %d app log entries', $cleared));
    } else {
        flashAlert(__('There was nothing to clear.'), 'error');
    }

    redirect('app_logs.php');

}
