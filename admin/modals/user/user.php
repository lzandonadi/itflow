<?php

/*
 * User quick look - from Administration > Users. Who they are, whether and
 * how they sign in, the clients they see, the work they have open, and their
 * latest sign ins.
 */

require_once '../../includes/modal_header.php';

$user = userForModal($_GET['id'] ?? 0);
$user_id = intval($user['user_id']);
$is_self = $user_id === intval($session_user_id);
$is_archived = !empty($user['user_archived_at']);
$is_admin = intval($user['role_is_admin']) === 1;
$display_name = $is_archived ? trim(str_replace(' (archived)', '', $user['user_name'])) : $user['user_name'];
$access = userClientAccess([$user_id])[$user_id];
$remembered = userRememberCounts([$user_id])[$user_id];
[$open_tickets, $recurring_tickets] = userOpenWork($user_id);
$recent = userRecentLogins($user_id, 6);
$role = roleRow($user['user_role_id'] ?? 0);

ob_start();

?>
<div class="modal-header bg-dark" data-bs-theme="dark">
    <div class="d-flex align-items-center gap-3 min-w-0">
        <?= userAvatar($user_id, $display_name, $user['user_avatar'], 'lg') ?>
        <div class="min-w-0">
            <h5 class="modal-title fw-semibold text-break"><?= escapeHtml($display_name) ?><?php if ($is_self) { ?> <span class="badge rounded-pill bg-primary-subtle text-primary-emphasis fw-normal small align-middle"><?= __('You') ?></span><?php } ?></h5>
            <div class="small text-body-secondary d-flex flex-wrap column-gap-3">
                <span><?= escapeHtml($user['user_email']) ?><button type="button" class="itflow-copy clipboardjs" title="<?= escapeHtml(__('Copy email')) ?>" data-clipboard-text="<?= escapeHtml($user['user_email']) ?>"><i class="far fa-copy"></i></button></span>
                <span><i class="fas fa-fw <?= $is_admin ? 'fa-user-shield' : 'fa-user-tag' ?> me-1"></i><?= escapeHtml($user['role_name'] ?? __('No role')) ?></span>
            </div>
        </div>
    </div>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="<?= escapeHtml(__('Close')) ?>"></button>
</div>

<div class="modal-body">

    <div class="d-flex flex-wrap gap-1 mb-3">
        <?= userStatusPill($user['user_status'], $is_archived) ?>
        <?php if (!$is_archived) { ?><?= userMfaPill($user['user_token'], $user['user_config_force_mfa']) ?><?php } ?>
        <?php if ($is_admin) { ?><span class="badge rounded-pill bg-primary-subtle text-primary-emphasis fw-normal"><i class="fas fa-user-shield me-1"></i><?= __('Admin') ?></span><?php } ?>
    </div>

    <dl class="itflow-asset-dl mb-0">
        <dt><?= __('Can open') ?></dt>
        <dd>
            <?php if ($role) { ?>
                <?= roleAccessHtml($role) ?>
                <div class="small mt-1"><a href="roles.php<?= $role['role_archived_at'] ? '?archived=1' : '' ?>"><?= escapeHtml(__('From their role, %s', $role['role_name'])) ?></a></div>
            <?php } else { ?>
                <span class="text-danger-emphasis small"><i class="fas fa-exclamation-triangle me-1"></i><?= __('No role - nothing until they are given one') ?></span>
            <?php } ?>
        </dd>

        <dt><?= __('Clients') ?></dt>
        <dd>
            <?= escapeHtml(userClientAccessText($access, $is_admin)) ?>
            <?php if (!$is_admin && $access['allow']) { ?>
                <div class="small"><span class="text-success-emphasis fw-semibold"><?= __('Only') ?></span> <?= escapeHtml(implode(', ', $access['allow'])) ?></div>
            <?php } ?>
            <?php if (!$is_admin && $access['deny']) { ?>
                <div class="small"><span class="text-danger-emphasis fw-semibold"><?= __('Never') ?></span> <?= escapeHtml(implode(', ', $access['deny'])) ?></div>
            <?php } ?>
        </dd>

        <?php if (!$is_archived) { ?>
            <dt><?= __('Remembered') ?></dt>
            <dd>
                <?php if ($remembered) { ?>
                    <?= $remembered ?> browser<?= $remembered === 1 ? '' : 's' ?> skip<?= $remembered === 1 ? 's' : '' ?> their MFA code
                <?php } else { ?>
                    <span class="text-body-secondary"><?= __('No browsers') ?></span>
                <?php } ?>
            </dd>

            <dt><?= __('Open work') ?></dt>
            <dd>
                <?php if ($open_tickets) { ?>
                    <a href="/agent/tickets.php?assigned=<?= $user_id ?>"><?= escapeHtml(_n('%d open ticket', '%d open tickets', $open_tickets)) ?></a>
                <?php } else { ?>
                    <span class="text-body-secondary"><?= __('No open tickets') ?></span>
                <?php } ?>
                <?php if ($recurring_tickets) { ?>
                    <span class="text-body-secondary">· <?= $recurring_tickets ?> recurring</span>
                <?php } ?>
            </dd>
        <?php } ?>

        <dt><?= __('Added') ?></dt>
        <dd><span title="<?= escapeHtml($user['user_created_at']) ?>"><?= escapeHtml(timeAgo($user['user_created_at'])) ?></span>
            <?php if ($joined = userInviteJoined($user_id)) { ?>
                <span class="text-body-secondary" data-user-joined>· joined from <?= $joined['by'] !== '' ? escapeHtml($joined['by']) . "'s" : 'an' ?> invite</span>
            <?php } ?>
        </dd>

        <?php if ($is_archived) { ?>
            <dt><?= __('Archived') ?></dt>
            <dd><span title="<?= escapeHtml($user['user_archived_at']) ?>"><?= escapeHtml(timeAgo($user['user_archived_at'])) ?></span></dd>
        <?php } ?>
    </dl>

    <h6 class="text-body-secondary text-uppercase small fw-semibold mt-4 mb-2"><?= __('Latest sign ins') ?></h6>
    <?php if ($recent) { ?>
        <div class="d-flex flex-column gap-2 small">
            <?php foreach ($recent as $log) { ?>
                <?= userLoginLineHtml($log) ?>
            <?php } ?>
        </div>
    <?php } else { ?>
        <div class="small text-body-secondary"><?= __('Never signed in.') ?></div>
    <?php } ?>
</div>

<div class="modal-footer">
    <a class="btn btn-outline-secondary me-auto" href="audit_logs.php?user=<?= $user_id ?>"><i class="fas fa-history me-2"></i><?= __('Audit log') ?></a>
    <?php if ($is_self) { ?>
        <a class="btn btn-primary" href="/agent/user/user_details.php"><i class="fas fa-user-cog me-2"></i><?= __('Your account') ?></a>
    <?php } elseif ($is_archived) { ?>
        <button type="button" class="btn btn-primary ajax-modal" data-modal-url="modals/user/user_restore.php?id=<?= $user_id ?>"><i class="fas fa-redo-alt me-2"></i><?= __('Restore') ?></button>
    <?php } else { ?>
        <button type="button" class="btn btn-primary ajax-modal" data-modal-size="lg" data-modal-url="modals/user/user_edit.php?id=<?= $user_id ?>"><i class="fas fa-user-edit me-2"></i><?= __('Edit') ?></button>
    <?php } ?>
    <button type="button" class="btn btn-light" data-bs-dismiss="modal"><?= __('Close') ?></button>
</div>

<?php
require_once "../../../includes/modal_footer.php";
