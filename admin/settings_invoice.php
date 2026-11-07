<?php

/*
 * Admin > Settings > Invoices and billing - how invoices and recurring
 * invoices are numbered, what each invoice shows, late fees, who hears of one
 * paid online, payment reminders and statements (cron/nightly_tasks.php),
 * how ticket time, billable expenses and trips are billed, and what agents
 * are paid back for driving. Saved by edit_invoice_settings
 * (admin/post/settings_invoice.php). The sections the rest of ITFlow links
 * to keep their anchors - #reminders, #billing-time, #billable-expenses and
 * #mileage.
 */

require_once "includes/inc_all_admin.php";

$company = settingsCurrent(['company_tax_id'], 'companies');

$reminder_rules = billingReminderRules();
$reminder_before = implode(', ', array_reverse(array_map('abs', array_filter($reminder_rules['days'], function ($day) { return $day < 0; }))));
$reminder_after = implode(', ', array_filter($reminder_rules['days'], function ($day) { return $day > 0; }));
$reminder_on_due = settingsChecked('reminder_on_due', in_array(0, $reminder_rules['days'], true));

$time_rules = billingTimeRules();
$trip_rules = tripRules();

$statement_days = [];
for ($day = 1; $day <= 28; $day++) {
    $statement_days[$day] = i18nLocale() === 'en_US' ? 'On the ' . $day . ([1 => 'st', 2 => 'nd', 3 => 'rd', 21 => 'st', 22 => 'nd', 23 => 'rd'][$day] ?? 'th') : __('On day %d', $day); // i18n-ignore - English ordinals
}

$decimal = function ($value, $places) {
    return $value > 0 ? rtrim(rtrim(number_format($value, $places, '.', ''), '0'), '.') : '';
};

echo settingsPageStart('invoice');

?>

