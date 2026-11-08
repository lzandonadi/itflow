<?php
/*
 * Client Portal
 * A contact - how to reach them, their roles and the devices they use. The
 * primary contact changes their details and roles here, and their portal
 * access: give access and email an invite, send it again, or turn it off.
 * When someone has left, Left the company turns their access off and opens
 * a ticket for us to close their accounts. ?new=1 adds a person.
 *
 * SCOPING: primary and technical contacts see a contact
 * (contactCan('contacts')); only the primary contact changes anything
 * (contactCan('contacts_manage')), never their own record or another
 * primary contact's - the handlers in post.php check the same.
 */

header("Content-Security-Policy: default-src 'self'");
$portal_load_phone_inputs = true;

require_once "includes/inc_all.php";

enforceContactCan('contacts');

$manage = contactCan('contacts_manage');
$new = !empty($_GET['new']) && $manage;
$contact = null;

if (!$new) {
    $contact_id = intval($_GET['id'] ?? 0);
    $contact = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT contact_id, contact_name, contact_title, contact_email, contact_phone, contact_phone_country_code,
            contact_extension, contact_mobile, contact_mobile_country_code, contact_photo, contact_primary, contact_billing, contact_technical,
            contact_department, contact_created_at, location_name, user_id, user_auth_method, user_status, user_archived_at
        FROM contacts
        LEFT JOIN users ON user_id = contact_user_id
        LEFT JOIN locations ON location_id = contact_location_id AND location_archived_at IS NULL
        WHERE contact_id = $contact_id AND contact_client_id = $session_client_id AND contact_archived_at IS NULL LIMIT 1"));
    if (!$contact) { ?>
        <a class="itflow-portal-back" href="contacts.php"><i class="fas fa-chevron-left"></i><?= __('Contacts') ?></a>
        <section class="itflow-portal-panel">
            <?= emptyStateHtml(['title' => __("That person isn't here"), 'text' => __('They may have been removed from your company\'s contacts.'), 'icon' => 'fa-address-book', 'filtered' => false,
                'actions' => [['label' => __('All contacts'), 'href' => 'contacts.php', 'icon' => 'fa-arrow-left']]]) ?>
        </section>
        <?php
        require_once "includes/footer.php";
        exit;
    }
}

$self = $contact && intval($contact['contact_id']) === $session_contact_id;
$editable = $new || ($manage && !$self && !intval($contact['contact_primary']));
$methods = portalSignInMethods();

// What was typed, when the form came back with a problem
$form = $_SESSION['portal_contact_form'] ?? null;
unset($_SESSION['portal_contact_form']);
if ($form && intval($form['contact_id'] ?? -1) !== intval($contact['contact_id'] ?? 0)) {
    $form = null;
}
$value = function ($field, $fallback = '') use ($form, $contact) {
    if ($form && array_key_exists($field, $form)) {
        return (string) $form[$field];
    }
    return (string) ($contact[$field] ?? $fallback);
};
?>

<a class="itflow-portal-back" href="contacts.php"><i class="fas fa-chevron-left"></i><?= __('Contacts') ?></a>

