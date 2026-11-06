<?php

/*
 * Contracts as documents, and signing them.
 *
 * The document is what a contract says, put together for the client to
 * read: who it is between, the agreement in a few lines (type, term,
 * renewal, support hours, rates, payment), the response times for each
 * ticket priority, and its terms with their placeholders filled in -
 * [Client], [Start Date], [Rate] and the rest (CONTRACT_PLACEHOLDERS), and
 * [SLA] on a line of its own as the table of response times.
 * contractDocumentHtml() makes it; it carries no styles of its own (the
 * pages' CSS and the PDF's style block dress it), as the client portal
 * allows none.
 *
 * Sending a contract for signature is a round (contract_signatures, from
 * database 3.4.3): the document as it was sent and its SHA-256, a link with a
 * key of its own, who it went to. The client signs - from the link or in the
 * portal - by name, with a signature drawn or typed; then an agent
 * countersigns, the signed PDF goes to the client, and a draft contract
 * becomes active. A round can be withdrawn; sending again starts a new one,
 * and the earlier rounds are kept. Changing a contract while it is out for
 * signature withdraws the request when the document comes out different, and
 * one that is cancelled or ends, or whose client is archived, has its request
 * withdrawn - nothing is signed on it after that (contractSignatureWithdrawStale()).
 *
 * The PDF is functions/contract_pdf.php; the pages are agent/contract.php,
 * agent/contract_document.php, guest/guest_view_contract.php and
 * client/contract.php, with includes/contract_paper.php drawing the document.
 */

/** The placeholders a contract's terms can use - what each becomes. */
const CONTRACT_PLACEHOLDERS = [
    'Client' => "the client's name", // i18n-ignore - placeholder names, and reasons stored and shown through contractWithdrawnReasonText()
    'Client Address' => "the client's main address, on one line", // i18n-ignore - placeholder names, and reasons stored and shown through contractWithdrawnReasonText()
    'Contact' => 'the contact on the contract', // i18n-ignore - placeholder names, and reasons stored and shown through contractWithdrawnReasonText()
    'Contact Title' => "the contact's job title", // i18n-ignore - placeholder names, and reasons stored and shown through contractWithdrawnReasonText()
    'Contact Email' => "the contact's email", // i18n-ignore - placeholder names, and reasons stored and shown through contractWithdrawnReasonText()
    'Company' => 'your company',
    'Contract' => "the contract's name", // i18n-ignore - placeholder names, and reasons stored and shown through contractWithdrawnReasonText()
    'Type' => 'what kind of contract - Fully managed', // i18n-ignore - placeholder names, and reasons stored and shown through contractWithdrawnReasonText()
    'Start Date' => 'the day it starts', // i18n-ignore - placeholder names, and reasons stored and shown through contractWithdrawnReasonText()
    'End Date' => 'its last day', // i18n-ignore - placeholder names, and reasons stored and shown through contractWithdrawnReasonText()
    'Term' => 'how long it runs - 1 year', // i18n-ignore - placeholder names, and reasons stored and shown through contractWithdrawnReasonText()
    'Renewal' => 'whether it renews itself, in a sentence', // i18n-ignore - placeholder names, and reasons stored and shown through contractWithdrawnReasonText()
    'Notice' => 'the notice to end it - 30 days', // i18n-ignore - placeholder names, and reasons stored and shown through contractWithdrawnReasonText()
    'Support Hours' => 'its support hours', // i18n-ignore - placeholder names, and reasons stored and shown through contractWithdrawnReasonText()
    'Monthly Hours' => 'the hours of support included each month - 10 hours', // i18n-ignore - placeholder names, and reasons stored and shown through contractWithdrawnReasonText()
    'Rate' => 'the hourly rate', // i18n-ignore - placeholder names, and reasons stored and shown through contractWithdrawnReasonText()
    'After Hours Rate' => 'the after-hours rate',
    'Net Terms' => 'when invoices are due - Net 30', // i18n-ignore - placeholder names, and reasons stored and shown through contractWithdrawnReasonText()
    'Owner' => 'who looks after it for you', // i18n-ignore - placeholder names, and reasons stored and shown through contractWithdrawnReasonText()
    'SLA' => 'on a line of its own, the response times for each priority as a table', // i18n-ignore - placeholder names, and reasons stored and shown through contractWithdrawnReasonText()
    'Date' => 'the day it is sent', // i18n-ignore - placeholder names, and reasons stored and shown through contractWithdrawnReasonText()
];

/** The largest signature picture taken, in bytes. */
const CONTRACT_SIGNATURE_MAX_BYTES = 400000;

/** Whether the 3.4.3 update has run - contract_signatures is there. */
function contractSignaturesReady() {
    global $mysqli;
    static $ready = null;
    if ($ready === null) {
        if (defined('CURRENT_DATABASE_VERSION')) {
            $version = CURRENT_DATABASE_VERSION;
        } else {
            $result = mysqli_query($mysqli, "SELECT config_current_database_version FROM settings WHERE company_id = 1");
            $version = $result && ($row = mysqli_fetch_row($result)) ? (string) $row[0] : '0';
        }
        $ready = version_compare($version, '3.4.3', '>=');
    }
    return $ready;
}

/* ---------------------------------------------------------------- the document */

/** A contract's money in the client's currency - "$165.00". */
function contractMoney($amount, array $row) {
    $currency = (string) ($row['client_currency_code'] ?? '');
    if ($currency === '') {
        $currency = (string) (billingCompany()['company_currency'] ?? '');
    }
    return billingMoney($amount, $currency);
}

/** Net terms in words - "Net 30", "Due on receipt". */
function contractNetTermsText($days) {
    $days = intval($days);
    return $days > 0 ? __('Net %d', $days) : __('Due on receipt');
}

/*
 * A day as the document writes it - "Nov 6, 2026". In English always as
 * date() writes it, so the fingerprint of a document sent before stays the
 * same; formatDate() output can change with the ICU library.
 */
function contractDocumentDate($date) {
    return i18nLocale() === 'en_US' ? date('M j, Y', strtotime((string) $date)) : formatDate($date, 'date');
}

/*
 * Whether the document of $row as of $as_of is the one with fingerprint
 * $hash - in any language ITFlow speaks, as the interface may have been in
 * another one when it was sent.
 */
function contractDocumentSame(array $row, $as_of, $hash) {
    $was = i18nLocale();
    try {
        foreach (array_unique(array_merge([$was], array_keys(i18nLanguages()))) as $locale) {
            i18nSetLocale($locale);
            if (contractDocumentHash(contractDocumentHtml($row, $as_of)) === (string) $hash) {
                return true;
            }
        }
        return false;
    } finally {
        i18nSetLocale($was);
    }
}

