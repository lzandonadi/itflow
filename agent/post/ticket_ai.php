<?php

/*
 * ITFlow - AI on a ticket (functions/ai_ticket.php): a summary saved as an
 * internal note, and triage - asked for from the ticket's menu, then applied
 * or dismissed from the suggestion on the ticket. Each needs the role's Use
 * AI permission, Support to change tickets, and the ticket's client.
 */

defined('FROM_POST_HANDLER') || die("Direct file access is not allowed");

// The AI summary, kept on the ticket as an internal note
if (isset($_POST['add_ticket_ai_note'])) {

    validateCSRFToken();

    $row = ticketForRequest($_POST['ticket_id'] ?? 0, 2);
    $ticket_id = intval($row['ticket_id']);
    if (!empty($row['ticket_closed_at'])) {
        flashAlert(__('Ticket <strong>%s</strong> is closed - nothing more goes on it.', escapeHtml(ticketReference($row))), 'error');
        redirect();
    }
    // What the window showed, cleaned again - it came back from the page
    $note = trim(documentHtmlPurify((string) ($_POST['note'] ?? '')));
    if (trim(strip_tags($note)) === '') {
        flashAlert(__('There was no summary to save - ask for it again.'), 'error');
        redirect();
    }
    $note = mysqli_real_escape_string($mysqli, '<p><strong>' . escapeHtml(__('AI summary')) . '</strong></p>' . $note);

    mysqli_query($mysqli, "INSERT INTO ticket_replies SET ticket_reply = '$note', ticket_reply_type = 'Internal', ticket_reply_time_worked = '00:00:00', ticket_reply_by = $session_user_id, ticket_reply_ticket_id = $ticket_id");
    mysqli_query($mysqli, "UPDATE tickets SET ticket_updated_at = NOW() WHERE ticket_id = $ticket_id");

    logAudit("Ticket", "Reply", "$session_name saved an AI summary as an internal note on ticket " . escapeSql(ticketReference($row)), $client_id, $ticket_id);
    flashAlert(__('Summary saved as an internal note on <strong>%s</strong>', escapeHtml(ticketReference($row))));
    redirect();

}

// Triage with AI, from the ticket's menu - what it suggests shows on the ticket, to apply or dismiss
if (isset($_GET['triage_ticket_ai'])) {

    validateCSRFToken();

    $row = ticketForRequest($_GET['triage_ticket_ai'], 2);
    if (!aiCan()) {
        permissionRefuse(permissionRefusedText('module_ai', 1, 0));
    }
    $result = aiTicketTriage(intval($row['ticket_id']));
    if (!$result['ok']) {
        flashAlert(__("AI triage didn't work - %s", escapeHtml($result['error'])), 'error');
        redirect();
    }
    $changes = aiTriageChanges($result['triage'], $row);
    flashAlert(!$changes ? __('AI triage agrees with the category and priority it has') : (count($changes) > 1 ? __('AI triage suggests a category and a priority - apply them or dismiss them on the ticket') : (isset($changes['category']) ? __('AI triage suggests a category - apply it or dismiss it on the ticket') : __('AI triage suggests a priority - apply it or dismiss it on the ticket'))), 'info');
    redirect();

}

// Apply what AI triage suggests - or dismiss it
if (isset($_POST['apply_ticket_ai_triage']) || isset($_POST['dismiss_ticket_ai_triage'])) {

    validateCSRFToken();

    $row = ticketForRequest($_POST['ticket_id'] ?? 0, 2);
    $ticket_id = intval($row['ticket_id']);
    $triage = aiTriageRow($ticket_id);
    if (!$triage || $triage['ai_triage_state'] !== 'suggested') {
        flashAlert(__('There is no AI triage waiting on this ticket.'), 'info');
        redirect();
    }

    if (isset($_POST['dismiss_ticket_ai_triage'])) {
        mysqli_query($mysqli, "UPDATE ai_ticket_triage SET ai_triage_state = 'dismissed', ai_triage_by = $session_user_id WHERE ai_triage_ticket_id = $ticket_id");
        flashAlert(__('AI triage dismissed'), 'info');
        redirect();
    }

    if (!ticketIsOpen($row)) {
        flashAlert(__("Ticket <strong>%s</strong> isn't open - reopen it to change it.", escapeHtml(ticketReference($row))), 'error');
        redirect();
    }
    // Those ticked - both, without the window's script
    $only = array_values(array_intersect((array) ($_POST['apply'] ?? ['category', 'priority']), ['category', 'priority']));
    $changes = aiTriageApply($ticket_id, stripslashes($session_name), $only, 'applied', $session_user_id);
    if (!$changes) {
        flashAlert(__('Nothing to change - the ticket has that already'), 'info');
        redirect();
    }
    $said = [];
    foreach ($changes as $field => $change) {
        $said[] = $field === 'priority' ? __('Priority <strong>%s</strong>', escapeHtml(ticketPriorityLabel($change[1]))) : __('Category <strong>%s</strong>', escapeHtml($change[1]));
    }
    flashAlert(__('%1$s set on <strong>%2$s</strong>', count($said) > 1 ? __('%1$s and %2$s', $said[0], $said[1]) : $said[0], escapeHtml(ticketReference($row))));
    redirect();

}
