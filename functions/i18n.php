<?php

/*
 * Interface language - translating what ITFlow shows people.
 *
 * The key is the English text itself: __('Add Ticket') looks 'Add Ticket' up in
 * lang/<locale>.php and hands the English back when there is no translation, so a
 * missing entry shows English rather than breaking the page. en_US is the source
 * language and has no file. _x() adds a context for a word that means two things
 * ('ticket status::Open' is not the button 'Open'); _n() picks a plural form.
 *
 * What comes back is plain text. Translations are reviewed code and can be echoed
 * as they are; values passed in as arguments follow the usual rules at the call
 * site - escapeHtml() for a page, escapeSql() for a query.
 *
 * Never translate a value that is stored, compared or posted (invoice_status =
 * 'Sent', an <option value>, a ticket status name read from the database) - only
 * where it is shown.
 *
 * The language is Admin > Settings > Localization (settings.config_language), read
 * once per process the first time it is needed. No database yet (setup) or no
 * column yet (files updated before the migration ran) means en_US, never an error.
 * API responses, logs and CLI output stay in English and do not call these.
 *
 * __, _x and _n are declared unconditionally: if anything else ever declares one of
 * them first, PHP stops on the first request with "Cannot redeclare", rather than
 * one of the two silently winning.
 */

/** The languages ITFlow can be shown in - each named in itself, so it can be found whatever the interface is in. */
function i18nLanguages(): array {
    return [
        'en_US' => 'English (US)',
        'pt_BR' => 'Português (Brasil)',
    ];
}

/** Shared per-process state: the language and the dictionaries loaded for it. */
function &i18nState(): array {
    static $state = ['locale' => null, 'dictionary' => null, 'dictionary_locale' => null, 'js' => null, 'js_locale' => null];
    return $state;
}

/** Sets the language for the rest of this process - one ITFlow offers, else en_US. */
function i18nSetLocale(string $locale): void {
    $state = &i18nState();
    $state['locale'] = isset(i18nLanguages()[$locale]) ? $locale : 'en_US';
}

/** The language this process shows things in (en_US, pt_BR). */
function i18nLocale(): string {
    global $mysqli;

    $state = &i18nState();
    if ($state['locale'] !== null) {
        return $state['locale'];
    }

    // No connection yet - English for now, and ask again once there is one
    if (!($mysqli instanceof mysqli)) {
        return 'en_US';
    }

    $locale = 'en_US';
    try {
        $result = mysqli_query($mysqli, "SELECT config_language FROM settings WHERE company_id = 1");
        $row = $result ? mysqli_fetch_assoc($result) : null;
        $locale = (string) ($row['config_language'] ?? 'en_US');
    } catch (mysqli_sql_exception $e) {
        // The column arrives with database 3.5.6 - until then, English
        $locale = 'en_US';
    }

    i18nSetLocale($locale);

    return $state['locale'];
}

/** The language as HTML wants it in <html lang> (pt-BR). */
function i18nHtmlLang(): string {
    return str_replace('_', '-', i18nLocale());
}

/**
 * The attributes of a page's <html> that carry the language: lang, and the
 * front end's dictionary (js/i18n.js reads data-itflow-i18n). An attribute rather
 * than an inline <script>, which the sign-in pages' and the client portal's CSP
 * (default-src 'self') would block. Every page with its own <html> that loads the
 * project's scripts uses it, with /js/i18n.js in its <head>.
 */
