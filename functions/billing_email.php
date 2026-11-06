<?php

/*
 * Billing emails - one look for every email a client gets about money: an
 * invoice, a receipt for one paid, a quote, a payment reminder, a payment
 * received and a statement.
 *
 * Each is drawn by emailHtml() (functions/email.php), as every email is: the
 * company's logo (or name), what it is about, a summary box with what it
 * comes to, any note from the agent, a button to open it online - to pay an
 * invoice when online payments are on, or answer a quote - and the company's
 * details at the foot. The PDF can go with it.
 *
 * Reminders follow a schedule from the invoice settings: days before and
 * after the due date, then every so many days while it is owed. Statements
 * can go out each month to every client with a balance, and a client can be
 * left out of both.
 */

/* ------------------------------------------------------------ The rules */

/**
 * Reminders and statements, from the invoice settings - with the old
 * behaviour until 2.9.8 is applied:
 *   enabled            send reminders at all
 *   days               days from the due date to send on, negative before it, sorted
 *   repeat             then every so many days after the last of them, 0 for no more
 *   statement_monthly  email a statement each month to clients with a balance
 *   statement_day      on this day of the month (1 to 28)
 *   attach_pdf         put the PDF on invoice, quote, reminder and statement emails
 */
function billingReminderRules($fresh = false) {
    global $mysqli;
    static $rules = null;
    if ($rules !== null && !$fresh) {
        return $rules;
    }
    $rules = ['enabled' => false, 'days' => [1, 30], 'repeat' => 30, 'statement_monthly' => false, 'statement_day' => 1, 'attach_pdf' => true];
    try {
        $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT config_send_invoice_reminders, config_invoice_reminder_days, config_invoice_reminder_repeat,
            config_statement_monthly, config_statement_day, config_billing_email_attach_pdf FROM settings WHERE company_id = 1"));
    } catch (mysqli_sql_exception $e) {
        $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT config_send_invoice_reminders FROM settings WHERE company_id = 1"));
    }
    if ($row) {
        $rules['enabled'] = intval($row['config_send_invoice_reminders'] ?? 0) === 1;
        if (array_key_exists('config_invoice_reminder_days', $row)) {
            $rules['days'] = billingReminderDaysParse($row['config_invoice_reminder_days']);
            $rules['repeat'] = max(0, min(365, intval($row['config_invoice_reminder_repeat'])));
            $rules['statement_monthly'] = intval($row['config_statement_monthly']) === 1;
            $rules['statement_day'] = max(1, min(28, intval($row['config_statement_day'])));
            $rules['attach_pdf'] = intval($row['config_billing_email_attach_pdf']) === 1;
        }
    }
    return $rules;
}

/** Days from a list - "-3, 0, 7 14" - as whole days from 60 before to a year after, sorted, each once. */
function billingReminderDaysParse($text) {
    $days = [];
    foreach (preg_split('/[\s,;]+/', trim((string) $text)) as $part) {
        if (preg_match('/^[+-]?\d{1,3}$/', $part)) {
            $day = intval($part);
            if ($day >= -60 && $day <= 365) {
                $days[$day] = $day;
            }
        }
    }
    sort($days);
    return array_values($days);
}

/** Is a reminder due for an invoice $days after its due date (negative before it)? */
function billingReminderOnDay($days, ?array $rules = null) {
    $rules = $rules ?? billingReminderRules();
    $days = intval($days);
    if (in_array($days, $rules['days'], true)) {
        return true;
    }
    $last = $rules['days'] ? max(max($rules['days']), 0) : 0;
    return $rules['repeat'] > 0 && $days > $last && ($days - $last) % $rules['repeat'] === 0;
}

/** The schedule in words - "3 days before it is due, on the day, 7 and 30 days late, then every 30 days". */
function billingReminderScheduleText(?array $rules = null) {
    $rules = $rules ?? billingReminderRules();
    $parts = [];
    $before = array_reverse(array_map('abs', array_filter($rules['days'], function ($day) {
        return $day < 0;
    })));
    $after = array_values(array_filter($rules['days'], function ($day) {
        return $day > 0;
    }));
    $list = function ($days) {
        return count($days) > 1 ? __('%1$s and %2$s', implode(', ', array_slice($days, 0, -1)), end($days)) : (string) reset($days);
    };
    // The plural follows the last number - "1 and 2 days", "1 day"
    $count = function ($days) {
        return count($days) === 1 ? intval(end($days)) : 2;
    };
    if ($before) {
        $parts[] = _n('%s day before it is due', '%s days before it is due', $count($before), $list($before));
    }
    if (in_array(0, $rules['days'], true)) {
        $parts[] = _x('on the due date', 'reminder schedule');
    }
    if ($after) {
        $parts[] = _n('%s day late', '%s days late', $count($after), $list($after));
    }
    if ($rules['repeat'] > 0) {
        $after_due = !($after || in_array(0, $rules['days'], true));
        $every = $after_due
            ? _n('every %d day after it is due until it is paid', 'every %d days after it is due until it is paid', $rules['repeat'], $rules['repeat'])
            : _n('every %d day until it is paid', 'every %d days until it is paid', $rules['repeat'], $rules['repeat']);
        $parts[] = $parts ? _x('then %s', 'reminder schedule', $every) : $every;
    }
    return $parts ? implode(', ', $parts) : _x('never', 'reminder schedule');
}

