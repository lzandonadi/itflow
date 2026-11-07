<?php

/*
 * The guest side of a shared link (functions/share.php, js/guest_share.js) -
 * JSON in, JSON out, POST only. Every call carries the link's id and its
 * secret (key - and ek for a link made before 2.9.0), so it needs nothing
 * else to prove who may ask.
 *
 *   open        what the link is and what it needs - uses nothing up
 *   passphrase  check the passphrase
 *   send_code   email a 6-digit code to the recipient
 *   code        check the code
 *   reveal      the login, note or document - this uses a view
 *   totp        the current 2FA code, for the browser that revealed it
 *   destroy     end the link, for the browser that revealed it
 *   submit      a request answered - the login they send us
 *
 * Files come down through guest_download_file.php. What this browser has
 * passed (passphrase, code) and opened is kept in its session, for
 * SHARE_GRACE_MINUTES - opening again in that time uses no view.
 */

require_once "../config.php";
require_once "../includes/inc_set_timezone.php";
require_once "../functions.php";
require_once "../includes/load_global_settings.php";
require_once "../includes/session_init.php";
require_once "../libs/totp/totp.php";

header('Content-Type: application/json');
header('Cache-Control: no-store');
header('X-Robots-Tag: noindex, nofollow');

// logAudit() reads these - guest rows record where the request came from
$session_ip = escapeSql(getIP());
$session_user_agent = escapeSql($_SERVER['HTTP_USER_AGENT'] ?? '');
$session_user_id = 0;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit(json_encode(['error' => __('Not like that.')]));
}

$input = json_decode((string) file_get_contents('php://input'), true);
if (!is_array($input)) {
    $input = $_POST;
}
$action = (string) ($input['action'] ?? '');
$item_id = intval($input['id'] ?? 0);
$secret = (string) ($input['key'] ?? '');
$legacy_ek = (string) ($input['ek'] ?? '');

/** Stop, with the reason the link no longer works. */
function shareGuestGone($reason) {
    exit(json_encode(['state' => 'gone', 'reason' => $reason]));
}

/** Why a link in this state no longer works, in words for the person holding it. */
function shareGuestReason($row, $state) {
    $reasons = [
        'expired' => __('It expired %s.', __('%1$s at %2$s', formatDate($row['item_expire_at'], 'date_short'), formatDate($row['item_expire_at'], 'time'))),
        'used' => intval($row['item_view_limit']) === 1 ? __('It could be opened once, and it has been.') : __('It has been opened as many times as it allowed.'),
        'revoked' => __('It was turned off.'),
        'locked' => __('It was locked after too many wrong tries.'),
        'destroyed' => __('It was destroyed once it had been opened.'),
        'received' => __('It has already been used to send what was asked for.'),
        'saved' => __('It has already been used to send what was asked for.'),
        'discarded' => __('It was turned off.'),
    ];
    return $reasons[$state] ?? __('It may have expired, been used up or been turned off.');
}

/** "j***@example.com" - enough to know which address, not enough to harvest it. */
function shareGuestMaskEmail($email) {
    [$user, $domain] = array_pad(explode('@', (string) $email, 2), 2, '');
    return mb_substr($user, 0, 1) . str_repeat('*', max(3, min(8, mb_strlen($user) - 1))) . '@' . $domain;
}

/** "Works until Oct 17 at 9:00 AM. Opens once." */
function shareGuestLimits($row) {
    $limit = intval($row['item_view_limit']);
    $left = $limit - intval($row['item_views']);
    $text = __('Works until %s.', __('%1$s at %2$s', formatDate($row['item_expire_at'], 'date_short'), formatDate($row['item_expire_at'], 'time'))) . ' ';
    if ($row['item_type'] === 'Request') {
        return $text . __('It can be used once.');
    }
    if (!$limit) {
        return $text . __('It can be opened any number of times until then.');
    }
    if ($limit === 1) {
        return $text . __('It opens once.');
    }
    return $text . _n('It can be opened %d more time.', 'It can be opened %d more times.', $left);
}