<?php if ($new) { ?>

    <div class="itflow-portal-page-head">
        <div>
            <h1><?= __('Add a person') ?></h1>
            <p><?= __('Someone new at %s - and whether they can sign in here', escapeHtml($session_client_name)) ?></p>
        </div>
    </div>
    <div class="row">
        <div class="col-lg-8">
            <?php require __DIR__ . '/includes/contact_form.php'; ?>
        </div>
    </div>

<?php } else {
    $access = portalContactAccess($contact);
    $last = $contact['user_id'] ? portalContactLastSignIn($contact['user_id']) : null;
    $roles = portalContactRoles($contact);
    $devices = [];
    if ($config_module_enable_itdoc && contactCan('itdoc')) {
        $sql = mysqli_query($mysqli, "SELECT asset_id, asset_name, asset_type, asset_make, asset_model FROM assets
            WHERE asset_contact_id = " . intval($contact['contact_id']) . " AND asset_client_id = $session_client_id AND asset_archived_at IS NULL ORDER BY asset_name ASC");
        while ($row = mysqli_fetch_assoc($sql)) {
            $devices[] = $row;
        }
    } ?>

    <div class="itflow-portal-page-head">
        <div class="d-flex align-items-center gap-3 min-w-0">
            <?= portalAvatarHtml((string) $contact['contact_name'], !empty($contact['contact_photo']) ? "/uploads/clients/$session_client_id/" . rawurlencode(basename($contact['contact_photo'])) : '', 'lg') ?>
            <div class="min-w-0">
                <h1 class="text-truncate"><?= escapeHtml($contact['contact_name']) ?><?= $self ? '<span class="fw-normal text-body-secondary fs-5"> (you)</span>' : '' ?></h1>
                <p><?= escapeHtml(implode(' · ', array_filter(array_merge([$contact['contact_title']], $roles)))) ?: 'Contact' ?></p>
            </div>
        </div>
        <?php if ($self) { ?>
            <a class="btn btn-outline-secondary" href="profile.php"><i class="fas fa-user me-2"></i><?= __('Your account') ?></a>
        <?php } ?>
    </div>

    <div class="row g-4">
        <div class="col-lg-7">
            <?php if ($editable) { ?>
                <?php require __DIR__ . '/includes/contact_form.php'; ?>
            <?php } else {
                $phone = !empty($contact['contact_phone']) ? formatPhoneNumber($contact['contact_phone'], $contact['contact_phone_country_code']) . (!empty($contact['contact_extension']) ? ' x' . $contact['contact_extension'] : '') : '';
                $mobile = !empty($contact['contact_mobile']) ? formatPhoneNumber($contact['contact_mobile'], $contact['contact_mobile_country_code']) : '';
                $facts = array_filter([
                    'Email' => !empty($contact['contact_email']) ? '<a href="mailto:' . escapeHtml($contact['contact_email']) . '">' . escapeHtml($contact['contact_email']) . '</a>' : '',
                    'Phone' => $phone !== '' ? '<a href="tel:' . escapeHtml(preg_replace('/[^0-9+]/', '', ($contact['contact_phone_country_code'] ? '+' . $contact['contact_phone_country_code'] : '') . $contact['contact_phone'])) . '">' . escapeHtml($phone) . '</a>' : '',
                    'Mobile' => $mobile !== '' ? '<a href="tel:' . escapeHtml(preg_replace('/[^0-9+]/', '', ($contact['contact_mobile_country_code'] ? '+' . $contact['contact_mobile_country_code'] : '') . $contact['contact_mobile'])) . '">' . escapeHtml($mobile) . '</a>' : '',
                    'Title' => escapeHtml($contact['contact_title']),
                    'Department' => escapeHtml($contact['contact_department']),
                    'Location' => escapeHtml($contact['location_name'] ?? ''),
                    'Roles' => escapeHtml(implode(', ', $roles)),
                ], function ($v) { return trim((string) $v) !== ''; }); ?>
                <section class="itflow-portal-panel">
                    <div class="itflow-portal-panel-head">
                        <h2><i class="fas fa-address-card"></i><?= __('Details') ?></h2>
                    </div>
                    <div class="itflow-portal-panel-body">
                        <dl class="itflow-portal-facts">
                            <?php foreach ($facts as $label => $fact) { ?>
                                <div><dt><?= $label ?></dt><dd><?= $fact ?></dd></div>
                            <?php } ?>
                        </dl>
                        <?php if ($manage && !$self) { ?>
                            <p class="small text-body-secondary mt-3 mb-0"><?= __("This is the company's primary contact - %s to change who that is.", '<a href="' . escapeHtml(portalAskUrl(__('Change the primary contact'))) . '">' . __('ask us') . '</a>') ?></p>
                        <?php } elseif ($self) { ?>
                            <p class="small text-body-secondary mt-3 mb-0"><?= __('Change your own phone numbers and sign-in in %s.', '<a href="profile.php">' . __('Your account') . '</a>') ?></p>
                        <?php } ?>
                    </div>
                </section>
            <?php } ?>
        </div>

        <div class="col-lg-5">
            <?php if ($editable) { ?>
                <section class="itflow-portal-panel mb-4">
                    <div class="itflow-portal-panel-head">
                        <h2><i class="fas fa-key"></i><?= __('Portal access') ?></h2>
                    </div>
                    <div class="itflow-portal-panel-body">
                        <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                            <?= portalStateBadge($access) ?>
                            <?php if (in_array($access['key'], ['local', 'azure'], true)) { ?>
                                <span class="small text-body-secondary"><?= $last ? __('Last signed in %s', escapeHtml(timeAgo($last))) : __("Hasn't signed in yet") ?></span>
                            <?php } ?>
                        </div>
                        <?php if (in_array($access['key'], ['local', 'azure'], true)) { ?>
                            <p class="small text-body-secondary"><?php $sees = $contact['contact_technical'] ? __('every ticket at %s', escapeHtml($session_client_name)) : __('the tickets they raise'); ?>
                <?= $contact['contact_billing'] ? __('They can sign in here as %1$s and see %2$s, and the billing.', '<strong>' . escapeHtml($contact['contact_email']) . '</strong>', $sees) : __('They can sign in here as %1$s and see %2$s.', '<strong>' . escapeHtml($contact['contact_email']) . '</strong>', $sees) ?></p>
                            <form action="post.php" method="post" class="d-flex flex-wrap gap-2">
                                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                <input type="hidden" name="contact_id" value="<?= intval($contact['contact_id']) ?>">
                                <input type="hidden" name="method" value="<?= escapeHtml($access['key']) ?>">
                                <button type="submit" class="btn btn-outline-primary" name="portal_contact_access" value="invite"><i class="fas fa-paper-plane me-2"></i><?= __('Email the invite again') ?></button>
                                <button type="submit" class="btn btn-outline-danger confirm-link" name="portal_contact_access" value="off"
                                    data-confirm-title="<?= __('Turn off %s\'s access?', escapeHtml($contact['contact_name'])) ?>" data-confirm-text="<?= escapeHtml(__('They are signed out straight away and can\'t sign in again until you give them access.')) ?>" data-confirm-button="<?= escapeHtml(__('Turn off')) ?>"><i class="fas fa-ban me-2"></i><?= __('Turn off access') ?></button>
                            </form>
                        <?php } else { ?>
                            <p class="small text-body-secondary"><?= __('Give them access and we email %s a link to sign in.', '<strong>' . escapeHtml($contact['contact_email'] ?: __('them')) . '</strong>') ?></p>
                            <form action="post.php" method="post">
                                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                <input type="hidden" name="contact_id" value="<?= intval($contact['contact_id']) ?>">
                                <?php if (count($methods) > 1) { ?>
                                    <div class="mb-3">
                                        <label class="form-label" for="accessMethod"><?= __('They sign in') ?></label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="fa fa-fw fa-sign-in-alt"></i></span>
                                            <select class="form-select" id="accessMethod" name="method">
                                                <?php foreach ($methods as $key => $label) { ?>
                                                    <option value="<?= $key ?>"><?= escapeHtml($label) ?></option>
                                                <?php } ?>
                                            </select>
                                        </div>
                                    </div>
                                <?php } else { ?>
                                    <input type="hidden" name="method" value="local">
                                <?php } ?>
                                <button type="submit" class="btn btn-primary" name="portal_contact_access" value="invite"<?= empty($contact['contact_email']) ? ' disabled' : '' ?>><i class="fas fa-paper-plane me-2"></i><?= __('Give access and email an invite') ?></button>
                            </form>
                        <?php } ?>
                    </div>
                </section>

                <section class="itflow-portal-panel mb-4">
                    <div class="itflow-portal-panel-head">
                        <h2><i class="fas fa-door-open"></i><?= __('Left the company?') ?></h2>
                    </div>
                    <div class="itflow-portal-panel-body">
                        <p class="small text-body-secondary"><?= __('We turn off their portal access now, and you tell us what else to close down - their email, accounts and devices - on a ticket.') ?></p>
                        <form action="post.php" method="post">
                            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                            <input type="hidden" name="contact_id" value="<?= intval($contact['contact_id']) ?>">
                            <button type="submit" class="btn btn-outline-danger confirm-link" name="portal_contact_left" value="1"
                                data-confirm-title="<?= __('Has %s left?', escapeHtml($contact['contact_name'])) ?>" data-confirm-text="<?= escapeHtml(__('Their portal access is turned off, and a new ticket opens for you to tell us what else to close down.')) ?>" data-confirm-button="<?= escapeHtml(__('Yes, they\'ve left')) ?>"><i class="fas fa-user-slash me-2"></i><?= escapeHtml(strtok($contact['contact_name'], ' ')) ?> has left</button>
                        </form>
                    </div>
                </section>
            <?php } ?>

            <?php if ($devices) { ?>
                <section class="itflow-portal-panel">
                    <div class="itflow-portal-panel-head">
                        <h2><i class="fas fa-desktop"></i><?= __('Devices they use') ?></h2>
                    </div>
                    <ul class="itflow-portal-list">
                        <?php foreach ($devices as $device) { ?>
                            <li>
                                <a class="itflow-portal-todo" href="asset.php?id=<?= intval($device['asset_id']) ?>">
                                    <?= portalTile('fa-' . getAssetIcon($device['asset_type']), 'sm') ?>
                                    <span class="itflow-portal-todo-main">
                                        <div><?= escapeHtml($device['asset_name']) ?></div>
                                        <div><?= escapeHtml(trim($device['asset_make'] . ' ' . $device['asset_model']) ?: $device['asset_type']) ?></div>
                                    </span>
                                    <i class="fas fa-chevron-right text-body-secondary small"></i>
                                </a>
                            </li>
                        <?php } ?>
                    </ul>
                </section>
            <?php } ?>
        </div>
    </div>

<?php } ?>

<?php
require_once "includes/footer.php";
