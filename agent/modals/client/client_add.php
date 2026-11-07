<?php

require_once '../../../includes/modal_header.php';

// Filters
$leads_filter = intval($_GET['lead'] ?? 0);

ob_start();

?>

<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fa fa-fw fa-user-plus me-2"></i><?= $leads_filter == 0 ? __('New Client') : __('New Lead') ?></h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>

<ul class="modal-header nav nav-pills nav-justified">
    <li class="nav-item">
        <a class="nav-link active" data-bs-toggle="pill" href="#pills-details"><?= __('Details') ?></a>
    </li>
    <li class="nav-item">
        <a class="nav-link" data-bs-toggle="pill" href="#pills-location"><?= __('Location') ?></a>
    </li>
    <li class="nav-item">
        <a class="nav-link" data-bs-toggle="pill" href="#pills-contact" id="contactNavPill"><?= __('Contact') ?></a>
    </li>
    <?php if ($config_module_enable_accounting) { ?>
        <li class="nav-item">
            <a class="nav-link" data-bs-toggle="pill" href="#pills-billing"><?= __('Billing') ?></a>
        </li>
    <?php } ?>
    <li class="nav-item">
        <a class="nav-link" data-bs-toggle="pill" href="#pills-notes"><?= __('Notes') ?></a>
    </li>
</ul>