/** What the person sees after it opens - whether it will open again. */
function shareGuestAfter($row, $again) {
    $row = shareRow($row['item_id']) ?: $row;
    $limit = intval($row['item_view_limit']);
    $left = $limit - intval($row['item_views']);
    $grace = SHARE_GRACE_MINUTES;
    if ($limit && $left <= 0) {
        return __('That was the last time this link opens - copy what you need now. On this device you can come back to it for %d minutes.', $grace);
    }
    if ($limit) {
        return _n('It can be opened %1$d more time before %2$s.', 'It can be opened %1$d more times before %2$s.', $left, $left, __('%1$s at %2$s', formatDate($row['item_expire_at'], 'date_short'), formatDate($row['item_expire_at'], 'time')));
    }
    return __('It keeps working until %s.', __('%1$s at %2$s', formatDate($row['item_expire_at'], 'date_short'), formatDate($row['item_expire_at'], 'time')));
}

/** Log it against the link, with this request's address - and tell whoever made it, when there is something to tell. */
function shareGuestRecord($row, $action, $description, $notify_title = '', $notify_details = '') {
    logAudit('Share', $action, escapeSql($description), intval($row['item_client_id']), intval($row['item_id']));
    if ($notify_title !== '') {
        // A login sent back is waiting on someone - that one is emailed too
        shareNotifyCreator($row, $notify_title, $notify_details . ' - ' . $row['client_name'], $action === 'Receive', $action === 'Lock');
    }
}

/** Who opened it, as far as we know - the recipient when they proved it with the emailed code. */
function shareGuestWho($row) {
    if (shareProtection($row) === 'code' && shareVerified($row)) {
        return ' ' . __('by %s (emailed code)', $row['item_recipient']);
    }
    if (shareProtection($row) === 'passphrase') {
        return ' ' . __('with the passphrase');
    }
    return '';
}

/** The link's details for the page, before anything is revealed. */
function shareGuestOpen($row) {
    $company = shareCompany();
    $verified = shareVerified($row);
    $type = $row['item_type'];
    $from = trim((string) $row['creator_name']) !== '' ? __('%1$s at %2$s', $row['creator_name'], $company['name']) : $company['name'];
    $sent = [
        'Credential' => __('%s sent you login details.', $from),
        'Note' => __('%s sent you a secure note.', $from),
        'Document' => __('%s sent you a document.', $from),
        'File' => __('%s sent you a file.', $from),
    ][$type] ?? __('%s sent you something.', $from);

    $response = [
        'state' => 'ready',
        'type' => $type,
        'protect' => shareProtection($row),
        'verified' => $verified,
        'title' => $verified ? shareName($row) : __('Something from %s', $company['name']),
        'intro' => $type === 'Request' ? __('%s is asking you to send a login.', $from) : $sent,
    ];
    if ($response['protect'] === 'code') {
        $response['masked_email'] = shareGuestMaskEmail($row['item_recipient']);
    }
    if (!$verified) {
        return $response;
    }

    $again = shareOpenedHere($row);
    $response['note'] = (string) $row['item_note'];
    $response['limits'] = $again ? __('You opened this a moment ago on this device - opening it again does not use it up.') : shareGuestLimits($row);
    if ($type === 'Request') {
        $response['title'] = __('Send us: %s', shareName($row));
        $response['intro'] = __('%s is asking you for this login. Fill in what you have - it is encrypted and goes straight to them, never by email.', $from);
    } elseif (!$again && intval($row['item_view_limit']) === 1 && in_array($type, ['Credential', 'Note'], true)) {
        $response['hint'] = __('It can only be shown once - have somewhere safe ready to put it, like your password manager.');
    }
    if ($type === 'File') {
        global $mysqli;
        $file = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT file_name, file_size, file_ext FROM files WHERE file_id = " . intval($row['item_related_id']) . " AND file_client_id = " . intval($row['item_client_id']) . " LIMIT 1"));
        if (!$file) {
            shareGuestGone(__('The file it shared has been removed.'));
        }
        $response['file_name'] = $file['file_name'];
        $response['title'] = $file['file_name'];
        $response['intro'] = __('%1$s sent you a file - %2$s.', $from, formatBytes(intval($file['file_size'])));
    }
    return $response;
}

