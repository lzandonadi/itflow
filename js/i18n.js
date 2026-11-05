/*
 * The front end's translations - __('Delete this ticket?') - from the dictionary
 * the page carries in <html data-itflow-i18n> (i18nHtmlAttributes(),
 * functions/i18n.php; entries in lang/js/<locale>.php).
 *
 * Same contract as PHP's __(): the key is the English and a missing entry shows
 * the English; with arguments, %s and %d fill in order, %1$s by position and %%
 * is a literal %; without any the text is left alone. What comes back is plain
 * text - put it in with textContent, or escape it, like any other text.
 *
 * Loaded in <head>, before every other project script, so any of them can call it.
 */
(function () {
    'use strict';

    var dictionary = {};
    try {
        dictionary = JSON.parse(document.documentElement.getAttribute('data-itflow-i18n') || '{}') || {};
    } catch (e) {
        dictionary = {};
    }

    function fill(text, args) {
        if (!args.length) {
            return text;
        }
        var next = 0;
        return text.replace(/%(?:(\d+)\$)?([sd%])/g, function (match, position, type) {
            if (type === '%') {
                return '%';
            }
            var value = position ? args[parseInt(position, 10) - 1] : args[next++];
            if (value === undefined) {
                return match;
            }
            return type === 'd' ? String(parseInt(value, 10)) : String(value);
        });
    }

    window.__ = function (text) {
        var args = Array.prototype.slice.call(arguments, 1);
        var entry = Object.prototype.hasOwnProperty.call(dictionary, text) ? dictionary[text] : null;
        var translated = (typeof entry === 'string' && entry !== '') ? entry : text;
        return fill(translated, args);
    };
})();
