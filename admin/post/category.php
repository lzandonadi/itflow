<?php

/*
 * Categories (Admin > Categories) - making, changing, ordering, merging,
 * archiving and deleting them. The work is in functions/categories.php.
 */

defined('FROM_POST_HANDLER') || die("Direct file access is not allowed");

// "asset status" - what a category is, for the audit log and messages
$category_kind_word = function ($row) {
    return categoryKind($row['category_type'] ?? '')['one'] ?? __('category');
};
// The same in English, for the audit log
$category_kind_en = function ($row) use ($category_kind_word) {
    return i18nEnglish(fn () => $category_kind_word($row));
};

// Admin > Categories on a kind, to go back to after making one
$category_page = function ($kind, $archived = false) {
    return 'categories.php?category=' . urlencode($kind) . ($archived ? '&show=archived' : '');
};

if (isset($_POST['add_category'])) {

    validateCSRFToken();

    // From the + beside a picker in a form - answered with JSON, the picker's new option included (js/category_form.js)
    $inline = !empty($_POST['inline']);

    [$fields, $error] = categoryFromInput($_POST);
    if ($error !== '') {
        if ($inline) {
            header('Content-Type: application/json');
            echo json_encode(['ok' => false, 'error' => $error]);
            exit;
        }
        flashAlert(escapeHtml($error), 'error');
        redirect();
    }

    $category_id = categoryCreate($fields);
    $kind = $category_kind_word(['category_type' => $fields['type']]);

    logAudit("Category", "Create", "$session_name created " . $category_kind_en(['category_type' => $fields['type']]) . " " . escapeSql($fields['name']), 0, $category_id);

    if ($inline) {
        $row = categoryRow($category_id);
        header('Content-Type: application/json');
        echo json_encode([
            'ok' => true,
            'value' => categoryKind($fields['type'])['by'] === 'name' ? $row['category_name'] : (string) $category_id,
            'option' => categoryOptionHtml($row, true),
            'message' => ucfirst($kind) . ' ' . $row['category_name'] . ' created',
        ]);
        exit;
    }

    flashAlert(__('%1$s <strong>%2$s</strong> created', ucfirst($kind), escapeHtml($fields['name'])));

    // Back to its kind in Admin > Categories, or to the page the window was opened on
    redirect(str_contains($_SERVER['HTTP_REFERER'] ?? '', '/admin/categories.php') ? $category_page($fields['type']) : null);

}

if (isset($_POST['edit_category'])) {

    validateCSRFToken();

    $row = categoryRow(intval($_POST['category_id'] ?? 0));
    if (!$row) {
        flashAlert(__('That category is gone - it may have been merged into another, or deleted'), 'error');
        redirect();
    }

    [$fields, $error] = categoryFromInput($_POST, $row);
    if ($error !== '') {
        flashAlert(escapeHtml($error), 'error');
        redirect();
    }

    $renamed = categoryUpdate($row, $fields);
    $kind = $category_kind_word($row);
    $was = $row['category_name'] !== $fields['name'] ? " (was " . escapeSql($row['category_name']) . ")" : '';

    logAudit("Category", "Edit", "$session_name edited " . $category_kind_en($row) . " " . escapeSql($fields['name']) . $was . ($renamed ? " - renamed on $renamed " . ($renamed === 1 ? 'record' : 'records') : ''), 0, intval($row['category_id']));

    flashAlert($renamed ? _n('%1$s <strong>%2$s</strong> saved - and renamed on the %3$s record that had it', '%1$s <strong>%2$s</strong> saved - and renamed on the %3$s records that had it', $renamed, ucfirst($kind), escapeHtml($fields['name']), number_format($renamed)) : __('%1$s <strong>%2$s</strong> saved', ucfirst($kind), escapeHtml($fields['name'])));

    redirect();

}

if (isset($_GET['archive_category'])) {

    validateCSRFToken();

    $row = categoryRow(intval($_GET['archive_category']));
    if ($row && !$row['category_archived_at']) {
        categoryArchive($row['category_id']);
        $used = categoryUsageFor($row['category_id']);
        logAudit("Category", "Archive", "$session_name archived " . $category_kind_en($row) . " " . escapeSql($row['category_name']), 0, intval($row['category_id']));
        flashAlert($used['all'] ? __('%1$s <strong>%2$s</strong> archived - it stays on the %3$s that have it, but isn\'t offered for new ones', ucfirst($category_kind_word($row)), escapeHtml($row['category_name']), escapeHtml(categoryUsageText($row, $used))) : __('%1$s <strong>%2$s</strong> archived - it isn\'t offered for new ones', ucfirst($category_kind_word($row)), escapeHtml($row['category_name'])));
    }

    redirect();

}