/*
 * The SLA for each priority as the contract sets it - its own, or what
 * applies when it sets none on $date (contractUsualTerms() - another contract
 * in force for the client, else the default from Admin > SLAs). [priority =>
 * ['sla' => row or null, 'own' => whether the contract sets it, 'from' =>
 * where one it doesn't set comes from - a contract's name, "the client's" or
 * '' for the default]].
 */
function contractSlaRows(array $row, $date = null) {
    $own = [];
    foreach (CONTRACT_SLA_PRIORITIES as $part) {
        $own[] = intval($row["contract_sla_{$part}_id"] ?? 0);
    }
    $usual_slas = contractUsualTerms($row, $date)['slas'];
    $usual = [];
    foreach (CONTRACT_SLA_PRIORITIES as $priority => $part) {
        $usual[$priority] = intval($row["contract_sla_{$part}_id"] ?? 0) ? 0 : intval($usual_slas[$priority][0]);
    }
    $slas = contractSlaChoices(array_merge($own, array_values($usual)));
    $rows = [];
    foreach (CONTRACT_SLA_PRIORITIES as $priority => $part) {
        $id = intval($row["contract_sla_{$part}_id"] ?? 0);
        $rows[$priority] = ['sla' => $slas[$id ?: $usual[$priority]] ?? null, 'own' => $id > 0, 'from' => $id > 0 ? '' : (string) $usual_slas[$priority][1]];
    }
    return $rows;
}

/** The response times as a table - for [SLA] and the document's own section - as of $date. */
function contractSlaTableHtml(array $row, $date = null) {
    $html = '<table class="itflow-contract-doc-table itflow-contract-doc-slas" width="100%" cellpadding="5" cellspacing="0"><thead><tr>'
        . '<th width="22%">' . escapeHtml(__('Priority')) . '</th><th width="38%">' . escapeHtml(__('Respond within')) . '</th><th width="40%">' . escapeHtml(__('Resolve within')) . '</th></tr></thead><tbody>';
    foreach (contractSlaRows($row, $date) as $priority => $item) {
        $sla = $item['sla'];
        $html .= '<tr><td width="22%">' . escapeHtml(_x($priority, 'ticket priority')) . '</td>'; // i18n-dynamic - Low, Medium, High, Urgent
        if ($sla) {
            $html .= '<td width="38%">' . escapeHtml(slaMinutesText($sla['sla_response_minutes'])) . '</td>'
                . '<td width="40%">' . (intval($sla['sla_resolution_minutes']) > 0 ? escapeHtml(slaMinutesText($sla['sla_resolution_minutes'])) : escapeHtml(__('No set time'))) . '</td>';
        } else {
            $html .= '<td width="38%">' . escapeHtml(__('No set time')) . '</td><td width="40%">' . escapeHtml(__('No set time')) . '</td>';
        }
        $html .= '</tr>';
    }
    return $html . '</tbody></table>';
}

/** What the placeholders become for a contract - lowercase name => value, raw. $as_of is the day it is sent ('Y-m-d'). */
function contractPlaceholderValues(array $row, $as_of = null) {
    global $mysqli;
    $as_of = $as_of ?: date('Y-m-d');
    $company = billingCompany();
    $bill_to = billingBillTo(intval($row['contract_client_id']));
    $contact = intval($row['contract_contact_id']) ? mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT contact_name, contact_title, contact_email FROM contacts
        WHERE contact_id = " . intval($row['contract_contact_id']) . " AND contact_client_id = " . intval($row['contract_client_id']))) : null;
    $day = function ($date) {
        return $date ? contractDocumentDate($date) : '';
    };
    $start = (string) ($row['contract_start_date'] ?? '');
    $end = (string) ($row['contract_end_date'] ?? '');
    if ($end === '' && $start !== '') {
        $end = contractTermEnd($start, $row['contract_term_months']);
    }
    $term = contractTermLabel($row['contract_term_months']);
    $notice = intval($row['contract_notice_days']);
    // What the contract leaves empty is what applies to the client on the day - another contract in force, else the default
    $usual = contractUsualTerms($row, $as_of);
    $rate = $row['contract_rate_standard'] !== null ? $row['contract_rate_standard'] : $usual['rate'][0];
    $after = $row['contract_rate_after_hours'] !== null ? $row['contract_rate_after_hours'] : $usual['after_hours'][0];
    $net = $row['contract_net_terms'] !== null ? $row['contract_net_terms'] : $usual['net_terms'][0];
    $slas = [];
    foreach (contractSlaRows($row, $as_of) as $priority => $item) {
        if ($item['sla']) {
            $slas[] = _x($priority, 'ticket priority') . ': ' . mb_strtolower(contractSlaTargets($item['sla'])); // i18n-dynamic - Low, Medium, High, Urgent
        }
    }
    return [
        'client' => (string) ($row['client_name'] ?? ''),
        'client address' => formatAddress($bill_to['location_address'] ?? '', $bill_to['location_city'] ?? '', $bill_to['location_state'] ?? '', $bill_to['location_zip'] ?? '', $bill_to['location_country'] ?? '', ', '),
        'contact' => (string) ($contact['contact_name'] ?? ''),
        'contact title' => (string) ($contact['contact_title'] ?? ''),
        'contact email' => (string) ($contact['contact_email'] ?? ''),
        'company' => (string) ($company['company_name'] ?? ''),
        'contract' => (string) $row['contract_name'],
        'type' => contractTypeLabel($row['contract_type']),
        'start date' => $day($start),
        'end date' => $day($end),
        'term' => $term,
        'renewal' => intval($row['contract_auto_renew'])
            ? ($notice ? __('It renews itself for another %1$s unless notice is given at least %2$d days before it ends.', $term, $notice) : __('It renews itself for another %s.', $term))
            : __('It ends on its last day unless it is renewed.'),
        'notice' => $notice ? __('%d days', $notice) : __('none'),
        'support hours' => (string) ($row['contract_support_hours'] ?? ''),
        'monthly hours' => floatval($row['contract_monthly_hours'] ?? 0) > 0 ? contractHoursWords($row['contract_monthly_hours']) : '',
        'rate' => $rate !== null && $rate !== '' ? contractMoney($rate, $row) : '',
        'after hours rate' => $after !== null ? contractMoney($after, $row) : '',
        'net terms' => $net !== null && $net !== '' ? contractNetTermsText($net) : '',
        'owner' => (string) ($row['owner_name'] ?? ''),
        'sla' => implode('; ', $slas),
        'date' => $day($as_of),
    ];
}

/*
 * The terms with their placeholders filled in - [SLA] on a line of its own
 * becomes the table of response times, in a sentence the times in words. A
 * [word] with nothing to put in stays as it is. $html is already made safe.
 */