// Secrets no longer needed go first, then find the link
shareSweep();
$row = shareFind($item_id, $secret);
if (!$row) {
    shareGuestGone(__('The link is not right - check you have all of it, including everything after the #.'));
}
$state = shareState($row);
$again = shareOpenedHere($row);

// Still usable: open, or used up but opened on this device a moment ago
$usable = in_array($state, ['active', 'waiting'], true) || ($state === 'used' && $again);
if (!$usable && !in_array($action, ['totp'], true)) {
    shareGuestGone(shareGuestReason($row, $state));
}

if ($action === 'open') {
    exit(json_encode(shareGuestOpen($row)));
}

if ($action === 'passphrase') {
    if (shareProtection($row) !== 'passphrase') {
        exit(json_encode(shareGuestOpen($row)));
    }
    $passphrase = trim((string) ($input['passphrase'] ?? ''));
    if (!shareTakeAttempt($row['item_id'])) {
        shareGuestGone(shareGuestReason($row, 'locked'));
    }
    if ($passphrase !== '' && password_verify($passphrase, $row['item_passphrase'])) {
        mysqli_query($mysqli, "UPDATE shared_items SET item_failed_attempts = 0 WHERE item_id = " . intval($row['item_id']));
        shareSessionMark($row['item_id'], 'verified');
        shareGuestRecord($row, 'Verify', 'The passphrase was entered'); // i18n-ignore - the share history stays English
        exit(json_encode(shareGuestOpen(shareRow($row['item_id']))));
    }
    $attempts = intval(mysqli_fetch_row(mysqli_query($mysqli, "SELECT item_failed_attempts FROM shared_items WHERE item_id = " . intval($row['item_id'])))[0] ?? SHARE_ATTEMPTS);
    shareGuestRecord($row, 'Fail', 'A wrong passphrase was entered'); // i18n-ignore - the share history stays English
    if ($attempts >= SHARE_ATTEMPTS) {
        shareEnd($row['item_id'], 'Locked');
        shareGuestRecord($row, 'Lock', 'Locked after ' . SHARE_ATTEMPTS . ' wrong passphrases', __('Shared link locked'), __('%1$s locked after %2$d wrong passphrases', shareName($row), SHARE_ATTEMPTS)); // i18n-ignore - the share history stays English
        shareGuestGone(shareGuestReason($row, 'locked'));
    }
    $left = SHARE_ATTEMPTS - $attempts;
    exit(json_encode(['error' => _n('That passphrase is not right. %d more try before the link locks.', 'That passphrase is not right. %d more tries before the link locks.', $left)]));
}

if ($action === 'send_code') {
    if (shareProtection($row) !== 'code') {
        exit(json_encode(shareGuestOpen($row)));
    }
    if ($row['item_code_sent_at'] && strtotime($row['item_code_sent_at']) > time() - SHARE_CODE_RESEND_SECONDS) {
        exit(json_encode(['error' => __('A code was just sent - give it a minute to arrive before asking for another.')]));
    }
    if (!shareMailReady()) {
        exit(json_encode(['error' => __('The code could not be sent - ask %s for a passphrase instead.', shareCompany()['name'])]));
    }
    $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    mysqli_query($mysqli, "UPDATE shared_items SET item_code = '" . escapeSql(password_hash($code, PASSWORD_DEFAULT)) . "', item_code_sent_at = NOW() WHERE item_id = " . intval($row['item_id']));
    shareEmailCode($row, $code);
    shareGuestRecord($row, 'Code', 'A code was emailed to ' . $row['item_recipient']); // i18n-ignore - the share history stays English
    exit(json_encode(['sent' => true]));
}

