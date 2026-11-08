<?php

/*
 * Edit a category - its name (carried onto the records of a kind held by
 * name), description, colour and icon. The fields are category_form.php.
 */

require_once '../../includes/modal_header.php';

$category = categoryRow(intval($_GET['id'] ?? 0));

ob_start();

if (!$category) { ?>
    <div class="modal-body text-center text-secondary py-5"><?= __('That category is gone - it may have been merged into another, or deleted.') ?></div>
<?php } else {
    $category_id = intval($category['category_id']);
    $kind = (string) $category['category_type'];
    $kind_config = categoryKind($kind);
    $category_used = categoryUsageFor($category_id); ?>

<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fas fa-fw <?= $kind_config['icon'] ?? 'fa-list-ul' ?> me-2"></i><?= escapeHtml(ucwords($kind_config['one'] ?? 'Category')) ?>: <strong><?= escapeHtml($category['category_name']) ?></strong></h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
<form action="/admin/post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <input type="hidden" name="category_id" value="<?= $category_id ?>">

    <div class="modal-body">
        <?php if ($category['category_archived_at']) { ?>
            <div class="alert alert-secondary small py-2"><i class="fas fa-fw fa-archive me-1"></i><?= _x('Archived %s', 'archived on', formatDate($category['category_archived_at'], 'date')) ?> - it stays on its records, but isn't offered for new ones.</div>
        <?php } ?>
        <?php if (!$kind_config) { ?>
            <div class="alert alert-warning small py-2"><?= __('This is a "%s" category - nothing in ITFlow uses that kind any more.', escapeHtml($kind)) ?></div>
        <?php } ?>
        <?php require __DIR__ . '/category_form.php'; ?>
    </div>
    <div class="modal-footer">
        <button type="submit" name="edit_category" class="btn btn-primary text-bold"><i class="fas fa-check me-2"></i><?= __('Save changes') ?></button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fas fa-times me-2"></i><?= __('Cancel') ?></button>
    </div>
</form>

<?php }
require_once '../../../includes/modal_footer.php';
