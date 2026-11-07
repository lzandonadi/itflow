<?php

/*
 * Vendor templates (Admin > Vendor Templates) - making, changing (the
 * vendors made from it that still said what it did changing with it),
 * copying, archiving, restoring and deleting them, saying who should have
 * one, and taking them to and from a file (functions/vendor_template_transfer.php).
 * The work is in functions/vendor_template.php; a vendor is made from
 * one by add_vendor, add_vendor_from_template and
 * add_vendor_template_to_clients, and brought in line with one, linked to one
 * or unlinked by update_vendors_from_template, link_vendors_to_template and
 * unlink_vendor_template (agent/post/vendor.php).
 */

defined('FROM_POST_HANDLER') || die("Direct file access is not allowed");

/* The template a request is for - or back to the list, saying it is gone. */
function vendorTemplateForRequest($vendor_template_id) {
    $row = vendorTemplateInfo($vendor_template_id);
    if (!$row) {
        flashAlert(__('That vendor template no longer exists.'), 'error');
        redirect('vendor_templates.php');
    }
    return $row;
}

if (isset($_POST['add_vendor_template']) || isset($_POST['edit_vendor_template'])) {

    validateCSRFToken();

    $editing = isset($_POST['edit_vendor_template']);
    $before = $editing ? vendorTemplateForRequest($_POST['vendor_template_id'] ?? 0) : null;
    $vendor_template_id = $before ? intval($before['vendor_template_id']) : 0;

    $fields = vendorTemplateFields($_POST, $error);
    if (!$fields) {
        flashAlert(escapeHtml($error), 'error');
        redirect();
    }
    $vendor_template_id = vendorTemplateSave($vendor_template_id, $fields);
    $name = $fields['name'];

    if ($editing) {
        $changed = i18nEnglish(fn () => vendorTemplateChanged(vendorTemplateRowFields($before), $fields));
        $renamed = $before['vendor_template_name'] !== $name ? ' (was ' . $before['vendor_template_name'] . ')' : '';
        logAudit("Vendor Template", "Edit", "$session_name edited vendor template " . escapeSql($name . $renamed) . ($changed ? ' - ' . escapeSql(mb_strtolower(implode(', ', $changed))) : ''), 0, $vendor_template_id);

        // The vendors made from it that still said what it did follow it (vendorTemplateFollow())
        $followed = !empty($_POST['follow']) ? vendorTemplateFollow($vendor_template_id, vendorTemplateRowFields($before), $fields) : [];
        foreach ($followed as $vendor_id => $vendor) {
            logAudit("Vendor", "Edit", "$session_name updated vendor " . escapeSql($vendor['name']) . " from template " . escapeSql($name) . " - " . escapeSql(mb_strtolower(implode(', ', $vendor['fields']))), $vendor['client_id'], $vendor_id);
        }
        $out_of_step = $followed || !empty($_POST['follow']) ? (vendorTemplateOutOfStep([$vendor_template_id])[$vendor_template_id] ?? 0) : 0;

        flashAlert(__('Vendor template <strong>%s</strong> saved', escapeHtml($name)) . ($followed ? _n(', and %d vendor made from it changed with it', ', and %d vendors made from it changed with it', count($followed)) : '')
            . ($out_of_step ? __(' - %d out of step', $out_of_step) : ''));

        redirect();
    }

    // Made from one of a client's vendors - that vendor is now made from it
    $from_vendor = vendorRow($_POST['from_vendor'] ?? 0);
    if ($from_vendor) {
        mysqli_query($mysqli, "UPDATE vendors SET vendor_template_id = $vendor_template_id, vendor_updated_at = vendor_updated_at WHERE vendor_id = " . intval($from_vendor['vendor_id']));
    }

    logAudit("Vendor Template", "Create", "$session_name created vendor template " . escapeSql($name) . ($from_vendor ? " from vendor " . escapeSql($from_vendor['vendor_name']) . ($from_vendor['client_name'] !== null ? " of " . escapeSql($from_vendor['client_name']) : '') : ''), 0, $vendor_template_id);

    flashAlert(__('Vendor template <strong>%s</strong> created', escapeHtml($name)));

    // A new one opens on its page
    redirect("vendor_template.php?vendor_template_id=$vendor_template_id");

}

if (isset($_GET['copy_vendor_template'])) {

    validateCSRFToken();

    $row = vendorTemplateForRequest($_GET['copy_vendor_template']);
    $new_id = vendorTemplateCopy($row['vendor_template_id']);
    $new_name = (string) vendorTemplateInfo($new_id)['vendor_template_name'];

    logAudit("Vendor Template", "Create", "$session_name copied vendor template " . escapeSql($row['vendor_template_name']) . " as " . escapeSql($new_name), 0, $new_id);

    flashAlert(__('Vendor template <strong>%1$s</strong> copied as <strong>%2$s</strong>', escapeHtml($row['vendor_template_name']), escapeHtml($new_name)));

    redirect("vendor_template.php?vendor_template_id=$new_id");

}

