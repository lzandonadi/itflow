<?php

/*
 * Incident response - when an agent's account may be in someone else's
 * hands: every other agent gets a new password, shown once on the next page,
 * and their remembered browsers are forgotten so MFA is asked for again.
 */

require_once '../../includes/modal_header.php';

$agent_count = intval(mysqli_fetch_row(mysqli_query($mysqli, "SELECT COUNT(*) FROM users WHERE user_type = 1 AND user_archived_at IS NULL AND user_id <> " . intval($session_user_id)))[0]);

ob_start();

?>
<div class="modal-header bg-danger">
    <h5 class="modal-title text-white"><i class="fas fa-fw fa-skull-crossbones me-2"></i><?= __('Incident response') ?></h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

    <div class="modal-body">
        <p class="fw-semibold mb-2"><?= _n('Reset the password of every other agent - %d of them.', 'Reset the password of every other agent - %d of them.', $agent_count) ?></p>
        <ul class="small mb-3 ps-3">
            <li><?= __('Each gets a new password, shown to you once on the next page - give them out by phone, not email, and have them changed.') ?></li>
            <li><?= __('Their remembered browsers are forgotten, so anyone with MFA is asked for a code again.') ?></li>
            <li><?= __('Not yours - change your own first, from your account.') ?></li>
            <li><?= __('Client portal logins are left alone.') ?></li>
        </ul>

        <label class="form-label"><?= __('Your password') ?> <strong class="text-danger">*</strong></label>
        <div class="input-group">
            <span class="input-group-text"><i class="fas fa-fw fa-lock"></i></span>
            <input type="password" class="form-control" data-toggle="password" name="admin_password" placeholder="<?= escapeHtml(__('To confirm it\'s you')) ?>" autocomplete="current-password" required autofocus>
            <span class="input-group-text"><i class="fa fa-fw fa-eye"></i></span>
        </div>
    </div>
    <div class="modal-footer">
        <button type="submit" name="ir_reset_user_password" class="btn btn-danger text-bold" <?= $agent_count ? '' : 'disabled' ?>><i class="fas fa-key me-2"></i><?= _n('Reset %d password', 'Reset %d passwords', $agent_count) ?></button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fas fa-times me-2"></i><?= __('Cancel') ?></button>
    </div>
</form>

<?php
require_once "../../../includes/modal_footer.php";
