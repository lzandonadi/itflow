<?php

/*
 * Your account > Security - your password, multi-factor authentication, and
 * the browsers that skip your MFA code. Changing the password or turning MFA
 * off takes your current password; you are emailed either way.
 */

require_once "includes/inc_all_user.php";

$me = userRow($session_user_id);
$has_mfa = trim((string) $me['user_token']) !== '';
$forced = intval($me['user_config_force_mfa']) === 1;
$browsers = userRememberedBrowsers($session_user_id);
$remember_days = max(1, intval($config_login_remember_me_expire));

?>

<div class="row g-3">

    <!-- Password -->
    <div class="col-xl-6">
        <div class="card h-100 mb-0">
            <div class="card-header bg-dark">
                <h3 class="card-title"><i class="fas fa-fw fa-key me-2"></i><?= __('Password') ?></h3>
            </div>
            <form action="post.php" method="post" autocomplete="off" id="passwordForm">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label"><?= __('Current password') ?> <strong class="text-danger">*</strong></label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-fw fa-lock"></i></span>
                            <input type="password" class="form-control" data-toggle="password" name="current_password" autocomplete="current-password" required>
                            <span class="input-group-text"><i class="fa fa-fw fa-eye"></i></span>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label"><?= __('New password') ?> <strong class="text-danger">*</strong></label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-fw fa-key"></i></span>
                            <input type="password" class="form-control" data-toggle="password" name="new_password" placeholder="<?= escapeHtml(__('At least %d characters', USER_PASSWORD_MIN)) ?>" minlength="<?= USER_PASSWORD_MIN ?>" autocomplete="new-password" required data-password-new>
                            <span class="input-group-text"><i class="fa fa-fw fa-eye"></i></span>
                        </div>
                    </div>
                    <div>
                        <label class="form-label"><?= __('New password, again') ?> <strong class="text-danger">*</strong></label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-fw fa-check-double"></i></span>
                            <input type="password" class="form-control" name="confirm_password" minlength="<?= USER_PASSWORD_MIN ?>" autocomplete="new-password" required data-password-confirm>
                        </div>
                        <div class="form-text" data-password-match><?= __('You\'re signed out once it\'s changed, to sign in with the new one.') ?></div>
                    </div>
                </div>
                <div class="card-footer">
                    <button type="submit" name="edit_your_user_password" class="btn btn-primary"><i class="fas fa-check me-2"></i><?= __('Change password') ?></button>
                </div>
            </form>
        </div>
    </div>

    <!-- MFA -->
    <div class="col-xl-6">
        <div class="card h-100 mb-0">
            <div class="card-header bg-dark">
                <h3 class="card-title"><i class="fas fa-fw fa-mobile-alt me-2"></i><?= __('Multi-factor authentication') ?></h3>
            </div>
            <div class="card-body">
                <div class="d-flex align-items-start gap-3">
                    <span class="itflow-mfa-state <?= $has_mfa ? 'is-on' : 'is-off' ?>"><i class="fas <?= $has_mfa ? 'fa-lock' : 'fa-unlock' ?>"></i></span>
                    <div class="min-w-0">
                        <div class="fw-semibold"><?= $has_mfa ? _x('On', 'MFA') : _x('Off', 'MFA') ?><?= $forced ? ' <span class="badge rounded-pill bg-secondary-subtle text-secondary-emphasis fw-normal ms-1">' . __('Required by your administrator') . '</span>' : '' ?></div>
                        <div class="small text-body-secondary">
                            <?php if ($has_mfa) { ?>
                                <?= __('Signing in takes your password and a code from your authenticator app.') ?>

                            <?php } else { ?>
                                <?= __("Signing in takes only your password. With MFA it also takes a code from an app on your phone - so a stolen password alone doesn't get anyone in.") ?>

                            <?php } ?>
                        </div>
                    </div>
                </div>

                <?php if ($has_mfa && !$forced) { ?>
                    <div class="collapse mt-3" id="disableMfa">
                        <form action="post.php" method="post" autocomplete="off" class="border rounded p-3">
                            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                            <label class="form-label"><?= __('Your password') ?> <strong class="text-danger">*</strong></label>
                            <div class="input-group mb-2">
                                <span class="input-group-text"><i class="fas fa-fw fa-lock"></i></span>
                                <input type="password" class="form-control" data-toggle="password" name="current_password" placeholder="<?= escapeHtml(__('To turn MFA off')) ?>" autocomplete="current-password" required>
                                <span class="input-group-text"><i class="fa fa-fw fa-eye"></i></span>
                            </div>
                            <button type="submit" name="disable_your_mfa" class="btn btn-danger btn-sm"><i class="fas fa-unlock me-2"></i><?= __('Turn MFA off') ?></button>
                        </form>
                    </div>
                <?php } ?>
            </div>
            <div class="card-footer">
                <?php if (!$has_mfa) { ?>
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#enableMFAModal"><i class="fas fa-lock me-2"></i><?= __('Turn on MFA') ?></button>
                <?php } elseif (!$forced) { ?>
                    <button type="button" class="btn btn-outline-danger" data-bs-toggle="collapse" data-bs-target="#disableMfa" aria-expanded="false"><i class="fas fa-unlock me-2"></i><?= __('Turn off') ?></button>
                    <span class="small text-body-secondary ms-2"><?= __('New phone? Turn it off, then on again with the new one.') ?></span>
                <?php } else { ?>
                    <span class="small text-body-secondary"><?= __('New phone? Ask an administrator to reset your MFA - you set it up again at your next sign in.') ?></span>
                <?php } ?>
            </div>
        </div>
    </div>

    <!-- Remembered browsers -->
    <div class="col-12">
        <div class="card mb-0">
            <div class="card-header bg-dark">
                <h3 class="card-title"><i class="fas fa-fw fa-laptop me-2"></i><?= __('Remembered browsers') ?></h3>
                <?php if (count($browsers) > 1) { ?>
                    <div class="card-tools">
                        <form action="post.php" method="post">
                            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                            <button type="submit" name="revoke_your_2fa_remember_tokens" class="btn btn-sm btn-outline-light"><i class="fas fa-times me-2"></i><?= __('Forget all') ?></button>
                        </form>
                    </div>
                <?php } ?>
            </div>
            <?php if ($browsers) { ?>
                <ul class="list-group list-group-flush">
                    <?php foreach ($browsers as $browser) {
                        $login = $browser['login'];
                        ?>
                        <li class="list-group-item d-flex align-items-center gap-3">
                            <i class="fas fa-fw fa-laptop fa-lg text-body-secondary"></i>
                            <div class="flex-grow-1 min-w-0">
                                <div>
                                    <?= $login ? userDeviceHtml($login['log_user_agent']) : '<span class="text-body-secondary">' . __('A browser') . '</span>' ?>
                                    <?php if ($browser['is_current']) { ?><span class="badge rounded-pill bg-primary-subtle text-primary-emphasis fw-normal ms-1"><?= __('This browser') ?></span><?php } ?>
                                </div>
                                <div class="small text-body-secondary">
                                    <?php
                                    $remembered_at = '<span title="' . escapeHtml($browser['remember_token_created_at']) . '">' . escapeHtml(timeAgo($browser['remember_token_created_at'])) . '</span>';
                                    $remembered_until = escapeHtml(formatDate($browser['remember_token_expires_at'], 'date_short'));
                                    echo $login
                                        ? __('Remembered %1$s from %2$s · skips the code until %3$s', $remembered_at, '<span class="font-monospace">' . escapeHtml($login['log_ip']) . '</span>', $remembered_until)
                                        : __('Remembered %1$s · skips the code until %2$s', $remembered_at, $remembered_until);
                                    ?>

                                </div>
                            </div>
                            <form action="post.php" method="post">
                                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                <button type="submit" name="revoke_your_remember_token" value="<?= intval($browser['remember_token_id']) ?>" class="btn btn-sm btn-outline-secondary" title="<?= escapeHtml(__('It asks for your code at the next sign in')) ?>"><?= __('Forget') ?></button>
                            </form>
                        </li>
                    <?php } ?>
                </ul>
            <?php } else { ?>
                <div class="card-body small text-body-secondary">
                    <?php if ($has_mfa) { ?>
                        <?= _n('None. Tick <strong>Remember me</strong> when you enter your code at sign in, and that browser skips the code for %d day.', 'None. Tick <strong>Remember me</strong> when you enter your code at sign in, and that browser skips the code for %d days.', intval($remember_days)) ?>

                    <?php } else { ?>
                        <?= __("None - browsers are remembered once MFA is on, so you're not asked for a code every time.") ?>

                    <?php } ?>
                </div>
            <?php } ?>
        </div>
    </div>

