<?php

/*
 * Delete a folder. Nothing in it is lost: its files, documents and
 * subfolders move up into the folder it sat in.
 */

require_once '../../../includes/modal_header.php';

enforceUserPermission('module_support', 3);

$folder_id = intval($_GET['id'] ?? 0);

$row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT folder_client_id, folder_name, parent_folder FROM folders WHERE folder_id = $folder_id LIMIT 1"));
if (!$row) {
    echo json_encode(['error' => __('That folder no longer exists.')]);
    exit;
}
$client_id = intval($row['folder_client_id']);
enforceClientAccess();

$parent_folder = folderOwnedId($row['parent_folder'], $client_id);
$parent_name = $parent_folder ? escapeHtml(folderDisplayName($parent_folder)) : __('Top level');
$inside_files = intval(mysqli_fetch_row(mysqli_query($mysqli, "SELECT COUNT(*) FROM files WHERE file_folder_id = $folder_id AND file_client_id = $client_id"))[0]);
$inside_documents = intval(mysqli_fetch_row(mysqli_query($mysqli, "SELECT COUNT(*) FROM documents WHERE document_folder_id = $folder_id AND document_client_id = $client_id"))[0]);
$inside_folders = intval(mysqli_fetch_row(mysqli_query($mysqli, "SELECT COUNT(*) FROM folders WHERE parent_folder = $folder_id AND folder_client_id = $client_id"))[0]);
$inside = array_filter([
    $inside_files ? _n('%d file', '%d files', $inside_files) : '',
    $inside_documents ? _n('%d document', '%d documents', $inside_documents) : '',
    $inside_folders ? _n('%d folder', '%d folders', $inside_folders) : '',
]);

ob_start();

?>

<div class="modal-body text-center py-4">
    <i class="fas fa-folder fa-3x text-body-secondary mb-3"></i>
    <h5 class="mb-1"><?= __('Delete %s?', '<strong>' . escapeHtml($row['folder_name']) . '</strong>') ?></h5>
    <div class="small text-body-secondary mb-4">
        <?php if ($inside) { ?>
            <?= __('What is inside (%1$s) moves up to %2$s - nothing is deleted with it.', implode(', ', $inside), '<strong>' . $parent_name . '</strong>') ?>
        <?php } else { ?>
            <?= __('It is empty.') ?>
        <?php } ?>
    </div>
    <form action="post.php" method="post">
        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
        <input type="hidden" name="folder_id" value="<?= $folder_id ?>">
        <button type="button" class="btn btn-light px-4 me-2" data-bs-dismiss="modal"><?= __('Cancel') ?></button>
        <button type="submit" name="delete_folder" class="btn btn-danger px-4"><i class="fas fa-trash me-2"></i><?= __('Delete folder') ?></button>
    </form>
</div>

<?php
require_once '../../../includes/modal_footer.php';
