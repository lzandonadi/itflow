<?php

/*
 * Archive User - they can no longer sign in, and their open tickets and
 * recurring tickets go to another agent (or to nobody).
 */

require_once '../../includes/modal_header.php';

$user = userForModal($_GET['id'] ?? 0);
$user_id = intval($user['user_id']);
[$open_tickets, $recurring_tickets] = userOpenWork($user_id);
$is_last_admin = userIsLastAdmin($user);

// Who can take their work - active agents, not them
$agents = [];
$sql = mysqli_query($mysqli, "SELECT user_id, user_name, user_avatar FROM users
    WHERE user_type = 1 AND user_status = 1 AND user_archived_at IS NULL AND user_id <> $user_id ORDER BY user_name ASC");
while ($row = mysqli_fetch_assoc($sql)) {
    $agents[] = $row;
}

ob_start();

?>
<div class="modal-header bg-dark">
    <h5 class="modal-title text-truncate"><i class="fas fa-fw fa-archive me-2"></i><?= escapeHtml(__('Archive %s', $user['user_name'])) ?></h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <input type="hidden" name="user_id" value="<?= $user_id ?>">

    <div class="modal-body">
        <div class="d-flex align-items-center gap-3 mb-3">
            <?= userAvatar($user_id, $user['user_name'], $user['user_avatar'], 'lg') ?>
            <div class="min-w-0">
                <div class="fw-semibold"><?= escapeHtml($user['user_name']) ?></div>
                <div class="small text-body-secondary"><?= escapeHtml($user['user_email']) ?> · <?= escapeHtml($user['role_name'] ?? __('No role')) ?></div>
            </div>
        </div>

        <?php if ($user_id === intval($session_user_id)) { ?>
            <div class="alert alert-danger small mb-0"><?= __('You can\'t archive yourself.') ?></div>
        <?php } elseif ($is_last_admin) { ?>
            <div class="alert alert-danger small mb-0"><i class="fas fa-user-shield me-2"></i><?= __('The only active admin - archived, there would be nobody to run ITFlow. Make someone else an admin first.') ?></div>
        <?php } else { ?>
            <ul class="small mb-3 ps-3">
                <li><?= __('They\'re signed out, and can\'t sign in.') ?></li>
                <li><?= __('Their password and their copy of the credential vault key are wiped - restoring them sets a new password.') ?></li>
                <li><?= __('Everything they did stays, under their name.') ?></li>
            </ul>

            <label class="form-label">
                <?php if ($open_tickets || $recurring_tickets) { ?>
                    <?= $recurring_tickets ? _n('Hand over %1$d open ticket and %2$d recurring to', 'Hand over %1$d open tickets and %2$d recurring to', $open_tickets, $open_tickets, $recurring_tickets) : _n('Hand over %d open ticket to', 'Hand over %d open tickets to', $open_tickets) ?>
                <?php } else { ?>
                    <?= __('Their open tickets go to') ?> <span class="fw-normal text-body-secondary">(<?= __('they have none now') ?>)</span>
                <?php } ?>
            </label>
            <div class="input-group">
                <span class="input-group-text"><i class="fas fa-fw fa-user-check"></i></span>
                <select class="form-select select2" name="ticket_assign">
                    <option value="0" data-icon="fas fa-user-slash"><?= __('Nobody - unassigned') ?></option>
                    <?php foreach ($agents as $row) { ?>
                        <option value="<?= intval($row['user_id']) ?>"<?= selectOptionUser($row['user_id'], $row['user_name'], $row['user_avatar'] ?? '') ?>><?= escapeHtml($row['user_name']) ?></option>
                    <?php } ?>
                </select>
            </div>
            <div class="form-text"><?= __('Each ticket\'s history says who it went to, and why.') ?></div>
        <?php } ?>
    </div>
    <div class="modal-footer">
        <?php if ($user_id !== intval($session_user_id) && !$is_last_admin) { ?>
            <button type="submit" name="archive_user" class="btn btn-danger text-bold"><i class="fas fa-archive me-2"></i><?= __('Archive') ?></button>
        <?php } ?>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fas fa-times me-2"></i><?= __('Cancel') ?></button>
    </div>
</form>

<?php
require_once "../../../includes/modal_footer.php";