function contractFillTerms($html, array $row, $as_of = null) {
    $space = '(?:\s|&nbsp;|\x{00A0})';
    $html = preg_replace_callback('/<(p|div|h[1-6])\b[^>]*>' . $space . '*\[SLA\]' . $space . '*<\/\1>/iu', function () use ($row, $as_of) {
        return contractSlaTableHtml($row, $as_of);
    }, (string) $html);
    return ticketPlaceholderFill($html, contractPlaceholderValues($row, $as_of), true);
}

/** The [words] left in filled-in terms - placeholders with nothing to put in, or words that aren't placeholders. */
function contractUnfilled($html) {
    return documentTemplateBracketsIn($html);
}

/*
 * The contract as a document - as of $as_of, the day it is sent (today when
 * not given). Escaped here; its terms are purified HTML. Classes only, no
 * styles: includes/contract_paper.php and the PDF dress it.
 */
function contractDocumentHtml(array $row, $as_of = null) {
    $company = billingCompany();
    $bill_to = billingBillTo(intval($row['contract_client_id']));
    $values = contractPlaceholderValues($row, $as_of);
    $e = function ($value) {
        return escapeHtml((string) $value);
    };

    $from_lines = array_filter([
        formatAddress($company['company_address'] ?? '', $company['company_city'] ?? '', $company['company_state'] ?? '', $company['company_zip'] ?? '', $company['company_country'] ?? '', "\n"),
        implode(' · ', array_filter([$company['company_email'] ?? '', $company['phone_display'] ?? ''])),
    ]);
    $to_lines = array_filter([
        $values['client address'] !== '' ? formatAddress($bill_to['location_address'] ?? '', $bill_to['location_city'] ?? '', $bill_to['location_state'] ?? '', $bill_to['location_zip'] ?? '', $bill_to['location_country'] ?? '', "\n") : '',
        $values['contact'] !== '' ? __('Attention: %s', $values['contact'] . ($values['contact title'] !== '' ? ', ' . $values['contact title'] : '')) : '',
        $values['contact email'],
    ]);

    $html = '<div class="itflow-contract-doc">';
    $html .= '<h1 class="itflow-contract-doc-title">' . $e($row['contract_name']) . '</h1>';
    $html .= '<p class="itflow-contract-doc-between">' . ($values['start date'] !== ''
        ? __('%1$s agreement between <strong>%2$s</strong> and <strong>%3$s</strong>, from %4$s.', $e(contractTypeLabel($row['contract_type'])), $e($values['company']), $e($values['client']), $e($values['start date']))
        : __('%1$s agreement between <strong>%2$s</strong> and <strong>%3$s</strong>.', $e(contractTypeLabel($row['contract_type'])), $e($values['company']), $e($values['client']))) . '</p>';

    $html .= '<table class="itflow-contract-doc-parties" width="100%" cellpadding="0" cellspacing="0"><tr>'
        . '<td width="50%"><div class="itflow-contract-doc-label">' . $e(__('Provider')) . '</div><strong>' . $e($values['company']) . '</strong><br><span class="itflow-contract-doc-muted">' . nl2br($e(implode("\n", $from_lines))) . '</span></td>'
        . '<td width="50%"><div class="itflow-contract-doc-label">' . $e(__('Client')) . '</div><strong>' . $e($values['client']) . '</strong><br><span class="itflow-contract-doc-muted">' . nl2br($e(implode("\n", $to_lines))) . '</span></td>'
        . '</tr></table>';

    // The agreement in a few lines
    $term = $values['term'];
    if ($values['start date'] !== '') {
        $term = $values['end date'] !== '' ? __('%1$s, from %2$s to %3$s', $term, $values['start date'], $values['end date']) : __('%1$s, from %2$s', $term, $values['start date']);
    }
    $rates = [];
    if ($values['rate'] !== '') {
        $rates[] = __('%s an hour', $values['rate']);
    }
    if ($values['after hours rate'] !== '') { // i18n-ignore - placeholder names, and reasons stored and shown through contractWithdrawnReasonText()
        $rates[] = __('%s an hour after hours', $values['after hours rate']);
    }
    $net = $values['net terms'];
    $net_days = intval(preg_replace('/\D/', '', $net));
    $summary = [
        __('Type') => contractTypeLabel($row['contract_type']),
        __('Term') => $term,
        __('Renewal') => $values['renewal'],
        __('Notice') => intval($row['contract_notice_days']) ? __('%s before it ends, in writing', $values['notice']) : __('None needed'),
        __('Support hours') => $values['support hours'],
        // Only when there are some - documents without them come out as they always did
        __('Included hours') => $values['monthly hours'] !== '' ? __('%s of support each month, used for that month only', $values['monthly hours']) : '',
        __('Rates') => implode('; ', $rates),
        __('Payment') => $net === '' ? '' : (!$net_days ? __('Invoices are due on receipt') : __('Invoices are due %1$d days after they are issued (%2$s)', $net_days, $net)),
    ];
    $html .= '<h2 class="itflow-contract-doc-heading">' . $e(__('The agreement')) . '</h2>';
    $html .= '<table class="itflow-contract-doc-table itflow-contract-doc-summary" width="100%" cellpadding="5" cellspacing="0"><tbody>';
    foreach ($summary as $label => $value) {
        if ($value !== '') {
            $html .= '<tr><th width="26%" align="left">' . $e($label) . '</th><td width="74%">' . $e($value) . '</td></tr>';
        }
    }
    $html .= '</tbody></table>';

    $html .= '<h2 class="itflow-contract-doc-heading">' . $e(__('Response times')) . '</h2>';
    $html .= contractSlaTableHtml($row, $as_of);

    $terms = trim((string) $row['contract_details']) !== '' ? contractFillTerms(documentHtmlPurify((string) $row['contract_details']), $row, $as_of) : '';
    if (trim(strip_tags($terms, '<img><table>')) !== '') {
        $html .= '<h2 class="itflow-contract-doc-heading">' . $e(_x('Terms', 'contract document')) . '</h2>';
        $html .= '<div class="itflow-contract-doc-terms">' . $terms . '</div>';
    }
    return $html . '</div>';
}

/** The fingerprint a document is signed by. */
function contractDocumentHash($html) {
    return hash('sha256', (string) $html);
}

/* ---------------------------------------------------------------- rounds of signing */

/** The columns a round is read with, and the contract and client it belongs to. */
function contractSignatureSelectSql() {
    return "SELECT contract_signatures.*, contract_id, contract_name, contract_status, contract_client_id, contract_owner_id, contract_start_date, contract_end_date, contract_term_months,
            client_name, client_archived_at, user_name AS owner_name, user_email AS owner_email
        FROM contract_signatures
        JOIN contracts ON contract_signature_contract_id = contract_id
        LEFT JOIN clients ON contract_client_id = client_id
        LEFT JOIN users ON contract_owner_id = user_id";
}

