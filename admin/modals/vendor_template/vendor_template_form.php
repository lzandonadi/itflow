<?php

/*
 * A vendor template's fields - for vendor_template_add.php and
 * vendor_template_edit.php, which set $vt (vendorTemplateRowFields() of the
 * template, or blanks when new), $vt_id (0 when new) and, editing, $vt_vendors
 * (how many vendors were made from it). The same tabs as a vendor's own
 * window - Details, Support, Account and Notes - as a vendor made from it
 * gets every one of them. Editing, the vendors made from it follow the
 * changes (vendorTemplateFollow()). Posts to admin/post.php
 * (admin/post/vendor_template.php).
 */

isset($mysqli) || die("Direct file access is not allowed");

$form_id = 'vendorTemplateForm' . ($vt_id ?: 'New');
$value = function ($key) use ($vt) {
    return escapeHtml((string) ($vt[$key] ?? ''));
};
$phone_value = ($vt['phone'] ?? '') !== '' ? escapeHtml(formatPhoneNumber($vt['phone'], $vt['phone_country_code'] ?? '', false)) : '';
$contact_phone_value = ($vt['contact_phone'] ?? '') !== '' ? escapeHtml(formatPhoneNumber($vt['contact_phone'], $vt['contact_phone_country_code'] ?? '', false)) : '';

?>

<ul class="nav nav-pills nav-fill flex-nowrap overflow-auto mb-3 itflow-asset-form-tabs" role="tablist">
    <li class="nav-item">
        <a class="nav-link active" data-bs-toggle="pill" href="#<?= $form_id ?>Details"><i class="fas fa-fw fa-building me-2 d-none d-lg-inline"></i><?= __('Details') ?></a>
    </li>
    <li class="nav-item">
        <a class="nav-link" data-bs-toggle="pill" href="#<?= $form_id ?>Support"><i class="fas fa-fw fa-headset me-2 d-none d-lg-inline"></i><?= __('Support') ?></a>
    </li>
    <li class="nav-item">
        <a class="nav-link" data-bs-toggle="pill" href="#<?= $form_id ?>Account"><i class="fas fa-fw fa-id-card me-2 d-none d-lg-inline"></i><?= __('Account') ?></a>
    </li>
    <li class="nav-item">
        <a class="nav-link" data-bs-toggle="pill" href="#<?= $form_id ?>Notes"><i class="fas fa-fw fa-sticky-note me-2 d-none d-lg-inline"></i><?= __('Notes') ?></a>
    </li>
</ul>

<div class="tab-content" id="<?= $form_id ?>">

<!-- Who they are -->
<div class="tab-pane fade show active" id="<?= $form_id ?>Details">
    <div class="row g-3">
        <div class="col-12">
            <label class="form-label"><?= __('Name') ?> <strong class="text-danger">*</strong></label>
            <div class="input-group">
                <span class="input-group-text"><i class="fas fa-fw fa-building"></i></span>
                <input type="text" class="form-control" name="name" placeholder="<?= escapeHtml(__('Comcast Business, Dell, Microsoft')) ?>" maxlength="200" value="<?= $value('name') ?>" required <?= $vt_id ? '' : 'autofocus' ?>>
            </div>
        </div>
        <div class="col-12">
            <label class="form-label"><?= __('What they are') ?></label>
            <div class="input-group">
                <span class="input-group-text"><i class="fa fa-fw fa-align-left"></i></span>
                <input type="text" class="form-control" name="description" placeholder="<?= escapeHtml(__('Business internet and phone, servers and workstations')) ?>" maxlength="200" value="<?= $value('description') ?>">
            </div>
        </div>
    </div>
    <div class="form-text mt-3"><?= __('A vendor added to a client from it starts with everything here - their own account number and PIN go on after.') ?></div>
</div>

