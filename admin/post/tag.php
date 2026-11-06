<?php

/*
 * Tags (Admin > Tags) - making, changing, merging, archiving and deleting
 * them. The work is in functions/tags.php.
 */

defined('FROM_POST_HANDLER') || die("Direct file access is not allowed");

// "asset" - what a tag is for, for the audit log and messages
$tag_kind_word = function ($row) {
    return strtolower(tagEntityByType($row['tag_type'] ?? 0)['title'] ?? __('unknown'));
};

// "12 assets" / "1 asset"
$tag_records_text = function ($n, $type) {
    $kind = tagEntityByType($type);
    return number_format($n) . ' ' . ($n === 1 ? strtolower($kind['title'] ?? __('record')) : ($kind['plural'] ?? __('records')));
};
// The same two in English, for the audit log
$tag_kind_en = function ($row) use ($tag_kind_word) {
    return i18nEnglish(fn () => $tag_kind_word($row));
};
$tag_records_en = function ($n, $type) use ($tag_records_text) {
    return i18nEnglish(fn () => $tag_records_text($n, $type));
};

if (isset($_POST['add_tag'])) {

    validateCSRFToken();

    [$fields, $error] = tagFromInput($_POST);
    if ($error !== '') {
        flashAlert(escapeHtml($error), 'error');
        redirect();
    }

    $tag_id = tagCreate($fields);
    $kind = $tag_kind_word(['tag_type' => $fields['type']]);

    logAudit("Tag", "Create", "$session_name created " . $tag_kind_en(['tag_type' => $fields['type']]) . " tag " . escapeSql($fields['name']), 0, $tag_id);

    flashAlert(__('%1$s tag <strong>%2$s</strong> created', ucfirst($kind), escapeHtml($fields['name'])));

    redirect("tags.php?type=" . intval($fields['type']));

}

if (isset($_POST['edit_tag'])) {

    validateCSRFToken();

    $tag_id = intval($_POST['tag_id'] ?? 0);
    $row = tagRow($tag_id);
    if (!$row) {
        flashAlert(__('That tag is gone - it may have been merged into another, or deleted'), 'error');
        redirect();
    }

    [$fields, $error] = tagFromInput($_POST, $row);
    if ($error !== '') {
        flashAlert(escapeHtml($error), 'error');
        redirect();
    }

    tagUpdate($tag_id, $fields);
    $kind = $tag_kind_word(['tag_type' => $fields['type']]);
    $moved = intval($row['tag_type']) !== intval($fields['type']);
    $renamed = $row['tag_name'] !== $fields['name'];

    logAudit("Tag", "Edit", "$session_name edited " . $tag_kind_en(['tag_type' => $fields['type']]) . " tag " . escapeSql($fields['name'])
        . ($renamed ? " (was " . escapeSql($row['tag_name']) . ")" : '')
        . ($moved ? " - moved from " . $tag_kind_en($row) . " tags" : ''), 0, $tag_id);

    flashAlert($moved ? __('Tag <strong>%1$s</strong> saved - it\'s %2$s now', escapeHtml($fields['name']), tagKindPhrase($fields['type'])) : __('Tag <strong>%s</strong> saved', escapeHtml($fields['name'])));

    redirect($moved ? "tags.php?type=" . intval($fields['type']) . ($row['tag_archived_at'] ? '&show=archived' : '') : null);

}

if (isset($_GET['archive_tag'])) {

    validateCSRFToken();

    $row = tagRow(intval($_GET['archive_tag']));
    if ($row && !$row['tag_archived_at']) {
        tagArchive($row['tag_id']);
        $used = tagUsage($row['tag_id']);
        logAudit("Tag", "Archive", "$session_name archived " . $tag_kind_en($row) . " tag " . escapeSql($row['tag_name']), 0, intval($row['tag_id']));
        flashAlert($used ? __('Tag <strong>%1$s</strong> archived - it stays on its %2$s, but isn\'t offered for tagging', escapeHtml($row['tag_name']), $tag_records_text($used, $row['tag_type'])) : __("Tag <strong>%s</strong> archived - it isn't offered for tagging", escapeHtml($row['tag_name'])));
    }

    redirect();

}

if (isset($_GET['restore_tag'])) {

    validateCSRFToken();

    $row = tagRow(intval($_GET['restore_tag']));
    if ($row && $row['tag_archived_at']) {
        tagRestore($row['tag_id']);
        logAudit("Tag", "Restore", "$session_name restored " . $tag_kind_en($row) . " tag " . escapeSql($row['tag_name']), 0, intval($row['tag_id']));
        flashAlert(__('Tag <strong>%s</strong> restored', escapeHtml($row['tag_name'])));
    }

    redirect();

}

