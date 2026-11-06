<?php

/*
 * The fields of New User and Edit User - included by user_add.php and
 * user_edit.php, which set $form (the user, raw - or the defaults for a new
 * one) and $is_new. Posts what admin/post/users.php add_user / edit_user read.
 *
 * Tabs: Details (picture, name, email, role), Sign in (password, MFA) and
 * Client access - which clients a role without admin is limited to or kept
 * from. An admin sees every client, so for an admin role that tab just says
 * so (any rules are kept, in case the role changes back).
 *
 * New User invites them by default (sign_in invite - functions/user_invite.php):
 * they choose their own password from a link, emailed or, with no mail set
 * up, shown to the admin to pass on. Set a password now (sign_in password)
 * is the other way - for the admin to give them.
 */

isset($mysqli) || die("Direct file access is not allowed");

$form_user_id = intval($form['user_id'] ?? 0);
$form_id = 'userForm' . ($is_new ? 'New' : $form_user_id);
$form_role_id = intval($form['user_role_id'] ?? 0);
$form_roles = userRoles($form_role_id);
$form_has_mfa = trim((string) ($form['user_token'] ?? '')) !== '';
$form_has_avatar = trim((string) ($form['user_avatar'] ?? '')) !== '';
$form_access = $form_user_id ? userClientAccess([$form_user_id])[$form_user_id] : ['allow' => [], 'deny' => []];
$form_invites = $is_new && userInvitesReady();
$form_invite_mail = $form_invites && userInviteMailReady();