if (isset($_GET['archive_vendor_template']) || isset($_GET['restore_vendor_template'])) {

    validateCSRFToken();

    $archive = isset($_GET['archive_vendor_template']);
    $row = vendorTemplateForRequest($archive ? $_GET['archive_vendor_template'] : $_GET['restore_vendor_template']);
    $vendor_template_id = intval($row['vendor_template_id']);

    // Archived, it isn't offered for new vendors; the ones made from it stay
    mysqli_query($mysqli, "UPDATE vendor_templates SET vendor_template_archived_at = " . ($archive ? 'NOW()' : 'NULL') . ", vendor_template_updated_at = vendor_template_updated_at
        WHERE vendor_template_id = $vendor_template_id");

    logAudit("Vendor Template", $archive ? "Archive" : "Restore", "$session_name " . ($archive ? 'archived' : 'restored') . " vendor template " . escapeSql($row['vendor_template_name']), 0, $vendor_template_id);

    flashAlert(($archive ? __('Vendor template <strong>%s</strong> archived', escapeHtml($row['vendor_template_name'])) : __('Vendor template <strong>%s</strong> restored', escapeHtml($row['vendor_template_name']))), $archive ? 'error' : 'success');

    redirect();

}

if (isset($_GET['delete_vendor_template'])) {

    validateCSRFToken();

    $row = vendorTemplateForRequest($_GET['delete_vendor_template']);

    // The vendors made from it stay, no longer linked to it
    vendorTemplateDelete($row['vendor_template_id']);

    logAudit("Vendor Template", "Delete", "$session_name deleted vendor template " . escapeSql($row['vendor_template_name']), 0, intval($row['vendor_template_id']));

    flashAlert(__('Vendor template <strong>%s</strong> deleted', escapeHtml($row['vendor_template_name'])), 'error');

    // From its own page, back to the list
    redirect(!empty($_GET['from_page']) ? 'vendor_templates.php' : null);

}

if (isset($_POST['save_vendor_template_required'])) {

    // Who should have a vendor from it
    validateCSRFToken();

    $row = vendorTemplateForRequest($_POST['vendor_template_id'] ?? 0);
    $vendor_template_id = intval($row['vendor_template_id']);
    $required = intval($_POST['required'] ?? 0);
    $tag_ids = array_map('intval', array_filter((array) ($_POST['tags'] ?? []), 'is_scalar'));
    if ($required === VENDOR_TEMPLATE_REQUIRED_TAGGED && !array_filter($tag_ids)) {
        flashAlert(__('Pick the client tags - or Every client.'), 'error');
        redirect();
    }
    vendorTemplateSetRequired($vendor_template_id, $required, $tag_ids);

    $saved = vendorTemplateInfo($vendor_template_id);
    $required_tags = vendorTemplateRequiredTags([$vendor_template_id])[$vendor_template_id];
    $said_in = function () use ($saved, $required_tags) {
        $scope = vendorTemplateScopeText($saved['vendor_template_required'], $required_tags);
        return $scope !== '' ? __('required of %s', lcfirst($scope)) : __('not required of anyone');
    };
    $said = i18nEnglish($said_in);

    logAudit("Vendor Template", "Edit", "$session_name set vendor template " . escapeSql($row['vendor_template_name']) . " " . escapeSql($said), 0, $vendor_template_id);

    flashAlert(__('<strong>%1$s</strong> is %2$s', escapeHtml($row['vendor_template_name']), escapeHtml($said_in())));

    redirect();

}

if (isset($_POST['export_vendor_templates']) || isset($_GET['export_vendor_template'])) {

    // A file of templates to import into another ITFlow - every current one, the ones picked, or one
    validateCSRFToken();

    if (isset($_GET['export_vendor_template'])) {
        $row = vendorTemplateForRequest($_GET['export_vendor_template']);
        $export = vendorTemplatesExport([intval($row['vendor_template_id'])]);
    } else {
        $export = vendorTemplatesExport((array) ($_POST['vendor_template_ids'] ?? []), !empty($_POST['archived']), !empty($_POST['manager']));
    }
    if (!$export['templates']) {
        flashAlert(__('There are no vendor templates to export.'), 'error');
        redirect();
    }

    $count = count($export['templates']);
    logAudit("Vendor Template", "Export", "$session_name exported $count vendor template" . ($count === 1 ? ' ' . escapeSql($export['templates'][0]['name']) : 's')
        . (!empty($_POST['manager']) ? ' with their account managers' : ''));

    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . vendorTemplatesExportFileName($export) . '"');
    header('Cache-Control: no-store');
    echo json_encode($export, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;

}

if (isset($_POST['import_vendor_templates'])) {

    // Templates from a file exported by ITFlow
    validateCSRFToken();

    $file = $_FILES['file'] ?? null;
    if ($file && in_array($file['error'], [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)) {
        flashAlert(__('That file is bigger than this server takes in an upload - export a few at a time.'), 'error');
        redirect('vendor_templates.php');
    }
    if (!$file || $file['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) {
        flashAlert(__('Pick the file to import.'), 'error');
        redirect('vendor_templates.php');
    }
    if ($file['size'] > 10 * 1024 * 1024) {
        flashAlert(__('That file is too big to be vendor templates.'), 'error');
        redirect('vendor_templates.php');
    }
    $templates = vendorTemplatesImportRead(file_get_contents($file['tmp_name']), $import_error);
    if ($templates === null) {
        flashAlert(escapeHtml($import_error), 'error');
        redirect('vendor_templates.php');
    }
    $existing = in_array($_POST['existing'] ?? '', ['skip', 'copy', 'replace'], true) ? $_POST['existing'] : 'skip';
    $counts = vendorTemplatesImport($templates, $existing);
    vendorTemplatesImportLogFollowed($counts);
    $followed = vendorTemplatesImportFollowedCount($counts);

    logAudit("Vendor Template", "Import", "$session_name imported vendor templates from " . escapeSql(mb_substr((string) $file['name'], 0, 100)) . " - {$counts['added']} added, {$counts['replaced']} replaced, {$counts['skipped']} skipped"
        . ($followed ? ", $followed vendors changed with them" : ''));

    flashAlert(vendorTemplatesImportSummary($counts), $counts['added'] || $counts['replaced'] ? 'success' : 'info');

    redirect('vendor_templates.php');

}