/**
 * When an invoice's next reminder goes, from tomorrow on: ['date' => Y-m-d,
 * 'days' => days from its due date], or null - none on the schedule, or
 * reminders are off. With $late_only, none before it is late - autopay
 * charges it on the day.
 */
function billingReminderNext(array $invoice, ?array $rules = null, $late_only = false) {
    $rules = $rules ?? billingReminderRules();
    $due = substr((string) ($invoice['invoice_due'] ?? ''), 0, 10);
    if (!$rules['enabled'] || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $due)) {
        return null;
    }
    $today = new DateTime('today');
    $due_date = new DateTime($due);
    $from = (int) $due_date->diff($today)->format('%r%a') + 1;
    if ($late_only) {
        $from = max($from, 1);
    }
    for ($days = $from; $days <= $from + 400; $days++) {
        if (billingReminderOnDay($days, $rules)) {
            $date = (clone $due_date)->modify(($days >= 0 ? '+' : '') . $days . ' days');
            return ['date' => $date->format('Y-m-d'), 'days' => $days];
        }
    }
    return null;
}

/** Will a recurring autopay charge this invoice on its due date? Then it isn't reminded before. */
function billingReminderAutopay(array $invoice) {
    global $mysqli;
    $recurring_id = intval($invoice['invoice_recurring_invoice_id'] ?? 0);
    return $recurring_id > 0 && mysqli_num_rows(mysqli_query($mysqli, "SELECT 1 FROM recurring_payments WHERE recurring_payment_recurring_invoice_id = $recurring_id LIMIT 1")) > 0;
}

/** How late an invoice is, in words - "due in 3 days", "due today", "7 days late". */
function billingReminderWhen($days) {
    $days = intval($days);
    if ($days < 0) {
        return _n('due in %d day', 'due in %d days', -$days);
    }
    return $days === 0 ? _x('due today', 'reminder') : _n('%d day late', '%d days late', $days);
}

/* ------------------------------------------------------------ The email */

/** A date for an email - "Oct 20, 2026", in the interface language. */
function billingEmailDate($date) {
    $time = strtotime((string) $date);
    return $time ? formatDate($date, 'date') : (string) $date;
}

/**
 * One billing email as HTML - emailHtml() (functions/email.php), greeting
 * nobody when it has no name, as an internal one has none.
 */
function billingEmailHtml(array $email) {
    if (empty($email['name'])) {
        $email['name'] = null;
    }
    return emailHtml($email);
}

/** Who billing emails come from - invoices and quotes each have their own address. */
function billingEmailFrom($which = 'invoice') {
    global $mysqli;
    static $from = null;
    if ($from === null) {
        $from = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT config_invoice_from_email, config_invoice_from_name, config_quote_from_email, config_quote_from_name,
            config_invoice_paid_notification_email, config_smtp_provider FROM settings WHERE company_id = 1")) ?: [];
    }
    if ($which === 'quote' && !empty($from['config_quote_from_email'])) {
        return ['email' => (string) $from['config_quote_from_email'], 'name' => (string) $from['config_quote_from_name']];
    }
    if ($which === 'notify') {
        return ['email' => (string) ($from['config_invoice_paid_notification_email'] ?? ''), 'name' => ''];
    }
    if ($which === 'ready') {
        return ['email' => (string) ($from['config_smtp_provider'] ?? ''), 'name' => ''];
    }
    return ['email' => (string) ($from['config_invoice_from_email'] ?? ''), 'name' => (string) ($from['config_invoice_from_name'] ?? '')];
}

/** Can billing emails go out at all - mail is set up? */
function billingEmailReady() {
    return billingEmailFrom('ready')['email'] !== '' && billingEmailFrom()['email'] !== '';
}

/**
 * A client's contacts to email: the ones picked (any of the client's with an
 * email address), or with none picked the defaults for $type - the primary
 * and billing contacts, or for a quote the primary contact.
 * [['id', 'name', 'email'], ...], each address once.
 */
