<?php

/*
 * Onboarding - people: the client's contacts so far, and someone to add -
 * name, title, email, phones, where they work and what they are for
 * (primary, billing, technical, important) - with client portal access, an
 * email to set their own password (client_onboarding_contact). The first is
 * the primary contact. Add as many as you like, one at a time.
 */

defined('FROM_CLIENT_ONBOARDING') || die("Direct file access is not allowed");

$co_people = [];
$co_sql = mysqli_query($mysqli, "SELECT contact_id, contact_name, contact_title, contact_email, contact_photo, contact_primary, contact_important, contact_billing, contact_technical,
        contact_user_id, user_auth_method
    FROM contacts LEFT JOIN users ON user_id = contact_user_id AND user_type = 2 AND user_archived_at IS NULL
    WHERE contact_client_id = $client_id AND contact_archived_at IS NULL ORDER BY contact_primary DESC, contact_important DESC, contact_name ASC");
while ($co_person = mysqli_fetch_assoc($co_sql)) {
    $co_people[] = $co_person;
}
$co_has_primary = (bool) array_filter($co_people, function ($p) {
    return intval($p['contact_primary']) === 1;
});
$co_locations = [];
$co_sql = mysqli_query($mysqli, "SELECT location_id, location_name FROM locations WHERE location_client_id = $client_id AND location_archived_at IS NULL ORDER BY location_primary DESC, location_name ASC");
while ($co_loc = mysqli_fetch_assoc($co_sql)) {
    $co_locations[intval($co_loc['location_id'])] = $co_loc['location_name'];
}
$co_portal = intval($config_client_portal_enable) === 1;
$co_portal_mail = $co_portal && portalMailReady();

?>
<?php if ($co_people) { ?>
    <ul class="list-unstyled itflow-gs-people mb-4" data-co-people="<?= count($co_people) ?>">
        <?php foreach ($co_people as $co_person) { ?>
            <li data-co-person="<?= intval($co_person['contact_id']) ?>">
                <?= contactAvatar($client_id, $co_person['contact_id'], $co_person['contact_name'], $co_person['contact_photo']) ?>
                <div class="min-w-0 flex-grow-1">
                    <div class="fw-semibold text-truncate"><?= escapeHtml($co_person['contact_name']) ?><?php if (trim((string) $co_person['contact_title']) !== '') { ?> <span class="small fw-normal text-body-secondary">· <?= escapeHtml($co_person['contact_title']) ?></span><?php } ?></div>
                    <div class="small text-body-secondary text-truncate"><?= escapeHtml($co_person['contact_email'] ?: __('No email')) ?></div>
                </div>
                <span class="d-flex flex-wrap justify-content-end gap-1"><?= contactFlagPills($co_person) ?></span>
            </li>
        <?php } ?>
    </ul>
    <h2 class="h6 fw-semibold"><?= __('Add someone else') ?></h2>
<?php } ?>

<form action="post.php" method="post" autocomplete="off" data-gs-form data-co-form="contact">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <input type="hidden" name="client_id" value="<?= $client_id ?>">
    <div class="row g-3 mb-3">
        <div class="col-sm-6">
            <?= settingsInput('name', '', ['id' => 'coPersonName', 'icon' => 'fa-user', 'label' => __('Name'), 'required' => true, 'maxlength' => 200, 'placeholder' => __('First and last name')]) ?>
        </div>
        <div class="col-sm-6">
            <?= settingsInput('title', '', ['id' => 'coPersonTitle', 'icon' => 'fa-id-badge', 'label' => __('Job title'), 'maxlength' => 200, 'placeholder' => __('Office manager')]) ?>
        </div>
        <div class="col-12">
            <?= settingsInput('email', '', ['id' => 'coPersonEmail', 'icon' => 'fa-envelope', 'type' => 'email', 'label' => __('Email'), 'maxlength' => 200, 'placeholder' => 'name@example.com']) ?>
        </div>
        <div class="col-sm-6">
            <div class="itflow-settings-field">
                <label class="form-label" for="coPersonPhone"><?= __('Phone') ?></label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fa fa-fw fa-phone"></i></span>
                    <input type="hidden" name="phone_country_code" value="<?= escapeHtml((string) settingsValue('phone_country_code', '')) ?>">
                    <input type="tel" class="form-control" id="coPersonPhone" name="phone" value="<?= escapeHtml((string) settingsValue('phone', '')) ?>" maxlength="200" data-itflow-phone="phone_country_code">
                </div>
            </div>
        </div>
        <div class="col-sm-6">
            <div class="itflow-settings-field">
                <label class="form-label" for="coPersonMobile"><?= __('Mobile') ?></label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fa fa-fw fa-mobile-alt"></i></span>
                    <input type="hidden" name="mobile_country_code" value="<?= escapeHtml((string) settingsValue('mobile_country_code', '')) ?>">
                    <input type="tel" class="form-control" id="coPersonMobile" name="mobile" value="<?= escapeHtml((string) settingsValue('mobile', '')) ?>" maxlength="200" data-itflow-phone="mobile_country_code">
                </div>
            </div>
        </div>
        <?php if ($co_locations) { ?>
            <div class="col-12">
                <?= settingsSelect('location', (string) array_key_first($co_locations), ['0' => __('- None -')] + $co_locations, ['id' => 'coPersonLocation', 'icon' => 'fa-map-marker-alt', 'label' => __('Where they work')]) ?>
            </div>
        <?php } ?>
        <div class="col-12">
            <label class="form-label d-block"><?= __('What they are for') ?></label>
            <div class="d-flex flex-wrap column-gap-4 row-gap-2">
                <?php foreach (['primary' => [__('Primary contact'), !$co_has_primary, __('Who you go to first - the client\'s overview and its portal show them')],
                    'billing' => [__('Billing'), false, __('Gets the invoices, quotes and statements')],
                    'technical' => [__('Technical'), false, __('Who to call about technical things')],
                    'important' => [__('Important'), false, __('Kept at the top of the contacts')]] as $co_flag => [$co_flag_label, $co_flag_on, $co_flag_title]) { ?>
                    <div class="form-check form-switch mb-0" title="<?= escapeHtml($co_flag_title) ?>">
                        <input class="form-check-input" type="checkbox" role="switch" name="contact_<?= $co_flag ?>" value="1" id="coPerson<?= ucfirst($co_flag) ?>"<?= settingsChecked("contact_$co_flag", $co_flag_on) ? ' checked' : '' ?>>
                        <label class="form-check-label" for="coPerson<?= ucfirst($co_flag) ?>"><?= $co_flag_label ?></label>
                    </div>
                <?php } ?>
            </div>
        </div>
        <?php if ($co_portal) { ?>
            <div class="col-12">
                <div class="itflow-gs-optional">
                    <?= settingsSwitch('portal', '', ['id' => 'coPersonPortal', 'label' => __('Give them client portal access'), 'disabled' => !$co_portal_mail,
                        'help' => $co_portal_mail ? __('They are emailed a link to set their own password, good for %d days - the email never has the password in it. They need an email address.', PORTAL_INVITE_LINK_DAYS)
                            : __('Mail isn\'t set up, so the email with their link can\'t go - give them access from their contact page once it is.')]) ?>
                </div>
            </div>
        <?php } ?>
    </div>
    <button type="submit" name="client_onboarding_contact" value="1" class="btn btn-primary" data-gs-save><i class="fas fa-user-plus me-2"></i><?= $co_people ? __('Add them') : __('Add the contact') ?></button>
</form>
