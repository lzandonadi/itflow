<?php

/*
 * Send a contract for signature - to one of the client's contacts, or a
 * name and address typed in, with a note for them. The email has a link to
 * read and sign it (guest/guest_view_contract.php); it can be signed in the
 * client portal too. Says what in the document has nothing to fill it in,
 * and what sending again does to a request already out or a copy signed.
 */

require_once '../../../includes/modal_header.php';

enforceUserPermission('module_sales', 2);

$contract = contractRow(intval($_GET['id'] ?? 0));
if ($contract) {
    $client_id = intval($contract['contract_client_id']);
    enforceClientAccess();
}

ob_start();

if (!$contract || !contractSignaturesReady() || in_array($contract['contract_status'], ['Ended', 'Cancelled'], true)) { ?>
    <div class="modal-body text-center text-secondary py-5">
        <?= !$contract ? __('That contract is gone - it may have been deleted.') : (!contractSignaturesReady() ? __('Signing needs the database update - run it from Maintenance > Update first.') : __('An ended or cancelled contract isn\'t sent for signature - renew it first.')) ?>
    </div>
<?php } else {
    $contacts = [];
    $sql = mysqli_query($mysqli, "SELECT contact_id, contact_name, contact_title, contact_email, contact_primary, contact_billing FROM contacts
        WHERE contact_client_id = $client_id AND contact_archived_at IS NULL ORDER BY contact_primary DESC, contact_billing DESC, contact_name ASC");
    while ($row = mysqli_fetch_assoc($sql)) {
        $contacts[intval($row['contact_id'])] = $row;
    }
    // The contract's own contact, else the primary or billing contact with an address
    $pick = intval($contract['contract_contact_id']);
    if (!isset($contacts[$pick]) || !filter_var((string) $contacts[$pick]['contact_email'], FILTER_VALIDATE_EMAIL)) {
        $pick = 0;
        foreach ($contacts as $id => $row) {
            if (filter_var((string) $row['contact_email'], FILTER_VALIDATE_EMAIL)) {
                $pick = $id;
                break;
            }
        }
    }
    $rounds = contractSignatureRounds(intval($contract['contract_id']));
    $pending = $rounds && contractSignaturePending($rounds[0]) ? $rounds[0] : null;
    $signed = contractSignatureSigned($rounds);
    $unfilled = contractUnfilled(contractDocumentHtml($contract));
    $draft_start = $contract['contract_status'] === 'Draft' ? (!empty($contract['contract_start_date']) ? formatDate($contract['contract_start_date'], 'date') : __('the day it is countersigned')) : '';
    ?>

<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fas fa-fw fa-file-signature me-2"></i><?= __('Send <strong>%s</strong> to sign', escapeHtml($contract['contract_name'])) ?></h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <input type="hidden" name="contract_id" value="<?= intval($contract['contract_id']) ?>">
    <div class="modal-body">

        <?php if ($pending) { ?>
            <div class="alert alert-info small py-2"><i class="fas fa-fw fa-info-circle me-1"></i><?= !empty($pending['contract_signature_client_signed_at'])
                ? __('It is already out for signature with <strong>%1$s</strong> (%2$s). Sending it now withdraws that request, signature and all.', escapeHtml($pending['contract_signature_sent_to_name']), escapeHtml(contractSignatureState($pending)['when']))
                : __('It is already out for signature with <strong>%1$s</strong> (%2$s). Sending it now withdraws that request.', escapeHtml($pending['contract_signature_sent_to_name']), escapeHtml(contractSignatureState($pending)['when'])) ?></div>
        <?php } elseif ($signed) { ?>
            <div class="alert alert-secondary small py-2"><i class="fas fa-fw fa-file-signature me-1"></i><?= escapeHtml(__('It was signed by both on %s. Sending it starts a new signing - that copy is kept.', formatDate(max($signed['contract_signature_client_signed_at'], $signed['contract_signature_agent_signed_at']), 'date'))) ?></div>
        <?php } ?>
        <?php if ($unfilled) { ?>
            <div class="alert alert-warning small py-2" data-contract-unfilled><i class="fas fa-fw fa-exclamation-triangle me-1"></i><?= escapeHtml(__('Nothing fills in %s - it goes out as written. Edit the contract first to fill it in.', implode(', ', array_map(function ($word) { return "[$word]"; }, $unfilled)))) ?></div>
        <?php } ?>

        <label class="form-label"><?= escapeHtml(__('Who signs for %s', $contract['client_name'])) ?></label>
        <div class="input-group mb-2">
            <span class="input-group-text"><i class="fa fa-fw fa-user"></i></span>
            <select class="form-select" name="contact_id">
                <?php foreach ($contacts as $id => $row) {
                    $has_email = filter_var((string) $row['contact_email'], FILTER_VALIDATE_EMAIL); ?>
                    <option value="<?= $id ?>"<?= $id === $pick ? ' selected' : '' ?><?= $has_email ? '' : ' disabled' ?>>
                        <?= escapeHtml($row['contact_name'] . ($row['contact_title'] ? ' - ' . $row['contact_title'] : '') . ($has_email ? ' (' . $row['contact_email'] . ')' : ' - ' . __('no email'))) ?>
                    </option>
                <?php } ?>
                <option value="0"<?= $pick === 0 ? ' selected' : '' ?>><?= __('Someone else - below') ?></option>
            </select>
        </div>
        <div class="row g-2 mb-3">
            <div class="col-sm-5">
                <div class="input-group">
                    <span class="input-group-text"><i class="fa fa-fw fa-user-plus"></i></span>
                    <input type="text" class="form-control" name="to_name" maxlength="200" placeholder="<?= escapeHtml(__('Or a name')) ?>" aria-label="<?= escapeHtml(__('Name of someone else')) ?>">
                </div>
            </div>
            <div class="col-sm-7">
                <div class="input-group">
                    <span class="input-group-text"><i class="fa fa-fw fa-envelope"></i></span>
                    <input type="email" class="form-control" name="to_email" maxlength="255" placeholder="<?= escapeHtml(__('and their email')) ?>" aria-label="<?= escapeHtml(__('Email of someone else')) ?>">
                </div>
            </div>
        </div>

        <label class="form-label"><?= __('A note for them') ?></label>
        <div class="input-group mb-3">
            <span class="input-group-text"><i class="fa fa-fw fa-comment"></i></span>
            <textarea class="form-control" name="note" rows="3" maxlength="2000" placeholder="<?= escapeHtml(__('Optional - goes in the email above the link')) ?>"></textarea>
        </div>

        <ul class="small text-secondary mb-0 ps-3">
            <li><?= __('They read it and sign it from the link in the email, or in the client portal - drawn or typed.') ?></li>
            <li><?= escapeHtml($draft_start !== '' ? __('You countersign after; they get the PDF signed by both, and the contract becomes active from %s.', $draft_start) : __('You countersign after; they get the PDF signed by both.')) ?></li>
            <li><?= __('Changing the contract while it is out withdraws the request.') ?></li>
        </ul>
    </div>
    <div class="modal-footer">
        <a class="btn btn-link me-auto" href="contract_document.php?client_id=<?= $client_id ?>&contract_id=<?= intval($contract['contract_id']) ?>" target="_blank"><i class="fas fa-eye me-2"></i><?= __('Preview') ?></a>
        <button type="submit" name="send_contract_signature" class="btn btn-primary text-bold"><i class="fas fa-paper-plane me-2"></i><?= __('Send') ?></button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fas fa-times me-2"></i><?= __('Cancel') ?></button>
    </div>
</form>

<?php }
require_once '../../../includes/modal_footer.php';
