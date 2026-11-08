<?php

/*
 * Whether the client portal shows a document to the client's technical contacts.
 */

require_once '../../../includes/modal_header.php';

enforceUserPermission('module_support', 2);

$document_id = intval($_GET['document_id'] ?? 0);

$row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT document_client_id, document_client_visible, document_name FROM documents WHERE document_id = $document_id LIMIT 1"));
if (!$row) {
    echo json_encode(['error' => __('That document no longer exists.')]);
    exit;
}
$client_id = intval($row['document_client_id']);
enforceClientAccess();

ob_start();

?>

<div class="modal-header bg-dark">
    <h5 class="modal-title text-truncate"><i class="fas fa-fw fa-eye me-2"></i><?= __('Client portal') ?> &middot; <strong><?= escapeHtml($row['document_name']) ?></strong></h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <input type="hidden" name="document_id" value="<?= $document_id ?>">
    <input type="hidden" name="document_visible" value="0">
    <div class="modal-body">
        <div class="form-check form-switch">
            <input class="form-check-input" type="checkbox" role="switch" name="document_visible" value="1" id="documentVisibleSwitch" <?= intval($row['document_client_visible']) ? 'checked' : '' ?>>
            <label class="form-check-label" for="documentVisibleSwitch"><?= __('Show this document in the client portal') ?></label>
        </div>
        <div class="form-text ms-5"><?= __('Only the client\'s contacts with the Technical role can see documents there, and only to read.') ?></div>
    </div>
    <div class="modal-footer">
        <button type="submit" name="toggle_document_visibility" class="btn btn-primary text-bold"><i class="fa fa-check me-2"></i><?= __('Save') ?></button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fa fa-times me-2"></i><?= __('Cancel') ?></button>
    </div>
</form>

<?php
require_once '../../../includes/modal_footer.php';
