#!/usr/bin/env php
<?php

/*
 * Checks a translation against the code - php i18n_check.php pt_BR
 *
 * Finds every __('...'), _x('...', '...') and _n('...', '...') with literal text
 * in ITFlow's own PHP and JS (not libs/), and says:
 *
 *   Missing             text in use with no translation in lang/<locale>.php (PHP)
 *                       or lang/js/<locale>.php (JS) - with file:line
 *   Placeholders differ a translation whose %s / %d / %1$s don't match the English,
 *                       or a plural that isn't [one, other]
 *   Not literal         a call whose text is a variable - __($label) - which this
 *                       can't follow: check by hand that every value it gets is
 *                       translated. A line marked i18n-dynamic has been, and is
 *                       left out.
 *   Unused              a translation whose English appears nowhere in the code as
 *                       a literal any more - typo fixed, text removed
 *
 * Exits 1 when anything is missing or a placeholder differs, 0 otherwise - unused
 * and not-literal are for reading, not for failing on.
 */

chdir(__DIR__);

if (php_sapi_name() !== 'cli') {
    die("This script must be run from the command line.\n");
}

require_once "../functions/i18n.php";

$locale = $argv[1] ?? '';
if ($locale === '' || $locale === 'en_US' || !isset(i18nLanguages()[$locale])) {
    fwrite(STDERR, "Usage: php i18n_check.php <locale>\n  locales: " . implode(', ', array_diff(array_keys(i18nLanguages()), ['en_US'])) . "\n");
    exit(2);
}

$root = realpath(__DIR__ . '/..');
$skip_dirs = ['libs', '.git', '.ddev', '.claude', 'node_modules', 'openspec', 'uploads', 'lang'];
$skip_files = ['scripts/i18n_check.php'];

$calls = ['php' => [], 'js' => []];  // key => [file:line, ...]
$not_literal = [];                   // file:line => code
$literals = [];                      // every string literal in the code, for Unused

/* PHP's own decoding of a quoted literal - single or double - without eval. */
function i18nCheckUnquote(string $token): string {
    $quote = $token[0];
    $body = substr($token, 1, -1);
    if ($quote === "'") {
        return str_replace(["\\\\", "\\'"], ["\\", "'"], $body);
    }
    return stripcslashes($body);
}

/* The JS calls in a piece of script - comments taken out first. */
function i18nCheckJs(string $code, string $file, int $first_line, array &$calls, array &$not_literal, array &$literals): void {
    // Blank out comments, keeping line breaks so line numbers still count
    $code = preg_replace_callback('#/\*.*?\*/#s', fn($m) => preg_replace('/[^\n]/', ' ', $m[0]), $code);
    $code = preg_replace('#(^|[\s;{}(])//[^\n]*#', '$1', $code);

    $string = "'(?:[^'\\\\\\n]|\\\\.)*'|\"(?:[^\"\\\\\\n]|\\\\.)*\"";
    if (preg_match_all("/(?:$string)/", $code, $all)) {
        foreach ($all[0] as $literal) {
            $literals[stripcslashes(substr($literal, 1, -1))] = true;
        }
    }
    if (!preg_match_all('/(?<![\w$.])__\s*\(\s*(' . $string . ')?/', $code, $found, PREG_OFFSET_CAPTURE)) {
        return;
    }
    foreach ($found[0] as $i => $match) {
        $line = $first_line + substr_count(substr($code, 0, $match[1]), "\n");
        $where = "$file:$line";
        if (empty($found[1][$i][0])) {
            $line_text = trim(strtok(substr($code, $match[1]), "\n"));
            if (strpos($line_text, 'i18n-dynamic') === false) {
                $not_literal[$where] = mb_substr($line_text, 0, 90);
            }
            continue;
        }
        $calls['js'][stripcslashes(substr($found[1][$i][0], 1, -1))][] = $where;
    }
}

