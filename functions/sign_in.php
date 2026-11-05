<?php

/*
 * ITFlow - signing in, watched. Why a sign in failed (signInFailureReason())
 * and whose account it was, so the audit log says so and the failures count
 * against the account; which sign ins were the first from an IP address
 * (signInNewIpIds()); and telling the admins about a run of failures from one
 * address or against one account (signInWatch()) - how many is Admin >
 * Settings > Security (config_login_alert_failures, 0 for never). login.php,
 * client/login_microsoft.php and api/v1/validate_api_key.php write it; Admin >
 * Sign-ins, the audit log and Your account > Activity read it.
 */

/** How long a run of failures is counted over, and how many from one IP address login.php turns away after. */
const SIGN_IN_WINDOW_MINUTES = 10;
const SIGN_IN_LOCKOUT = 15;

/** The kinds of audit log entry that are signing in, and the actions that are a failed one. */
const SIGN_IN_TYPES = ['Login', 'Client Login'];
const SIGN_IN_FAILED = ['Failed', 'MFA Failed'];

/** The choices for when admins are told - failures in SIGN_IN_WINDOW_MINUTES => label. */
function signInAlertOptions() {
    return [0 => __('Never'), 5 => __('After %d failed sign-ins', 5), 10 => __('After %d failed sign-ins', 10), 20 => __('After %d failed sign-ins', 20), 50 => __('After %d failed sign-ins', 50)];
}

/** How many failed sign-ins in a row tell the admins - 0 for never. Read once a request; 0 before the setting exists. */
function signInAlertLimit() {
    global $mysqli;
    static $limit = null;
    if ($limit === null) {
        try {
            $row = mysqli_fetch_row(mysqli_query($mysqli, "SELECT config_login_alert_failures FROM settings WHERE company_id = 1"));
            $limit = max(0, intval($row[0] ?? 0));
        } catch (Throwable $e) {
            $limit = 0;
        }
    }
    return $limit;
}

/*
 * Why a password didn't get someone in as $email (raw) - the account it
 * belongs to, if any: ['user_id', 'client_id', 'name', 'type' (Login for an
 * agent or no account, Client Login for a portal contact), 'reason']. An
 * agent's account comes before a portal one with the same email.
 */
function signInFailureReason($email) {
    global $mysqli;
    $email_sql = escapeSql(trim((string) $email));
    $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT users.user_id, user_name, user_type, user_status, user_archived_at, contact_client_id, client_archived_at
        FROM users
        LEFT JOIN contacts ON contact_user_id = users.user_id
        LEFT JOIN clients ON client_id = contact_client_id
        WHERE user_email = '$email_sql'
        ORDER BY user_type ASC, users.user_id ASC LIMIT 1"));
    if (!$row) {
        return ['user_id' => 0, 'client_id' => 0, 'name' => '', 'type' => 'Login', 'reason' => 'no account uses that email'];
    }
    $agent = intval($row['user_type']) === 1;
    if ($row['user_archived_at'] !== null) {
        $reason = 'the account is archived';
    } elseif (intval($row['user_status']) === 2) {
        $reason = "they haven't set a password yet";
    } elseif (intval($row['user_status']) !== 1) {
        $reason = $agent ? 'the account is disabled' : 'their portal access is turned off';
    } elseif (!$agent && $row['client_archived_at'] !== null) {
        $reason = 'their client is archived';
    } else {
        $reason = 'wrong password';
    }
    return [
        'user_id' => intval($row['user_id']),
        'client_id' => $agent ? 0 : intval($row['contact_client_id']),
        'name' => (string) $row['user_name'],
        'type' => $agent ? 'Login' : 'Client Login',
        'reason' => $reason,
    ];
}

/*
 * A sign in event in the audit log, as $user_id - whose account it was, even
 * when nobody signed in - from this request's IP address and browser.
 * $description is SQL-safe.
 */
function signInLog($type, $action, $description, $user_id = 0, $client_id = 0) {
    global $session_user_id;
    $was = $session_user_id ?? 0;
    $session_user_id = intval($user_id);
    logAudit($type, $action, $description, $client_id, $user_id);
    $session_user_id = $was;
}

