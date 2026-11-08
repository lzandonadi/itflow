<?php
/*
 * The fields of an email to many contacts - Email on the contacts and clients
 * lists (modals/contact/contact_bulk_email.php, modals/client/client_bulk_email.php):
 * the mailbox it comes from, the name it shows, the subject, the message, a
 * greeting, and when to send it. They post what emailBulkSend() takes
 * (functions/email.php) - the email goes out in the email look, with the
 * logo and the company's details.
 *
 * Should not be accessed directly.
 */

isset($mysqli) || die("Direct file access is not allowed");

$bulk_mailboxes = emailMailboxes();

?>

<?php if (!emailReady()) { ?>
    <div class="alert alert-warning mb-3">
        <i class="fas fa-fw fa-exclamation-triangle me-2"></i><?= $session_is_admin ? __('Outgoing mail isn\'t set up, so nothing can be sent yet - set it up in <a href="/admin/settings_mail.php" class="alert-link">Admin &gt; Mail</a>.') : __('Outgoing mail isn\'t set up, so nothing can be sent yet.') ?>
    </div>
<?php } ?>

<div class="row g-3">
    <div class="col-sm-6">
        <label class="form-label"><?= __('From') ?></label>
        <div class="input-group">
            <span class="input-group-text"><i class="fas fa-fw fa-inbox"></i></span>
            <select class="form-select" name="mailbox" required>
                <?php foreach ($bulk_mailboxes as $key => $box) { ?>
                    <option value="<?= $key ?>" data-name="<?= escapeHtml($box['name']) ?>"><?= escapeHtml($box['label'] . ' - ' . $box['email']) ?></option>
                <?php } ?>
            </select>
        </div>
    </div>
    <div class="col-sm-6">
        <label class="form-label"><?= __('Name it shows') ?></label>
        <div class="input-group">
            <span class="input-group-text"><i class="fas fa-fw fa-signature"></i></span>
            <input type="text" class="form-control" name="mail_from_name" maxlength="255" placeholder="<?= escapeHtml(__('The mailbox\'s own name')) ?>"
                value="<?= escapeHtml((string) (reset($bulk_mailboxes)['name'] ?? '')) ?>">
        </div>
    </div>
    <div class="col-12">
        <label class="form-label"><?= __('Subject') ?> <strong class="text-danger">*</strong></label>
        <div class="input-group">
            <span class="input-group-text"><i class="fas fa-fw fa-heading"></i></span>
            <input type="text" class="form-control" name="subject" placeholder="<?= escapeHtml(__('Subject')) ?>" maxlength="255" required>
        </div>
    </div>
    <div class="col-12">
        <label class="form-label"><?= __('Message') ?> <strong class="text-danger">*</strong></label>
        <textarea class="form-control tinymce" name="body" placeholder="<?= escapeHtml(__('Type an email in here')) ?>"></textarea>
        <div class="form-text"><?= __('It goes out in your email look - your logo above it, your company\'s details below, and a line under it saying why they got it.') ?></div>
    </div>
    <div class="col-sm-6">
        <div class="form-check mt-sm-4">
            <input type="checkbox" class="form-check-input" name="greet" value="1" id="bulkEmailGreet">
            <label class="form-check-label" for="bulkEmailGreet"><?= __('Start it "Hello" and their name') ?></label>
        </div>
    </div>
    <div class="col-sm-6">
        <label class="form-label"><?= __('Send at') ?></label>
        <div class="input-group">
            <span class="input-group-text"><i class="fas fa-fw fa-calendar"></i></span>
            <input type="datetime-local" class="form-control" name="queued_at">
        </div>
        <div class="form-text"><?= __('Leave it empty to send now.') ?></div>
    </div>
</div>

<script>
    itflowReady(function () {
        // The name follows the mailbox, until it is typed over
        document.querySelectorAll('select[name="mailbox"]').forEach(function (select) {
            var form = select.closest('form');
            var name = form.querySelector('input[name="mail_from_name"]');
            var last = name.value;
            select.addEventListener('change', function () {
                var picked = select.options[select.selectedIndex].getAttribute('data-name') || '';
                if (name.value === last) {
                    name.value = picked;
                }
                last = picked;
            });
        });
    });
</script>