if ($action === 'code') {
    if (shareProtection($row) !== 'code') {
        exit(json_encode(shareGuestOpen($row)));
    }
    $code = preg_replace('/\D/', '', (string) ($input['code'] ?? ''));
    if (empty($row['item_code']) || !$row['item_code_sent_at'] || strtotime($row['item_code_sent_at']) < time() - SHARE_CODE_MINUTES * 60) {
        exit(json_encode(['error' => __('That code has run out - send another.')]));
    }
    if (!shareTakeAttempt($row['item_id'])) {
        shareGuestGone(shareGuestReason($row, 'locked'));
    }
    if (strlen($code) === 6 && password_verify($code, $row['item_code'])) {
        mysqli_query($mysqli, "UPDATE shared_items SET item_failed_attempts = 0, item_code = NULL WHERE item_id = " . intval($row['item_id']));
        shareSessionMark($row['item_id'], 'verified');
        shareGuestRecord($row, 'Verify', $row['item_recipient'] . ' entered the emailed code'); // i18n-ignore - the share history stays English
        exit(json_encode(shareGuestOpen(shareRow($row['item_id']))));
    }
    $attempts = intval(mysqli_fetch_row(mysqli_query($mysqli, "SELECT item_failed_attempts FROM shared_items WHERE item_id = " . intval($row['item_id'])))[0] ?? SHARE_ATTEMPTS);
    shareGuestRecord($row, 'Fail', 'A wrong code was entered'); // i18n-ignore - the share history stays English
    if ($attempts >= SHARE_ATTEMPTS) {
        shareEnd($row['item_id'], 'Locked');
        shareGuestRecord($row, 'Lock', 'Locked after ' . SHARE_ATTEMPTS . ' wrong codes', __('Shared link locked'), __('%1$s locked after %2$d wrong codes', shareName($row), SHARE_ATTEMPTS)); // i18n-ignore - the share history stays English
        shareGuestGone(shareGuestReason($row, 'locked'));
    }
    $left = SHARE_ATTEMPTS - $attempts;
    exit(json_encode(['error' => _n('That code is not right. %d more try before the link locks.', 'That code is not right. %d more tries before the link locks.', $left)]));
}

// Everything below needs the passphrase or code passed, when the link has one
if (!shareVerified($row)) {
    exit(json_encode(shareGuestOpen($row)));
}

if ($action === 'reveal') {
    $type = $row['item_type'];
    if (!in_array($type, ['Credential', 'Note', 'Document'], true)) {
        exit(json_encode(['error' => __('Nothing to show here.')]));
    }

    // Read what it shows before using a view, so a link whose record has gone is not used up
    $key = shareContentKey($secret);
    $content = ['state' => 'shown', 'type' => $type];
    if ($type === 'Credential' && intval($row['item_version']) >= 2) {
        $data = shareUnseal($row['item_payload'], $key);
        if (!$data) {
            shareGuestGone(shareGuestReason($row, $state === 'active' ? 'revoked' : $state));
        }
        $content += ['name' => $data['name'], 'uri' => $data['uri'], 'username' => $data['username'], 'password' => $data['password'], 'has_otp' => $data['otp'] !== ''];
    } elseif ($type === 'Credential') {
        // A link made before 2.9.0 - the login under the ek in the link, the rest live
        $credential = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT credential_name, credential_uri, credential_otp_secret FROM credentials WHERE credential_id = " . intval($row['item_related_id']) . " AND credential_client_id = " . intval($row['item_client_id']) . " LIMIT 1"));
        $legacy = function ($stored) use ($legacy_ek) {
            $stored = (string) $stored;
            return $stored === '' || $legacy_ek === '' ? false : openssl_decrypt(substr($stored, 16), 'aes-128-cbc', $legacy_ek, 0, substr($stored, 0, 16));
        };
        $username = $legacy($row['item_encrypted_username']);
        $password = $legacy($row['item_encrypted_credential']);
        if (!$credential || $password === false || $username === false) {
            shareGuestGone(__('The login it shared is no longer there - ask for a new link.'));
        }
        $content += ['name' => $credential['credential_name'], 'uri' => $credential['credential_uri'], 'username' => $username, 'password' => $password, 'has_otp' => (string) credentialOtpSeed($credential['credential_otp_secret']) !== ''];
    } elseif ($type === 'Note') {
        $data = shareUnseal($row['item_payload'], $key);
        if (!$data) {
            shareGuestGone(shareGuestReason($row, $state === 'active' ? 'revoked' : $state));
        }
        $content += ['name' => shareName($row), 'text' => $data['text']];
    } else {
        $document = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT document_name, document_content FROM documents WHERE document_id = " . intval($row['item_related_id']) . " AND document_client_id = " . intval($row['item_client_id']) . " LIMIT 1"));
        if (!$document) {
            shareGuestGone(__('The document it shared has been removed.'));
        }
        $content += ['name' => $document['document_name'], 'html' => documentHtmlPurify($document['document_content'])];
    }

    if (!$again) {
        // The claim - racing requests cannot both win the last view
        if (!shareClaim($row['item_id'])) {
            shareGuestGone(shareGuestReason($row, 'used'));
        }
        shareSessionMark($row['item_id'], 'opened');
        $label = shareTypes()[$type]['label'];
        shareGuestRecord($row, 'Open', 'Opened' . i18nEnglish(fn () => shareGuestWho($row)), __('Shared link opened'), __('%1$s %2$s was opened', $label, shareName($row)) . shareGuestWho($row));
    }
    $content['after'] = shareGuestAfter($row, $again);
    exit(json_encode($content));
}

