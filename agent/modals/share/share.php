<?php

/*
 * A shared link's quick look (functions/share.php) - what it is, who it was
 * for and who made it, what it asks for before it opens, where it is now, and
 * everything that happened to it from the audit log: opened when, from which
 * address and browser, wrong passphrases, codes sent, turned off.
 *
 * For a request a client has answered, what they sent - read through the
 * vault, and logged - with Save to credentials and Discard.
 */

require_once '../../../includes/modal_header.php';

$item_id = intval($_GET['id'] ?? 0);
$row = shareRow($item_id);
if (!$row || !shareCanSee($row['item_type'])) {
    exit(json_encode(['error' => __('That link no longer exists.')]));
}
$client_id = intval($row['item_client_id']);
enforceClientAccess();

$types = shareTypes();
$type = $row['item_type'];
$type_info = $types[$type] ?? ['label' => $type, 'icon' => 'fa-share-alt'];
$state = shareState($row);
$is_open = in_array($state, ['active', 'waiting'], true);
$can_end = shareCanMake($type);
$protection = shareProtection($row);
$history = shareHistory($row);

// What a client sent back - only for whoever may make credentials, and read through the vault
$received = null;
if ($state === 'received' && lookupUserPermission('module_credential') >= 2) {
    $received = shareRequestOpen($row);
    if (is_array($received)) {
        logAudit('Share', 'Read', "$session_name looked at the login sent back for " . escapeSql(i18nEnglish(fn () => shareName($row))), $client_id, $item_id);
    }
}

$history_icons = [
    'Create' => 'fa-plus-circle', 'Open' => 'fa-eye', 'View' => 'fa-eye', 'Download' => 'fa-download', 'Receive' => 'fa-inbox',
    'Code' => 'fa-envelope', 'Verify' => 'fa-check', 'Fail' => 'fa-times-circle text-danger', 'Lock' => 'fa-lock text-danger',
    'Destroy' => 'fa-fire', 'Revoke' => 'fa-unlink', 'Save' => 'fa-save', 'Discard' => 'fa-trash', 'Read' => 'fa-key',
];

ob_start();

?>

<div class="modal-header bg-dark" data-bs-theme="dark">
    <div class="d-flex align-items-center gap-3 min-w-0">
        <span class="itflow-link-icon" aria-hidden="true"><i class="fas fa-fw <?= $type_info['icon'] ?>"></i></span>
        <div class="min-w-0">
            <h5 class="modal-title d-flex flex-wrap align-items-center column-gap-2">
                <span class="fw-semibold text-break"><?= escapeHtml(shareName($row)) ?></span>
                <?= shareStateBadge($row) ?>
            </h5>
            <div class="small text-body-secondary mt-1"><?= escapeHtml($type_info['label']) ?> &middot; <?= escapeHtml($row['client_name']) ?></div>
        </div>
    </div>
    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
</div>