/** A round of signing, with its contract's name, status and client - or null. */
function contractSignatureRow($contract_signature_id) {
    global $mysqli;
    $contract_signature_id = intval($contract_signature_id);
    if ($contract_signature_id <= 0 || !contractSignaturesReady()) {
        return null;
    }
    return mysqli_fetch_assoc(mysqli_query($mysqli, contractSignatureSelectSql() . " WHERE contract_signature_id = $contract_signature_id LIMIT 1")) ?: null;
}

/** The round a link opens - its id and key, matched exactly - or null. */
function contractSignatureByKey($contract_signature_id, $key) {
    $key = is_string($key) ? $key : '';
    $round = contractSignatureRow($contract_signature_id);
    if (!$round || $key === '' || (string) $round['contract_signature_url_key'] === '' || !hash_equals((string) $round['contract_signature_url_key'], $key)) {
        return null;
    }
    return $round;
}

/** A contract's rounds, newest first. */
function contractSignatureRounds($contract_id) {
    global $mysqli;
    $rounds = [];
    if (!contractSignaturesReady()) {
        return $rounds;
    }
    $sql = mysqli_query($mysqli, contractSignatureSelectSql() . " WHERE contract_signature_contract_id = " . intval($contract_id) . " ORDER BY contract_signature_id DESC");
    while ($row = mysqli_fetch_assoc($sql)) {
        $rounds[] = $row;
    }
    return $rounds;
}

/** The latest round signed by both - the copy in force - or null. */
function contractSignatureSigned(array $rounds) {
    foreach ($rounds as $round) {
        if (!empty($round['contract_signature_client_signed_at']) && !empty($round['contract_signature_agent_signed_at'])) {
            return $round;
        }
    }
    return null;
}

/*
 * Where a round stands - key, label, Bootstrap tone, icon and a line saying
 * when: sent, viewed, countersign (the client has signed), signed (both),
 * withdrawn, incomplete (recorded before 3.4.3 and not signed by both - it
 * has no link, and waits on no one). One still waiting on a contract that has
 * ended or was cancelled, or whose client was archived, is withdrawn - the
 * nightly job marks it so (contractSignatureWithdrawStale()). null for no round.
 */
function contractSignatureState($round) {
    if (!$round) {
        return null;
    }
    $day = function ($date) {
        return formatDate($date, 'date');
    };
    $client = !empty($round['contract_signature_client_signed_at']);
    $agent = !empty($round['contract_signature_agent_signed_at']);
    if ($client && $agent) {
        return ['key' => 'signed', 'label' => _x('Signed', 'contract signing'), 'tone' => 'success', 'icon' => 'fa-file-signature', 'when' => __('Signed by both %s', $day(max($round['contract_signature_client_signed_at'], $round['contract_signature_agent_signed_at'])))];
    }
    if (!empty($round['contract_signature_withdrawn_at'])) {
        return ['key' => 'withdrawn', 'label' => _x('Withdrawn', 'contract signing'), 'tone' => 'secondary', 'icon' => 'fa-undo', 'when' => _x('Withdrawn %s', 'contract signing', $day($round['contract_signature_withdrawn_at']))];
    }
    // Waiting on a contract that is over, or an archived client's - nothing more is signed on it
    $closed = in_array($round['contract_status'] ?? '', ['Ended', 'Cancelled'], true) || !empty($round['client_archived_at']);
    if ($closed && !$agent && !empty($round['contract_signature_sent_at']) && (string) ($round['contract_signature_url_key'] ?? 'x') !== '') {
        return ['key' => 'withdrawn', 'label' => _x('Withdrawn', 'contract signing'), 'tone' => 'secondary', 'icon' => 'fa-undo',
            'when' => ['Cancelled' => __('The contract was cancelled'), 'Ended' => __('The contract ended')][$round['contract_status']] ?? __('The client was archived')];
    }
    // One recorded before 3.4.3 has no link - it waits on no one
    if (array_key_exists('contract_signature_url_key', $round) && (string) $round['contract_signature_url_key'] === '') {
        return ['key' => 'incomplete', 'label' => __('Not signed by both'), 'tone' => 'secondary', 'icon' => 'fa-file-alt', 'when' => __('Recorded before signing came to ITFlow')];
    }
    if ($client) {
        return ['key' => 'countersign', 'label' => __('To countersign'), 'tone' => 'warning', 'icon' => 'fa-pen-nib', 'when' => __('Signed by %1$s %2$s', $round['contract_signature_client_name'], $day($round['contract_signature_client_signed_at']))];
    }
    if ($agent || empty($round['contract_signature_sent_at'])) {
        return ['key' => 'incomplete', 'label' => __('Not signed by both'), 'tone' => 'secondary', 'icon' => 'fa-file-alt', 'when' => __('Recorded before signing came to ITFlow')];
    }
    if (!empty($round['contract_signature_viewed_at'])) {
        return ['key' => 'viewed', 'label' => _x('Opened', 'contract signing'), 'tone' => 'info', 'icon' => 'fa-envelope-open', 'when' => _x('Opened %s', 'contract signing', $day($round['contract_signature_viewed_at']))];
    }
    return ['key' => 'sent', 'label' => __('Out for signature'), 'tone' => 'primary', 'icon' => 'fa-paper-plane', 'when' => _x('Sent %s', 'contract signing', $day($round['contract_signature_sent_at']))];
}

/** Whether a round is waiting on someone - the client to sign, or us to countersign. */
function contractSignaturePending($round) {
    $state = contractSignatureState($round);
    return $state && in_array($state['key'], ['sent', 'viewed', 'countersign'], true);
}

/** Whether a contract has a round waiting - for the list's badge and the edit check. The latest round, or null. */
function contractSignaturePendingFor($contract_id) {
    $rounds = contractSignatureRounds($contract_id);
    return $rounds && contractSignaturePending($rounds[0]) ? $rounds[0] : null;
}

/** The signing link of a round - absolute when ITFlow's address is set. */
function contractSignatureUrl(array $round) {
    global $config_base_url;
    $path = '/guest/guest_view_contract.php?id=' . intval($round['contract_signature_id']) . '&key=' . rawurlencode((string) $round['contract_signature_url_key']);
    return !empty($config_base_url) ? "https://$config_base_url$path" : $path;
}

/*
 * A drawn signature from a form - a PNG data URI from the signing pad - checked
 * and redrawn: a real PNG, not too big, with something drawn on it, cut down
 * to what was drawn. [data URI, error].
 */