<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <div class="modal-body">

        <div class="tab-content">

            <div class="tab-pane fade show active" id="pills-details">

                <div class="mb-3">
                    <label><?= __('Name') ?> <strong class="text-danger">*</strong> / <span class="text-secondary"><?= __('Is Lead') ?></span></label>
                    <div class="input-group">
                            <span class="input-group-text"><i class="fa fa-fw fa-user"></i></span>
                        <input type="text" class="form-control" name="name" id="client_name" placeholder="<?= escapeHtml(__('Name or Company')) ?>" maxlength="200" onfocusout="checkClientDuplicate()" required autofocus>
                            <div class="input-group-text">
                                <input class="form-check-input" type="checkbox" name="lead" value="1" <?php if($leads_filter == 1){ echo "checked"; } ?>>
                            </div>
                    </div>
                    <div class="mt-2">
                        <span class="text-info" id="client_duplicate_info"></span>
                    </div>
                </div>

                <div class="mb-3">
                    <label><?= __('Shortened Name') ?></label>
                    <div class="input-group">
                            <span class="input-group-text"><i class="fa fa-fw fa-id-badge"></i></span>
                        <input type="text" class="form-control" name="abbreviation" placeholder="<?= escapeHtml(__('Shortened name for client - Max chars 6')) ?>" maxlength="6" oninput="this.value = this.value.toUpperCase()">
                    </div>
                </div>

                <div class="mb-3">
                    <label><?= __('Industry') ?></label>
                    <div class="input-group">
                            <span class="input-group-text"><i class="fa fa-fw fa-briefcase"></i></span>
                        <input type="text" class="form-control" name="type" placeholder="<?= escapeHtml(__('Company Type')) ?>" maxlength="200">
                    </div>
                </div>

                <div class="mb-3">
                    <label><?= __('Referral') ?></label>
                    <div class="input-group">
                            <span class="input-group-text"><i class="fa fa-fw fa-link"></i></span>
                        <select class="form-select select2" data-tags="true" name="referral">
                            <option value=""><?= __('- Select Referral -') ?></option>
                            <?= categoryOptionsHtml('Referral') ?>
                        </select>
                        <?= categoryAddButtonHtml('Referral', 'btn btn-secondary') ?>
                    </div>
                </div>

                <div class="mb-3">
                    <label><?= __('Website') ?></label>
                    <div class="input-group">
                            <span class="input-group-text"><i class="fa fa-fw fa-globe"></i></span>
                        <input type="text" class="form-control" name="website" placeholder="<?= escapeHtml(__('ex. google.com')) ?>" maxlength="200">
                    </div>
                </div>

                <div class="mb-3">
                    <label><?= __('Tags') ?></label>
                    <div class="input-group">
                            <span class="input-group-text"><i class="fa fa-fw fa-tags"></i></span>
                        <select class="form-select select2" name="tags[]" data-placeholder="<?= escapeHtml(__('Add some tags')) ?>" multiple<?= !empty($session_is_admin) ? ' data-tags="true"' : '' ?>>
                            <?= tagOptionsHtml('client', []) ?>
                        </select>
                    </div>
                </div>

            </div>

            <div class="tab-pane fade" id="pills-location">

                <div class="mb-3">
                    <label><?= __('Address') ?></label>
                    <div class="input-group">
                            <span class="input-group-text"><i class="fa fa-fw fa-map-marker-alt"></i></span>
                        <input type="text" class="form-control" name="address" placeholder="<?= escapeHtml(__('Street Address')) ?>" maxlength="200">
                    </div>
                </div>

                <div class="mb-3">
                    <label><?= __('City') ?></label>
                    <div class="input-group">
                            <span class="input-group-text"><i class="fa fa-fw fa-city"></i></span>
                        <input type="text" class="form-control" name="city" placeholder="<?= escapeHtml(__('City')) ?>" maxlength="200">
                    </div>
                </div>

                <div class="mb-3">
                    <label><?= __('State / Province') ?></label>
                    <div class="input-group">
                            <span class="input-group-text"><i class="fa fa-fw fa-flag"></i></span>
                        <input type="text" class="form-control" name="state" placeholder="<?= escapeHtml(__('State or Province')) ?>" maxlength="200">
                    </div>
                </div>

                <div class="mb-3">
                    <label><?= __('Postal Code') ?></label>
                    <div class="input-group">
                            <span class="input-group-text"><i class="fab fa-fw fa-usps"></i></span>
                        <input type="text" class="form-control" name="zip" placeholder="<?= escapeHtml(__('Zip or Postal Code')) ?>" maxlength="200">
                    </div>
                </div>

                <div class="mb-3">
                    <label><?= __('Country') ?></label>
                    <div class="input-group">
                            <span class="input-group-text"><i class="fa fa-fw fa-globe-americas"></i></span>
                        <select class="form-select select2" name="country">
                            <option value=""><?= __('- Select Country -') ?></option>
                            <?php foreach($countries_array as $country_name) { ?>
                                <option <?php if ($session_company_country == $country_name) { echo "selected"; } ?>  data-iso2="<?= $country_iso2_array[$country_name] ?? '' ?>"><?= $country_name ?></option>
                            <?php } ?>
                        </select>
                    </div>
                </div>


                <label><?= __('Location Phone') ?> / <span class="text-secondary"><?= __('Extension') ?></span></label>
                <div class="row g-2">
                    <div class="col-9">
                        <div class="mb-3">
                            <div class="input-group">
                                    <span class="input-group-text"><i class="fa fa-fw fa-phone"></i></span>
                                <input type="hidden" name="location_phone_country_code">
                                <input type="tel" class="form-control" name="location_phone" placeholder="<?= escapeHtml(__('Phone Number')) ?>" maxlength="200" data-itflow-phone="location_phone_country_code" data-itflow-phone-country-select="country">
                            </div>
                        </div>
                    </div>
                    <div class="col-3">
                        <div class="mb-3">
                            <input type="text" class="form-control" name="location_extension" placeholder="ext." maxlength="200">
                        </div>
                    </div>
                </div>

                <label><?= __('Location Fax') ?></label>
                <div class="row g-2">
                    <div class="col-9">
                        <div class="mb-3">
                            <div class="input-group">
                                    <span class="input-group-text"><i class="fa fa-fw fa-fax"></i></span>
                                <input type="hidden" name="location_fax_country_code">
                                <input type="tel" class="form-control" name="location_fax" placeholder="<?= escapeHtml(__('Fax Number')) ?>" maxlength="200" data-itflow-phone="location_fax_country_code" data-itflow-phone-country-select="country">
                            </div>
                        </div>
                    </div>
                </div>

            </div>

            <div class="tab-pane fade" id="pills-contact">

                <div class="mb-3">
                    <label><?= __('Primary Contact') ?> <strong class="text-danger">*</strong></label>
                    <div class="input-group">
                            <span class="input-group-text"><i class="fa fa-fw fa-user-check"></i></span>
                        <input type="text" class="form-control" id="primaryContact" name="contact" placeholder="<?= escapeHtml(__('Primary Contact Person')) ?>" maxlength="200" required autofocus>
                    </div>
                </div>

                <div class="mb-3">
                    <label><?= __('Title') ?></label>
                    <div class="input-group">
                            <span class="input-group-text"><i class="fa fa-fw fa-id-badge"></i></span>
                        <input type="text" class="form-control" name="title" placeholder="<?= escapeHtml(__('Title')) ?>" maxlength="200">
                    </div>
                </div>

                <label><?= __('Contact Phone') ?> / <span class="text-secondary"><?= __('Extension') ?></span></label>
                <div class="row g-2">
                    <div class="col-9">
                        <div class="mb-3">
                            <div class="input-group">
                                    <span class="input-group-text"><i class="fa fa-fw fa-phone"></i></span>
                                <input type="hidden" name="contact_phone_country_code">
                                <input type="tel" class="form-control" name="contact_phone" placeholder="<?= escapeHtml(__('Phone Number')) ?>" maxlength="200" data-itflow-phone="contact_phone_country_code">
                            </div>
                        </div>
                    </div>
                    <div class="col-3">
                        <div class="mb-3">
                            <input type="text" class="form-control" name="contact_extension" placeholder="ext." maxlength="200">
                        </div>
                    </div>
                </div>

                <label><?= __('Mobile') ?></label>
                <div class="row g-2">
                    <div class="col-9">
                        <div class="mb-3">
                            <div class="input-group">
                                    <span class="input-group-text"><i class="fa fa-fw fa-mobile-alt"></i></span>
                                <input type="hidden" name="contact_mobile_country_code">
                                <input type="tel" class="form-control" name="contact_mobile" placeholder="<?= escapeHtml(__('Mobile Phone Number')) ?>" maxlength="200" data-itflow-phone="contact_mobile_country_code">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mb-3">
                    <label><?= __('Contact Email') ?></label>
                    <div class="input-group">
                            <span class="input-group-text"><i class="fa fa-fw fa-envelope"></i></span>
                        <input type="email" class="form-control" name="contact_email" placeholder="<?= escapeHtml(__('Contact\'s Email Address')) ?>" maxlength="200">
                    </div>
                </div>

            </div>

            <?php if ($config_module_enable_accounting) { ?>

                <div class="tab-pane fade" id="pills-billing">

                    <?php $contract_pick_edit = false; require __DIR__ . '/client_contract_pick.php'; ?>

                    <?php $terms_note_client_id = 0; require __DIR__ . '/client_terms_note.php'; ?>

                    <div class="mb-3">
                        <label><?= __('Tax ID') ?></label>
                        <div class="input-group">
                                <span class="input-group-text"><i class="fa fa-fw fa-balance-scale"></i></span>
                            <input type="text" class="form-control" name="tax_id_number" placeholder="<?= escapeHtml(__('Tax ID Number')) ?>" maxlength="255">
                        </div>
                    </div>

                    <?php
                    $client_tax = [];
                    $client_tax_key = 'New';
                    require __DIR__ . '/client_tax_fields.php';
                    ?>

                    <div class="mb-3">
                        <input type="hidden" name="reminders_field" value="1">
                        <div class="form-check form-switch">
                            <input type="checkbox" class="form-check-input" name="reminders" value="1" id="clientRemindersNew" checked>
                            <label class="form-check-label" for="clientRemindersNew"><?= __('Payment reminders and statements') ?></label>
                        </div>
                        <small class="text-secondary"><?= __('Emailed as set in Invoice Settings. Off, this client gets neither - invoices can still be sent by hand.') ?></small>
                    </div>


                </div>

            <?php } ?>

            <div class="tab-pane fade" id="pills-notes">
                <div class="mb-3">
                    <textarea class="form-control" rows="10" name="notes" placeholder="<?= escapeHtml(__('Enter some notes')) ?>"></textarea>
                </div>

            </div>

        </div>
    </div>
    <div class="modal-footer">
        <button type="submit" name="add_client" class="btn btn-primary text-bold" onclick="promptPrimaryContact()"><i class="fa fa-check me-2"></i><?= __('Create Client') ?></button>
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal"><i class="fa fa-times me-2"></i><?= __('Close') ?></button>
    </div>
</form>

<script>
    // Checks/prompts that the primary contact field (required) is populated
    function promptPrimaryContact() {
        let primaryContactField = document.getElementById("primaryContact").value;
        if (primaryContactField == null || primaryContactField === "") {
            document.getElementById("contactNavPill").click();
        }
    }
</script>

<script>
    // Checks for duplicate clients
    function checkClientDuplicate() {
        var name = document.getElementById("client_name").value;
        //Send a GET request to ajax.php as ajax.php?client_duplicate_check=true&name=NAME
        itflowGet(
            "ajax.php",
            {client_duplicate_check: 'true', name: name},
            function(data) {
                //If we get a response from ajax.php, parse it as JSON
                const client_duplicate_data = JSON.parse(data);
                document.getElementById("client_duplicate_info").innerHTML = client_duplicate_data.message;
            }
        );
    }
</script>

<?php
require_once '../../../includes/modal_footer.php';