function billingEmailRecipients($client_id, ?array $contact_ids = null, $type = 'invoice') {
    global $mysqli;
    $client_id = intval($client_id);
    if ($contact_ids !== null) {
        $contact_ids = array_filter(array_map('intval', $contact_ids));
        if (!$contact_ids) {
            return [];
        }
        $filter = 'AND contact_id IN (' . implode(', ', $contact_ids) . ')';
    } else {
        $filter = documentDefaultContactFilterSql($type === 'quote' ? 'quote' : 'invoice');
    }
    $recipients = [];
    $sql = mysqli_query($mysqli, "SELECT contact_id, contact_name, contact_email FROM contacts
        WHERE contact_client_id = $client_id AND contact_archived_at IS NULL AND contact_email IS NOT NULL AND contact_email != '' $filter
        ORDER BY contact_primary DESC, contact_billing DESC, contact_name ASC");
    while ($row = mysqli_fetch_assoc($sql)) {
        $key = strtolower(trim($row['contact_email']));
        if (!filter_var(trim($row['contact_email']), FILTER_VALIDATE_EMAIL) || isset($recipients[$key])) {
            continue;
        }
        $recipients[$key] = ['id' => intval($row['contact_id']), 'name' => (string) $row['contact_name'], 'email' => trim($row['contact_email'])];
    }
    return array_values($recipients);
}

/**
 * Queue a billing email to each recipient, greeting each by name - nobody
 * without one - through emailQueue(), whether or not mail is set up yet, as
 * the Send windows always have. $opts: from ('invoice' or 'quote' -
 * whose address it comes from) and attach (a list of billingEmailPdf()
 * files). Who it was queued to, "Name (email)".
 */
function billingEmailQueue(array $recipients, $subject, array $email, array $opts = []) {
    $recipients = array_map(function ($recipient) {
        $greet = array_key_exists('greet', $recipient) ? $recipient['greet'] : ($recipient['name'] ?? '');
        return ['greet' => trim((string) $greet) !== '' ? (string) $greet : null] + $recipient;
    }, $recipients);
    return emailQueue($recipients, $subject, $email, ['from' => billingEmailFrom($opts['from'] ?? 'invoice'), 'attachments' => (array) ($opts['attach'] ?? []), 'always' => true]);
}

/**
 * A document's PDF, saved where the mail queue can attach it - ['path' =>
 * app-relative path, 'name' => file name], or null when it can't be made.
 * They go in uploads/tmp/billing_mail, which the web can't reach, and the
 * nightly run clears them out after a week. $pdf is a billingPdf() or
 * billingStatementPdf() result.
 */
function billingEmailPdf(array $pdf) {
    $dir = 'uploads/tmp/billing_mail/' . bin2hex(random_bytes(12));
    $full = dirname(__DIR__) . '/' . $dir;
    if (!is_dir($full) && !@mkdir($full, 0775, true)) {
        return null;
    }
    $name = preg_replace('/[^A-Za-z0-9._-]+/', '-', (string) $pdf['name']);
    if (@file_put_contents("$full/$name", $pdf['data']) === false) {
        return null;
    }
    return ['path' => "$dir/$name", 'name' => $name];
}

/** Clear PDFs made for emails more than $days days ago. How many went. */
function billingEmailPdfCleanup($days = 7) {
    $base = dirname(__DIR__) . '/uploads/tmp/billing_mail';
    if (!is_dir($base)) {
        return 0;
    }
    $cutoff = time() - max(1, intval($days)) * 86400;
    $removed = 0;
    foreach (glob("$base/*", GLOB_ONLYDIR) ?: [] as $dir) {
        if (filemtime($dir) >= $cutoff) {
            continue;
        }
        foreach (glob("$dir/*") ?: [] as $file) {
            @unlink($file);
        }
        if (@rmdir($dir)) {
            $removed++;
        }
    }
    return $removed;
}

/* ----------------------------------------------- What each one says */

/** The footnote for a client's billing contact. */
function billingEmailFootnote($client_name) {
    $company = billingCompany();
    return __('You received this email as a billing contact for %1$s with %2$s.', $client_name, $company['company_name'] ?? __('us'));
}

/**
 * An invoice email - [subject, email]. $opts:
 *   kind  invoice (the default), or reminder
 *   note  the agent's own words
 * Paid, it is a receipt. A reminder says when it is or was due and what is
 * still owed - firmer once it is late.
 */
function billingInvoiceEmail(array $invoice, array $opts = []) {
    $number = billingNumber('invoice', $invoice);
    $currency = (string) $invoice['invoice_currency_code'];
    $paid = invoicePaid($invoice['invoice_id']);
    $state = invoiceState($invoice, $paid);
    $company = billingCompany();
    $company_name = (string) ($company['company_name'] ?? '');
    $scope = trim((string) $invoice['invoice_scope']);
    $url = billingGuestUrl('invoice', $invoice);
    $owed = $state['balance'] > 0 && !in_array($invoice['invoice_status'], ['Paid', 'Cancelled', 'Non-Billable'], true);
    $pay = $owed && billingOnlinePayments();
    $kind = ($opts['kind'] ?? 'invoice') === 'reminder' && $owed ? 'reminder' : 'invoice';
    $contact_line = !empty($company['phone_display'])
        ? __('Questions? Reply to this email or call us on %s.', $company['phone_display'])
        : __('Questions? Reply to this email.');
    $due = billingEmailDate($invoice['invoice_due']);
    $balance_due = _x('Balance due', 'billing document');

    $summary = [__('Invoice') => $number];
    if ($scope !== '') {
        $summary[__('For')] = $scope;
    }
    $summary[_x('Issued', 'invoice')] = billingEmailDate($invoice['invoice_date']);
    if ($owed) {
        $summary[_x('Due', 'billing document')] = $due;
    }
    $summary[_x('Total', 'billing document')] = billingMoney($invoice['invoice_amount'], $currency);
    if ($paid > 0) {
        $summary[__('Paid')] = billingMoney($paid, $currency);
    }
    $email = [
        'note' => $opts['note'] ?? '',
        'button' => ['label' => $pay ? _x('View and pay invoice', 'billing email') : _x('View invoice', 'billing email'), 'url' => $url],
        'footnote' => billingEmailFootnote($invoice['client_name']),
    ];

    if (!$owed && $invoice['invoice_status'] === 'Paid') {
        return [__('Receipt for invoice %1$s from %2$s', $number, $company_name), $email + [
            'kind' => __('Receipt'), 'tone' => 'success', 'title' => __('Invoice %s is paid', $number),
            'intro' => [$scope !== '' ? __('Thank you - invoice %1$s for "%2$s" is paid in full.', $number, $scope) : __('Thank you - invoice %s is paid in full.', $number)],
            'summary' => $summary, 'strong' => __('Paid'),
            'after' => [__('Please keep this email for your records. You can download the invoice as a PDF from its page.')],
        ]];
    }

    if ($kind === 'reminder') {
        $days = $state['days'] !== null ? -intval($state['days']) : 0;
        $balance = billingMoney($state['balance'], $currency);
        $summary[$balance_due] = $balance;
        if ($days < 0) {
            $subject = __('Reminder: invoice %1$s is due %2$s', $number, $due);
            $intro = [$scope !== '' ? __('A friendly reminder that invoice %1$s for "%2$s" is due on %3$s, with %4$s to pay.', $number, $scope, $due, $balance)
                : __('A friendly reminder that invoice %1$s is due on %2$s, with %3$s to pay.', $number, $due, $balance)];
            $tone = 'primary';
            $title = _x('Due %s', 'reminder', $due);
        } elseif ($days === 0) {
            $subject = __('Reminder: invoice %s is due today', $number);
            $intro = [$scope !== '' ? __('A friendly reminder that invoice %1$s for "%2$s" is due today, with %3$s to pay.', $number, $scope, $balance)
                : __('A friendly reminder that invoice %1$s is due today, with %2$s to pay.', $number, $balance)];
            $tone = 'warning';
            $title = __('Due today');
        } else {
            $subject = _n('Overdue: invoice %2$s is %1$d day past due', 'Overdue: invoice %2$s is %1$d days past due', $days, $days, $number);
            $intro = [$scope !== '' ? __('Invoice %1$s for "%2$s" was due on %3$s and %4$s is still outstanding. Please arrange payment as soon as you can.', $number, $scope, $due, $balance)
                : __('Invoice %1$s was due on %2$s and %3$s is still outstanding. Please arrange payment as soon as you can.', $number, $due, $balance)];
            $tone = 'danger';
            $title = _n('%d day past due', '%d days past due', $days);
        }
        return [$subject, $email + [
            'kind' => _x('Payment reminder', 'billing email'), 'reminder' => true, 'tone' => $tone, 'title' => __('Invoice %1$s - %2$s', $number, $title),
            'intro' => $intro, 'summary' => $summary, 'strong' => $balance_due,
            'after' => [__('If you have already paid, thank you, and please disregard this reminder.') . ' ' . $contact_line],
        ]];
    }

    $balance_label = $owed ? $balance_due : __('Balance');
    $summary[$balance_label] = billingMoney($state['balance'], $currency);
    return [__('Invoice %1$s from %2$s', $number, $company_name), $email + [
        'kind' => __('Invoice'), 'tone' => 'primary', 'title' => _x('Invoice %s', 'billing email', $number),
        'intro' => [($scope !== '' ? __('Here is your invoice for "%s".', $scope) : __('Here is your invoice.')) . ($owed ? ' ' . __('Payment is due by %s.', $due) : '')],
        'summary' => $summary, 'strong' => $balance_label,
        'after' => [($pay ? __('You can pay online by card from the invoice page, and download it as a PDF.') : __('You can download it as a PDF from the invoice page.')) . ' ' . $contact_line],
    ]];
}

/** A quote email - [subject, email]. $opts: note. */
function billingQuoteEmail(array $quote, array $opts = []) {
    $number = billingNumber('quote', $quote);
    $currency = (string) $quote['quote_currency_code'];
    $company = billingCompany();
    $scope = trim((string) $quote['quote_scope']);
    $groups = quoteGroups($quote);
    $summary = [__('Quote') => $number];
    if ($scope !== '') {
        $summary[__('For')] = $scope;
    }
    $summary[__('Date')] = billingEmailDate($quote['quote_date']);
    $summary[_x('Valid until', 'billing document')] = billingEmailDate($quote['quote_expire']);
    $strong = _x('Total', 'billing document');
    $frequency_labels = ['month' => _x('Each month', 'billing frequency'), 'quarter' => _x('Each quarter', 'billing frequency'), 'year' => _x('Each year', 'billing frequency')];
    if (quoteHasRecurring($groups)) {
        foreach ($groups as $group) {
            if ($group['items'] || $group['frequency'] === '') {
                $label = $group['frequency'] === '' ? _x('Up front', 'billing document') : ($frequency_labels[$group['frequency']] ?? $group['label']);
                $summary[$label] = billingMoney($group['total'], $currency);
                if ($group['frequency'] === '') {
                    $strong = $label;
                }
            }
        }
    } else {
        $summary[_x('Total', 'billing document')] = billingMoney($quote['quote_amount'], $currency);
    }
    $expire = billingEmailDate($quote['quote_expire']);
    return [__('Quote %1$s from %2$s', $number, $company['company_name'] ?? '') . ($scope !== '' ? " - $scope" : ''), [
        'kind' => __('Quote'), 'tone' => 'primary', 'title' => __('Quote %s', $number),
        'intro' => [$scope !== '' ? __('Thank you for the opportunity. Here is our quote for "%1$s", valid until %2$s.', $scope, $expire) : __('Thank you for the opportunity. Here is our quote, valid until %s.', $expire)],
        'note' => $opts['note'] ?? '',
        'summary' => $summary, 'strong' => $strong,
        'button' => ['label' => _x('View and accept quote', 'billing email'), 'url' => billingGuestUrl('quote', $quote)],
        'after' => [__('You can accept or decline it online, and download it as a PDF.') . ' ' . __('Questions? Reply to this email.')],
        'footnote' => billingEmailFootnote($quote['client_name']),
    ]];
}

/**
 * A payment received - [subject, email]. $applied: what went on each
 * invoice, [['invoice' => billingRow(), 'amount' => ...], ...]. $payment:
 * date, method, reference, currency.
 */
function billingPaymentEmail(array $applied, array $payment) {
    $currency = (string) ($payment['currency'] ?? ($applied[0]['invoice']['invoice_currency_code'] ?? ''));
    $total = array_sum(array_column($applied, 'amount'));
    $first = $applied[0]['invoice'];
    $amount_paid = _x('Amount paid', 'billing email');
    $summary = [$amount_paid => billingMoney($total, $currency), __('Date') => billingEmailDate($payment['date'] ?? date('Y-m-d'))];
    if (!empty($payment['method'])) {
        $summary[_x('Method', 'payment')] = $payment['method'];
    }
    if (!empty($payment['reference'])) {
        $summary[__('Reference')] = $payment['reference'];
    }
    $email = ['kind' => _x('Payment received', 'billing email'), 'tone' => 'success', 'footnote' => billingEmailFootnote($first['client_name'])];
    if (count($applied) === 1) {
        $number = billingNumber('invoice', $first);
        $balance = invoiceState($first, invoicePaid($first['invoice_id']))['balance'];
        $summary[__('Invoice')] = $number;
        $summary[$balance > 0 ? _x('Still to pay', 'billing email') : _x('Invoice balance', 'billing email')] = $balance > 0 ? billingMoney($balance, $currency) : _x('Paid in full', 'billing email');
        return [__('Payment received for invoice %s', $number), $email + [
            'title' => __('Thank you for your payment'),
            'intro' => [__('We have received your payment of %1$s for invoice %2$s.', billingMoney($total, $currency), $number)],
            'summary' => $summary, 'strong' => $amount_paid,
            'button' => ['label' => _x('View invoice', 'billing email'), 'url' => billingGuestUrl('invoice', $first)],
            'after' => [__('Please keep this email as your receipt.')],
        ]];
    }
    $rows = [];
    foreach ($applied as $entry) {
        $balance = invoiceState($entry['invoice'], invoicePaid($entry['invoice']['invoice_id']))['balance'];
        $rows[] = [['text' => billingNumber('invoice', $entry['invoice']), 'url' => billingGuestUrl('invoice', $entry['invoice'])],
            billingMoney($entry['amount'], $currency), $balance > 0 ? billingMoney($balance, $currency) : __('Paid')];
    }
    return [_n('Payment received - %d invoice', 'Payment received - %d invoices', count($applied)), $email + [
        'title' => __('Thank you for your payment'),
        'intro' => [__('We have received your payment of %s and applied it to these invoices.', billingMoney($total, $currency))],
        'summary' => $summary, 'strong' => $amount_paid,
        'lines' => ['head' => [__('Invoice'), _x('Applied', 'payment'), __('Balance')], 'align' => ['left', 'right', 'right'], 'rows' => $rows],
        'after' => [__('Please keep this email as your receipt.')],
    ]];
}

/** Confirm a card a contact saved in the client portal - its brand, last four digits and expiry. Who it went to. */
function billingMailCardSaved($email, $name, $client_name, $brand, $last4, $exp_month, $exp_year) {
    return billingEmailQueue([['email' => (string) $email, 'name' => (string) $name]], __('Payment method saved'), [
        'kind' => _x('Payment method', 'billing email'), 'tone' => 'success', 'preview' => __('Your card is saved for future invoices'),
        'title' => __('Your payment method is saved'),
        'intro' => [__('Your payment details are stored securely with Stripe, our payment processor, and you authorized us to bill this card automatically for future invoices.')],
        'summary' => [_x('Card', 'payment') => __('%1$s ending %2$s', ucfirst((string) $brand), $last4), __('Expires') => sprintf('%02d/%d', intval($exp_month), intval($exp_year))],
        'after' => [__('You can change or remove it at any time in the client portal.'), __('Thank you for your business!')],
        'footnote' => billingEmailFootnote((string) $client_name),
    ]);
}

/** An email to one of our own addresses - no greeting, from the invoice or quote address. Who it went to. */
function billingNotifyEmail($to, $subject, array $email, $from = 'invoice') {
    $to = trim((string) $to);
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
        return [];
    }
    return billingEmailQueue([['email' => $to, 'name' => '', 'greet' => '']], $subject, $email, ['from' => $from]);
}

