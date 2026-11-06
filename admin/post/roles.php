<?php

/*
 * Roles (Admin > Roles) - making, changing, copying, archiving, restoring
 * and deleting them. The work is in functions/roles.php.
 */

defined('FROM_POST_HANDLER') || die("Direct file access is not allowed");

if (isset($_POST['add_role'])) {

    validateCSRFToken();

    [$fields, $error] = roleFromInput($_POST);
    if ($error !== '') {
        flashAlert(escapeHtml($error), 'error');
        redirect();
    }

    $role_id = roleSave($fields);

    // The audit log stays in English
    $access = i18nEnglish(fn () => $fields['admin'] ? __('an admin role') : (implode(', ', array_filter(array_map(function ($module_id) use ($fields) {
        $level = $fields['levels'][$module_id];
        return $level ? roleModules()[$module_id]['label'] . ' ' . ROLE_LEVELS[$level] : '';
    }, array_keys($fields['levels'])))) ?: 'no access'));
    logAudit("User Role", "Create", "$session_name created role " . escapeSql($fields['name']) . " - " . escapeSql($access), 0, $role_id);

    flashAlert(__('Role <strong>%s</strong> created', escapeHtml($fields['name'])));

    redirect();

}

if (isset($_POST['edit_role'])) {

    validateCSRFToken();

    $row = roleRow(intval($_POST['role_id'] ?? 0));
    if (!$row) {
        flashAlert(__('That role is gone - it may have been deleted'), 'error');
        redirect();
    }
    $role_id = intval($row['role_id']);

    [$fields, $error] = roleFromInput($_POST, $role_id);
    if ($error === '' && intval($row['role_is_admin']) && !$fields['admin'] && ($blocker = roleAdminBlocker($row)) !== '') {
        $error = __('%1$s stays an admin role: %2$s', $row['role_name'], $blocker);
    }
    if ($error !== '') {
        flashAlert(escapeHtml($error), 'error');
        redirect();
    }

    $changes = i18nEnglish(fn () => roleChangeText($row, $fields)); // the audit log stays in English
    roleSave($fields, $role_id);

    if ($changes !== '') {
        logAudit("User Role", "Edit", "$session_name edited role " . escapeSql($fields['name']) . " - " . escapeSql($changes), 0, $role_id);
    }

    flashAlert($changes !== '' ? __('Role <strong>%s</strong> saved', escapeHtml($fields['name'])) : __('Role <strong>%s</strong> unchanged', escapeHtml($fields['name'])));

    redirect();

}

if (isset($_GET['archive_role'])) {

    validateCSRFToken();

    $row = roleRow(intval($_GET['archive_role']));
    if (!$row) {
        flashAlert(__('That role is gone - it may have been deleted'), 'error');
        redirect();
    }
    if (($blocker = roleArchiveBlocker($row)) !== '') {
        flashAlert("<strong>" . escapeHtml($row['role_name']) . "</strong> wasn't archived: " . escapeHtml($blocker), 'error');
        redirect();
    }

    roleSetArchived($row['role_id'], true);

    logAudit("User Role", "Archive", "$session_name archived role " . escapeSql($row['role_name']), 0, $row['role_id']);

    flashAlert(__('Role <strong>%s</strong> archived', escapeHtml($row['role_name'])));

    redirect();

}

if (isset($_GET['restore_role'])) {

    validateCSRFToken();

    $row = roleRow(intval($_GET['restore_role']));
    if (!$row) {
        flashAlert(__('That role is gone - it may have been deleted'), 'error');
        redirect();
    }

    roleSetArchived($row['role_id'], false);

    logAudit("User Role", "Restore", "$session_name restored role " . escapeSql($row['role_name']), 0, $row['role_id']);

    flashAlert(__('Role <strong>%s</strong> restored', escapeHtml($row['role_name'])));

    redirect();

}

if (isset($_GET['delete_role'])) {

    validateCSRFToken();

    $row = roleRow(intval($_GET['delete_role']));
    if (!$row) {
        flashAlert(__('That role is gone - it may have been deleted'), 'error');
        redirect();
    }
    if (($blocker = roleDeleteBlocker($row)) !== '') {
        flashAlert("<strong>" . escapeHtml($row['role_name']) . "</strong> wasn't deleted: " . escapeHtml($blocker), 'error');
        redirect();
    }

    roleDelete($row['role_id']);

    logAudit("User Role", "Delete", "$session_name deleted role " . escapeSql($row['role_name']), 0, $row['role_id']);

    flashAlert(__('Role <strong>%s</strong> deleted', escapeHtml($row['role_name'])), 'error');

    redirect();

}