<!-- How to reach support -->
<div class="tab-pane fade" id="<?= $form_id ?>Support">
    <div class="row g-3">
        <div class="col-md-8">
            <label class="form-label"><?= __('Support phone') ?></label>
            <div class="input-group">
                <span class="input-group-text"><i class="fa fa-fw fa-phone"></i></span>
                <input type="hidden" name="phone_country_code" value="<?= $value('phone_country_code') ?>">
                <input type="tel" class="form-control" name="phone" value="<?= $phone_value ?>" placeholder="<?= escapeHtml(__('Phone number')) ?>" maxlength="200" data-itflow-phone="phone_country_code">
            </div>
        </div>
        <div class="col-md-4">
            <label class="form-label"><?= __('Extension / option') ?></label>
            <div class="input-group">
                <span class="input-group-text"><i class="fas fa-fw fa-hashtag"></i></span>
                <input type="text" class="form-control" name="extension" value="<?= $value('extension') ?>" placeholder="<?= escapeHtml(__('ext.')) ?>" maxlength="200" inputmode="numeric">
            </div>
        </div>
        <div class="col-md-6">
            <label class="form-label"><?= __('Support hours') ?></label>
            <div class="input-group">
                <span class="input-group-text"><i class="fa fa-fw fa-clock"></i></span>
                <input type="text" class="form-control" name="hours" placeholder="<?= escapeHtml(__('24/7, Mon-Fri 8-6 ET')) ?>" maxlength="200" value="<?= $value('hours') ?>">
            </div>
        </div>
        <div class="col-md-6">
            <label class="form-label"><?= __('SLA') ?></label>
            <div class="input-group">
                <span class="input-group-text"><i class="fa fa-fw fa-handshake"></i></span>
                <input type="text" class="form-control" name="sla" placeholder="<?= escapeHtml(__('4 hour response, next business day')) ?>" maxlength="200" value="<?= $value('sla') ?>">
            </div>
        </div>
        <div class="col-12">
            <label class="form-label"><?= __('Support email') ?></label>
            <div class="input-group">
                <span class="input-group-text"><i class="fa fa-fw fa-envelope"></i></span>
                <input type="email" class="form-control" name="email" placeholder="<?= escapeHtml(__('support@vendor.com')) ?>" maxlength="200" value="<?= $value('email') ?>">
            </div>
        </div>
        <div class="col-12">
            <label class="form-label"><?= __('Support site') ?></label>
            <div class="input-group">
                <span class="input-group-text"><i class="fa fa-fw fa-globe"></i></span>
                <input type="text" class="form-control" name="website" placeholder="<?= escapeHtml(__('support.vendor.com')) ?>" maxlength="200" value="<?= $value('website') ?>">
            </div>
        </div>
        <div class="col-md-6">
            <label class="form-label"><?= __('Status page') ?></label>
            <div class="input-group">
                <span class="input-group-text"><i class="fa fa-fw fa-heartbeat"></i></span>
                <input type="text" class="form-control" name="status_page" placeholder="<?= escapeHtml(__('status.vendor.com')) ?>" maxlength="200" value="<?= $value('status_page') ?>">
            </div>
        </div>
        <div class="col-md-6">
            <label class="form-label"><?= __('Support portal') ?></label>
            <div class="input-group">
                <span class="input-group-text"><i class="fa fa-fw fa-door-open"></i></span>
                <input type="text" class="form-control" name="portal" placeholder="<?= escapeHtml(__('Where cases are opened')) ?>" maxlength="200" value="<?= $value('portal') ?>">
            </div>
        </div>
        <div class="col-12">
            <div class="form-text mt-0"><?= __('Where they post outages, and where you sign in to open a case - every client\'s vendor gets them.') ?></div>
        </div>
    </div>
</div>

