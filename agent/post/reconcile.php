<?php

/*
 * ITFlow - reconciling an account against a statement (functions/reconcile.php
 * does the work; ticking lines is agent/ajax.php's reconcile_tick). Write
 * access to Financial starts, changes, finishes and discards one, and
 * corrects the opening balance where an account was brought forward;
 * undoing a finished one takes full access.
 */

defined('FROM_POST_HANDLER') || die("Direct file access is not allowed");

/** The statement fields as reconcileStart() and reconcileUpdate() take them. */
function reconcilePostInput() {
    return [
        'statement_date' => (string) ($_POST['statement_date'] ?? ''),
        'ending_balance' => (string) ($_POST['ending_balance'] ?? ''),
        'notes' => (string) ($_POST['notes'] ?? ''),
        'start_from' => (string) ($_POST['start_from'] ?? ''),
        'start_date' => (string) ($_POST['start_date'] ?? ''),
        'start_balance' => (string) ($_POST['start_balance'] ?? ''),
    ];
}

/** "Operating Checking statement to Sep 30, 2026" */
function reconcileLabel(array $row) {
    return __('%1$s statement to %2$s', $row['account']['account_name'], formatDate($row['reconciliation_statement_date'], 'date'));
}

if (isset($_POST['reconcile_start'])) {

    validateCSRFToken();

    enforceUserPermission('module_financial', 2);

    $account_id = intval($_POST['account_id'] ?? 0);
    $had_opening = (bool) reconcileOpening($account_id);
    $id = reconcileStart($account_id, reconcilePostInput(), $error);
    if (!$id) {
        flashAlert(escapeHtml($error), 'error');
        redirect("reconcile.php?account=$account_id");
    }
    $row = reconcileRow($id);
    $totals = reconcileTotals($row);
    $opening = reconcileOpening($row['reconciliation_account_id']);
    if ($opening && !$had_opening) {
        logAudit("Reconciliation", "Create", "$session_name brought " . escapeSql($row['account']['account_name']) . " forward as reconciled to "
            . date('M j, Y', strtotime($opening['reconciliation_statement_date'])) . " at a bank balance of " . escapeSql(i18nEnglish(fn () => reconcileBalanceText($row['account'], $opening['reconciliation_ending_balance']))), 0, intval($opening['reconciliation_id']));
    }

    logAudit("Reconciliation", "Create", "$session_name started reconciling the " . escapeSql(i18nEnglish(fn () => reconcileLabel($row))), 0, $id);

    flashAlert(__('Reconciling the %s', '<strong>' . escapeHtml(reconcileLabel($row)) . '</strong>') . ($totals['count'] ? _n(' - %d line from bank statements ticked already', ' - %d lines from bank statements ticked already', $totals['count']) : ''));

    redirect("reconcile.php?account=$account_id");

}

if (isset($_POST['reconcile_update'])) {

    validateCSRFToken();

    enforceUserPermission('module_financial', 2);

    $id = intval($_POST['reconciliation_id'] ?? 0);
    $before = reconcileRow($id);
    if (!reconcileUpdate($id, reconcilePostInput(), $error)) {
        flashAlert(escapeHtml($error), 'error');
        redirect($before ? 'reconcile.php?account=' . intval($before['reconciliation_account_id']) : 'accounts.php');
    }
    $row = reconcileRow($id);

    logAudit("Reconciliation", "Edit", "$session_name changed the statement being reconciled to the " . escapeSql(i18nEnglish(fn () => reconcileLabel($row))) . ", ending at "
        . escapeSql(i18nEnglish(fn () => reconcileBalanceText($row['account'], $row['reconciliation_ending_balance']))), 0, $id);

    flashAlert(__('Statement changed - %s', '<strong>' . escapeHtml(reconcileLabel($row)) . '</strong>'));

    redirect('reconcile.php?account=' . intval($row['reconciliation_account_id']));

}

if (isset($_POST['reconcile_finish'])) {

    validateCSRFToken();

    enforceUserPermission('module_financial', 2);

    $id = intval($_POST['reconciliation_id'] ?? 0);
    $row = reconcileRow($id);
    $totals = $row ? reconcileFinish($id, $error) : null;
    if (!$totals) {
        flashAlert(escapeHtml($row ? $error : __('That reconciliation no longer exists')), 'error');
        redirect($row ? 'reconcile.php?account=' . intval($row['reconciliation_account_id']) : 'accounts.php');
    }

    logAudit("Reconciliation", "Finish", "$session_name reconciled the " . escapeSql(i18nEnglish(fn () => reconcileLabel($row))) . " - {$totals['count']} lines cleared, ending at "
        . escapeSql(i18nEnglish(fn () => reconcileBalanceText($row['account'], $totals['ending']))), 0, $id);

    flashAlert(_n('%2$s is reconciled to %3$s - %1$d line cleared', '%2$s is reconciled to %3$s - %1$d lines cleared', $totals['count'], $totals['count'], '<strong>' . escapeHtml($row['account']['account_name']) . '</strong>', escapeHtml(formatDate($row['reconciliation_statement_date'], 'date'))));

    redirect('reconcile.php?account=' . intval($row['reconciliation_account_id']));

}