if (isset($_GET['restore_category'])) {

    validateCSRFToken();

    $row = categoryRow(intval($_GET['restore_category']));
    if ($row && $row['category_archived_at']) {
        categoryRestore($row['category_id']);
        logAudit("Category", "Restore", "$session_name restored " . $category_kind_en($row) . " " . escapeSql($row['category_name']), 0, intval($row['category_id']));
        flashAlert(__('%1$s <strong>%2$s</strong> restored', ucfirst($category_kind_word($row)), escapeHtml($row['category_name'])));
    }

    redirect();

}

if (isset($_GET['delete_category'])) {

    validateCSRFToken();

    $row = categoryRow(intval($_GET['delete_category']));
    if (!$row) {
        redirect();
    }

    $error = '';
    if (!categoryDelete($row['category_id'], $error)) {
        flashAlert(escapeHtml(ucfirst($category_kind_word($row)) . " " . $row['category_name'] . " is kept - " . lcfirst($error)), 'error');
        redirect();
    }

    logAudit("Category", "Delete", "$session_name deleted " . $category_kind_en($row) . " " . escapeSql($row['category_name']));

    flashAlert(__('%1$s <strong>%2$s</strong> deleted', ucfirst($category_kind_word($row)), escapeHtml($row['category_name'])), 'error');

    redirect();

}

if (isset($_POST['merge_categories'])) {

    validateCSRFToken();

    $into = categoryRow(intval($_POST['into_category_id'] ?? 0));
    if (!$into) {
        flashAlert(__('Pick the category to keep'), 'error');
        redirect();
    }
    $into_id = intval($into['category_id']);

    // Their names before they go - other categories of the same kind only
    $from = [];
    foreach (array_unique(array_map('intval', (array) ($_POST['category_ids'] ?? []))) as $from_id) {
        $from_row = $from_id !== $into_id ? categoryRow($from_id) : null;
        if ($from_row && $from_row['category_type'] === $into['category_type']) {
            $from[$from_id] = $from_row['category_name'];
        }
    }
    if (!$from) {
        flashAlert(__('Pick another %1$s to merge into %2$s', escapeHtml($category_kind_word($into)), escapeHtml($into['category_name'])), 'error');
        redirect();
    }

    $moved = categoryMerge(array_keys($from), $into_id);
    $kind = $category_kind_word($into);
    $records = $moved ? number_format($moved) . ($moved === 1 ? ' record' : ' records') : ''; // i18n-ignore - the audit log

    logAudit("Category", "Merge", "$session_name merged " . i18nEnglish(fn () => count($from) === 1 ? $category_kind_word($into) : categoryKind($into['category_type'])['many']) . " " . escapeSql(implode(', ', $from)) . " into " . escapeSql($into['category_name'])
        . ($moved ? " - $records" : ''), 0, $into_id);

    $merged_names = implode(', ', array_map(function ($name) {
        return '<strong>' . escapeHtml($name) . '</strong>';
    }, $from));
    $records_shown = _n('%s record', '%s records', $moved, number_format($moved));
    flashAlert(__('Merged %1$s into %2$s', $merged_names, '<strong>' . escapeHtml($into['category_name']) . '</strong>')
        . ($moved ? (count($from) === 1 ? __(' - the %s that had it have it now', $records_shown) : __(' - the %s that had them have it now', $records_shown)) : ''));

    redirect();

}

