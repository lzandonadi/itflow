<?php
/*
 * Client Portal
 * Joining - from an invite (ours, or a colleague's in the portal) or the link
 * to finish signing up: ?invite=&token=. The person puts in their name, job
 * title, phone and a password - or, when Microsoft 365 sign-in is set up,
 * chooses that instead - and is made a contact who can sign in
 * (portalInviteAccept(), functions/portal_invite.php). Then they sign in on
 * the usual page. A link that has run out, been used or cancelled says so,
 * and what to do instead.
 */

header("Content-Security-Policy: default-src 'self'; img-src 'self' data:");
header("X-Frame-Options: DENY");
header("Referrer-Policy: no-referrer");

require_once '../config.php';
require_once '../functions.php';
require_once '../includes/load_global_settings.php';

if (intval($config_client_portal_enable) !== 1 || !portalInvitesReady()) {
    header("Location: /login.php");
    exit();
}

require_once __DIR__ . "/../includes/session_init.php";
require_once "../includes/inc_set_timezone.php";

// Nobody is signed in here - the history notes where it came from
$session_ip = escapeSql(getIP());
$session_user_agent = escapeSql($_SERVER['HTTP_USER_AGENT'] ?? '');
$session_user_id = 0;

$company = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT company_name, company_logo FROM companies WHERE company_id = 1"));
$company_name = (string) ($company['company_name'] ?? '');
$methods = portalSignInMethods();

$invite_id = intval($_POST['invite'] ?? ($_GET['invite'] ?? 0));
$token = $_POST['token'] ?? ($_GET['token'] ?? '');
$token = is_string($token) ? $token : '';
$link = portalInviteByLink($invite_id, $token);
$invite = $link['row'];
$error = '';
$in = ['name' => '', 'title' => '', 'phone' => ''];

if ($invite && $link['state'] === 'pending') {
    $who = portalInviteWho($invite['invite_email'], $invite['invite_client_id']);
    $in['name'] = trim((string) $invite['invite_name']) !== '' ? (string) $invite['invite_name'] : (string) ($who['name'] ?? '');

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['portal_join'])) {
        $in = [
            'name' => (string) ($_POST['name'] ?? ''),
            'title' => (string) ($_POST['title'] ?? ''),
            'phone' => (string) ($_POST['phone'] ?? ''),
        ];
        $method = (string) ($_POST['method'] ?? 'local');
        $contact_id = portalInviteAccept($invite, $in + [
            'method' => $method,
            'password' => (string) ($_POST['new_password'] ?? ''),
            'confirm' => (string) ($_POST['confirm_password'] ?? ''),
        ], $error);
        if ($contact_id) {
            $first = strtok(trim(preg_replace('/\s+/', ' ', $in['name'])), ' ');
            $_SESSION['login_message'] = isset($methods[$method]) && $method === 'azure'
                ? __('Welcome, %s - your account is ready. Sign in with your Microsoft 365 account below.', $first)
                : __('Welcome, %1$s - your account is ready. Sign in with %2$s and your new password.', $first, $invite['invite_email']);
            $_SESSION['login_message_type'] = 'success';
            header("Location: /login.php");
            exit();
        }
        // Used in the meantime - say so rather than show the form again
        $link = portalInviteByLink($invite_id, $token);
        $invite = $link['row'];
    }
}

$signup = $invite && $invite['invite_kind'] === 'signup';
$by = $invite ? portalInviteBy($invite) : '';
$ask = $by !== '' ? __('Ask %s to send it again.', $by) : __("Ask your company's primary contact or us to invite you.");
$notes = [
    'gone' => [__("That link doesn't work"), __('It may have been copied only in part - or a newer email replaced it, so use the link in the latest one.')],
    'expired' => [__('That link has run out'), $signup ? _n('Links to finish signing up work for %d hour - ask for a new one.', 'Links to finish signing up work for %d hours - ask for a new one.', PORTAL_SIGNUP_LINK_HOURS) : _n('Invites work for %d day.', 'Invites work for %d days.', PORTAL_INVITE_LINK_DAYS) . " $ask"],
    'accepted' => [__("You've joined already"), (isset($invite['invite_email']) ? __('Sign in with %s.', $invite['invite_email']) : __('Sign in with your email address.')) . ' ' . __('Forgot your password? Choose Forgot password on the sign-in page.')],
    'cancelled' => [__('That invite was cancelled'), $ask],
];
$state = $link['state'];
$title = $state === 'pending' ? ($signup ? __('Finish signing up') : __('Join %s', $invite['client_name'])) : $notes[$state][0];
?>
<!DOCTYPE html>
<html <?= i18nHtmlAttributes() ?> data-bs-theme="light" data-lte-color-mode="off">
<head>
    <meta charset="utf-8">
    <title><?= escapeHtml($company_name) ?> | <?= escapeHtml($title) ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <link rel="stylesheet" href="/libs/fontawesome-free/css/all.min.css">
    <?php if (file_exists(__DIR__ . '/../uploads/favicon.ico')) { ?>
        <link rel="icon" type="image/x-icon" href="/uploads/favicon.ico">
    <?php } ?>
    <link rel="stylesheet" href="/libs/adminlte/css/adminlte.min.css">
    <link rel="stylesheet" href="/css/itflow_custom.css">
<script src="/js/i18n.js"></script>
</head>
<body class="hold-transition login-page">