/**
 * Tell our team a client answered a quote online - to the quote notification
 * address in the quote settings. $accepted or declined; $by, who answered
 * in the client portal, and the note they left with it.
 */
function billingQuoteAnsweredNotify($quote_id, $accepted, $by = '', $note = '') {
    global $mysqli, $config_base_url;
    $quote = billingRow('quote', $quote_id, false);
    $to = (string) (mysqli_fetch_row(mysqli_query($mysqli, "SELECT config_quote_notification_email FROM settings WHERE company_id = 1"))[0] ?? '');
    if (!$quote || $to === '') {
        return [];
    }
    $number = billingNumber('quote', $quote);
    $by = trim((string) $by);
    $client_name = $quote['client_name'];
    $scope = trim((string) $quote['quote_scope']);
    $summary = [__('Client') => $client_name, __('Quote') => $number, _x('Total', 'billing document') => billingMoney($quote['quote_amount'], $quote['quote_currency_code'])];
    if ($by !== '') {
        $summary = [__('Client') => $client_name, ($accepted ? _x('Accepted by', 'quote') : _x('Declined by', 'quote')) => $by] + $summary;
    }
    if ($accepted) {
        $subject = __('Quote Accepted - %1$s - Quote %2$s', $client_name, $number);
        $title = __('%1$s accepted %2$s', $client_name, $number);
        $intro = $by !== '' ? __('%1$s accepted quote %2$s in the client portal', $client_name, $number) : __('%1$s accepted quote %2$s online', $client_name, $number);
    } else {
        $subject = __('Quote Declined - %1$s - Quote %2$s', $client_name, $number);
        $title = __('%1$s declined %2$s', $client_name, $number);
        $intro = $by !== '' ? __('%1$s declined quote %2$s in the client portal', $client_name, $number) : __('%1$s declined quote %2$s online', $client_name, $number);
    }
    return billingNotifyEmail($to, $subject, [
        'kind' => $accepted ? _x('Quote accepted', 'billing email') : _x('Quote declined', 'billing email'), 'tone' => $accepted ? 'success' : 'danger', 'title' => $title,
        'intro' => [$intro . ($scope !== '' ? " - \"$scope\"" : '') . '.' . (trim((string) $note) !== '' ? ' ' . __('They left a note:') : '')],
        'note' => trim((string) $note),
        'summary' => $summary,
        'button' => ['label' => __('Open in ITFlow'), 'url' => "https://$config_base_url/agent/quote.php?quote_id=" . intval($quote['quote_id'])],
    ], 'quote');
}