if (isset($_GET['move_category'])) {

    validateCSRFToken();

    // One place up or down among its kind's current categories, in the order pickers use
    $row = categoryRow(intval($_GET['move_category']));
    $config = $row ? categoryKind($row['category_type']) : null;
    if ($config && $config['ordered']) {
        $ids = array_keys(array_filter(categoryRows($row['category_type']), function ($r) {
            return !$r['category_archived_at'];
        }));
        $at = array_search(intval($row['category_id']), $ids, true);
        $to = ($_GET['direction'] ?? '') === 'up' ? $at - 1 : $at + 1;
        if ($at !== false && isset($ids[$to])) {
            [$ids[$at], $ids[$to]] = [$ids[$to], $ids[$at]];
            categoryReorder($row['category_type'], $ids);
            logAudit("Category", "Edit", "$session_name moved " . $category_kind_en($row) . " " . escapeSql($row['category_name']) . (($_GET['direction'] ?? '') === 'up' ? ' up' : ' down'), 0, intval($row['category_id']));
        }
    }

    redirect();

}

if (isset($_POST['reorder_categories'])) {

    // Dragged into order on Admin > Categories - answers with JSON, as the page saves it in the background
    header('Content-Type: application/json');
    if (!hash_equals((string) ($_SESSION['csrf_token'] ?? ''), (string) ($_POST['csrf_token'] ?? ''))) {
        echo json_encode(['ok' => false, 'error' => __('The page is out of date - reload it')]);
        exit;
    }

    $config = categoryKind($_POST['category'] ?? '');
    if (!$config || !$config['ordered']) {
        echo json_encode(['ok' => false, 'error' => __('Those categories keep no order')]);
        exit;
    }

    // The ones dragged, a page of them, in the order dragged in their place among the current ones - offset is how many
    // come before them, on the pages before - and the archived ones after them all
    $rows = categoryRows($config['kind']);
    $current = array_keys(array_filter($rows, function ($row) {
        return !$row['category_archived_at'];
    }));
    $ids = array_values(array_intersect(array_unique(array_map('intval', (array) ($_POST['category_ids'] ?? []))), $current));
    $new_order = array_values(array_diff($current, $ids));
    array_splice($new_order, min(max(0, intval($_POST['offset'] ?? 0)), count($new_order)), 0, $ids);
    if (categoryReorder($config['kind'], $new_order)) {
        logAudit("Category", "Edit", "$session_name reordered the " . i18nEnglish(fn () => categoryKind($config['kind'])['many']));
    }

    echo json_encode(['ok' => true]);
    exit;

}

if (isset($_POST['bulk_archive_categories']) || isset($_POST['bulk_restore_categories'])) {

    validateCSRFToken();

    $archive = isset($_POST['bulk_archive_categories']);
    $count = 0;
    foreach (array_unique(array_map('intval', (array) ($_POST['category_ids'] ?? []))) as $category_id) {
        $row = categoryRow($category_id);
        if (!$row || (bool) $row['category_archived_at'] === $archive) {
            continue;
        }
        if ($archive) {
            categoryArchive($category_id);
        } else {
            categoryRestore($category_id);
        }
        logAudit("Category", $archive ? "Archive" : "Restore", "$session_name " . ($archive ? 'archived ' : 'restored ') . $category_kind_en($row) . " " . escapeSql($row['category_name']), 0, $category_id);
        $count++;
    }

    flashAlert($archive ? ($count ? _n('Archived %d category - it stays on its records, but isn\'t offered for new ones', 'Archived %d categories - they stay on their records, but aren\'t offered for new ones', $count) : __('Archived 0 categories')) : _n('Restored %d category', 'Restored %d categories', $count));

    redirect();

}

if (isset($_POST['bulk_delete_categories'])) {

    validateCSRFToken();

    $deleted = 0;
    $kept = [];
    foreach (array_unique(array_map('intval', (array) ($_POST['category_ids'] ?? []))) as $category_id) {
        $row = categoryRow($category_id);
        if (!$row) {
            continue;
        }
        $error = '';
        if (!categoryDelete($category_id, $error)) {
            if ($error !== '') {
                $kept[] = '<strong>' . escapeHtml($row['category_name']) . '</strong>';
            }
            continue;
        }
        logAudit("Category", "Delete", "$session_name deleted " . $category_kind_en($row) . " " . escapeSql($row['category_name']));
        $deleted++;
    }

    flashAlert(_n('Deleted %d category', 'Deleted %d categories', $deleted)
        . ($kept ? (count($kept) === 1 ? __('. Kept %s - it is in use, so merge it into another, or archive it', implode(', ', $kept)) : __('. Kept %s - they are in use, so merge them into another, or archive them', implode(', ', $kept))) : ''), $kept ? 'warning' : 'error');

    redirect();

}