function i18nHtmlAttributes(): string {
    $dictionary = i18nJsDictionary();
    $json = json_encode($dictionary ?: new stdClass(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    return 'lang="' . escapeHtml(i18nHtmlLang()) . '" data-itflow-i18n="' . escapeHtml($json === false ? '{}' : $json) . '"';
}

/** The dictionary files of a language: lang/<dir><locale>.php, then lang/<dir><locale>/*.php by name. */
function i18nFiles(string $dir, string $locale): array {
    if ($locale === 'en_US' || !isset(i18nLanguages()[$locale])) {
        return [];
    }
    $base = dirname(__DIR__) . "/lang/$dir$locale";
    $files = is_file("$base.php") ? ["$base.php"] : [];
    $parts = is_dir($base) ? (glob("$base/*.php") ?: []) : [];
    sort($parts);

    return array_merge($files, $parts);
}

/**
 * Loads a language's dictionary - lang/<locale>.php plus any area files in
 * lang/<locale>/ (lang/js/ for the front end) - as one array. Nothing for en_US
 * or a language with no files. An entry in two files: the later file wins, and
 * scripts/i18n_check.php reports it.
 */
function i18nLoadFile(string $dir, string $locale): array {
    $dictionary = [];
    foreach (i18nFiles($dir, $locale) as $file) {
        $entries = include $file;
        if (is_array($entries)) {
            $dictionary = array_replace($dictionary, $entries);
        }
    }

    return $dictionary;
}

/** The translations for the current language, keyed by the English. */
function i18nDictionary(): array {
    $state = &i18nState();
    $locale = i18nLocale();
    if ($state['dictionary_locale'] !== $locale) {
        $state['dictionary'] = i18nLoadFile('', $locale);
        $state['dictionary_locale'] = $locale;
    }

    return $state['dictionary'];
}

/** The translations the front end shows (window.itflowI18n) - empty in en_US. */
function i18nJsDictionary(): array {
    $state = &i18nState();
    $locale = i18nLocale();
    if ($state['js_locale'] !== $locale) {
        $state['js'] = i18nLoadFile('js/', $locale);
        $state['js_locale'] = $locale;
    }

    return $state['js'];
}

/**
 * Fills in the arguments. Without any the text is left alone, so "100%" needs no
 * escaping; with some, a translation whose placeholders don't fit falls back to the
 * English rather than throwing on the page.
 */
function i18nFill(string $translated, string $original, array $args): string {
    if (!$args) {
        return $translated;
    }
    try {
        return vsprintf($translated, $args);
    } catch (ValueError | ArgumentCountError $e) {
        try {
            return vsprintf($original, $args);
        } catch (ValueError | ArgumentCountError $e) {
            return $original;
        }
    }
}

/** Which plural form a count takes: 0 = one, 1 = other (CLDR - in Portuguese 0 and 1 are both "one"). */
function i18nPluralIndex(string $locale, int $count): int {
    $count = abs($count);
    switch ($locale) {
        case 'pt_BR':
            return $count <= 1 ? 0 : 1;
        default:
            return $count === 1 ? 0 : 1;
    }
}

/** Translates $text; any $args fill its placeholders (%s, %d, %1$s). */
function __(string $text, ...$args): string {
    $dictionary = i18nDictionary();
    $translated = (isset($dictionary[$text]) && is_string($dictionary[$text]) && $dictionary[$text] !== '') ? $dictionary[$text] : $text;

    return i18nFill($translated, $text, $args);
}

/** Translates $text as meant in $context - its entry is "context::text". */
function _x(string $text, string $context, ...$args): string {
    $dictionary = i18nDictionary();
    $key = "$context::$text";
    $translated = (isset($dictionary[$key]) && is_string($dictionary[$key]) && $dictionary[$key] !== '') ? $dictionary[$key] : $text;

    return i18nFill($translated, $text, $args);
}

/**
 * Picks the singular or the plural for $count and translates it. The entry is keyed
 * by the singular and holds [one, other]. With no $args, $count fills the
 * placeholder ('%d ticket', '%d tickets').
 */
function _n(string $singular, string $plural, int $count, ...$args): string {
    $dictionary = i18nDictionary();
    $original = $count === 1 ? $singular : $plural;
    $translated = $original;
    if (isset($dictionary[$singular]) && is_array($dictionary[$singular])) {
        $forms = array_values($dictionary[$singular]);
        $form = $forms[i18nPluralIndex(i18nLocale(), $count)] ?? null;
        if (is_string($form) && $form !== '') {
            $translated = $form;
        }
    }

    return i18nFill($translated, $original, $args ?: [$count]);
}