<div class="login-box itflow-join-box">
    <div class="login-logo">
        <?php if (!empty($company['company_logo'])) { ?>
            <img alt="<?= escapeHtml(__('%s logo', $company_name)) ?>" height="110" width="380" class="img-fluid" src="/uploads/settings/<?= escapeHtml($company['company_logo']) ?>">
        <?php } else { ?>
            <span class="fw-semibold"><?= escapeHtml($company_name) ?></span>
        <?php } ?>
    </div>

    <div class="card">
        <div class="card-body login-card-body">

            <?php if ($state === 'pending') { ?>

                <h1 class="h5 mb-1" data-join="<?= $signup ? 'signup' : 'invite' ?>"><?= escapeHtml($title) ?></h1>
                <p class="text-body-secondary small mb-3">
                    <?php if ($signup) { ?>
                        <?= __('Your client portal account for %1$s, as <strong>%2$s</strong>.', escapeHtml($invite['client_name']), escapeHtml($invite['invite_email'])) ?>
                    <?php } else { ?>
                        <?= $by !== ''
                            ? __('%1$s invited <strong>%2$s</strong> to the %3$s client portal - where you raise and follow support tickets.', escapeHtml($by), escapeHtml($invite['invite_email']), escapeHtml($company_name))
                            : __('You are invited as <strong>%1$s</strong> to the %2$s client portal - where you raise and follow support tickets.', escapeHtml($invite['invite_email']), escapeHtml($company_name)) ?>
                    <?php } ?>
                </p>

                <?php if ($error !== '') { ?>
                    <div class="alert alert-danger small" data-join-error><?= escapeHtml($error) ?></div>
                <?php } ?>

                <form method="post" autocomplete="off">
                    <input type="hidden" name="invite" value="<?= intval($invite['invite_id']) ?>">
                    <input type="hidden" name="token" value="<?= escapeHtml($token) ?>">
                    <input type="hidden" name="portal_join" value="1">
                    <input type="hidden" name="method" value="local">
                    <input type="text" class="d-none" name="username" value="<?= escapeHtml($invite['invite_email']) ?>" autocomplete="username" aria-hidden="true" tabindex="-1">

                    <label class="form-label small mb-1" for="joinName"><?= __('Your name') ?> <strong class="text-danger">*</strong></label>
                    <div class="input-group mb-3">
                        <span class="input-group-text"><i class="fas fa-fw fa-user"></i></span>
                        <input type="text" class="form-control" id="joinName" name="name" value="<?= escapeHtml($in['name']) ?>" maxlength="200" autocomplete="name" required<?= $in['name'] === '' ? ' autofocus' : '' ?>>
                    </div>
                    <div class="row g-2">
                        <div class="col-sm-6">
                            <label class="form-label small mb-1" for="joinTitle"><?= __('Job title') ?></label>
                            <div class="input-group mb-3">
                                <span class="input-group-text"><i class="fas fa-fw fa-id-badge"></i></span>
                                <input type="text" class="form-control" id="joinTitle" name="title" value="<?= escapeHtml($in['title']) ?>" maxlength="200" autocomplete="organization-title">
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <label class="form-label small mb-1" for="joinPhone"><?= __('Phone') ?></label>
                            <div class="input-group mb-3">
                                <span class="input-group-text"><i class="fas fa-fw fa-phone"></i></span>
                                <input type="tel" class="form-control" id="joinPhone" name="phone" value="<?= escapeHtml($in['phone']) ?>" maxlength="40" autocomplete="tel">
                            </div>
                        </div>
                    </div>

                    <label class="form-label small mb-1" for="joinPassword"><?= __('Choose a password') ?> <span class="text-body-secondary"><?= __('- at least %d characters', USER_PASSWORD_MIN) ?></span></label>
                    <div class="input-group mb-3">
                        <span class="input-group-text"><i class="fas fa-fw fa-lock"></i></span>
                        <input type="password" class="form-control" id="joinPassword" name="new_password" minlength="<?= USER_PASSWORD_MIN ?>" autocomplete="new-password" required<?= $in['name'] !== '' ? ' autofocus' : '' ?>>
                    </div>
                    <div class="input-group mb-3">
                        <span class="input-group-text"><i class="fas fa-fw fa-check-double"></i></span>
                        <input type="password" class="form-control" name="confirm_password" minlength="<?= USER_PASSWORD_MIN ?>" autocomplete="new-password" placeholder="<?= escapeHtml(__('The same again')) ?>" aria-label="<?= escapeHtml(__('The password again')) ?>" required>
                    </div>

                    <button type="submit" class="btn btn-primary w-100 mb-2"><i class="fas fa-check me-2"></i><?= $signup ? __('Create my account') : __('Join') ?></button>
                    <?php if (isset($methods['azure'])) { ?>
                        <div class="text-center small text-body-secondary my-2"><?= __('or, with no password here') ?></div>
                        <button type="submit" class="btn btn-outline-secondary w-100 mb-3" name="method" value="azure" formnovalidate data-join-microsoft><i class="fab fa-microsoft me-2"></i><?= __('Use my Microsoft 365 account') ?></button>
                    <?php } ?>
                </form>

            <?php } else { ?>

                <h1 class="h5 mb-1" data-join-state="<?= escapeHtml($state) ?>"><?= escapeHtml($title) ?></h1>
                <p class="text-body-secondary small mb-3"><?= escapeHtml($notes[$state][1]) ?></p>
                <?php if ($state === 'accepted') { ?>
                    <a class="btn btn-primary w-100 mb-3" href="/login.php"><i class="fas fa-sign-in-alt me-2"></i><?= __('Sign in') ?></a>
                <?php } elseif ($signup && portalSignupOpen()) { ?>
                    <a class="btn btn-primary w-100 mb-3" href="/client/signup.php"><i class="fas fa-paper-plane me-2"></i><?= __('Ask for a new link') ?></a>
                <?php } ?>

            <?php } ?>

            <a href="/login.php"><i class="fas fa-arrow-left me-1"></i><?= __('Back to sign in') ?></a>

        </div>
    </div>
</div>

<script src="/js/login_prevent_resubmit.js"></script>

</body>
</html>