/* A failed sign in, logged with why, then watched (signInWatch()). */
function signInLogFailure($type, $action, $description, $user_id = 0, $client_id = 0) {
    signInLog($type, $action, $description, $user_id, $client_id);
    signInWatch($user_id);
}

/*
 * After a failed sign in: when this IP address, or the account, has just
 * reached the admins' number of failures in SIGN_IN_WINDOW_MINUTES, tell them
 * (signInAlertAdmins()). Only on reaching it, so one run is one alert.
 */
function signInWatch($user_id = 0) {
    global $mysqli, $session_ip;
    $limit = signInAlertLimit();
    if ($limit <= 0) {
        return;
    }
    $ip = escapeSql((string) $session_ip);
    $window = "log_type IN ('" . implode("', '", SIGN_IN_TYPES) . "') AND log_action IN ('" . implode("', '", SIGN_IN_FAILED) . "')
        AND log_created_at > NOW() - INTERVAL " . SIGN_IN_WINDOW_MINUTES . " MINUTE";

    if ($ip !== '' && intval(mysqli_fetch_row(mysqli_query($mysqli, "SELECT COUNT(*) FROM logs WHERE $window AND log_ip = '$ip'"))[0]) === $limit) {
        $where = "$window AND log_ip = '$ip'";
        signInAlertAdmins(function () use ($limit, $session_ip, $where) {
            $accounts = signInAccountsTried($where);
            return [
                __('%1$d failed sign-ins from %2$s', $limit, $session_ip),
                __('%1$d failed sign-ins in %2$d minutes from %3$s, %4$s.', $limit, SIGN_IN_WINDOW_MINUTES, $session_ip, $accounts),
                [__('IP address') => (string) $session_ip, __('Accounts tried') => ucfirst($accounts)],
            ];
        }, '/admin/sign_ins.php?view=ips&q=' . rawurlencode((string) $session_ip));
    }

    $user_id = intval($user_id);
    if ($user_id && intval(mysqli_fetch_row(mysqli_query($mysqli, "SELECT COUNT(*) FROM logs WHERE $window AND log_user_id = $user_id"))[0]) === $limit) {
        $user = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT user_name, user_email FROM users WHERE user_id = $user_id"));
        $ips = intval(mysqli_fetch_row(mysqli_query($mysqli, "SELECT COUNT(DISTINCT log_ip) FROM logs WHERE $window AND log_user_id = $user_id"))[0]);
        signInAlertAdmins(function () use ($limit, $user, $ips) {
            $name = isset($user['user_name']) ? (string) $user['user_name'] : __('an account');
            $account = $user ? __("%1\$s's account (%2\$s)", $name, $user['user_email']) : __("%s's account", $name);
            return [
                __('%1$d failed sign-ins for %2$s', $limit, $name),
                _n('%1$d failed sign-ins in %2$d minutes for %3$s, from one IP address.', '%1$d failed sign-ins in %2$d minutes for %3$s, from %4$d IP addresses.', $ips, $limit, SIGN_IN_WINDOW_MINUTES, $account, $ips),
                [__('Account') => $name . ($user ? ' - ' . $user['user_email'] : ''), __('IP addresses') => (string) $ips],
            ];
        }, '/admin/audit_logs.php?view=signins&user=' . $user_id);
    }
}

/* The accounts a run of failures tried, in words - "trying Tess Tech, Johnny Test and 2 emails with no account". Raw. */
function signInAccountsTried($where) {
    global $mysqli;
    $names = [];
    $sql = mysqli_query($mysqli, "SELECT DISTINCT user_name FROM logs JOIN users ON user_id = log_user_id WHERE $where AND log_user_id > 0 ORDER BY user_name LIMIT 6");
    while ($row = mysqli_fetch_row($sql)) {
        $names[] = $row[0];
    }
    $unknown = intval(mysqli_fetch_row(mysqli_query($mysqli, "SELECT COUNT(DISTINCT log_description) FROM logs WHERE $where AND log_user_id = 0"))[0]);
    if ($unknown) {
        $names[] = _n('an email with no account', '%d emails with no account', $unknown);
    }
    if (!$names) {
        return __('trying no account in particular');
    }
    $last = array_pop($names);
    return __('trying %s', $names ? __('%1$s and %2$s', implode(', ', $names), $last) : $last);
}