/**
 * Tell the paid notification address (Invoice settings) that an invoice was
 * paid - for online and automatic payments. Nothing when none is set.
 */
function billingPaymentNotify(array $invoice, $amount, $how) {
    global $config_base_url;
    $to = billingEmailFrom('notify')['email'];
    if ($to === '') {
        return;
    }
    $number = billingNumber('invoice', $invoice);
    $currency = (string) $invoice['invoice_currency_code'];
    $balance = invoiceState($invoice, invoicePaid($invoice['invoice_id']))['balance'];
    $amount_paid = _x('Amount paid', 'billing email');
    billingNotifyEmail($to, __('Payment received - %1$s - invoice %2$s', $invoice['client_name'], $number), [
        'kind' => _x('Payment received', 'billing email'), 'tone' => 'success', 'title' => __('%1$s paid %2$s', $invoice['client_name'], $number),
        'intro' => [billingPaymentHowText($how, billingMoney($amount, $currency)) . ' ' . __('On invoice %1$s for %2$s.', $number, $invoice['client_name'])],
        'summary' => [__('Client') => $invoice['client_name'], __('Invoice') => $number, $amount_paid => billingMoney($amount, $currency), __('Balance') => $balance > 0 ? billingMoney($balance, $currency) : _x('Paid in full', 'billing email')],
        'strong' => $amount_paid,
        'button' => ['label' => __('Open in ITFlow'), 'url' => "https://$config_base_url/agent/invoice.php?invoice_id=" . intval($invoice['invoice_id'])],
    ]);
}