function contractSignatureImageFromInput($value) {
    $value = trim((string) $value);
    if (!preg_match('/^data:image\/png;base64,([A-Za-z0-9+\/=\s]+)$/', $value, $match)) {
        return [null, __('Draw your signature in the box, or type it.')];
    }
    $binary = base64_decode(preg_replace('/\s+/', '', $match[1]), true);
    if ($binary === false || strlen($binary) > CONTRACT_SIGNATURE_MAX_BYTES) {
        return [null, __('That signature is too big - clear it and draw it again.')];
    }
    $info = @getimagesizefromstring($binary);
    if (!$info || $info[2] !== IMAGETYPE_PNG || $info[0] < 40 || $info[1] < 20 || $info[0] > 3000 || $info[1] > 1500 || !function_exists('imagecreatefromstring')) {
        return [null, __('That signature could not be read - clear it and draw it again.')];
    }
    $image = @imagecreatefromstring($binary);
    if (!$image) {
        return [null, __('That signature could not be read - clear it and draw it again.')];
    }
    if (!imageistruecolor($image)) {
        imagepalettetotruecolor($image);
    }
    $width = imagesx($image);
    $height = imagesy($image);
    // Where the ink is - anything not transparent and not near white
    $left = $width;
    $top = $height;
    $right = $bottom = -1;
    $ink = 0;
    for ($y = 0; $y < $height; $y++) {
        for ($x = 0; $x < $width; $x++) {
            $color = imagecolorat($image, $x, $y);
            $alpha = ($color >> 24) & 0x7F;
            $light = (($color >> 16) & 0xFF) + (($color >> 8) & 0xFF) + ($color & 0xFF);
            if ($alpha < 100 && $light < 600) {
                $ink++;
                $left = min($left, $x);
                $right = max($right, $x);
                $top = min($top, $y);
                $bottom = max($bottom, $y);
            }
        }
    }
    if ($ink < 30 || $right - $left < 15) {
        imagedestroy($image);
        return [null, __('Draw your signature in the box, or type it.')];
    }
    // Cut down to the signature, with a little room round it, on a clear background
    $pad = 8;
    $left = max(0, $left - $pad);
    $top = max(0, $top - $pad);
    $cut_width = min($width, $right + $pad + 1) - $left;
    $cut_height = min($height, $bottom + $pad + 1) - $top;
    $out = imagecreatetruecolor($cut_width, $cut_height);
    imagealphablending($out, false);
    imagesavealpha($out, true);
    imagefill($out, 0, 0, imagecolorallocatealpha($out, 255, 255, 255, 127));
    imagecopy($out, $image, 0, 0, $left, $top, $cut_width, $cut_height);
    ob_start();
    imagepng($out, null, 6);
    $png = ob_get_clean();
    imagedestroy($image);
    imagedestroy($out);
    return ['data:image/png;base64,' . base64_encode($png), ''];
}

/*
 * A signature from a signing form - the name, the job title, and how it was
 * signed: drawn (the pad's picture) or typed (the name itself). [fields,
 * error]. $agree is the box saying they agree to sign electronically.
 */
function contractSignatureFromInput(array $in) {
    $text = function ($key, $max) use ($in) {
        return mb_substr(trim(preg_replace('/\s+/u', ' ', strip_tags((string) ($in[$key] ?? '')))), 0, $max);
    };
    $f = ['name' => $text('signer_name', 200), 'title' => $text('signer_title', 200), 'image' => null];
    if ($f['name'] === '') {
        return [$f, __('Write your full name.')];
    }
    if (($in['signature_mode'] ?? 'type') === 'draw') {
        [$f['image'], $error] = contractSignatureImageFromInput($in['signature'] ?? '');
        if ($error !== '') {
            return [$f, $error];
        }
    }
    if (empty($in['agree'])) {
        return [$f, __('Tick the box to say you agree to sign it.')];
    }
    return [$f, ''];
}

/*
 * Send a contract for signature - a new round with the document as it is
 * today, to $to_name at $to_email ($contact_id when one of the client's
 * contacts). Anything still waiting is withdrawn, replaced by this. The
 * email has $note in it when given. [round id, whether the email was queued].
 */
function contractSignatureSend(array $row, $to_name, $to_email, $contact_id, $note, $user_id) {
    global $mysqli;
    $contract_id = intval($row['contract_id']);
    foreach (contractSignatureRounds($contract_id) as $round) {
        if (contractSignaturePending($round)) {
            contractSignatureWithdraw($round, 'Replaced by a new request'); // i18n-ignore - placeholder names, and reasons stored and shown through contractWithdrawnReasonText()
        }
    }
    $document = contractDocumentHtml($row, date('Y-m-d'));
    $key = bin2hex(random_bytes(24));
    mysqli_query($mysqli, "INSERT INTO contract_signatures SET contract_signature_contract_id = $contract_id, contract_signature_url_key = '$key',
        contract_signature_document = '" . mysqli_real_escape_string($mysqli, $document) . "', contract_signature_document_hash = '" . contractDocumentHash($document) . "',
        contract_signature_contact_id = " . intval($contact_id) . ", contract_signature_sent_to_name = '" . escapeSql($to_name) . "',
        contract_signature_sent_to_email = '" . escapeSql($to_email) . "', contract_signature_sent_at = NOW(), contract_signature_sent_by = " . intval($user_id));
    $round_id = intval(mysqli_insert_id($mysqli));
    $queued = contractSignatureRequestEmail(contractSignatureRow($round_id), $note);
    return [$round_id, $queued];
}

/** Email the signing link of a round to whoever it was sent to - with $note when given. Whether it was queued. */
function contractSignatureRequestEmail(array $round, $note = '') {
    global $mysqli;
    $company = billingCompany();
    $company_name = (string) ($company['company_name'] ?? '');
    $by = mysqli_fetch_row(mysqli_query($mysqli, "SELECT user_name FROM users WHERE user_id = " . intval($round['contract_signature_sent_by'])))[0] ?? '';
    $term = !empty($round['contract_start_date']) ? __('%1$s from %2$s', contractTermLabel($round['contract_term_months']), formatDate($round['contract_start_date'], 'date')) : contractTermLabel($round['contract_term_months']);
    $sent = emailQueue([[
        'email' => (string) $round['contract_signature_sent_to_email'],
        'name' => (string) $round['contract_signature_sent_to_name'],
        'footnote' => __('You received this because %1$s asked you to sign for %2$s. The link is for you - please don\'t forward it.', $company_name, $round['client_name']),
    ]], __('Please sign: %1$s - %2$s', $round['contract_name'], $company_name), [
        'kind' => __('Contract'), 'tone' => 'primary',
        'title' => __('%1$s sent you %2$s to sign', $company_name, $round['contract_name']),
        'intro' => [__('Please read it and sign it online - it takes a minute, on a phone or a computer. Once we have countersigned, you will get a copy signed by both of us.')],
        'note' => trim((string) $note),
        'summary' => array_filter([__('Contract') => (string) $round['contract_name'], __('For') => (string) $round['client_name'], __('Term') => $term, __('Sent by') => (string) $by]),
        'button' => ['label' => __('Review and sign'), 'url' => contractSignatureUrl($round)],
    ], ['from' => contractEmailFrom()]);
    return (bool) $sent;
}

/** The mailbox contract emails to clients come from - the quotes one (Admin > Mail), else ITFlow's own. */
function contractEmailFrom() {
    $from = billingEmailFrom('quote');
    return $from['email'] !== '' ? $from : emailFrom();
}

/*
 * Withdraw what is still waiting - on the client's signature, or ours - on
 * contracts that have ended or were cancelled, or whose client was archived:
 * every one, or a client's ($client_id) or a contract's ($contract_id). Run
 * when a contract is cancelled, a client archived, and nightly. How many.
 */
function contractSignatureWithdrawStale($client_id = 0, $contract_id = 0) {
    global $mysqli;
    if (!contractSignaturesReady()) {
        return 0;
    }
    mysqli_query($mysqli, "UPDATE contract_signatures
        JOIN contracts ON contract_signature_contract_id = contract_id
        LEFT JOIN clients ON contract_client_id = client_id
        SET contract_signature_withdrawn_at = NOW(),
            contract_signature_withdrawn_reason = CASE WHEN contract_status = 'Cancelled' THEN 'The contract was cancelled'
                WHEN contract_status = 'Ended' THEN 'The contract ended' ELSE 'The client was archived' END
        WHERE contract_signature_withdrawn_at IS NULL AND contract_signature_agent_signed_at IS NULL AND contract_signature_url_key <> ''
            AND contract_signature_sent_at IS NOT NULL AND (contract_status IN ('Ended', 'Cancelled') OR client_archived_at IS NOT NULL)"
        . (intval($client_id) ? " AND contract_client_id = " . intval($client_id) : '') . (intval($contract_id) ? " AND contract_id = " . intval($contract_id) : ''));
    return max(0, mysqli_affected_rows($mysqli));
}

/** SQL: the round's contract is still open to signing - a draft or active one, its client not archived. */
function contractSignatureOpenSql() {
    return "EXISTS (SELECT 1 FROM contracts JOIN clients ON contract_client_id = client_id WHERE contract_id = contract_signature_contract_id
        AND contract_status IN ('Draft', 'Active') AND client_archived_at IS NULL)";
}

