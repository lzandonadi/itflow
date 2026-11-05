#!/usr/bin/env php
<?php

/*
 * Checks a translation against the code - php i18n_check.php pt_BR
 *
 * Finds every __('...'), _x('...', '...') and _n('...', '...') with literal text
 * in ITFlow's own PHP and JS (not libs/), and says:
 *
 *   Missing             text in use with no translation in lang/<locale>.php or
 *                       lang/<locale>/*.php (PHP), lang/js/<locale>.php (JS) -
 *                       with file:line
 *   Placeholders differ a translation whose %s / %d / %1$s or HTML tags don't
 *                       match the English, or a plural that isn't [one, other]
 *   Duplicates          the same English in two dictionary files with different
 *                       translations - the later file wins
 *   Not literal         a call whose text is a variable - __($label) - which this
 *                       can't follow: check by hand that every value it gets is
 *                       translated. A line marked i18n-dynamic has been, and is
 *                       left out.
 *   Unused              a translation whose English appears nowhere in the code as
 *                       a literal any more - typo fixed, text removed
 *
 * Exits 1 when anything is missing or a placeholder or tag differs, 0 otherwise -
 * the rest is for reading, not for failing on.
 *
 *   php i18n_check.php pt_BR --hardcoded agent/modals/ticket agent/ticket.php
 *
 * lists instead what still looks like fixed English under those paths, file:line:
 * text between tags, title / placeholder / aria-label / alt attributes, and
 * sentences in PHP and JS strings. A guess, to find what a batch missed - a line
 * marked i18n-ignore is left out. Always exits 0.
 */

chdir(__DIR__);

if (php_sapi_name() !== 'cli') {
    die("This script must be run from the command line.\n");
}

require_once "../functions/i18n.php";

$locale = $argv[1] ?? '';
if ($locale === '' || $locale === 'en_US' || !isset(i18nLanguages()[$locale])) {
    fwrite(STDERR, "Usage: php i18n_check.php <locale> [--hardcoded <path>...]\n  locales: " . implode(', ', array_diff(array_keys(i18nLanguages()), ['en_US'])) . "\n");
    exit(2);
}
$hardcoded_paths = null;
if (($argv[2] ?? '') === '--hardcoded') {
    $hardcoded_paths = array_slice($argv, 3);
    if (!$hardcoded_paths) {
        fwrite(STDERR, "--hardcoded needs at least one path, relative to the ITFlow folder\n");
        exit(2);
    }
}

$root = realpath(__DIR__ . '/..');
$skip_dirs = ['libs', '.git', '.ddev', '.claude', 'node_modules', 'openspec', 'uploads', 'lang', 'setup'];  // and js/vendor-lang, third-party language packs
$skip_files = ['scripts/i18n_check.php'];

$calls = ['php' => [], 'js' => [], 'php_plural' => []];  // key => [file:line, ...]
$not_literal = [];                   // file:line => code
$literals = [];                      // every string literal in the code, for Unused
$hardcoded = [];                     // file:line => text

/* PHP's own decoding of a quoted literal - single or double - without eval. */
function i18nCheckUnquote(string $token): string {
    $quote = $token[0];
    $body = substr($token, 1, -1);
    if ($quote === "'") {
        return str_replace(["\\\\", "\\'"], ["\\", "'"], $body);
    }
    return stripcslashes($body);
}