/**
 * Tell the paid notification address about one payment over several
 * invoices - the online payments the client portal takes.
 */
function billingPaymentNotifyMany(array $applied, $how) {
    global $config_base_url;
    $to = billingEmailFrom('notify')['email'];
    if ($to === '' || !$applied) {
        return;
    }
    $first = $applied[0]['invoice'];
    $currency = (string) $first['invoice_currency_code'];
    $total = array_sum(array_column($applied, 'amount'));
    $rows = [];
    foreach ($applied as $entry) {
        $balance = invoiceState($entry['invoice'], invoicePaid($entry['invoice']['invoice_id']))['balance'];
        $rows[] = [['text' => billingNumber('invoice', $entry['invoice']), 'url' => "https://$config_base_url/agent/invoice.php?invoice_id=" . intval($entry['invoice']['invoice_id'])],
            billingMoney($entry['amount'], $currency), $balance > 0 ? billingMoney($balance, $currency) : __('Paid')];
    }
    $amount_paid = _x('Amount paid', 'billing email');
    $count = count($applied);
    billingNotifyEmail($to, _n('Payment received - %2$s - %1$d invoice', 'Payment received - %2$s - %1$d invoices', $count, $count, $first['client_name']), [
        'kind' => _x('Payment received', 'billing email'), 'tone' => 'success', 'title' => _n('%2$s paid %1$d invoice', '%2$s paid %1$d invoices', $count, $count, $first['client_name']),
        'intro' => [billingPaymentHowText($how, billingMoney($total, $currency)) . ' ' . __('For %s, over these invoices.', $first['client_name'])],
        'summary' => [__('Client') => $first['client_name'], __('Invoices') => (string) $count, $amount_paid => billingMoney($total, $currency)],
        'strong' => $amount_paid,
        'lines' => ['head' => [__('Invoice'), _x('Applied', 'payment'), __('Balance')], 'align' => ['left', 'right', 'right'], 'rows' => $rows],
    ]);
}