if (isset($_GET['reconcile_discard'])) {

    validateCSRFToken();

    enforceUserPermission('module_financial', 2);

    $row = reconcileDiscard(intval($_GET['reconcile_discard']), $error);
    if (!$row) {
        flashAlert(escapeHtml($error), 'error');
        redirect();
    }

    logAudit("Reconciliation", "Delete", "$session_name discarded reconciling the " . escapeSql(i18nEnglish(fn () => reconcileLabel($row))), 0, intval($row['reconciliation_id']));

    flashAlert(__('Reconciling the %s discarded - its ticks are off', '<strong>' . escapeHtml(reconcileLabel($row)) . '</strong>'), 'error');

    redirect('reconcile.php?account=' . intval($row['reconciliation_account_id']));

}

if (isset($_GET['reconcile_undo'])) {

    validateCSRFToken();

    enforceUserPermission('module_financial', 3);

    $row = reconcileUndo(intval($_GET['reconcile_undo']), $error);
    if (!$row) {
        flashAlert(escapeHtml($error), 'error');
        redirect();
    }

    if ($row['opening']) {
        logAudit("Reconciliation", "Undo", "$session_name took away " . escapeSql($row['account']['account_name']) . " being brought forward as reconciled to " . date('M j, Y', strtotime($row['reconciliation_statement_date']))
            . (abs(floatval($row['reconciliation_adjustment'])) >= 0.005 ? ', the opening balance back to ' . escapeSql(billingMoney(accountRow($row['reconciliation_account_id'])['opening_balance'], $row['account']['account_currency_code'])) : ''), 0, intval($row['reconciliation_id'])); // i18n-ignore - the audit log
        flashAlert(__('%s is no longer brought forward - its next statement can start from either balance', '<strong>' . escapeHtml($row['account']['account_name']) . '</strong>'), 'warning');
        redirect('reconcile.php?account=' . intval($row['reconciliation_account_id']));
    }

    logAudit("Reconciliation", "Undo", "$session_name undid the reconciliation of the " . escapeSql(i18nEnglish(fn () => reconcileLabel($row))) . " - back to in progress", 0, intval($row['reconciliation_id']));

    flashAlert(__('The %s is being reconciled again - its lines can change, and it can be finished or discarded', '<strong>' . escapeHtml(reconcileLabel($row)) . '</strong>'), 'warning');

    redirect('reconcile.php?account=' . intval($row['reconciliation_account_id']));

}

/*
 * Correct an account's opening balance by how far ITFlow was off the bank on
 * the day it was brought forward, so the two match from then on.
 */
if (isset($_GET['reconcile_correct_opening'])) {

    validateCSRFToken();

    enforceUserPermission('module_financial', 2);

    $row = reconcileRow(intval($_GET['reconcile_correct_opening']));
    $gap = $row ? reconcileCorrectOpening($row['reconciliation_id'], $error) : null;
    if ($gap === null) {
        flashAlert(escapeHtml($row ? $error : __('That reconciliation no longer exists')), 'error');
        redirect();
    }
    $account = accountRow($row['reconciliation_account_id']);
    $moved = billingMoney($gap, $account['account_currency_code']);

    logAudit("Account", "Edit", "$session_name corrected the opening balance of " . escapeSql($account['account_name']) . " by " . escapeSql($moved) . ", to "
        . escapeSql(billingMoney($account['opening_balance'], $account['account_currency_code'])) . ", to match the bank on " . date('M j, Y', strtotime($row['reconciliation_statement_date'])), 0, intval($account['account_id']));

    flashAlert(__("%1\$s's opening balance is corrected by %2\$s - ITFlow matches the bank from %3\$s on", '<strong>' . escapeHtml($account['account_name']) . '</strong>', escapeHtml($moved), escapeHtml(formatDate($row['reconciliation_statement_date'], 'date'))));

    redirect('reconcile.php?account=' . intval($account['account_id']));

}