<div class="modal-body">

    <div class="row g-2 mb-3">
        <div class="col-sm-6">
            <div class="itflow-glance-item" title="<?= escapeHtml(__('Who it is for')) ?>">
                <i class="fas fa-fw fa-user"></i>
                <span class="text-break"><?= trim((string) $row['item_recipient']) !== '' ? escapeHtml($row['item_recipient']) : '<span class="text-body-secondary">' . __('Anyone with the link') . '</span>' ?></span>
            </div>
            <div class="itflow-glance-item" title="<?= escapeHtml(__('Made')) ?>">
                <i class="fas fa-fw fa-plus-circle"></i>
                <span><?= $row['creator_name'] !== null ? __('Made %1$s by %2$s', timeAgo($row['item_created_at']), escapeHtml($row['creator_name'])) : __('Made %s', timeAgo($row['item_created_at'])) ?></span>
            </div>
            <div class="itflow-glance-item" title="<?= escapeHtml($row['item_received_at'] ? __('Sent back') : __('Works until')) ?>">
                <i class="fas fa-fw fa-<?= $row['item_received_at'] ? 'inbox' : 'clock' ?>"></i>
                <?php if ($row['item_received_at']) { ?>
                    <span><?= escapeHtml(__('Sent back %s', formatDate($row['item_received_at'], 'datetime'))) ?></span>
                <?php } else { ?>
                    <span><?= escapeHtml($is_open ? __('Works until %s', formatDate($row['item_expire_at'], 'datetime')) : __('Was to work until %s', formatDate($row['item_expire_at'], 'datetime'))) ?></span>
                <?php } ?>
            </div>
        </div>
        <div class="col-sm-6">
            <div class="itflow-glance-item" title="<?= escapeHtml(__('Opened')) ?>">
                <i class="fas fa-fw fa-eye"></i>
                <span><?= escapeHtml(shareViewsText($row)) ?><?= $row['item_last_viewed_at'] ? ' <span class="text-body-secondary">&middot; ' . __('last %s', timeAgo($row['item_last_viewed_at'])) . '</span>' : '' ?></span>
            </div>
            <div class="itflow-glance-item" title="<?= escapeHtml(__('Before it opens')) ?>">
                <i class="fas fa-fw fa-shield-alt"></i>
                <span>
                    <?= ['passphrase' => __('Needs a passphrase'), 'code' => __('Needs a code emailed to the recipient'), '' => __('Opens with the link alone')][$protection] ?>
                    <?php if (intval($row['item_failed_attempts'])) { ?>
                        <span class="text-danger-emphasis">&middot; <?= _n('%d wrong', '%d wrong', intval($row['item_failed_attempts'])) ?></span>
                    <?php } ?>
                </span>
            </div>
            <div class="itflow-glance-item" title="<?= escapeHtml(__('Notifications')) ?>">
                <i class="fas fa-fw fa-bell<?= $row['item_notify'] ? '' : '-slash' ?>"></i>
                <span><?= $row['item_notify'] ? ($row['creator_name'] !== null ? ($type === 'Request' ? __('%s is told when it is answered', escapeHtml($row['creator_name'])) : __('%s is told when it is opened', escapeHtml($row['creator_name']))) : __('Everyone is told when it is opened')) : __('Nobody is told when it is opened') ?></span>
            </div>
        </div>
    </div>

    <?php if ($row['item_ended_at']) { ?>
        <div class="small text-body-secondary mb-3">
            <i class="fas fa-fw fa-info-circle me-1"></i><?= $row['ender_name'] !== null ? __('%1$s %2$s by %3$s.', escapeHtml(shareStates()[$state][0]), timeAgo($row['item_ended_at']), escapeHtml($row['ender_name'])) : __('%1$s %2$s.', escapeHtml(shareStates()[$state][0]), timeAgo($row['item_ended_at'])) ?>
            <?= in_array($state, ['locked', 'destroyed'], true) ? ($state === 'locked' ? __('Too many wrong passphrases or codes.') : __('The person it was for destroyed it once they had what they needed.')) : '' ?>
        </div>
    <?php } ?>

    <?php if (trim((string) $row['item_note']) !== '') { ?>
        <div class="small text-body-secondary fw-semibold text-uppercase mb-1"><?= __('Note for them') ?></div>
        <div class="small mb-3"><?= nl2br(escapeHtml($row['item_note'])) ?></div>
    <?php } ?>

    <?php if ($state === 'received') { ?>
        <div class="card border-warning-subtle mb-3">
            <div class="card-header bg-warning-subtle py-2 small fw-semibold">
                <i class="fas fa-inbox me-2"></i><?= __('Sent back %s - save it to the client\'s credentials', timeAgo($row['item_received_at'])) ?>
            </div>
            <div class="card-body py-2">
                <?php if ($received === false) { ?>
                    <div class="small text-danger-emphasis"><i class="fas fa-lock me-2"></i><?= __('Your vault session has expired, so it cannot be read - sign out and back in, then open this again.') ?></div>
                <?php } elseif (is_array($received)) { ?>
                    <dl class="row small mb-0">
                        <?php if (trim((string) ($received['uri'] ?? '')) !== '') { ?>
                            <dt class="col-sm-3 fw-normal text-body-secondary"><?= __('Website') ?></dt>
                            <dd class="col-sm-9 text-break"><?= escapeHtml($received['uri']) ?></dd>
                        <?php } ?>
                        <dt class="col-sm-3 fw-normal text-body-secondary"><?= __('Username') ?></dt>
                        <dd class="col-sm-9 d-flex align-items-center min-w-0">
                            <span class="font-monospace text-break"><?= trim((string) ($received['username'] ?? '')) !== '' ? escapeHtml($received['username']) : '-' ?></span>
                            <?php if (trim((string) ($received['username'] ?? '')) !== '') { ?><button type="button" class="itflow-copy clipboardjs" title="<?= escapeHtml(__('Copy')) ?>" data-clipboard-text="<?= escapeHtml($received['username']) ?>"><i class="far fa-copy"></i></button><?php } ?>
                        </dd>
                        <dt class="col-sm-3 fw-normal text-body-secondary"><?= __('Password') ?></dt>
                        <dd class="col-sm-9">
                            <?php if (($received['password'] ?? '') !== '') { ?>
                                <div class="input-group input-group-sm">
                                    <input type="password" class="form-control font-monospace" value="<?= escapeHtml($received['password']) ?>" readonly data-toggle="password" aria-label="<?= escapeHtml(__('Password')) ?>">
                                    <span class="input-group-text"><i class="fa fa-fw fa-eye"></i></span>
                                    <button type="button" class="btn btn-outline-secondary clipboardjs" title="<?= escapeHtml(__('Copy password')) ?>" data-clipboard-text="<?= escapeHtml($received['password']) ?>"><i class="far fa-fw fa-copy"></i></button>
                                </div>
                            <?php } else { ?>
                                -
                            <?php } ?>
                        </dd>
                        <?php if (trim((string) ($received['otp'] ?? '')) !== '') { ?>
                            <dt class="col-sm-3 fw-normal text-body-secondary"><?= __('2FA secret') ?></dt>
                            <dd class="col-sm-9"><span class="text-body-secondary"><?= __('Sent - saved with the credential') ?></span></dd>
                        <?php } ?>
                        <?php if (trim((string) ($received['note'] ?? '')) !== '') { ?>
                            <dt class="col-sm-3 fw-normal text-body-secondary"><?= __('Their note') ?></dt>
                            <dd class="col-sm-9"><?= nl2br(escapeHtml($received['note'])) ?></dd>
                        <?php } ?>
                    </dl>
                <?php } elseif (lookupUserPermission('module_credential') < 2) { ?>
                    <div class="small text-body-secondary"><?= __('Someone who can add credentials for this client can save it.') ?></div>
                <?php } else { ?>
                    <div class="small text-body-secondary"><?= __('There is nothing to read - it may have been saved or discarded already.') ?></div>
                <?php } ?>
            </div>
            <?php if (is_array($received) && $can_end) { ?>
                <div class="card-footer d-flex gap-2 py-2">
                    <form action="post.php" method="post" class="m-0">
                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                        <input type="hidden" name="item_id" value="<?= $item_id ?>">
                        <button type="submit" name="share_request_save" value="1" class="btn btn-sm btn-primary text-bold"><i class="fas fa-save me-2"></i><?= __('Save to credentials') ?></button>
                    </form>
                    <a class="btn btn-sm btn-outline-danger confirm-link" href="post.php?share_request_discard=<?= $item_id ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>" data-confirm-text="<?= escapeHtml(__('What they sent is deleted without being saved.')) ?>"><i class="fas fa-trash me-2"></i><?= __('Discard') ?></a>
                </div>
            <?php } ?>
        </div>
    <?php } ?>

    <div class="small text-body-secondary fw-semibold text-uppercase mb-1"><?= __('History') ?></div>
    <?php if ($history) { ?>
        <ul class="list-unstyled small mb-0">
            <?php foreach ($history as $entry) {
                $icon = $history_icons[$entry['log_action']] ?? 'fa-circle';
                $where = [];
                if ($entry['user_name'] === null && $entry['log_ip'] !== '') {
                    $where[] = escapeHtml($entry['log_ip']);
                }
                if ($entry['user_name'] === null && $entry['log_user_agent'] !== '') {
                    // "Chrome on Windows" - as much of it as the browser said
                    $browser = trim(strip_tags(getWebBrowser($entry['log_user_agent'])));
                    $os = trim(strip_tags(getOS($entry['log_user_agent'])));
                    $parts = array_values(array_filter([$browser, $os], function ($part) { return $part !== '-' && $part !== ''; }));
                    $device = count($parts) === 2 ? __('%1$s on %2$s', $parts[0], $parts[1]) : ($parts[0] ?? '');
                    if ($device !== '') {
                        $where[] = escapeHtml($device);
                    }
                } ?>
                <li class="d-flex gap-2 py-1 border-bottom">
                    <i class="fas fa-fw <?= $icon ?> text-body-secondary mt-1" aria-hidden="true"></i>
                    <span class="flex-grow-1 min-w-0">
                        <?= escapeHtml($entry['log_description']) ?>
                        <?php if ($where) { ?><span class="d-block text-body-secondary"><?= implode(' &middot; ', $where) ?></span><?php } ?>
                    </span>
                    <span class="text-body-secondary text-nowrap" title="<?= escapeHtml($entry['log_created_at']) ?>"><?= timeAgo($entry['log_created_at']) ?></span>
                </li>
            <?php } ?>
        </ul>
    <?php } else { ?>
        <div class="small text-body-secondary"><?= __('Nothing yet.') ?></div>
    <?php } ?>

</div>

<div class="modal-footer">
    <?php $item_link = shareItemMenuLink($row); ?>
    <?php if ($item_link) { ?>
        <div class="me-auto"><?= str_replace('dropdown-item', 'btn btn-outline-secondary', $item_link) ?></div>
    <?php } ?>
    <?php if ($can_end && $is_open) { ?>
        <a class="btn btn-outline-danger confirm-link" href="post.php?share_revoke=<?= $item_id ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>" data-confirm-text="<?= escapeHtml(__('The link stops working straight away.')) ?>">
            <i class="fas fa-unlink me-2"></i><?= $type === 'Request' ? __('Cancel the request') : __('Turn off') ?>
        </a>
    <?php } ?>
    <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fa fa-times me-2"></i><?= __('Close') ?></button>
</div>

<?php
require_once '../../../includes/modal_footer.php';