if ($action === 'totp') {
    // Only for the browser that revealed it, and only while the link has not been ended or expired
    if ($row['item_type'] !== 'Credential' || !$again || !in_array($state, ['active', 'used'], true)) {
        exit(json_encode(['error' => 'ended']));
    }
    if (intval($row['item_version']) >= 2) {
        $data = shareUnseal($row['item_payload'], shareContentKey($secret));
        $seed = $data['otp'] ?? '';
    } else {
        $seed = (string) credentialOtpSeed(mysqli_fetch_row(mysqli_query($mysqli, "SELECT credential_otp_secret FROM credentials WHERE credential_id = " . intval($row['item_related_id']) . " AND credential_client_id = " . intval($row['item_client_id']) . " LIMIT 1"))[0] ?? '');
    }
    if ($seed === '') {
        exit(json_encode(['error' => 'ended']));
    }
    exit(json_encode(['code' => TokenAuth6238::getTokenCode($seed), 'expires_in' => 30 - (time() % 30)]));
}

if ($action === 'destroy') {
    if (!$again) {
        exit(json_encode(['error' => __('Open it first.')]));
    }
    if (shareEnd($row['item_id'], 'Destroyed')) {
        shareGuestRecord($row, 'Destroy', 'Destroyed by the person who opened it'); // i18n-ignore - the share history stays English
    }
    unset($_SESSION['itflow_shares'][intval($row['item_id'])]);
    exit(json_encode(['state' => 'destroyed']));
}

if ($action === 'submit') {
    if ($row['item_type'] !== 'Request' || $state !== 'waiting') {
        shareGuestGone(shareGuestReason($row, $state));
    }
    $fields = [
        'uri' => trim((string) ($input['uri'] ?? '')),
        'username' => trim((string) ($input['username'] ?? '')),
        'password' => (string) ($input['password'] ?? ''),
        'otp' => trim((string) ($input['otp'] ?? '')),
        'note' => trim((string) ($input['note'] ?? '')),
    ];
    $limits = ['uri' => 500, 'username' => 350, 'password' => 350, 'otp' => 200, 'note' => 2000];
    foreach ($limits as $field => $max) {
        if (mb_strlen($fields[$field]) > $max) {
            exit(json_encode(['error' => __('Something you typed is too long - keep each field shorter.')]));
        }
    }
    if ($fields['username'] === '' && $fields['password'] === '') {
        exit(json_encode(['error' => __('Fill in the username, the password, or both.')]));
    }
    if ($fields['otp'] !== '' && credentialOtpSeed($fields['otp']) === false) {
        exit(json_encode(['error' => __('That 2FA secret does not look right - it is the key shown under "can\'t scan the code", or leave it empty.')]));
    }
    if (!shareClaim($row['item_id'])) {
        shareGuestGone(shareGuestReason($row, 'used'));
    }
    $payload = shareSeal($fields, shareContentKey($secret));
    mysqli_query($mysqli, "UPDATE shared_items SET item_payload = '" . escapeSql($payload) . "', item_received_at = NOW() WHERE item_id = " . intval($row['item_id']));
    $row = shareRow($row['item_id']);
    shareGuestRecord($row, 'Receive', 'The login was sent back', __('Login sent back'), __('%s was sent back - save it to the credentials', shareName($row))); // i18n-ignore - the share history stays English
    exit(json_encode(['state' => 'sent']));
}

exit(json_encode(['error' => __('Not something this link does.')]));
