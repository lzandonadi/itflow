<?php

/*
 * Admin > Settings > Localization - the language ITFlow is shown in
 * (settings.config_language, functions/i18n.php), how numbers and money are
 * written and the currency you bill in (the company's), and the timezone
 * ITFlow keeps time in - with money and the time now as they will look
 * (js/settings.js). Saved by edit_localization (admin/post/settings_localization.php).
 */

require_once "includes/inc_all_admin.php";

$company = settingsCurrent(['company_locale', 'company_currency'], 'companies');
// config_language arrives with database 3.5.6 - English until the update has run
$settings = settingsColumnExists($mysqli, 'config_language') ? settingsCurrent(['config_language']) : ['config_language' => 'en_US'];
$localization = settingsLocalizationArrays();

$locales = ['' => __('- Format -')] + $localization['locales'];
$currencies = ['' => __('- Currency -')];
foreach ($localization['currencies'] as $code => $name) {
    $currencies[$code] = "$code - $name";
}
$timezones = ['' => __('- Timezone -')];
foreach (DateTimeZone::listIdentifiers() as $timezone) {
    $timezones[$timezone] = $timezone;
}

echo settingsPageStart('localization');

?>

<form action="post.php" method="post" autocomplete="off" data-settings-form data-settings-sample-form>
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

    <div class="card itflow-settings-card">
        <div class="card-body">

            <?= settingsSectionStart('language', __('Language and currency'), escapeHtml(__('The language ITFlow is shown in, how numbers and money are written - on screen, on invoices and in emails - and the currency new clients, invoices and quotes start in.'))) ?>
                <?= settingsSelect('config_language', (string) ($settings['config_language'] ?: 'en_US'), i18nLanguages(), ['icon' => 'fa-language', 'required' => true,
                    'help' => escapeHtml(__('For everyone - agents, the client portal and the emails ITFlow sends. What is already saved stays in the language it was written in.'))]) ?>
                <?= settingsSelect('company_locale', (string) $company['company_locale'], $locales, ['icon' => 'fa-hashtag', 'required' => true, 'search' => true]) ?>
                <?= settingsSelect('company_currency', (string) $company['company_currency'], $currencies, ['icon' => 'fa-money-bill', 'required' => true, 'search' => true,
                    'help' => escapeHtml(__('What is already entered keeps the currency it was entered in - nothing is converted.'))]) ?>
            <?= settingsSectionEnd() ?>

            <?= settingsSectionStart('timezone', __('Timezone'), escapeHtml(__('The clock ITFlow keeps - when tickets are due, when cron runs the nightly jobs, and the times on everything.'))) ?>
                <?= settingsSelect('config_timezone', (string) $config_timezone, $timezones, ['icon' => 'fa-business-time', 'required' => true, 'search' => true]) ?>
                <div class="itflow-settings-sample mt-3" data-settings-sample>
                    <span class="text-body-secondary"><?= escapeHtml(__('Looks like')) ?></span>
                    <span data-settings-sample-money><?= escapeHtml(numfmt_format_currency(numfmt_create((string) $company['company_locale'] ?: 'en_US', NumberFormatter::CURRENCY), 1234.56, (string) $company['company_currency'] ?: 'USD')) ?></span>
                    <span data-settings-sample-date><?= escapeHtml(date('l, F j, Y g:i A')) ?></span>
                </div>
            <?= settingsSectionEnd() ?>

            <?= settingsSaveBar('edit_localization') ?>
        </div>
    </div>
</form>

<?php
require_once "../includes/footer.php";
