<?php

/*
 * Admin > Settings > Localization (admin/settings_localization.php):
 * edit_localization saves the interface language, the number format and
 * currency (the company's) and the timezone - each one ITFlow offers, or the
 * page comes back saying which is not.
 */

defined('FROM_POST_HANDLER') || die("Direct file access is not allowed");

if (isset($_POST['edit_localization'])) {

    validateCSRFToken();

    $localization = settingsLocalizationArrays();
    $before = settingsCurrent(['company_locale', 'company_currency'], 'companies');
    // config_language arrives with database 3.5.6 - left out until the update has run
    $has_language = settingsColumnExists($mysqli, 'config_language');
    $before_settings = settingsCurrent($has_language ? ['config_timezone', 'config_language'] : ['config_timezone']);

    $after = [
        'company_locale' => trim((string) ($_POST['company_locale'] ?? '')),
        'company_currency' => strtoupper(trim((string) ($_POST['company_currency'] ?? ''))),
    ];
    $timezone = trim((string) ($_POST['config_timezone'] ?? ''));
    $language = trim((string) ($_POST['config_language'] ?? ''));

    $errors = [];
    if ($has_language && !isset(i18nLanguages()[$language])) {
        $errors['config_language'] = __('Pick the language from the list.');
    }
    if (!isset($localization['locales'][$after['company_locale']])) {
        $errors['company_locale'] = __('Pick the format from the list.');
    }
    if (!isset($localization['currencies'][$after['company_currency']])) {
        $errors['company_currency'] = __('Pick the currency from the list.');
    }
    if (!in_array($timezone, DateTimeZone::listIdentifiers(), true)) {
        $errors['config_timezone'] = __('Pick the timezone from the list.');
    }
    if ($errors) {
        settingsRefuse('localization', $errors);
    }

    $changed = settingsWrite('companies', $before, $after) + settingsWrite('settings', $before_settings, ['config_timezone' => $timezone] + ($has_language ? ['config_language' => $language] : []));
    settingsSaid('localization', settingsChangeLines($before + $before_settings, $changed));

    redirect();

}