// Every client - and an archived one only when it has a rule, so saving does not drop it. Those with a rule first.
$form_clients = [];
$sql = mysqli_query($mysqli, "SELECT client_id, client_name, client_abbreviation, client_archived_at FROM clients
    WHERE client_archived_at IS NULL OR client_id IN (SELECT client_id FROM user_client_permissions WHERE user_id = $form_user_id)
    ORDER BY client_id IN (SELECT client_id FROM user_client_permissions WHERE user_id = $form_user_id) DESC, client_archived_at IS NOT NULL, client_name ASC");
while ($row = mysqli_fetch_assoc($sql)) {
    $form_clients[] = $row;
}

$value = function ($key) use ($form) {
    return escapeHtml($form[$key] ?? '');
};

?>

<ul class="nav nav-pills nav-fill flex-nowrap overflow-auto mb-3 itflow-asset-form-tabs" role="tablist">
    <li class="nav-item">
        <a class="nav-link active" data-bs-toggle="pill" href="#<?= $form_id ?>Details"><i class="fas fa-fw fa-user me-2 d-none d-lg-inline"></i><?= __('Details') ?></a>
    </li>
    <li class="nav-item">
        <a class="nav-link" data-bs-toggle="pill" href="#<?= $form_id ?>Security"><i class="fas fa-fw fa-key me-2 d-none d-lg-inline"></i><?= __('Sign in') ?></a>
    </li>
    <li class="nav-item">
        <a class="nav-link" data-bs-toggle="pill" href="#<?= $form_id ?>Access"><i class="fas fa-fw fa-building me-2 d-none d-lg-inline"></i><?= __('Client access') ?></a>
    </li>
</ul>

<div class="tab-content" id="<?= $form_id ?>">

<!-- Who they are -->
<div class="tab-pane fade show active" id="<?= $form_id ?>Details">
    <div class="d-flex align-items-center gap-3 mb-3">
        <span data-user-avatar-preview><?= userAvatar($form_user_id ?: 0, $form['user_name'] ?? '', $form['user_avatar'] ?? '', 'lg') ?></span>
        <div class="flex-grow-1 min-w-0">
            <label class="form-label mb-1"><?= __('Picture') ?></label>
            <div class="input-group">
                <span class="input-group-text"><i class="fas fa-fw fa-image"></i></span>
                <input type="file" class="form-control" name="avatar" accept="image/jpeg,image/png,image/gif,image/webp" data-user-avatar-input>
            </div>
            <div class="d-flex flex-wrap align-items-center column-gap-3 mt-1">
                <span class="form-text mt-0"><?= __('JPG, PNG, GIF or WebP, up to 5 MB. Without one, their initials.') ?></span>
                <?php if ($form_has_avatar) { ?>
                    <div class="form-check small mb-0">
                        <input class="form-check-input" type="checkbox" name="remove_avatar" value="1" id="<?= $form_id ?>RemoveAvatar" data-user-avatar-remove>
                        <label class="form-check-label" for="<?= $form_id ?>RemoveAvatar"><?= __('Remove the picture') ?></label>
                    </div>
                <?php } ?>
            </div>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-md-6">
            <label class="form-label"><?= __('Name') ?> <strong class="text-danger">*</strong></label>
            <div class="input-group">
                <span class="input-group-text"><i class="fas fa-fw fa-user"></i></span>
                <input type="text" class="form-control" name="name" placeholder="<?= escapeHtml(__('First and last name')) ?>" maxlength="200" value="<?= $value('user_name') ?>" required <?= $is_new ? 'autofocus' : '' ?> data-user-name>
            </div>
        </div>
        <div class="col-md-6">
            <label class="form-label"><?= __('Email') ?> <strong class="text-danger">*</strong></label>
            <div class="input-group">
                <span class="input-group-text"><i class="fas fa-fw fa-envelope"></i></span>
                <input type="email" class="form-control" name="email" placeholder="<?= escapeHtml(__('They sign in with this')) ?>" maxlength="200" value="<?= $value('user_email') ?>" required>
            </div>
            <?php if (!$is_new) { ?>
                <div class="form-text"><?= __('If you change it, they sign in with the new address - the old one gets a notice.') ?></div>
            <?php } ?>
        </div>
        <div class="col-12">
            <label class="form-label"><?= __('Role') ?> <strong class="text-danger">*</strong></label>
            <div class="input-group">
                <span class="input-group-text"><i class="fas fa-fw fa-user-shield"></i></span>
                <select class="form-select select2" name="role" required data-user-role>
                    <?php if (!isset($form_roles[$form_role_id])) { ?>
                        <option value=""><?= __('- Pick a role -') ?></option>
                    <?php } ?>
                    <?php foreach ($form_roles as $role_id => $role) { ?>
                        <option value="<?= $role_id ?>" <?= $form_role_id === $role_id ? 'selected' : '' ?> data-role-admin="<?= intval($role['role_is_admin']) ?>"<?= selectOptionRole($role) ?>><?= escapeHtml($role['role_name']) ?><?= $role['role_archived_at'] ? ' (archived)' : '' ?></option>
                    <?php } ?>
                </select>
            </div>
            <?php
            // What each role opens, under the picker - the one picked shows
            $form_role_rows = roleRows();
            foreach (array_keys($form_roles) as $role_id) {
                if (isset($form_role_rows[$role_id])) { ?>
                    <div class="mt-2" data-user-role-access="<?= $role_id ?>"<?= $form_role_id === $role_id ? '' : ' hidden' ?>><?= roleAccessHtml($form_role_rows[$role_id]) ?></div>
                <?php }
            } ?>
            <div class="form-text"><?= __('What they can see and change.') ?> <a href="roles.php" target="_blank"><?= __('Roles') ?> <i class="fas fa-external-link-alt small"></i></a></div>
        </div>
    </div>
</div>

<!-- How they sign in -->
<div class="tab-pane fade" id="<?= $form_id ?>Security">
    <div class="row g-3">
        <?php if ($form_invites) { ?>
            <div class="col-12">
                <label class="form-label d-block"><?= __('How they get in') ?></label>
                <div class="btn-group w-100" role="group" aria-label="<?= escapeHtml(__('How they get in')) ?>">
                    <input type="radio" class="btn-check" name="sign_in" value="invite" id="<?= $form_id ?>Invite" checked data-user-sign-in>
                    <label class="btn btn-outline-primary" for="<?= $form_id ?>Invite"><i class="fas fa-fw fa-paper-plane me-1"></i><?= __('Invite them') ?></label>
                    <input type="radio" class="btn-check" name="sign_in" value="password" id="<?= $form_id ?>SetPassword" data-user-sign-in>
                    <label class="btn btn-outline-primary" for="<?= $form_id ?>SetPassword"><i class="fas fa-fw fa-lock me-1"></i><?= __('Set a password now') ?></label>
                </div>
                <div class="form-text" data-user-sign-in-note="invite">
                    <?php if ($form_invite_mail) { ?>
                        <?= __("They're emailed a link to choose their own password, so nobody else ever knows it. It works for %d days - until they use it, they're under Invited.", USER_INVITE_LINK_DAYS) ?>
                    <?php } else { ?>
                        <?= __("Mail isn't set up, so you get the link to send them yourself - they choose their own password with it. It works for %d days.", USER_INVITE_LINK_DAYS) ?>
                    <?php } ?>
                </div>
                <div class="form-text" data-user-sign-in-note="password" hidden><?= __('For you to give them yourself - nothing is emailed. They should change it once they\'re in.') ?></div>
            </div>
        <?php } ?>
        <div class="col-12" data-user-password-field<?= $form_invites ? ' hidden' : '' ?>>
            <label class="form-label"><?= $is_new ? __('Password <strong class="text-danger">*</strong>') : __('New password') ?></label>
            <div class="input-group">
                <span class="input-group-text"><i class="fas fa-fw fa-lock"></i></span>
                <input type="password" class="form-control font-monospace" data-toggle="password" name="<?= $is_new ? 'password' : 'new_password' ?>" id="<?= $form_id ?>Password"<?php /* i18n-ignore - ids */ ?>
                    placeholder="<?= $is_new ? escapeHtml(__('At least %d characters', USER_PASSWORD_MIN)) : __('Leave blank to keep their password') ?>" minlength="<?= USER_PASSWORD_MIN ?>" autocomplete="new-password" <?= $is_new && !$form_invites ? 'required' : '' ?><?= $form_invites ? ' disabled' : '' ?> data-user-password>
                <span class="input-group-text"><i class="fa fa-fw fa-eye"></i></span>
                <button class="btn btn-outline-secondary clipboardjs" type="button" title="<?= escapeHtml(__('Copy password')) ?>" data-clipboard-target="#<?= $form_id ?>Password"><i class="far fa-fw fa-copy"></i></button>
                <button class="btn btn-outline-secondary" type="button" title="<?= escapeHtml(__('Make one up - words that are easy to read out')) ?>" data-user-generate><i class="fas fa-fw fa-magic"></i></button>
            </div>
            <?php if (!$is_new) { ?>
                <div class="form-text"><?= __('Set, they\'re emailed that it changed. It also unlocks their copy of the credential vault, so it is set here rather than reset by email.') ?></div>
            <?php } elseif (!$form_invites) { ?>
                <div class="form-text"><?= __('For you to give them yourself - nothing is emailed. They should change it once they\'re in.') ?></div>
            <?php } ?>
        </div>

        <div class="col-12">
            <div class="border rounded p-3">
                <div class="d-flex align-items-center gap-2 mb-2">
                    <span class="fw-semibold"><i class="fas fa-fw fa-mobile-alt text-body-secondary me-1"></i><?= __('Multi-factor authentication') ?></span>
                    <?php if (!$is_new) { ?><?= userMfaPill($form['user_token'] ?? '', $form['user_config_force_mfa'] ?? 0) ?><?php } ?>
                </div>
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" role="switch" name="force_mfa" value="1" id="<?= $form_id ?>ForceMfa" <?= intval($form['user_config_force_mfa'] ?? 0) ? 'checked' : '' ?>><?php /* i18n-ignore - ids */ ?>
                    <label class="form-check-label" for="<?= $form_id ?>ForceMfa"><?= __('Require it') ?></label>
                    <div class="form-text mt-0"><?= __('If they haven\'t set it up yet, they\'re asked to at their next sign in - and they can\'t turn it off.') ?></div>
                </div>
                <?php if ($form_has_mfa) { ?>
                    <div class="form-check mt-2">
                        <input class="form-check-input" type="checkbox" name="reset_mfa" value="1" id="<?= $form_id ?>ResetMfa">
                        <label class="form-check-label" for="<?= $form_id ?>ResetMfa"><?= __('Reset it') ?></label>
                        <div class="form-text mt-0"><?= __('For a lost or replaced phone - their authenticator app is forgotten, and so are their remembered browsers.') ?></div>
                    </div>
                <?php } ?>
            </div>
        </div>
    </div>
</div>

<!-- The clients they see -->
<div class="tab-pane fade" id="<?= $form_id ?>Access">
    <input type="hidden" name="client_permission_shown" value="1">

    <div data-user-access-admin hidden>
        <div class="alert alert-info small d-flex gap-2">
            <i class="fas fa-user-shield mt-1"></i>
            <div><?= __('An admin sees every client, so these rules don\'t apply to them. Rules already set are kept, in case the role changes back.') ?></div>
        </div>
    </div>

    <div data-user-access-rules>
        <div class="small text-body-secondary mb-2">
            <strong class="text-success-emphasis"><?= __('Only') ?></strong> <?= __('limits them to those clients - with none, they see every client.') ?>
            <strong class="text-danger-emphasis"><?= __('Never') ?></strong> <?= __('keeps them out of a client, whatever else is set.') ?>
        </div>
        <div class="d-flex flex-wrap gap-2 align-items-center mb-2">
            <div class="input-group input-group-sm flex-grow-1" style="max-width: 18rem">
                <span class="input-group-text"><i class="fas fa-fw fa-search"></i></span>
                <input type="search" class="form-control" placeholder="<?= escapeHtml(__('Find a client')) ?>" data-user-access-filter aria-label="<?= escapeHtml(__('Find a client')) ?>">
            </div>
            <div class="btn-group btn-group-sm" role="group" aria-label="<?= escapeHtml(__('Set every client shown')) ?>">
                <button type="button" class="btn btn-outline-secondary" data-user-access-all="" title="<?= escapeHtml(__('Every client shown')) ?>"><?= __('Clear') ?></button>
                <button type="button" class="btn btn-outline-success" data-user-access-all="allow" title="<?= escapeHtml(__('Every client shown')) ?>"><?= __('All only') ?></button>
                <button type="button" class="btn btn-outline-danger" data-user-access-all="deny" title="<?= escapeHtml(__('Every client shown')) ?>"><?= __('All never') ?></button>
            </div>
            <span class="small fw-semibold ms-auto" data-user-access-summary></span>
        </div>

        <div class="border rounded itflow-user-access">
            <table class="table table-sm table-hover align-middle mb-0">
                <tbody>
                <?php foreach ($form_clients as $client) {
                    $cid = intval($client['client_id']);
                    $rule = isset($form_access['allow'][$cid]) ? 'allow' : (isset($form_access['deny'][$cid]) ? 'deny' : '');
                    $tone = clientInitialsTone($cid);
                    $radio = "{$form_id}Client{$cid}";
                    ?>
                    <tr data-user-access-row data-name="<?= escapeHtml(mb_strtolower($client['client_name'] . ' ' . $client['client_abbreviation'])) ?>">
                        <td class="ps-2">
                            <span class="itflow-client-avatar itflow-client-avatar-xs bg-<?= $tone ?>-subtle text-<?= $tone ?>-emphasis me-2" aria-hidden="true"><?= escapeHtml(clientInitials($client['client_abbreviation'], $client['client_name'])) ?></span><?= escapeHtml($client['client_name']) ?>
                            <?php if ($client['client_archived_at']) { ?><span class="badge rounded-pill text-bg-secondary fw-normal ms-1"><?= __('Archived') ?></span><?php } ?>
                        </td>
                        <td class="text-end pe-2 text-nowrap">
                            <div class="btn-group btn-group-sm" role="group" aria-label="<?= escapeHtml($client['client_name']) ?>">
                                <input type="radio" class="btn-check" name="client_permission[<?= $cid ?>]" value="" id="<?= $radio ?>None" <?= $rule === '' ? 'checked' : '' ?>><?php /* i18n-ignore - ids */ ?>
                                <label class="btn btn-outline-secondary" for="<?= $radio ?>None" title="<?= escapeHtml(__('No rule')) ?>">-</label><?php /* i18n-ignore - ids */ ?>
                                <input type="radio" class="btn-check" name="client_permission[<?= $cid ?>]" value="allow" id="<?= $radio ?>Allow" <?= $rule === 'allow' ? 'checked' : '' ?>><?php /* i18n-ignore - ids */ ?>
                                <label class="btn btn-outline-success" for="<?= $radio ?>Allow"><?= __('Only') ?></label>
                                <input type="radio" class="btn-check" name="client_permission[<?= $cid ?>]" value="deny" id="<?= $radio ?>Deny" <?= $rule === 'deny' ? 'checked' : '' ?>><?php /* i18n-ignore - ids */ ?>
                                <label class="btn btn-outline-danger" for="<?= $radio ?>Deny"><?= __('Never') ?></label>
                            </div>
                        </td>
                    </tr>
                <?php } ?>
                <?php if (!$form_clients) { ?>
                    <tr><td class="text-body-secondary p-3"><?= __('No clients yet.') ?></td></tr>
                <?php } ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

</div>

<script>
(function () {
    var root = document.getElementById(<?= json_encode($form_id) ?>);
    if (!root) {
        return;
    }
    var form = root.closest('form');

    // The picture - shown as soon as it is picked
    var avatarInput = root.querySelector('[data-user-avatar-input]');
    var avatarPreview = root.querySelector('[data-user-avatar-preview]');
    var avatarOriginal = avatarPreview.innerHTML;
    var avatarRemove = root.querySelector('[data-user-avatar-remove]');
    avatarInput.addEventListener('change', function () {
        var file = avatarInput.files && avatarInput.files[0];
        if (file && /^image\//.test(file.type)) {
            var img = document.createElement('img');
            img.className = 'itflow-contact-avatar itflow-contact-avatar-lg';
            img.alt = '';
            img.src = URL.createObjectURL(file);
            avatarPreview.replaceChildren(img);
            if (avatarRemove) {
                avatarRemove.checked = false;
            }
        } else {
            avatarPreview.innerHTML = avatarOriginal;
        }
    });
    if (avatarRemove) {
        avatarRemove.addEventListener('change', function () {
            avatarPreview.classList.toggle('opacity-25', avatarRemove.checked);
            if (avatarRemove.checked) {
                avatarInput.value = '';
                avatarPreview.innerHTML = avatarOriginal;
            }
        });
    }

    // No picture - their initials, as the name is typed
    var nameInput = root.querySelector('[data-user-name]');
    nameInput.addEventListener('input', function () {
        var initials = avatarPreview.querySelector('span.itflow-contact-avatar');
        if (initials && !(avatarInput.files && avatarInput.files.length)) {
            var words = nameInput.value.trim().split(/\s+/).filter(Boolean).slice(0, 2);
            initials.textContent = words.map(function (w) { return w.charAt(0).toUpperCase(); }).join('') || '?';
        }
    });

    // Make up a password - words, easy to read out over the phone
    var password = root.querySelector('[data-user-password]');
    root.querySelector('[data-user-generate]').addEventListener('click', function () {
        itflowGet('/agent/ajax.php', { get_readable_pass: 'true' }, function (data) {
            password.value = JSON.parse(data);
            password.type = 'text';
            var eye = password.closest('.input-group').querySelector('.fa-eye');
            if (eye) {
                eye.classList.replace('fa-eye', 'fa-eye-slash');
            }
            password.dispatchEvent(new Event('input'));
        });
    });

    // New User: invite them - no password here - or set one now
    var signIn = root.querySelectorAll('[data-user-sign-in]');
    if (signIn.length) {
        var passwordField = root.querySelector('[data-user-password-field]');
        var submit = form.querySelector('[data-user-submit]');
        var signInChanged = function () {
            var checked = root.querySelector('[data-user-sign-in]:checked');
            var invite = !checked || checked.value === 'invite';
            passwordField.hidden = invite;
            password.required = !invite;
            password.disabled = invite;
            root.querySelectorAll('[data-user-sign-in-note]').forEach(function (note) {
                note.hidden = note.dataset.userSignInNote !== (invite ? 'invite' : 'password');
            });
            if (submit) {
                submit.querySelector('[data-user-submit-label]').textContent = invite ? submit.dataset.labelInvite : submit.dataset.labelCreate;
                submit.querySelector('i').className = 'fas me-2 ' + (invite ? 'fa-paper-plane' : 'fa-check');
            }
        };
        signIn.forEach(function (radio) {
            radio.addEventListener('change', signInChanged);
        });
        signInChanged();
    }

    // Client access: an admin sees everything, so the rules are put away
    var role = root.querySelector('[data-user-role]');
    var adminNote = root.querySelector('[data-user-access-admin]');
    var rules = root.querySelector('[data-user-access-rules]');
    function roleChanged() {
        var option = role.options[role.selectedIndex];
        var isAdmin = option && option.dataset.roleAdmin === '1';
        adminNote.hidden = !isAdmin;
        rules.classList.toggle('opacity-50', isAdmin);
        // What the role picked opens
        root.querySelectorAll('[data-user-role-access]').forEach(function (access) {
            access.hidden = access.dataset.userRoleAccess !== role.value;
        });
    }
    role.addEventListener('change', roleChanged);
    roleChanged();

    // What the rules add up to
    var summary = root.querySelector('[data-user-access-summary]');
    function summarize() {
        var allow = root.querySelectorAll('[data-user-access-row] input[value="allow"]:checked').length;
        var deny = root.querySelectorAll('[data-user-access-row] input[value="deny"]:checked').length;
        var text, tone;
        if (allow) {
            text = 'Sees ' + allow + ' client' + (allow === 1 ? '' : 's') + ' only';
            tone = 'text-warning-emphasis';
        } else if (deny) {
            text = deny === 1 ? __('Sees all but 1 client') : __('Sees all but %s clients', deny);
            tone = 'text-warning-emphasis';
        } else {
            text = __('Sees every client');
            tone = 'text-success-emphasis';
        }
        summary.textContent = text;
        summary.className = 'small fw-semibold ms-auto ' + tone;
    }
    root.addEventListener('change', function (e) {
        if (e.target.matches('[data-user-access-row] input')) {
            summarize();
        }
    });
    summarize();

    // Find a client, and set every one shown at once
    var filter = root.querySelector('[data-user-access-filter]');
    filter.addEventListener('input', function () {
        var term = filter.value.trim().toLowerCase();
        root.querySelectorAll('[data-user-access-row]').forEach(function (row) {
            row.hidden = term !== '' && row.dataset.name.indexOf(term) === -1;
        });
    });
    // Enter in the filter would submit the whole form
    filter.addEventListener('keydown', function (e) {
        if (e.key === 'Enter') {
            e.preventDefault();
        }
    });
    root.querySelectorAll('[data-user-access-all]').forEach(function (button) {
        button.addEventListener('click', function () {
            var value = button.dataset.userAccessAll;
            root.querySelectorAll('[data-user-access-row]').forEach(function (row) {
                if (!row.hidden) {
                    var radio = row.querySelector('input[value="' + value + '"]');
                    if (radio) {
                        radio.checked = true;
                    }
                }
            });
            summarize();
        });
    });

    // A required field on a tab not showing - show that tab, so the browser can point at it
    form.addEventListener('invalid', function (e) {
        var pane = e.target.closest('.tab-pane');
        if (pane && !pane.classList.contains('active')) {
            var link = form.querySelector('[href="#' + pane.id + '"]');
            if (link) {
                bootstrap.Tab.getOrCreateInstance(link).show();
            }
        }
    }, true);
})();
</script>