<form action="post.php" method="post" autocomplete="off" data-settings-form>
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

    <div class="card itflow-settings-card">
        <div class="card-body">

            <?= settingsSectionStart('numbering', 'Numbering', __('Each invoice gets the next number, after its prefix - one a recurring invoice makes too. Recurring invoices are numbered on their own.')) ?>
                <div class="fw-semibold small mb-2"><?= __('Invoices') ?></div>
                <?= settingsNumberingFields('invoice', $config_invoice_prefix, $config_invoice_next_number) ?>
                <div class="fw-semibold small mt-4 mb-2"><?= __('Recurring invoices') ?></div>
                <?= settingsNumberingFields('recurring_invoice', $config_recurring_invoice_prefix, $config_recurring_invoice_next_number) ?>
            <?= settingsSectionEnd() ?>

            <?= settingsSectionStart('invoice', __('On the invoice'), __('What every invoice shows - on the page, the PDF and the client portal.')) ?>
                <?= settingsTextarea('config_invoice_footer', $config_invoice_footer, ['rows' => 5, 'maxlength' => 65000, 'help' => __('Plain text - each new line is kept. Payment terms, bank details, the late fee.')]) ?>
                <?= settingsSwitch('config_invoice_show_tax_id', $config_invoice_show_tax_id, ['help' => trim((string) $company['company_tax_id']) !== ''
                    ? __('Under your address - %s, from <a href="settings_company.php#setting=company_tax_id">Company details</a>.', escapeHtml($company['company_tax_id']))
                    : __('Under your address. There is none in <a href="settings_company.php#setting=company_tax_id">Company details</a> yet.')]) ?>
            <?= settingsSectionEnd() ?>

            <?= settingsSectionStart('late-fees', __('Late fees'), __('A percent of what is still owed, added as a line of its own every 30 days late - by the nightly job, while payment reminders are on.')) ?>
                <?php if ($config_invoice_late_fee_enable && !$reminder_rules['enabled']) { ?>
                    <div class="alert alert-warning py-2 small" data-late-fee-no-reminders><i class="fas fa-exclamation-triangle me-2"></i><?= __('Payment reminders are off, so no late fee is added.') ?></div>
                <?php } ?>
                <?= settingsSwitch('config_invoice_late_fee_enable', $config_invoice_late_fee_enable, ['help' => __('Say so in the footer above, so a client knows before it comes.')]) ?>
                <div data-settings-depends-on="config_invoice_late_fee_enable">
                    <?= settingsInput('config_invoice_late_fee_percent', $decimal(floatval($config_invoice_late_fee_percent), 2) ?: '0', ['icon' => 'fa-percent', 'type' => 'number', 'min' => 0, 'max' => 100, 'step' => '0.01',
                        'label' => __('Each late fee'), 'suffix' => '% ' . __('of what is owed')]) ?>
                </div>
            <?= settingsSectionEnd() ?>

            <?= settingsSectionStart('paid', __('Paid online'), __('When a client pays an invoice by card - in the client portal or from the link in its email - through a provider in <a href="payment_providers.php">Payment providers</a>.')) ?>
                <div class="itflow-settings-field">
                    <div class="itflow-settings-note" data-card-payments><?= paymentProviderSummaryHtml() ?></div>
                </div>
                <?= settingsInput('config_invoice_paid_notification_email', $config_invoice_paid_notification_email, ['icon' => 'fa-bell', 'type' => 'email', 'maxlength' => 200,
                    'placeholder' => 'billing@company.com', 'help' => __('A shared mailbox or list works best. Empty, nobody is emailed - the payment is still recorded.')]) ?>
            <?= settingsSectionEnd() ?>

            <?= settingsSectionStart('reminders', __('Reminders and statements'), __('Reminders go to each client\'s primary and billing contacts while an invoice is still owed, with a button to view and pay it. A client can be left out of reminders and statements on the Billing tab of its Edit window.'), 'reminders') ?>
                <?= settingsSwitch('config_send_invoice_reminders', $reminder_rules['enabled'], ['id' => 'settingsReminders']) ?>

                <div class="itflow-settings-field" data-reminder-fields>
                    <div class="row g-3">
                        <div class="col-md-6"><?= settingsInput('reminder_before', $reminder_before, ['icon' => 'fa-hourglass-start', 'maxlength' => 60, 'label' => __('Before it is due'), 'suffix' => __('days'),
                            'placeholder' => __('None - or 3, or 3, 7'), 'help' => __('Up to 60 days before.')]) ?></div>
                        <div class="col-md-6"><?= settingsInput('reminder_after', $reminder_after, ['icon' => 'fa-exclamation-circle', 'maxlength' => 120, 'label' => __('After it is due'), 'suffix' => __('days late'),
                            'placeholder' => __('None - or 7, 14, 30')]) ?></div>
                        <div class="col-md-6"><?= settingsInput('reminder_repeat', intval($reminder_rules['repeat']), ['icon' => 'fa-redo-alt', 'type' => 'number', 'min' => 0, 'max' => 365, 'label' => __('Then every'),
                            'suffix' => __('days until paid'), 'help' => __('0 for no more after the last one.')]) ?></div>
                        <div class="col-md-6 d-flex align-items-center">
                            <div class="itflow-settings-field" data-setting="reminder_on_due" id="setting-reminder_on_due">
                                <div class="form-check form-switch mt-md-3 mb-0">
                                    <input type="checkbox" class="form-check-input" role="switch" name="reminder_on_due" value="1" id="settingsReminderOnDue"<?= $reminder_on_due ? ' checked' : '' ?>>
                                    <label class="form-check-label" for="settingsReminderOnDue"><?= __('And on the due date') ?></label>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="itflow-settings-note mt-3"><i class="fa fa-fw fa-bell me-1 text-body-secondary"></i><?= __('Reminders go') ?> <strong data-reminder-summary><?= escapeHtml(billingReminderScheduleText($reminder_rules)) ?></strong>. <?= __("An invoice a recurring autopay charges on its due date isn't reminded before then.") ?></div>
                </div>

                <div class="row g-3 align-items-start itflow-settings-field">
                    <div class="col-md-8"><?= settingsSwitch('config_statement_monthly', $reminder_rules['statement_monthly'], ['id' => 'settingsStatements',
                        'label' => __('Email a statement each month to every client with a balance'), 'help' => __('Their open invoices, how late each is and what is owed, as a PDF too.')]) ?></div>
                    <div class="col-md-4"><?= settingsSelect('config_statement_day', $reminder_rules['statement_day'], $statement_days, ['icon' => 'fa-calendar-day', 'label' => __('Day of the month'), 'search' => false]) ?></div>
                </div>

                <?= settingsSwitch('config_billing_email_attach_pdf', $reminder_rules['attach_pdf'], ['id' => 'settingsAttachPdf', 'label' => __('Attach the PDF to invoice, quote, reminder and statement emails'),
                    'help' => __('The default in each Send window, and what reminders, statements and recurring invoices do.')]) ?>

                <script>
                (function () {
                    // The schedule in words as it is typed - the same as billingReminderScheduleText()
                    var form = document.currentScript.closest('form');
                    var summary = form.querySelector('[data-reminder-summary]');
                    function days(name) {
                        var seen = {};
                        return String(form.querySelector('[name="' + name + '"]').value).split(/[\s,;]+/).map(Number).filter(function (n) {
                            var ok = n > 0 && n <= 365 && Math.floor(n) === n && !seen[n];
                            seen[n] = true;
                            return ok;
                        }).sort(function (a, b) { return a - b; });
                    }
                    function list(numbers) {
                        return numbers.length > 1 ? __('%1$s and %2$s', numbers.slice(0, -1).join(', '), numbers[numbers.length - 1]) : String(numbers[0]);
                    }
                    function update() {
                        var on = form.querySelector('[name="config_send_invoice_reminders"]').checked;
                        form.querySelector('[data-reminder-fields]').classList.toggle('opacity-50', !on);
                        var before = days('reminder_before').filter(function (n) { return n <= 60; });
                        var after = days('reminder_after');
                        var onDue = form.querySelector('[name="reminder_on_due"]').checked;
                        var repeat = Math.max(0, Math.min(365, parseInt(form.querySelector('[name="reminder_repeat"]').value, 10) || 0));
                        var parts = [];
                        if (before.length) {
                            parts.push(before.length === 1 && before[0] === 1 ? __('1 day before it is due') : __('%s days before it is due', list(before)));
                        }
                        if (onDue) {
                            parts.push(__('on the due date'));
                        }
                        if (after.length) {
                            parts.push(after.length === 1 && after[0] === 1 ? __('1 day late') : __('%s days late', list(after)));
                        }
                        if (repeat) {
                            var every = repeat === 1 ? (after.length || onDue ? __('every day until it is paid') : __('every day after it is due until it is paid')) : (after.length || onDue ? __('every %s days until it is paid', repeat) : __('every %s days after it is due until it is paid', repeat));
                            parts.push(parts.length ? __('then %s', every) : every);
                        }
                        summary.textContent = on ? (parts.length ? parts.join(', ') : __('never')) : __('never - reminders are off');
                    }
                    form.addEventListener('input', update);
                    form.addEventListener('change', update);
                    form.addEventListener('settings:reset', update);
                    update();
                })();
                </script>
            <?= settingsSectionEnd() ?>

            <?= settingsSectionStart('time', __('Billing time'), __('How the time logged on a billable ticket becomes hours on an invoice - in Unbilled Time, a ticket\'s Invoice window and Add Tickets. Each ticket\'s time is rounded once, as a whole, and billed at the client\'s hourly rate, or the one in <a href="settings_default.php#setting=config_default_hourly_rate">Defaults</a> when the client has none.'), 'billing-time') ?>
                <div class="row g-3">
                    <div class="col-md-6"><?= settingsSelect('config_time_increment', $time_rules['increment'], billingTimeIncrements(), ['icon' => 'fa-stopwatch', 'label' => __('Round to')]) ?></div>
                    <div class="col-md-6"><?= settingsSelect('config_time_rounding', $time_rules['rounding'], ['nearest' => __('To the nearest - under half a step rounds down'), 'up' => __('Always up - any part of a step bills the whole step')],
                        ['icon' => 'fa-sort-amount-up']) ?></div>
                    <div class="col-md-6"><?= settingsSelect('config_time_minimum', $time_rules['minimum'], [0 => __('No minimum'), 15 => __('15 minutes'), 30 => __('30 minutes'), 60 => __('1 hour'), 120 => __('2 hours')],
                        ['icon' => 'fa-arrow-down', 'help' => __('Once it has any time logged. A ticket with none bills nothing unless the hours are typed.')]) ?></div>
                    <div class="col-md-6"><?= settingsInput('config_time_item_name', $time_rules['item_name'], ['icon' => 'fa-cube', 'maxlength' => 200, 'required' => true, 'label' => __('Invoice line'),
                        'placeholder' => __('Support [Hourly]'), 'help' => __('The ticket number, subject and date go in its description.')]) ?></div>
                </div>
            <?= settingsSectionEnd() ?>

            <?= settingsSectionStart('expenses', __('Billable expenses'), __('An expense for a client can be billed on to them - it waits in Unbilled Time and goes on their invoice at what it cost, less any refund, plus a markup. A recurring expense can be billed on each time it records one.'), 'billable-expenses') ?>
                <div class="row g-3">
                    <div class="col-md-6"><?= settingsInput('config_expense_markup', rtrim(rtrim(number_format(expenseMarkupDefault(), 2, '.', ''), '0'), '.'), ['icon' => 'fa-percent', 'inputmode' => 'decimal', 'label' => __('Markup to start with'),
                        'placeholder' => '0', 'suffix' => '%', 'help' => __('0 bills it at cost. Each expense can have its own.')]) ?></div>
                </div>
            <?= settingsSectionEnd() ?>

            <?= settingsSectionStart('mileage', __('Trips and mileage'), __('An agent driving their own vehicle is paid back for the distance: Pay Mileage on the Trips page makes their trips a mileage expense they paid personally, to pay back with Reimburse. A trip for a client can be billed to them from Unbilled Time, beside their tickets and expenses.'), 'mileage') ?>
                <div class="row g-3">
                    <div class="col-md-6"><?= settingsSelect('config_mileage_unit', $trip_rules['unit'], ['mi' => 'Miles', 'km' => 'Kilometers'], ['icon' => 'fa-ruler',
                        'help' => __('For every trip, the rates below and the reports. Distances already logged aren\'t converted.')]) ?></div>
                    <div class="col-md-6"><?= settingsInput('config_mileage_rate', $decimal($trip_rules['rate'], 4), ['icon' => 'fa-hand-holding-usd', 'inputmode' => 'decimal', 'label' => __('Paid back a %s', tripUnitOne()),
                        'placeholder' => '0.70', 'help' => __('For driving their own vehicle - your reimbursement rate, such as the IRS standard mileage rate. Empty turns paying back off.')]) ?></div>
                    <div class="col-md-6"><?= settingsSelect('config_mileage_category_id', $trip_rules['category_id'], [], ['icon' => 'fa-list', 'search' => true,
                        'options_html' => '<option value="0">' . __('- Mileage, or Travel -') . '</option>' . categoryOptionsHtml('Expense', intval(settingsValue('config_mileage_category_id', $trip_rules['category_id']))),
                        'append' => categoryAddButtonHtml('Expense', 'btn btn-secondary'),
                        'help' => __('The expense category of the mileage paid back. The expenses are to the vendor Mileage, made the first time.')]) ?></div>
                </div>

                <div class="fw-semibold small mt-4 mb-2"><?= __('Billing trips') ?></div>
                <div class="row g-3">
                    <div class="col-md-6"><?= settingsInput('config_trip_charge', $trip_rules['charge'] > 0 ? number_format($trip_rules['charge'], 2, '.', '') : '', ['icon' => 'fa-dollar-sign', 'inputmode' => 'decimal',
                        'placeholder' => '0.00', 'help' => __('What each billable trip bills to start with.')]) ?></div>
                    <div class="col-md-6"><?= settingsInput('config_trip_bill_rate', $decimal($trip_rules['bill_rate'], 4), ['icon' => 'fa-road', 'inputmode' => 'decimal', 'label' => __('Billed a %s', tripUnitOne()),
                        'placeholder' => '0.00', 'help' => __('For the whole trip, there and back.')]) ?></div>
                    <div class="col-md-6"><?= settingsInput('config_trip_included', $decimal($trip_rules['included'], 1), ['icon' => 'fa-route', 'inputmode' => 'decimal', 'label' => __('The charge covers the first'),
                        'placeholder' => '0', 'suffix' => tripUnit(true), 'help' => __('The rate bills only the distance beyond it. Empty bills all of it.')]) ?></div>
                    <div class="col-md-6"><?= settingsInput('config_trip_item_name', $trip_rules['item_name'], ['icon' => 'fa-cube', 'maxlength' => 200, 'required' => true, 'label' => __('Invoice line'),
                        'placeholder' => __('Travel'), 'help' => __('What it was for, the route, date and distance go in its description.')]) ?></div>
                </div>
                <div class="itflow-settings-note mt-3" data-trip-rule><?= tripBillingOn() ? __('A trip bills %s.', escapeHtml(tripBillRuleText())) : __('With no charge and no rate, trips aren\'t billed.') ?> <?= __('The price can be changed in Unbilled Time before it goes on.') ?></div>
            <?= settingsSectionEnd() ?>

            <?= settingsSaveBar('edit_invoice_settings') ?>
        </div>
    </div>
</form>

<?php
require_once "../includes/footer.php";