if (isset($_GET['delete_tag'])) {

    validateCSRFToken();

    $row = tagRow(intval($_GET['delete_tag']));
    if (!$row) {
        redirect();
    }

    $used = tagUsage($row['tag_id']);
    $error = '';
    if (!tagDelete($row['tag_id'], $error)) {
        flashAlert($error !== '' ? escapeHtml($error) : __('That tag is gone'), 'error');
        redirect();
    }

    logAudit("Tag", "Delete", "$session_name deleted " . $tag_kind_en($row) . " tag " . escapeSql($row['tag_name'])
        . ($used ? ", taking it off " . $tag_records_en($used, $row['tag_type']) : ''));

    flashAlert($used ? __('Tag <strong>%1$s</strong> deleted and taken off %2$s', escapeHtml($row['tag_name']), $tag_records_text($used, $row['tag_type'])) : __('Tag <strong>%s</strong> deleted', escapeHtml($row['tag_name'])), 'error');

    redirect();

}

if (isset($_POST['merge_tags'])) {

    validateCSRFToken();

    $into = tagRow(intval($_POST['into_tag_id'] ?? 0));
    if (!$into) {
        flashAlert(__('Pick the tag to keep'), 'error');
        redirect();
    }
    $into_id = intval($into['tag_id']);

    // Their names before they go - other tags of the same kind only
    $from = [];
    foreach (array_unique(array_map('intval', (array) ($_POST['tag_ids'] ?? []))) as $from_id) {
        $from_row = $from_id !== $into_id ? tagRow($from_id) : null;
        if ($from_row && intval($from_row['tag_type']) === intval($into['tag_type'])) {
            $from[$from_id] = $from_row['tag_name'];
        }
    }
    if (!$from) {
        flashAlert(__('Pick another %1$s tag to merge into %2$s', $tag_kind_word($into), escapeHtml($into['tag_name'])), 'error');
        redirect();
    }

    $moved = tagMerge(array_keys($from), $into_id);

    logAudit("Tag", "Merge", "$session_name merged " . $tag_kind_en($into) . " " . (count($from) === 1 ? 'tag' : 'tags') . " " . escapeSql(implode(', ', $from)) . " into " . escapeSql($into['tag_name'])
        . ($moved ? " - " . $tag_records_en($moved, $into['tag_type']) : ''), 0, $into_id);

    $merged_names = implode(', ', array_map(function ($name) {
        return '<strong>' . escapeHtml($name) . '</strong>';
    }, $from));
    flashAlert(__('Merged %1$s into %2$s', $merged_names, '<strong>' . escapeHtml($into['tag_name']) . '</strong>')
        . ($moved ? (count($from) === 1 ? __(' - the %s that had it have it now', $tag_records_text($moved, $into['tag_type'])) : __(' - the %s that had them have it now', $tag_records_text($moved, $into['tag_type']))) : ''));

    redirect();

}

if (isset($_POST['bulk_archive_tags']) || isset($_POST['bulk_restore_tags'])) {

    validateCSRFToken();

    $archive = isset($_POST['bulk_archive_tags']);
    $count = 0;
    foreach (array_unique(array_map('intval', (array) ($_POST['tag_ids'] ?? []))) as $tag_id) {
        $row = tagRow($tag_id);
        if (!$row || (bool) $row['tag_archived_at'] === $archive) {
            continue;
        }
        if ($archive) {
            tagArchive($tag_id);
        } else {
            tagRestore($tag_id);
        }
        logAudit("Tag", $archive ? "Archive" : "Restore", "$session_name " . ($archive ? 'archived ' : 'restored ') . $tag_kind_en($row) . " tag " . escapeSql($row['tag_name']), 0, $tag_id);
        $count++;
    }

    flashAlert($archive ? ($count ? _n('Archived %d tag - it stays on its records, but isn\'t offered for tagging', 'Archived %d tags - they stay on their records, but aren\'t offered for tagging', $count) : __('Archived 0 tags')) : _n('Restored %d tag', 'Restored %d tags', $count));

    redirect();

}

if (isset($_POST['bulk_delete_tags'])) {

    validateCSRFToken();

    $deleted = $kept = $records = 0;
    foreach (array_unique(array_map('intval', (array) ($_POST['tag_ids'] ?? []))) as $tag_id) {
        $row = tagRow($tag_id);
        if (!$row) {
            continue;
        }
        $used = tagUsage($tag_id);
        $error = '';
        if (!tagDelete($tag_id, $error)) {
            $kept += $error !== '' ? 1 : 0;
            continue;
        }
        logAudit("Tag", "Delete", "$session_name deleted " . $tag_kind_en($row) . " tag " . escapeSql($row['tag_name'])
            . ($used ? ", taking it off " . $tag_records_en($used, $row['tag_type']) : ''));
        $deleted++;
        $records += $used;
    }

    flashAlert(_n('Deleted %d tag', 'Deleted %d tags', $deleted) . ($records ? _n(', taken off %s record', ', taken off %s records', $records, number_format($records)) : '')
        . ($kept ? _n('. Kept %d that recurring invoice lines count by - archive it, or merge it into another tag', '. Kept %d that recurring invoice lines count by - archive them, or merge them into another tag', $kept) : ''), $kept ? 'warning' : 'error');

    redirect();

}