<!-- What they ask for, and who looks after us -->
<div class="tab-pane fade" id="<?= $form_id ?>Account">
    <div class="row g-3">
        <div class="col-md-7">
            <label class="form-label"><?= __('Account number') ?></label>
            <div class="input-group">
                <span class="input-group-text"><i class="fa fa-fw fa-fingerprint"></i></span>
                <input type="text" class="form-control" name="account_number" placeholder="<?= escapeHtml(__('Usually each client\'s own - leave blank')) ?>" maxlength="200" value="<?= $value('account_number') ?>">
            </div>
        </div>
        <div class="col-md-5">
            <label class="form-label"><?= __('PIN / passcode') ?></label>
            <div class="input-group">
                <span class="input-group-text"><i class="fa fa-fw fa-key"></i></span>
                <input type="text" class="form-control" name="code" placeholder="<?= escapeHtml(__('Usually each client\'s own')) ?>" maxlength="200" value="<?= $value('code') ?>" autocomplete="off">
            </div>
        </div>
        <div class="col-12">
            <div class="form-text mt-0"><?= __('Fill these in only when every client uses the same one - your own partner or reseller account, say.') ?></div>
        </div>
        <div class="col-12">
            <label class="form-label"><?= __('Account manager') ?></label>
            <div class="input-group">
                <span class="input-group-text"><i class="fa fa-fw fa-user-tie"></i></span>
                <input type="text" class="form-control" name="contact_name" placeholder="<?= escapeHtml(__('Your rep there, when every client has the same one')) ?>" maxlength="200" value="<?= $value('contact_name') ?>">
            </div>
        </div>
        <div class="col-md-6">
            <label class="form-label"><?= __('Their direct line') ?></label>
            <div class="input-group">
                <span class="input-group-text"><i class="fa fa-fw fa-phone-alt"></i></span>
                <input type="hidden" name="contact_phone_country_code" value="<?= $value('contact_phone_country_code') ?>">
                <input type="tel" class="form-control" name="contact_phone" value="<?= $contact_phone_value ?>" placeholder="<?= escapeHtml(__('Phone number')) ?>" maxlength="200" data-itflow-phone="contact_phone_country_code">
            </div>
        </div>
        <div class="col-md-6">
            <label class="form-label"><?= __('Their email') ?></label>
            <div class="input-group">
                <span class="input-group-text"><i class="fa fa-fw fa-at"></i></span>
                <input type="email" class="form-control" name="contact_email" placeholder="<?= escapeHtml(__('name@vendor.com')) ?>" maxlength="200" value="<?= $value('contact_email') ?>">
            </div>
        </div>
        <div class="col-12">
            <div class="form-text mt-0"><?= __('A vendor added from it starts with its account manager - a client\'s own rep goes on their vendor, and is never changed from here.') ?></div>
        </div>
    </div>
</div>

<!-- Everything else -->
<div class="tab-pane fade" id="<?= $form_id ?>Notes">
    <label class="form-label"><?= __('Notes') ?></label>
    <textarea class="form-control" rows="10" name="notes" placeholder="<?= escapeHtml(__('Escalation path, what they need before they will help, menu options, how to open a case online - what every client\'s vendor should say')) ?>"><?= $value('notes') ?></textarea>
</div>

</div>

<?php if ($vt_id && !empty($vt_vendors)) { ?>
    <!-- The vendors made from it follow it, where they still say what it did -->
    <div class="border rounded p-3 mt-3 bg-body-tertiary" data-vendor-template-follow>
        <div class="form-check form-switch mb-1">
            <input class="form-check-input" type="checkbox" role="switch" name="follow" value="1" id="<?= $form_id ?>Follow" checked>
            <label class="form-check-label fw-semibold" for="<?= $form_id ?>Follow"><?= _n('Change the %d vendor made from it too', 'Change the %d vendors made from it too', intval($vt_vendors)) ?></label>
        </div>
        <div class="small text-body-secondary"><?= __('Each field you change here changes on the vendors that still say what it said - one a client has its own for is left as it is, and shows as out of step on the template\'s page. Account numbers, PINs and account managers are always each client\'s own.') ?></div>
    </div>
<?php } ?>

<script>
    itflowReady(function () {
        var form = document.getElementById(<?= json_encode($form_id) ?>).closest('form');

        // A required field on a tab that isn't showing: show that tab and point at it
        var reported = false;
        form.addEventListener('invalid', function (e) {
            var pane = e.target.closest('.tab-pane');
            var tab = pane && form.querySelector('[data-bs-toggle="pill"][href="#' + pane.id + '"]');
            if (!tab || pane.classList.contains('active') || reported) {
                return;
            }
            reported = true;
            tab.addEventListener('shown.bs.tab', function () {
                reported = false;
                e.target.reportValidity();
            }, { once: true });
            bootstrap.Tab.getOrCreateInstance(tab).show();
        }, true);
    });
</script>