/* The active admins - [user_id => ['name', 'email']]. */
function signInAdmins() {
    global $mysqli;
    $admins = [];
    $sql = mysqli_query($mysqli, "SELECT users.user_id, user_name, user_email FROM users
        JOIN user_roles ON role_id = user_role_id AND role_is_admin = 1
        WHERE user_type = 1 AND user_status = 1 AND user_archived_at IS NULL");
    while ($row = mysqli_fetch_assoc($sql)) {
        $admins[intval($row['user_id'])] = ['name' => $row['user_name'], 'email' => $row['user_email']];
    }
    return $admins;
}

/*
 * Tell the admins about a run of failures - a notification each, an email
 * each, and an entry in the audit log (Login Alert, from the address it is
 * about). $words gives [title, text, summary], raw - it is called once for
 * the interface language and once for the log's English; $url is where to look.
 */
function signInAlertAdmins(callable $words, $url) {
    global $mysqli;
    // [title, text, summary] in the interface language; the audit log gets the text in English
    [$title, $text, $summary] = $words();
    $log_text = i18nEnglish(fn () => $words()[1]);
    $type = escapeSql('Failed Sign-ins');
    $notice = escapeSql(mb_substr($text, 0, 1000));
    $action = escapeSql($url);
    foreach (signInAdmins() as $admin_id => $admin) {
        mysqli_query($mysqli, "INSERT INTO notifications SET notification_type = '$type', notification = '$notice', notification_action = '$action', notification_user_id = $admin_id");
        signInMailAlert($admin['name'], $admin['email'], $title, $text, $summary, $url);
    }
    signInLog('Login', 'Alert', escapeSql("Admins were told: $log_text"));
}

/* The email to an admin about a run of failed sign-ins. Raw values in. */
function signInMailAlert($name, $email, $title, $text, array $summary, $url) {
    global $config_base_url;
    $app = userMailAppName();
    emailQueue([['email' => $email, 'name' => $name, 'footnote' => __('You get this as an %s administrator. Admin > Settings > Security says when.', $app)]],
        "$app: $title", [
            'kind' => __('Security'), 'tone' => 'danger', 'preview' => $text,
            'title' => __('Failed sign-ins'),
            'intro' => [$text],
            'summary' => [__('When') => formatDate(time(), 'datetime_weekday')] + $summary,
            'callout' => __('%1$s turns an IP address away after %2$d failed sign-ins in %3$d minutes. If an account is being guessed at, make sure it has a strong password and multi-factor authentication.', $app, SIGN_IN_LOCKOUT, SIGN_IN_WINDOW_MINUTES),
            'button' => ['label' => __('See the sign-ins'), 'url' => "https://$config_base_url$url"],
        ]);
}

/*
 * The successful sign ins among $rows (audit log rows with log_id, log_type,
 * log_action, log_user_id, log_ip) that were the first from their IP address
 * for that account - [log_id => true]. Measured against the log ITFlow still
 * keeps.
 */
function signInNewIpIds(array $rows) {
    global $mysqli;
    $ids = [];
    foreach ($rows as $row) {
        if (in_array($row['log_type'], SIGN_IN_TYPES, true) && $row['log_action'] === 'Success' && intval($row['log_user_id']) > 0 && trim((string) $row['log_ip']) !== '') {
            $ids[] = intval($row['log_id']);
        }
    }
    if (!$ids) {
        return [];
    }
    $new = [];
    $sql = mysqli_query($mysqli, "SELECT l.log_id FROM logs l
        WHERE l.log_id IN (" . implode(',', $ids) . ")
        AND NOT EXISTS (SELECT 1 FROM logs p WHERE p.log_user_id = l.log_user_id AND p.log_type = l.log_type AND p.log_action = 'Success' AND p.log_ip = l.log_ip AND p.log_id < l.log_id)");
    while ($row = mysqli_fetch_row($sql)) {
        $new[intval($row[0])] = true;
    }
    return $new;
}

/** The New IP badge on a sign in that was the first from its address. */
function signInNewIpBadge() {
    return '<span class="badge rounded-pill bg-warning-subtle text-warning-emphasis border border-warning-subtle fw-normal text-nowrap" title="' . escapeHtml(__('The first time this account signed in from this IP address')) . '" data-new-ip><i class="fas fa-map-marker-alt me-1"></i>' . __('New IP') . '</span>';
}