/**
 * Send the receipt for a payment on one invoice or several - $applied as
 * billingPaymentEmail() takes it - to the client's primary and billing
 * contacts, and log it on each invoice. $notify also tells the paid
 * notification address. The addresses it went to.
 */
function billingPaymentReceiptSend(array $applied, array $payment, $notify = false) {
    if (!$applied) {
        return [];
    }
    $first = $applied[0]['invoice'];
    [$subject, $email] = billingPaymentEmail($applied, $payment + ['currency' => $first['invoice_currency_code']]);
    $sent = billingEmailQueue(billingEmailRecipients($first['client_id']), $subject, $email);
    if ($sent) {
        foreach ($applied as $entry) {
            billingLog('invoice', $entry['invoice']['invoice_id'], 'Sent', 'Payment receipt emailed to ' . implode(', ', $sent)); // i18n-ignore - invoice history stays English
        }
    }
    if ($notify && count($applied) === 1) {
        billingPaymentNotify($first, $applied[0]['amount'], $payment['how'] ?? 'Online');
    } elseif ($notify) {
        billingPaymentNotifyMany($applied, $payment['how'] ?? 'Online');
    }
    return $sent;
}

/**
 * Send a payment received email for a payment on one invoice - to its
 * client's primary and billing contacts - and log it on the invoice.
 * $notify also tells the paid notification address. The addresses it went to.
 */
function billingPaymentSend($invoice_id, $amount, array $payment, $notify = false) {
    $invoice = billingRow('invoice', $invoice_id, false);
    if (!$invoice) {
        return [];
    }
    return billingPaymentReceiptSend([['invoice' => $invoice, 'amount' => $amount]], $payment, $notify);
}

/* ------------------------------------------------------------ Statements */

/**
 * A client's statement - their invoices sent and not cancelled, dated up to
 * $to (and from $from), the open ones only unless $include_paid, with what
 * has been paid on each:
 *   client     the client, with its currency
 *   from, to   the period
 *   invoices   each with paid, balance and days (late, negative while not due)
 *   total      what is owed on them
 *   overdue    what of it is past due
 *   aging      current, 1-30, 31-60, 61-90 and 90+ days late
 */
function billingStatement($client_id, $from = '', $to = '', $include_paid = false) {
    global $mysqli, $session_company_currency;
    $client_id = intval($client_id);
    $to = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $to) ? $to : date('Y-m-d');
    $from = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $from) ? $from : '';
    $client = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT client_id, client_name, client_currency_code, client_reminders FROM clients WHERE client_id = $client_id")) ?: null;
    $statement = ['client' => $client, 'from' => $from, 'to' => $to, 'include_paid' => (bool) $include_paid, 'invoices' => [], 'total' => 0.0, 'overdue' => 0.0,
        'aging' => ['Current' => 0.0, '1-30 days' => 0.0, '31-60 days' => 0.0, '61-90 days' => 0.0, 'Over 90 days' => 0.0],
        'currency' => (string) (($client['client_currency_code'] ?? '') ?: ($session_company_currency ?? '') ?: (billingCompany()['company_currency'] ?? ''))];
    if (!$client) {
        return $statement;
    }
    $sql = mysqli_query($mysqli, "SELECT invoice_id, invoice_prefix, invoice_number, invoice_scope, invoice_date, invoice_due, invoice_status, invoice_amount,
            invoice_currency_code, invoice_url_key, COALESCE(paid.amount, 0) AS paid
        FROM invoices
        LEFT JOIN (SELECT payment_invoice_id, SUM(payment_amount) AS amount FROM payments WHERE payment_archived_at IS NULL GROUP BY payment_invoice_id) AS paid
            ON paid.payment_invoice_id = invoice_id
        WHERE invoice_client_id = $client_id AND invoice_status NOT IN ('Draft', 'Cancelled', 'Non-Billable')
            AND invoice_date <= '$to'" . ($from !== '' ? " AND invoice_date >= '$from'" : '') . "
        ORDER BY invoice_date ASC, invoice_number ASC");
    $today = strtotime(date('Y-m-d'));
    while ($row = mysqli_fetch_assoc($sql)) {
        $row['balance'] = round(floatval($row['invoice_amount']) - floatval($row['paid']), 2);
        if ($row['balance'] <= 0 && !$include_paid) {
            continue;
        }
        $row['days'] = intval(round(($today - strtotime($row['invoice_due'])) / 86400));
        $row['client_name'] = $client['client_name'];
        if ($row['balance'] > 0) {
            $statement['total'] += $row['balance'];
            $days = $row['days'];
            $bucket = $days <= 0 ? 'Current' : ($days <= 30 ? '1-30 days' : ($days <= 60 ? '31-60 days' : ($days <= 90 ? '61-90 days' : 'Over 90 days')));
            $statement['aging'][$bucket] += $row['balance'];
            if ($days > 0) {
                $statement['overdue'] += $row['balance'];
            }
        }
        $statement['invoices'][] = $row;
    }
    $statement['total'] = round($statement['total'], 2);
    $statement['overdue'] = round($statement['overdue'], 2);
    return $statement;
}