/* The PHP calls in a file, through the tokenizer - and the JS in its inline HTML. */
function i18nCheckPhp(string $code, string $file, array &$calls, array &$not_literal, array &$literals): void {
    $tokens = token_get_all($code);
    $count = count($tokens);
    $significant = function ($from, $step) use ($tokens, $count) {
        for ($i = $from; $i >= 0 && $i < $count; $i += $step) {
            if (is_array($tokens[$i]) && in_array($tokens[$i][0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                continue;
            }
            return $i;
        }
        return -1;
    };
    $lines = explode("\n", $code);

    for ($i = 0; $i < $count; $i++) {
        $token = $tokens[$i];
        if (!is_array($token)) {
            continue;
        }
        if ($token[0] === T_INLINE_HTML) {
            i18nCheckJs($token[1], $file, $token[2], $calls, $not_literal, $literals);
            continue;
        }
        if ($token[0] === T_CONSTANT_ENCAPSED_STRING) {
            $literals[i18nCheckUnquote($token[1])] = true;
            continue;
        }
        if ($token[0] !== T_STRING || !in_array($token[1], ['__', '_x', '_n'], true)) {
            continue;
        }
        $open = $significant($i + 1, 1);
        if ($open < 0 || $tokens[$open] !== '(') {
            continue;
        }
        // Declared or called as a method, not ours
        $before = $significant($i - 1, -1);
        if ($before >= 0 && is_array($tokens[$before]) && in_array($tokens[$before][0], [T_FUNCTION, T_OBJECT_OPERATOR, T_NULLSAFE_OBJECT_OPERATOR, T_DOUBLE_COLON, T_NEW], true)) {
            continue;
        }

        $where = "$file:{$token[2]}";
        // The literal arguments the key is made of: text, then context (_x) or plural (_n)
        $wanted = $token[1] === '__' ? 1 : 2;
        $args = [];
        $at = $open;
        for ($n = 0; $n < $wanted; $n++) {
            $arg = $significant($at + 1, 1);
            $after = $arg >= 0 ? $significant($arg + 1, 1) : -1;
            if ($arg < 0 || !is_array($tokens[$arg]) || $tokens[$arg][0] !== T_CONSTANT_ENCAPSED_STRING || $after < 0 || !in_array($tokens[$after], [',', ')'], true)) {
                $args = null;
                break;
            }
            $args[] = i18nCheckUnquote($tokens[$arg][1]);
            $at = $after;
        }
        if ($args === null) {
            $line_text = trim($lines[$token[2] - 1] ?? '');
            if (strpos($line_text, 'i18n-dynamic') === false) {
                $not_literal[$where] = mb_substr($line_text, 0, 90);
            }
            continue;
        }
        $key = $token[1] === '_x' ? "{$args[1]}::{$args[0]}" : $args[0];
        $calls['php'][$key][] = $where;
        if ($token[1] === '_n') {
            $calls['php_plural'][$key] = $args[1];
        }
    }
}

$iterator = new RecursiveIteratorIterator(new RecursiveCallbackFilterIterator(
    new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
    function ($item) use ($root, $skip_dirs) {
        $relative = substr($item->getPathname(), strlen($root) + 1);
        return !($item->isDir() && in_array(explode('/', $relative)[0], $skip_dirs, true));
    }
));
foreach ($iterator as $item) {
    $relative = substr($item->getPathname(), strlen($root) + 1);
    $extension = strtolower($item->getExtension());
    if (in_array($relative, $skip_files, true) || !in_array($extension, ['php', 'js'], true)) {
        continue;
    }
    $code = file_get_contents($item->getPathname());
    if ($code === false || (strpos($code, '__') === false && strpos($code, '_x') === false && strpos($code, '_n') === false && $extension === 'js')) {
        continue;
    }
    if ($extension === 'php') {
        i18nCheckPhp($code, $relative, $calls, $not_literal, $literals);
    } else {
        i18nCheckJs($code, $relative, 1, $calls, $not_literal, $literals);
    }
}

/* The placeholders a text takes, as a sorted list of their types - %% is not one. */
function i18nCheckPlaceholders(string $text): array {
    preg_match_all('/%(?:\d+\$)?[-+ 0\'.\d]*([bcdeEfFgGosuxX%])/', $text, $m);
    $types = array_values(array_filter($m[1], fn($type) => $type !== '%'));
    sort($types);
    return $types;
}

$dictionaries = [
    'php' => ['file' => "lang/$locale.php", 'entries' => i18nLoadFile('', $locale)],
    'js' => ['file' => "lang/js/$locale.php", 'entries' => i18nLoadFile('js/', $locale)],
];

$missing = [];
$placeholders = [];
$unused = [];
$in_use = 0;
$translated = 0;

foreach (['php', 'js'] as $side) {
    $entries = $dictionaries[$side]['entries'];
    $file = $dictionaries[$side]['file'];
    foreach ($calls[$side] as $key => $wheres) {
        $in_use++;
        if (!isset($entries[$key]) || $entries[$key] === '' || $entries[$key] === []) {
            $missing[] = [$wheres, $key, $file];
            continue;
        }
        $translated++;
        $english = str_contains($key, '::') && !isset($calls['php_plural'][$key]) ? substr($key, strpos($key, '::') + 2) : $key;
        if (isset($calls['php_plural'][$key])) {
            $forms = $entries[$key];
            if (!is_array($forms) || count($forms) !== 2) {
                $placeholders[] = "'$key' in $file must be [one, other]";
                continue;
            }
            foreach (array_values($forms) as $index => $form) {
                $source = $index === 0 ? $key : $calls['php_plural'][$key];
                if (i18nCheckPlaceholders((string) $form) !== i18nCheckPlaceholders($source)) {
                    $placeholders[] = "'$source' -> '$form' ($file)";
                }
            }
        } elseif (is_string($entries[$key]) && i18nCheckPlaceholders($entries[$key]) !== i18nCheckPlaceholders($english)) {
            $placeholders[] = "'$english' -> '{$entries[$key]}' ($file)";
        }
    }
    foreach ($entries as $key => $translation) {
        $english = str_contains($key, '::') ? substr($key, strpos($key, '::') + 2) : $key;
        if (!isset($calls[$side][$key]) && !isset($literals[$english])) {
            $unused[] = "'$key' ($file)";
        }
    }
}

echo "$locale - $in_use texts in use, $translated translated\n";

$section = function ($title, array $items, callable $line) {
    if (!$items) {
        return;
    }
    echo "\n$title (" . count($items) . "):\n";
    foreach ($items as $key => $item) {
        echo '  ' . $line($item, $key) . "\n";
    }
};
$section('Missing', $missing, fn($item) => implode(', ', array_slice($item[0], 0, 3)) . (count($item[0]) > 3 ? ' +' . (count($item[0]) - 3) : '') . "  '{$item[1]}'  -> {$item[2]}");
$section('Placeholders differ', $placeholders, fn($item) => $item);
$section('Not literal - check by hand, or mark the line i18n-dynamic once checked', $not_literal, fn($code, $where) => "$where  $code");
$section('Unused', $unused, fn($item) => $item);

exit(($missing || $placeholders) ? 1 : 0);
