<?php

/*
 * Edit a role - its name, description, whether it is an admin role, and
 * its level of each module. The fields are role_form.php.
 */

require_once '../../includes/modal_header.php';

$role = roleRow(intval($_GET['id'] ?? 0));

ob_start();

if (!$role) { ?>
    <div class="modal-body text-center text-secondary py-5"><?= __('That role is gone - it may have been deleted.') ?></div>
<?php } else {
    $role_id = intval($role['role_id']);
    $role_admin_blocker = roleAdminBlocker($role);
    $agent_count = intval($role['agents']); ?>

<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fas fa-fw fa-user-shield me-2"></i><?= __('Role:') ?> <strong><?= escapeHtml($role['role_name']) ?></strong></h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <input type="hidden" name="role_id" value="<?= $role_id ?>">

    <div class="modal-body">
        <?php if ($role['role_archived_at']) { ?>
            <div class="alert alert-secondary small py-2"><i class="fas fa-fw fa-archive me-1"></i><?= escapeHtml(__('Archived %s - kept for the agents archived with it, but not given to anyone new.', formatDate($role['role_archived_at']))) ?></div>
        <?php } elseif ($agent_count) { ?>
            <div class="small text-secondary mb-3"><i class="fas fa-fw fa-users me-1"></i><?= _n('A change applies to its %d agent from the next page they open.', 'A change applies to its %d agents from the next page they open.', $agent_count) ?></div>
        <?php } ?>
        <?php require __DIR__ . '/role_form.php'; ?>
    </div>
    <div class="modal-footer">
        <button type="submit" name="edit_role" class="btn btn-primary text-bold"><i class="fas fa-check me-2"></i><?= __('Save changes') ?></button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fas fa-times me-2"></i><?= __('Cancel') ?></button>
    </div>
</form>

<?php }
require_once '../../../includes/modal_footer.php';
