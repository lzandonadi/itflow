<?php
/*
 * Client Portal
 * Invite people - the primary contact puts in addresses (several at once,
 * each with a name if they like) and what else they see. Each is emailed a
 * link to join, where they put in their own name, phone and password, and
 * become a contact when they do (functions/portal_invite.php). Posts
 * portal_invites_send to post.php; the invites waiting are on Contacts.
 *
 * SCOPING: the primary contact only (contactCan('contacts_manage')), for
 * $session_client_id.
 */

header("Content-Security-Policy: default-src 'self'");

require_once "includes/inc_all.php";

enforceContactCan('contacts_manage');

$ready = portalInvitesReady() && portalMailReady();
$typed = (string) ($_SESSION['portal_invite_form'] ?? '');
unset($_SESSION['portal_invite_form']);
?>

<a class="itflow-portal-back" href="contacts.php"><i class="fas fa-chevron-left"></i><?= __('Contacts') ?></a>

<div class="itflow-portal-page-head">
    <div>
        <h1><?= __('Invite people') ?></h1>
        <p><?= __('To the portal for %s - they put in their own name, phone and password', escapeHtml($session_client_name)) ?></p>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-8">
        <?php if (!$ready) { ?>
            <section class="itflow-portal-panel">
                <?= emptyStateHtml(['title' => __("Invites can't be sent just now"), 'text' => __('Add the person on Contacts instead, or ask us to set them up.'), 'icon' => 'fa-paper-plane', 'filtered' => false,
                    'actions' => [['label' => __('Add a person'), 'href' => 'contact.php?new=1', 'icon' => 'fa-user-plus']]]) ?>
            </section>
        <?php } else { ?>
            <form class="itflow-portal-panel" action="post.php" method="post" autocomplete="off" data-portal-once>
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                <div class="itflow-portal-panel-head">
                    <h2><i class="fas fa-paper-plane"></i><?= __('Who to invite') ?></h2>
                </div>
                <div class="itflow-portal-panel-body">
                    <div class="mb-3">
                        <label class="form-label" for="inviteEmails"><?= __('Email addresses') ?> <strong class="text-danger">*</strong></label>
                        <div class="input-group">
                            <span class="input-group-text align-items-start pt-2"><i class="fa fa-fw fa-envelope"></i></span>
                            <textarea class="form-control" id="inviteEmails" name="emails" rows="4" maxlength="20000" placeholder="jamie@example.com&#10;Sam Lee &lt;sam@example.com&gt;" required autofocus><?= escapeHtml($typed) ?></textarea>
                        </div>
                        <div class="form-text"><?= __('One to a line, or separated by commas - up to %d. A name in front is optional.', PORTAL_INVITE_MAX) ?></div>
                    </div>

                    <fieldset class="mb-3">
                        <legend class="form-label fs-6"><?= __('What else they see') ?></legend>
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="checkbox" id="inviteTechnical" name="technical" value="1">
                            <label class="form-check-label" for="inviteTechnical"><?= __('Technical') ?> <span class="d-block small text-body-secondary"><?= __('Every ticket at %s, and the devices, licenses and documents', escapeHtml($session_client_name)) ?></span></label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="inviteBilling" name="billing" value="1">
                            <label class="form-check-label" for="inviteBilling"><?= __('Billing') ?> <span class="d-block small text-body-secondary"><?= __('Invoices, quotes and statements, and the billing emails') ?></span></label>
                        </div>
                        <div class="form-text"><?= __('Without either, they see the tickets they raise.') ?></div>
                    </fieldset>

                    <button type="submit" class="btn btn-primary" name="portal_invites_send"><i class="fas fa-paper-plane me-2"></i><?= __('Send invites') ?></button>
                </div>
            </form>
        <?php } ?>
    </div>
    <div class="col-lg-4">
        <section class="itflow-portal-panel">
            <div class="itflow-portal-panel-head">
                <h2><i class="fas fa-info-circle"></i><?= __('How it works') ?></h2>
            </div>
            <div class="itflow-portal-panel-body small text-body-secondary">
                <p><?= __('Each person gets an email from us with a link to join. They put in their name, job title, phone and a password, and can sign in straight away.') ?></p>
                <p><?= __('The link works for %1$d days. Until they use it, they\'re listed under %2$s, where you can send it again or cancel it.', PORTAL_INVITE_LINK_DAYS, '<a href="contacts.php">' . __('Contacts') . '</a>') ?></p>
                <p class="mb-0"><?= __('We let you know when they join.') ?></p>
            </div>
        </section>
    </div>
</div>

<?php
require_once "includes/footer.php";