/** Withdraw a round - its link stops working for signing - and why. */
function contractSignatureWithdraw(array $round, $reason) {
    global $mysqli;
    mysqli_query($mysqli, "UPDATE contract_signatures SET contract_signature_withdrawn_at = NOW(), contract_signature_withdrawn_reason = '" . escapeSql(mb_substr((string) $reason, 0, 255)) . "'
        WHERE contract_signature_id = " . intval($round['contract_signature_id']) . " AND contract_signature_withdrawn_at IS NULL
        AND NOT (contract_signature_client_signed_at IS NOT NULL AND contract_signature_agent_signed_at IS NOT NULL)");
    return mysqli_affected_rows($mysqli) === 1;
}

/*
 * The client has opened a round - the first time is noted, and the agents
 * told. Nothing when an agent is looking (the portal's View as contact).
 */
function contractSignatureViewed(array $round, $who = '') {
    global $mysqli;
    if (!empty($round['contract_signature_viewed_at']) || !contractSignaturePending($round) || !empty($round['contract_signature_client_signed_at'])) {
        return;
    }
    mysqli_query($mysqli, "UPDATE contract_signatures SET contract_signature_viewed_at = NOW() WHERE contract_signature_id = " . intval($round['contract_signature_id']) . " AND contract_signature_viewed_at IS NULL");
    if (mysqli_affected_rows($mysqli) === 1) {
        $text = trim((string) $who) !== '' ? __('%1$s for %2$s was opened by %3$s in the client portal', $round['contract_name'], $round['client_name'], trim((string) $who))
            : __('%1$s for %2$s was opened by the client, from its link', $round['contract_name'], $round['client_name']);
        appNotify("Contract", escapeSql($text), contractAgentPath($round), intval($round['contract_client_id']), intval($round['contract_id']));
    }
}

/** A contract's page on the agent side. */
function contractAgentPath(array $row) {
    return "/agent/contract.php?client_id=" . intval($row['contract_client_id']) . "&contract_id=" . intval($row['contract_id']);
}

/*
 * The client signs a round - $f from contractSignatureFromInput(), $via
 * 'link' or 'portal', $contact_id who signed in the portal. Once only, not a
 * withdrawn round, and only while the contract is a draft or active and its
 * client isn't archived. The agents are told, and the owner emailed, to
 * countersign. Whether it was signed.
 */
function contractSignatureClientSign(array $round, array $f, $via, $contact_id = 0) {
    global $mysqli, $config_base_url;
    $ip = getIP();
    $agent = mb_substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255);
    mysqli_query($mysqli, "UPDATE contract_signatures SET contract_signature_client_name = '" . escapeSql($f['name']) . "',
        contract_signature_client_title = " . ($f['title'] !== '' ? "'" . escapeSql($f['title']) . "'" : 'NULL') . ",
        contract_signature_client_image = " . ($f['image'] ? "'" . mysqli_real_escape_string($mysqli, $f['image']) . "'" : 'NULL') . ",
        contract_signature_client_signed_at = NOW(), contract_signature_client_via = '" . ($via === 'portal' ? 'portal' : 'link') . "',
        contract_signature_client_contact_id = " . intval($contact_id) . ", contract_signature_client_ip = '" . escapeSql($ip) . "',
        contract_signature_client_user_agent = '" . mysqli_real_escape_string($mysqli, $agent) . "',
        contract_signature_viewed_at = IFNULL(contract_signature_viewed_at, NOW())
        WHERE contract_signature_id = " . intval($round['contract_signature_id']) . " AND contract_signature_client_signed_at IS NULL AND contract_signature_withdrawn_at IS NULL
        AND contract_signature_url_key <> '' AND " . contractSignatureOpenSql());
    if (mysqli_affected_rows($mysqli) !== 1) {
        return false;
    }
    $name = escapeSql($f['name']);
    $where = $via === 'portal' ? 'in the client portal' : 'from its link'; // i18n-ignore - placeholder names, and reasons stored and shown through contractWithdrawnReasonText()
    logAudit("Contract", "Sign", "$name signed contract " . escapeSql($round['contract_name']) . " for " . escapeSql($round['client_name']) . " $where", intval($round['contract_client_id']), intval($round['contract_id']));
    appNotify("Contract", escapeSql(__('%1$s signed %2$s for %3$s - it is ready to countersign', $f['name'], $round['contract_name'], $round['client_name'])), contractAgentPath($round), intval($round['contract_client_id']), intval($round['contract_id']));
    if (!empty($round['owner_email'])) {
        emailQueue([['email' => $round['owner_email'], 'name' => $round['owner_name'], 'footnote' => __('You look after this contract in ITFlow.')]],
            __('Signed by the client: %1$s - %2$s', $round['contract_name'], $round['client_name']), [
                'kind' => __('Contract'), 'tone' => 'success',
                'title' => __('%1$s signed %2$s', $f['name'], $round['contract_name']),
                'intro' => [$round['contract_status'] === 'Draft' ? __('It is ready for you to countersign. Once you have, the client gets a copy signed by both of you and the contract becomes active.') : __('It is ready for you to countersign. Once you have, the client gets a copy signed by both of you.')],
                'summary' => array_filter([__('Client') => (string) $round['client_name'], __('Signed by') => $f['name'] . ($f['title'] !== '' ? ", {$f['title']}" : ''),
                    _x('Signed', 'contract signing') => ($via === 'portal' ? __('%s in the client portal', formatDate(date('Y-m-d H:i:s'), 'datetime')) : __('%s from its link', formatDate(date('Y-m-d H:i:s'), 'datetime')))]),
                'button' => ['label' => __('Countersign'), 'url' => "https://$config_base_url" . contractAgentPath($round)],
            ]);
    }
    return true;
}

/*
 * An agent countersigns a round the client has signed - $f from
 * contractSignatureFromInput() - while the contract is a draft or active and
 * its client isn't archived. A draft contract becomes active (from its start
 * date, or today), and the client gets the PDF signed by both - unless $email
 * is false. Whether it was signed.
 */
function contractSignatureAgentSign(array $round, array $f, $user_id, $email = true) {
    global $mysqli;
    mysqli_query($mysqli, "UPDATE contract_signatures SET contract_signature_agent_user_id = " . intval($user_id) . ", contract_signature_agent_name = '" . escapeSql($f['name']) . "',
        contract_signature_agent_title = " . ($f['title'] !== '' ? "'" . escapeSql($f['title']) . "'" : 'NULL') . ",
        contract_signature_agent_image = " . ($f['image'] ? "'" . mysqli_real_escape_string($mysqli, $f['image']) . "'" : 'NULL') . ",
        contract_signature_agent_signed_at = NOW(), contract_signature_agent_ip = '" . escapeSql(getIP()) . "'
        WHERE contract_signature_id = " . intval($round['contract_signature_id']) . " AND contract_signature_client_signed_at IS NOT NULL
        AND contract_signature_agent_signed_at IS NULL AND contract_signature_withdrawn_at IS NULL AND " . contractSignatureOpenSql());
    if (mysqli_affected_rows($mysqli) !== 1) {
        return false;
    }
    $row = contractRow($round['contract_id']);
    if ($row && $row['contract_status'] === 'Draft') {
        contractActivate($row, !empty($row['contract_start_date']) ? (string) $row['contract_start_date'] : date('Y-m-d'));
    }
    if ($email) {
        contractSignatureSignedEmail(contractSignatureRow($round['contract_signature_id']));
    }
    return true;
}

/*
 * Email the copy signed by both to whoever signed it for the client - and to
 * whoever it was sent to, when someone else signed in the portal - with the
 * PDF. $attach false leaves the PDF off (the email previews). Who it went to.
 */
function contractSignatureSignedEmail(array $round, $attach = true) {
    global $mysqli;
    $company = billingCompany();
    $company_name = (string) ($company['company_name'] ?? '');
    $recipients = [];
    if (filter_var((string) $round['contract_signature_sent_to_email'], FILTER_VALIDATE_EMAIL)) {
        $recipients[] = ['email' => (string) $round['contract_signature_sent_to_email'], 'name' => (string) $round['contract_signature_sent_to_name']];
    }
    if (intval($round['contract_signature_client_contact_id'])) {
        $signer = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT contact_name, contact_email FROM contacts WHERE contact_id = " . intval($round['contract_signature_client_contact_id'])));
        if ($signer && filter_var((string) $signer['contact_email'], FILTER_VALIDATE_EMAIL)) {
            $recipients[] = ['email' => (string) $signer['contact_email'], 'name' => (string) $signer['contact_name']];
        }
    }
    if (!$recipients) {
        return [];
    }
    foreach ($recipients as $i => $recipient) {
        $recipients[$i]['footnote'] = __('You received this as the signer for %s.', $round['client_name']);
    }
    $attachments = [];
    if ($attach) {
        $file = billingEmailPdf(contractPdf(contractRow($round['contract_id']), $round));
        if ($file) {
            $attachments[] = $file;
        }
    }
    $signed_on = function ($side) use ($round) {
        $name = (string) $round["contract_signature_{$side}_name"];
        return !empty($round["contract_signature_{$side}_signed_at"]) ? trim(__('%1$s on %2$s', $name, formatDate($round["contract_signature_{$side}_signed_at"], 'date'))) : trim($name);
    };
    return emailQueue($recipients, __('Signed: %1$s - %2$s', $round['contract_name'], $company_name), [
        'kind' => __('Contract'), 'tone' => 'success',
        'title' => __('%s is signed', $round['contract_name']),
        'intro' => [$attach ? __('Thank you - both of us have signed it. A copy signed by both is attached. Keep it for your records.') : __('Thank you - both of us have signed it. A copy signed by both is attached as a PDF. Keep it for your records.')],
        'summary' => array_filter([
            __('Contract') => (string) $round['contract_name'],
            __('Signed for %s', $round['client_name']) => $signed_on('client'),
            __('Signed for %s', $company_name) => $signed_on('agent'),
        ]),
    ], ['from' => contractEmailFrom(), 'attachments' => $attachments]);
}

/*
 * The signature block - the client's and ours, each the picture drawn or the
 * name typed, the name, title and date; an empty line to sign on while not
 * signed. $image_url gives a side's picture an address (the client portal
 * allows no data: pictures); otherwise the picture is put in the page.
 */
function contractSignatureBlockHtml(array $row, $round = null, $image_url = null) {
    $company = billingCompany();
    $sides = [
        'client' => ['for' => (string) $row['client_name']],
        'agent' => ['for' => (string) ($company['company_name'] ?? '')],
    ];
    $html = '<div class="itflow-contract-signatures">';
    foreach ($sides as $side => $info) {
        $signed = $round && !empty($round["contract_signature_{$side}_signed_at"]);
        $html .= '<div class="itflow-contract-signature' . ($signed ? ' is-signed' : '') . '" data-contract-signature="' . $side . '">';
        $html .= '<div class="itflow-contract-doc-label">' . escapeHtml(__('Signed for %s', $info['for'])) . '</div>';
        $html .= '<div class="itflow-contract-signature-mark">';
        if ($signed && !empty($round["contract_signature_{$side}_image"])) {
            $src = $image_url ? $image_url($side) : (string) $round["contract_signature_{$side}_image"];
            $html .= '<img src="' . escapeHtml($src) . '" alt="' . escapeHtml(__('Signature of %s', $round["contract_signature_{$side}_name"])) . '">';
        } elseif ($signed) {
            $html .= '<span class="itflow-contract-signature-typed">' . escapeHtml($round["contract_signature_{$side}_name"]) . '</span>';
        }
        $html .= '</div>';
        if ($signed) {
            $html .= '<div class="fw-semibold">' . escapeHtml($round["contract_signature_{$side}_name"]) . '</div>';
            $html .= '<div class="small text-body-secondary">' . escapeHtml(implode(' · ', array_filter([(string) $round["contract_signature_{$side}_title"], formatDate($round["contract_signature_{$side}_signed_at"], 'date')]))) . '</div>';
        } else {
            $html .= '<div class="small text-body-secondary">' . escapeHtml(__('Name, title and date')) . '</div>';
        }
        $html .= '</div>';
    }
    return $html . '</div>';
}

/*
 * What happened to a round, in order - for its page and the PDF's last
 * section: kind (sent, opened, signed, countersigned, withdrawn) => [when,
 * what, detail] - raw text; the detail (from where, and how)
 * may be ''.
 */
function contractSignatureTrail(array $round) {
    global $mysqli;
    $trail = [];
    $at = function ($date) {
        return formatDate($date, 'datetime');
    };
    if (!empty($round['contract_signature_sent_at'])) {
        $by = mysqli_fetch_row(mysqli_query($mysqli, "SELECT user_name FROM users WHERE user_id = " . intval($round['contract_signature_sent_by'])))[0] ?? '';
        $trail['sent'] = [$at($round['contract_signature_sent_at']), $by !== '' ? __('Sent to %1$s by %2$s', $round['contract_signature_sent_to_name'], $by) : __('Sent to %s', $round['contract_signature_sent_to_name']), (string) $round['contract_signature_sent_to_email']];
    }
    if (!empty($round['contract_signature_viewed_at'])) {
        $trail['opened'] = [$at($round['contract_signature_viewed_at']), __('Opened by the client'), ''];
    }
    if (!empty($round['contract_signature_client_signed_at'])) {
        $how = ['link' => __('From the link in the email'), 'portal' => __('In the client portal'), 'recorded' => __('Recorded before signing came to ITFlow')][$round['contract_signature_client_via']] ?? '';
        $device = trim(strip_tags(getOS((string) $round['contract_signature_client_user_agent'])) . ', ' . strip_tags(getWebBrowser((string) $round['contract_signature_client_user_agent'])), ' ,-');
        $trail['signed'] = [$at($round['contract_signature_client_signed_at']), __('Signed for %1$s by %2$s', $round['client_name'], $round['contract_signature_client_name']
            . (!empty($round['contract_signature_client_title']) ? ', ' . $round['contract_signature_client_title'] : '')),
            implode(' · ', array_filter([$how, !empty($round['contract_signature_client_ip']) ? __('IP address %s', $round['contract_signature_client_ip']) : '', $device,
                $round['contract_signature_client_via'] === 'recorded' ? '' : (!empty($round['contract_signature_client_image']) ? __('Signature drawn') : __('Signature typed'))]))];
    }
    if (!empty($round['contract_signature_agent_signed_at'])) {
        $trail['countersigned'] = [$at($round['contract_signature_agent_signed_at']), __('Countersigned for %1$s by %2$s', billingCompany()['company_name'] ?? '', $round['contract_signature_agent_name']
            . (!empty($round['contract_signature_agent_title']) ? ', ' . $round['contract_signature_agent_title'] : '')),
            !empty($round['contract_signature_agent_ip']) ? __('IP address %s', $round['contract_signature_agent_ip']) : ''];
    }
    if (!empty($round['contract_signature_withdrawn_at']) && empty($round['contract_signature_agent_signed_at'])) {
        $trail['withdrawn'] = [$at($round['contract_signature_withdrawn_at']), _x('Withdrawn', 'contract signing'), contractWithdrawnReasonText($round['contract_signature_withdrawn_reason'])];
    }
    return $trail;
}

/*
 * Why a round was withdrawn, as stored (in English) - in the interface
 * language when ITFlow wrote it, else as it is.
 */
function contractWithdrawnReasonText($reason) {
    $reason = (string) $reason;
    $known = [
        'Replaced by a new request' => __('Replaced by a new request'),
        'The contract was changed after it was sent' => __('The contract was changed after it was sent'),
        'The contract was cancelled' => __('The contract was cancelled'),
        'The contract ended' => __('The contract ended'),
        'The client was archived' => __('The client was archived'),
    ];
    if (isset($known[$reason])) {
        return $known[$reason];
    }
    if (preg_match('/^Withdrawn by (.+)$/s', $reason, $m)) {
        return __('Withdrawn by %s', $m[1]);
    }
    return $reason;
}

/*
 * After a contract is changed: when it is out for signature and the
 * document comes out different from the one sent, the request is withdrawn -
 * no one should sign what it no longer says. The round withdrawn, or null.
 */
function contractSignatureCheckChanged(array $row) {
    $round = contractSignaturePendingFor(intval($row['contract_id']));
    if (!$round) {
        return null;
    }
    $as_of = date('Y-m-d', strtotime((string) $round['contract_signature_sent_at']));
    if (contractDocumentSame($row, $as_of, $round['contract_signature_document_hash'])) {
        return null;
    }
    return contractSignatureWithdraw($round, 'The contract was changed after it was sent') ? $round : null; // i18n-ignore - placeholder names, and reasons stored and shown through contractWithdrawnReasonText()
}

/** Whether what a contract says now differs from a signed round - by what the document would be on the day that round was sent. */
function contractSignatureOutdated(array $row, array $round) {
    if (empty($round['contract_signature_sent_at'])) {
        return false;
    }
    $as_of = date('Y-m-d', strtotime((string) $round['contract_signature_sent_at']));
    return !contractDocumentSame($row, $as_of, $round['contract_signature_document_hash']);
}

/** A contract as a PDF - the copy of a round ($round) when given, else the document as it is today. ['name', 'data']. */
function contractPdf(array $row, $round = null) {
    require_once __DIR__ . '/../libs/TCPDF/tcpdf.php';
    require_once __DIR__ . '/contract_pdf.php';
    return contractPdfRender($row, $round);
}