/** A statement's period in words - "up to Oct 16, 2026", "Jul 1, 2026 to Oct 16, 2026". */
function billingStatementPeriod(array $statement) {
    return $statement['from'] !== ''
        ? _x('%1$s to %2$s', 'billing period', billingEmailDate($statement['from']), billingEmailDate($statement['to']))
        : _x('up to %s', 'billing period', billingEmailDate($statement['to']));
}

/** A statement's aging bucket as shown - the keys stay English. */
function billingAgingLabel($bucket) {
    $labels = ['Current' => _x('Current', 'aging'), '1-30 days' => _x('1-30 days', 'aging'), '31-60 days' => _x('31-60 days', 'aging'),
        '61-90 days' => _x('61-90 days', 'aging'), 'Over 90 days' => _x('Over 90 days', 'aging')];
    return $labels[$bucket] ?? (string) $bucket;
}

/**
 * "Online payment of R$ 10.00." - $how is how it was paid, as the payment
 * code passes it ('Online', 'Saved card', ...); one not known is shown as is.
 */
function billingPaymentHowText($how, $amount) {
    switch ((string) $how) {
        case 'Online':
            return __('Online payment of %s.', $amount);
        case 'Online card':
            return __('Online card payment of %s.', $amount);
        case 'Saved card':
            return __('Saved card payment of %s.', $amount);
        case 'Automatic card':
            return __('Automatic card payment of %s.', $amount);
        case 'Card':
            return __('Card payment of %s.', $amount);
    }
    return __('%1$s payment of %2$s.', $how, $amount);
}

/** A statement as a PDF - ['name', 'data']. See functions/billing_pdf.php. */
function billingStatementPdf(array $statement) {
    require_once __DIR__ . '/../libs/TCPDF/tcpdf.php';
    require_once __DIR__ . '/billing_pdf.php';
    return billingStatementPdfRender($statement);
}

/** A statement email - [subject, email]. $opts: note. */
function billingStatementEmail(array $statement, array $opts = []) {
    $currency = $statement['currency'];
    $company = billingCompany();
    $rows = [];
    foreach ($statement['invoices'] as $invoice) {
        $late = $invoice['balance'] > 0 && $invoice['days'] > 0;
        $rows[] = [
            ['text' => billingNumber('invoice', $invoice), 'url' => billingGuestUrl('invoice', $invoice)],
            billingEmailDate($invoice['invoice_date']),
            ['text' => billingEmailDate($invoice['invoice_due']) . ($late ? ' (' . _n('%dd late', '%dd late', intval($invoice['days'])) . ')' : ''), 'tone' => $late ? 'danger' : ''],
            ['text' => $invoice['balance'] > 0 ? billingMoney($invoice['balance'], $currency) : __('Paid'), 'tone' => $invoice['balance'] > 0 ? '' : 'success'],
        ];
    }
    $total_due = _x('Total due', 'billing document');
    $summary = [_x('Statement date', 'billing document') => billingEmailDate(date('Y-m-d')), _x('Period', 'billing document') => ucfirst(billingStatementPeriod($statement)), _x('Open invoices', 'billing email') => (string) count(array_filter($statement['invoices'], function ($invoice) {
        return $invoice['balance'] > 0;
    }))];
    if ($statement['overdue'] > 0) {
        $summary[_x('Overdue', 'billing email')] = billingMoney($statement['overdue'], $currency);
    }
    $summary[$total_due] = billingMoney($statement['total'], $currency);
    if ($statement['total'] > 0) {
        $open = billingOnlinePayments() ? __('Open an invoice to view or pay it.') : __('Open an invoice to view it.');
    } else {
        $open = __('Nothing is owed - thank you.');
    }
    return [__('Statement from %1$s - %2$s', $company['company_name'] ?? '', billingEmailDate($statement['to'])), [
        'kind' => __('Statement'), 'tone' => $statement['overdue'] > 0 ? 'warning' : 'primary', 'title' => __('Your account statement'),
        'intro' => [__('Here is your statement for %1$s, %2$s.', $statement['client']['client_name'], billingStatementPeriod($statement)) . ' ' . $open],
        'note' => $opts['note'] ?? '',
        'summary' => $summary, 'strong' => $total_due,
        'lines' => ['head' => [__('Invoice'), __('Date'), _x('Due', 'billing document'), __('Balance')], 'align' => ['left', 'left', 'left', 'right'], 'rows' => $rows],
        'after' => [!empty($company['phone_display']) ? __('Questions about your account? Reply to this email or call us on %s.', $company['phone_display']) : __('Questions about your account? Reply to this email.')],
        'footnote' => billingEmailFootnote($statement['client']['client_name']),
    ]];
}

/**
 * Email a statement to a client's contacts (the defaults when $contact_ids
 * is null), with its PDF when $attach. The addresses it went to.
 */
function billingStatementSend(array $statement, ?array $contact_ids = null, array $opts = []) {
    [$subject, $email] = billingStatementEmail($statement, $opts);
    $attach = [];
    if (!empty($opts['attach'])) {
        $file = billingEmailPdf(billingStatementPdf($statement));
        if ($file) {
            $attach[] = $file;
        }
    }
    return billingEmailQueue(billingEmailRecipients($statement['client']['client_id'], $contact_ids), $subject, $email, ['attach' => $attach]);
}