/* Whether a piece of text reads like words for people - not a class, a key or a number. */
function i18nCheckIsProse(string $text, bool $single_word_ok): bool {
    $text = trim(html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    $text = preg_replace('/%(\d+\$)?[sd]|\{[^}]*\}|\$\w+/', '', $text);
    if (!preg_match('/[A-Za-z]{2,}/', $text)) {
        return false;
    }
    // Code-looking: snake_case, camelCase, paths, urls, css, selectors
    if (preg_match('/^[a-z0-9_.\-\/:#\[\]=]+$/', $text) || preg_match('/^[a-z]+[A-Z]\w*$/', $text) || preg_match('#^(https?:|/|\.|\#)#', $text)) {
        return false;
    }
    if (!$single_word_ok && !preg_match('/[A-Za-z][^\s]*\s+[A-Za-z(]/', $text)) {
        return false;
    }
    // A sentence or label starts with a capital, a digit or a quote/dash/paren -
    // or is the middle of one: three plain words or more
    if (preg_match('/^[\-–(\'"0-9]*\s*[A-Z0-9]/u', $text)) {
        return true;
    }
    return !$single_word_ok && preg_match_all("/(?:^|\\s)[a-z']+[.,!?]?(?=\\s|$)/", $text) >= 3;
}

/* Records a candidate unless its line opts out - $lines[0] is line $lines_first. */
function i18nCheckHardcoded(array &$hardcoded, array $lines, int $lines_first, string $file, int $line, string $text): void {
    if (strpos($lines[$line - $lines_first] ?? '', 'i18n-ignore') !== false) {
        return;
    }
    $text = trim(preg_replace('/\s+/', ' ', $text));
    $hardcoded["$file:$line"] = (isset($hardcoded["$file:$line"]) ? $hardcoded["$file:$line"] . ' | ' : '') . mb_substr($text, 0, 100);
}

/* Fixed text in a piece of HTML that starts on $first_line: between tags, and in the attributes people read. */
function i18nCheckHardcodedHtml(string $html, array $lines, int $lines_first, string $file, int $first_line, array &$hardcoded): void {
    // Comments and styles out, keeping line breaks
    $blank = fn($m) => preg_replace('/[^\n]/', ' ', $m[0]);
    $html = preg_replace_callback('#<!--.*?-->#s', $blank, $html);
    $html = preg_replace_callback('#<style\b.*?</style>#is', $blank, $html);

    $at = fn($offset) => $first_line + substr_count(substr($html, 0, $offset), "\n");
    if (preg_match_all('/(?:^|>)([^<>]+)(?=<|$)/', $html, $m, PREG_OFFSET_CAPTURE)) {
        foreach ($m[1] as [$text, $offset]) {
            if (i18nCheckIsProse($text, true)) {
                $lead = strlen($text) - strlen(ltrim($text));
                i18nCheckHardcoded($hardcoded, $lines, $lines_first, $file, $at($offset + $lead), $text);
            }
        }
    }
    if (preg_match_all('/\b(?:title|placeholder|aria-label|alt|data-bs-title|data-original-title)\s*=\s*"([^"<]*)"/i', $html, $m, PREG_OFFSET_CAPTURE)) {
        foreach ($m[1] as [$text, $offset]) {
            if (i18nCheckIsProse($text, true)) {
                i18nCheckHardcoded($hardcoded, $lines, $lines_first, $file, $at($offset), $text);
            }
        }
    }
}

/*
 * Splits inline HTML into its HTML and the script inside <script> - which may
 * have started in an earlier piece. Returns [[is_script, text, first_line], ...]
 * and leaves $in_script as it stands at the end.
 */
function i18nCheckSplitScript(string $html, int $first_line, bool &$in_script): array {
    $parts = [];
    $offset = 0;
    $length = strlen($html);
    while ($offset < $length) {
        $pattern = $in_script ? '#</script\s*>#i' : '#<script\b[^>]*>#i';
        if (!preg_match($pattern, $html, $m, PREG_OFFSET_CAPTURE, $offset)) {
            $parts[] = [$in_script, substr($html, $offset), $first_line + substr_count(substr($html, 0, $offset), "\n")];
            break;
        }
        $end = $in_script ? $m[0][1] : $m[0][1] + strlen($m[0][0]);
        $parts[] = [$in_script, substr($html, $offset, $end - $offset), $first_line + substr_count(substr($html, 0, $offset), "\n")];
        $offset = $in_script ? $m[0][1] : $end;
        $in_script = !$in_script;
    }
    return $parts;
}

/* The JS calls in a piece of script - comments taken out first - and, when looking for them, its fixed sentences. */
function i18nCheckJs(string $code, string $file, int $first_line, array &$calls, array &$not_literal, array &$literals, ?array &$hardcoded = null): void {
    // Blank out comments, keeping line breaks so line numbers still count
    $code = preg_replace_callback('#/\*.*?\*/#s', fn($m) => preg_replace('/[^\n]/', ' ', $m[0]), $code);
    $code = preg_replace('#(^|[\s;{}(])//[^\n]*#', '$1', $code);

    $string = "'(?:[^'\\\\\\n]|\\\\.)*'|\"(?:[^\"\\\\\\n]|\\\\.)*\"|`(?:[^`\\\\]|\\\\.)*`";
    if (preg_match_all("/(?:$string)/", $code, $all, PREG_OFFSET_CAPTURE)) {
        $lines = $hardcoded !== null ? explode("\n", $code) : [];
        foreach ($all[0] as [$literal, $offset]) {
            $value = stripcslashes(substr($literal, 1, -1));
            $literals[$value] = true;
            if ($hardcoded === null) {
                continue;
            }
            // Inside __( ... ) already, or a console / selector / attribute name
            $before = substr($code, max(0, $offset - 60), min(60, $offset));
            if (preg_match('/(?<![\w$.])__\s*\(\s*$/', $before) || preg_match('/(console\.\w+|querySelector(All)?|getElementById|getAttribute|setAttribute|removeAttribute|\$|closest|find|addEventListener|on|off|attr|data|prop|css|is|hasClass|addClass|removeClass|toggleClass|classList\.\w+|fetch|matches)\s*\(\s*$/', $before)) {
                continue;
            }
            $line = $first_line + substr_count(substr($code, 0, $offset), "\n");
            $text = $literal[0] === '`' ? preg_replace('/\$\{[^}]*\}/', '', $value) : $value;
            if (strpos($text, '<') !== false && strpos($text, '>') !== false) {
                i18nCheckHardcodedHtml($text, $lines, $first_line, $file, $line, $hardcoded);
            } elseif (i18nCheckIsProse($text, false)) {
                i18nCheckHardcoded($hardcoded, $lines, $first_line, $file, $line, $text);
            }
        }
    }
    if (!preg_match_all('/(?<![\w$.])__\s*\(\s*(' . $string . ')?/', $code, $found, PREG_OFFSET_CAPTURE)) {
        return;
    }
    foreach ($found[0] as $i => $match) {
        $line = $first_line + substr_count(substr($code, 0, $match[1]), "\n");
        $where = "$file:$line";
        if (empty($found[1][$i][0]) || $found[1][$i][0][0] === '`') {
            $line_text = trim(strtok(substr($code, $match[1]), "\n"));
            if (strpos($line_text, 'i18n-dynamic') === false) {
                $not_literal[$where] = mb_substr($line_text, 0, 90);
            }
            continue;
        }
        $calls['js'][stripcslashes(substr($found[1][$i][0], 1, -1))][] = $where;
    }
}

/*
 * Calls whose string arguments are never shown to people - logs, SQL, headers,
 * comparisons and the like. A sentence passed to one of these isn't reported as
 * fixed text.
 */
function i18nCheckQuietCall(string $name): bool {
    static $quiet = ['die', 'exit', 'error_log', 'header', 'define', 'defined', 'setcookie', 'redirect', 'in_array', 'array_key_exists',
        'triggerCustomAction', 'escapeSql', 'getFieldById', 'appLog', 'logAudit', 'logApp', 'logHistory', 'logTicketHistory', 'logForward',
        'date', 'strtotime', 'date_create', 'mktime', 'number_format', 'json_decode', 'hash', 'hash_hmac', 'openssl_encrypt', 'openssl_decrypt',
        'mb_strpos', 'strpos', 'stripos', 'str_contains', 'str_starts_with', 'str_ends_with', 'explode', 'implode', 'trim', 'ltrim', 'rtrim',
        'array_search', 'settingsColumnExists', 'mysqli_query', 'mysqli_real_query', 'mysqli_prepare', 'function_exists', 'class_exists',
        'is_dir', 'is_file', 'file_exists', 'fopen', 'file_put_contents', 'file_get_contents', 'mkdir', 'unlink', 'curl_setopt', 'ini_set',
        'session_name', 'filter_var', 'checkdate', 'version_compare', 'cronJobLog', 'cronJobStop', 'cronLog', 'apiOutput', 'apiError'];
    return in_array($name, $quiet, true) || str_starts_with($name, 'preg_') || str_starts_with($name, 'mysqli_') || str_starts_with($name, 'log')
        || str_starts_with($name, 'curl_') || str_starts_with($name, 'enforce');
}

/* The PHP calls in a file, through the tokenizer - and the JS in its inline HTML. */
function i18nCheckPhp(string $code, string $file, array &$calls, array &$not_literal, array &$literals, ?array &$hardcoded = null): void {
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

    // For --hardcoded: the call each '(' opened, and whether the HTML is inside a <script>
    $stack = [];
    $in_script = false;

    for ($i = 0; $i < $count; $i++) {
        $token = $tokens[$i];
        if (!is_array($token)) {
            if ($hardcoded !== null) {
                if ($token === '(') {
                    $name = '';
                    $prev = $significant($i - 1, -1);
                    if ($prev >= 0 && is_array($tokens[$prev]) && in_array($tokens[$prev][0], [T_STRING, T_NAME_QUALIFIED, T_EXIT, T_ISSET, T_EMPTY, T_ARRAY], true)) {
                        $name = strtolower($tokens[$prev][1]) === 'exit' ? 'exit' : $tokens[$prev][1];
                    }
                    $stack[] = $name;
                } elseif ($token === ')') {
                    array_pop($stack);
                }
            }
            continue;
        }
        if ($token[0] === T_INLINE_HTML) {
            i18nCheckJs($token[1], $file, $token[2], $calls, $not_literal, $literals);
            if ($hardcoded !== null) {
                $no_calls = ['php' => [], 'js' => [], 'php_plural' => []];
                $no_literals = [];
                foreach (i18nCheckSplitScript($token[1], $token[2], $in_script) as [$is_script, $text, $first]) {
                    if ($is_script) {
                        i18nCheckJs($text, $file, $first, $no_calls, $no_literals, $no_literals, $hardcoded);
                    } else {
                        i18nCheckHardcodedHtml($text, $lines, 1, $file, $first, $hardcoded);
                    }
                }
            }
            continue;
        }
        if ($token[0] === T_CONSTANT_ENCAPSED_STRING || $token[0] === T_ENCAPSED_AND_WHITESPACE) {
            if ($token[0] === T_CONSTANT_ENCAPSED_STRING) {
                $literals[i18nCheckUnquote($token[1])] = true;
            }
            if ($hardcoded !== null && !array_intersect($stack, ['__', '_x', '_n']) && !($stack && i18nCheckQuietCall(end($stack)))) {
                $text = $token[0] === T_CONSTANT_ENCAPSED_STRING ? i18nCheckUnquote($token[1]) : $token[1];
                $next = $significant($i + 1, 1);
                $prev = $significant($i - 1, -1);
                $is_key = $next >= 0 && is_array($tokens[$next]) && $tokens[$next][0] === T_DOUBLE_ARROW;
                $compared = ($next >= 0 && is_array($tokens[$next]) && in_array($tokens[$next][0], [T_IS_EQUAL, T_IS_IDENTICAL, T_IS_NOT_EQUAL, T_IS_NOT_IDENTICAL], true))
                    || ($prev >= 0 && is_array($tokens[$prev]) && in_array($tokens[$prev][0], [T_IS_EQUAL, T_IS_IDENTICAL, T_IS_NOT_EQUAL, T_IS_NOT_IDENTICAL, T_CASE], true));
                $sql = preg_match('/\b(SELECT|INSERT INTO|UPDATE|DELETE FROM|WHERE|ORDER BY|LEFT JOIN|SET [a-z_]+ =)\b/', $text);
                if (!$is_key && !$compared && !$sql) {
                    if (strpos($text, '<') !== false && strpos($text, '>') !== false) {
                        i18nCheckHardcodedHtml($text, $lines, 1, $file, $token[2], $hardcoded);
                    } elseif (i18nCheckIsProse($text, $stack && end($stack) === 'flashAlert')) {
                        i18nCheckHardcoded($hardcoded, $lines, 1, $file, $token[2], $text);
                    }
                }
            }
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

/* Every PHP and JS file of ours under a path. */
function i18nCheckFiles(string $root, string $path, array $skip_dirs, array $skip_files): array {
    $full = $root . '/' . ltrim($path, '/');
    if (is_file($full)) {
        return [ltrim($path, '/')];
    }
    if (!is_dir($full)) {
        return [];
    }
    $files = [];
    $iterator = new RecursiveIteratorIterator(new RecursiveCallbackFilterIterator(
        new RecursiveDirectoryIterator($full, FilesystemIterator::SKIP_DOTS),
        function ($item) use ($root, $skip_dirs) {
            $relative = substr($item->getPathname(), strlen($root) + 1);
            return !($item->isDir() && (in_array(explode('/', $relative)[0], $skip_dirs, true) || $relative === 'js/vendor-lang'));
        }
    ));
    foreach ($iterator as $item) {
        $relative = substr($item->getPathname(), strlen($root) + 1);
        if (!in_array($relative, $skip_files, true) && in_array(strtolower($item->getExtension()), ['php', 'js'], true)) {
            $files[] = $relative;
        }
    }
    sort($files);
    return $files;
}

if ($hardcoded_paths !== null) {
    $files = [];
    foreach ($hardcoded_paths as $path) {
        $found = i18nCheckFiles($root, $path, $skip_dirs, $skip_files);
        if (!$found) {
            fwrite(STDERR, "Nothing to look at in $path\n");
        }
        $files = array_merge($files, $found);
    }
    foreach (array_unique($files) as $relative) {
        $code = file_get_contents("$root/$relative");
        if ($code === false) {
            continue;
        }
        if (str_ends_with($relative, '.php')) {
            i18nCheckPhp($code, $relative, $calls, $not_literal, $literals, $hardcoded);
        } else {
            i18nCheckJs($code, $relative, 1, $calls, $not_literal, $literals, $hardcoded);
        }
    }
    uksort($hardcoded, 'strnatcmp');
    foreach ($hardcoded as $where => $text) {
        echo "$where  $text\n";
    }
    echo count($hardcoded) . " line(s) with what looks like fixed text\n";
    exit(0);
}

foreach (i18nCheckFiles($root, '', $skip_dirs, $skip_files) as $relative) {
    $code = file_get_contents("$root/$relative");
    if ($code === false) {
        continue;
    }
    if (str_ends_with($relative, '.php')) {
        i18nCheckPhp($code, $relative, $calls, $not_literal, $literals);
    } elseif (strpos($code, '__') !== false) {
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

/* The HTML tags a text carries, in order - <strong>, </strong>, <a> - attributes left out. */
function i18nCheckTags(string $text): array {
    preg_match_all('#<(/?)([a-zA-Z][a-zA-Z0-9]*)\b[^>]*>#', $text, $m, PREG_SET_ORDER);
    return array_map(fn($tag) => '<' . $tag[1] . strtolower($tag[2]) . '>', $m);
}

/* What differs between a translation and its English, or '' when nothing does. */
function i18nCheckDiffers(string $source, string $translation): string {
    if (i18nCheckPlaceholders($translation) !== i18nCheckPlaceholders($source)) {
        return 'placeholders';
    }
    $source_tags = i18nCheckTags($source);
    $translation_tags = i18nCheckTags($translation);
    sort($source_tags);
    sort($translation_tags);
    return $source_tags !== $translation_tags ? 'tags' : '';
}

$dictionaries = [
    'php' => ['file' => "lang/$locale.php", 'entries' => i18nLoadFile('', $locale), 'files' => i18nFiles('', $locale)],
    'js' => ['file' => "lang/js/$locale.php", 'entries' => i18nLoadFile('js/', $locale), 'files' => i18nFiles('js/', $locale)],
];

$missing = [];
$placeholders = [];
$duplicates = [];
$unused = [];
$in_use = 0;
$translated = 0;

foreach (['php', 'js'] as $side) {
    // Which file each entry came from, and the same English given twice
    $from = [];
    foreach ($dictionaries[$side]['files'] as $path) {
        $entries = include $path;
        $relative = substr(realpath($path), strlen($root) + 1);
        foreach (is_array($entries) ? $entries : [] as $key => $translation) {
            if (isset($from[$key]) && $from[$key][1] !== $translation) {
                $duplicates[] = "'$key' in {$from[$key][0]} and $relative";
            }
            $from[$key] = [$relative, $translation];
        }
    }

    $entries = $dictionaries[$side]['entries'];
    $file = $dictionaries[$side]['file'];
    foreach ($calls[$side] as $key => $wheres) {
        $in_use++;
        if (!isset($entries[$key]) || $entries[$key] === '' || $entries[$key] === []) {
            $missing[] = [$wheres, $key, $side === 'php' ? "lang/$locale" : $file];
            continue;
        }
        $translated++;
        $where_from = $from[$key][0] ?? $file;
        $english = str_contains($key, '::') && !isset($calls['php_plural'][$key]) ? substr($key, strpos($key, '::') + 2) : $key;
        if ($side === 'php' && isset($calls['php_plural'][$key])) {
            $forms = $entries[$key];
            if (!is_array($forms) || count($forms) !== 2) {
                $placeholders[] = "'$key' in $where_from must be [one, other]";
                continue;
            }
            foreach (array_values($forms) as $index => $form) {
                $source = $index === 0 ? $key : $calls['php_plural'][$key];
                if ($what = i18nCheckDiffers($source, (string) $form)) {
                    $placeholders[] = "'$source' -> '$form' ($where_from, $what)";
                }
            }
        } elseif (is_string($entries[$key]) && ($what = i18nCheckDiffers($english, $entries[$key]))) {
            $placeholders[] = "'$english' -> '{$entries[$key]}' ($where_from, $what)";
        }
    }
    foreach ($entries as $key => $translation) {
        $english = str_contains($key, '::') ? substr($key, strpos($key, '::') + 2) : $key;
        if (!isset($calls[$side][$key]) && !isset($literals[$english])) {
            $unused[] = "'$key' (" . ($from[$key][0] ?? $file) . ")";
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
$section('Duplicates - the later file wins', $duplicates, fn($item) => $item);
$section('Not literal - check by hand, or mark the line i18n-dynamic once checked', $not_literal, fn($code, $where) => "$where  $code");
$section('Unused', $unused, fn($item) => $item);

exit(($missing || $placeholders) ? 1 : 0);