</div>

<?php if (!$has_mfa) {
    require_once "modals/user_mfa_modal.php";
} ?>

<script>
(function () {
    // The new password, typed twice - said as soon as they match, or don't
    var form = document.getElementById('passwordForm');
    var next = form.querySelector('[data-password-new]');
    var again = form.querySelector('[data-password-confirm]');
    var hint = form.querySelector('[data-password-match]');
    var hintText = hint.textContent;
    function check() {
        var differs = again.value !== '' && again.value !== next.value;
        again.setCustomValidity(differs ? __('The two new passwords are not the same') : '');
        again.classList.toggle('is-invalid', differs);
        again.classList.toggle('is-valid', again.value !== '' && !differs && next.value.length >= <?= USER_PASSWORD_MIN ?>);
        hint.textContent = differs ? __('Not the same as the new password above.') : hintText;
        hint.classList.toggle('text-danger', differs);
    }
    next.addEventListener('input', check);
    again.addEventListener('input', check);
})();
</script>

<?php
// A code that didn't match - open the set up again, with the same secret
if (!empty($_SESSION['show_mfa_modal']) && !$has_mfa) { ?>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            bootstrap.Modal.getOrCreateInstance(document.getElementById('enableMFAModal')).show();
        });
    </script>
<?php }
unset($_SESSION['show_mfa_modal']);

require_once "../../includes/footer.php";
