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

    /*
     * The third-party widgets' own words in the interface language - each
     * function returns the options to hand the widget. In English they return
     * nothing to add, so the widgets keep their defaults exactly. Month and day
     * names come from the browser (Intl) in the page's language.
     */
    var lang = document.documentElement.getAttribute('lang') || 'en-US';
    var english = lang === 'en-US';

    function names(options, count, from) {
        var list = [];
        for (var i = 0; i < count; i++) {
            var date = from(i);
            var name = new Intl.DateTimeFormat(lang, options).format(date).replace(/\.$/, '');
            list.push(name.charAt(0).toLocaleUpperCase(lang) + name.slice(1));
        }
        return list;
    }

    // Widget languages ITFlow carries a file for (js/vendor-lang/)
    var tinyMceFiles = {'pt-BR': '/js/vendor-lang/tinymce/pt-BR.js'};

    window.itflowI18n = {
        lang: lang,

        // DataTables: new DataTable(el, Object.assign({...}, itflowI18n.dataTables()))
        dataTables: function () {
            if (english) {
                return {};
            }
            return {
                language: {
                    aria: {
                        orderable: __(': Activate to sort'),
                        orderableRemove: __(': Activate to remove sorting'),
                        orderableReverse: __(': Activate to invert sorting'),
                        paginate: {first: __('First'), last: __('Last'), next: __('Next'), previous: __('Previous')}
                    },
                    emptyTable: __('No data available in table'),
                    entries: {_: __('entries'), 1: __('entry')},
                    info: __('Showing _START_ to _END_ of _TOTAL_ _ENTRIES-TOTAL_'),
                    infoEmpty: __('Showing 0 to 0 of 0 _ENTRIES-TOTAL_'),
                    infoFiltered: __('(filtered from _MAX_ total _ENTRIES-MAX_)'),
                    lengthLabels: {'-1': __('All')},
                    lengthMenu: __('_MENU_ _ENTRIES_ per page'),
                    loadingRecords: __('Loading...'),
                    search: __('Search:'),
                    zeroRecords: __('No matching records found')
                }
            };
        },

        // flatpickr: once, before the first picker - flatpickr.localize() sets every picker's default
        localizeFlatpickr: function () {
            if (english || typeof flatpickr === 'undefined' || flatpickr.itflowLocalized) {
                return;
            }
            flatpickr.itflowLocalized = true;
            var sunday = function (i) { return new Date(2026, 0, 4 + i); };
            var month = function (i) { return new Date(2026, i, 1); };
            flatpickr.localize({
                weekdays: {shorthand: names({weekday: 'short'}, 7, sunday), longhand: names({weekday: 'long'}, 7, sunday)},
                months: {shorthand: names({month: 'short'}, 12, month), longhand: names({month: 'long'}, 12, month)},
                rangeSeparator: __(' to '),
                weekAbbreviation: __('Wk'),
                scrollTitle: __('Scroll to increment'),
                toggleTitle: __('Click to toggle'),
                yearAriaLabel: __('Year'),
                monthAriaLabel: __('Month'),
                hourAriaLabel: __('Hour'),
                minuteAriaLabel: __('Minute'),
                ordinal: function () { return ''; }
            });
        },

        // FullCalendar: a locale of its own - dates follow it through Intl, these are its buttons and labels
        fullCalendar: function () {
            if (english) {
                return {};
            }
            return {
                locale: {
                    code: lang.toLowerCase(),
                    week: {dow: 0, doy: 4},
                    todayText: __('Today'),
                    prevText: __('Prev'),
                    nextText: __('Next'),
                    prevYearText: __('Prev year'),
                    nextYearText: __('Next year'),
                    yearText: __('Year'),
                    monthText: __('Month'),
                    weekTextLong: __('Week'),
                    weekTextShort: __('Wk'),
                    dayText: __('Day'),
                    listText: __('List'),
                    closeHint: __('Close'),
                    eventsHint: __('Events'),
                    allDayText: __('All-day'),
                    timedText: __('Timed'),
                    moreLinkText: __('more'),
                    noEventsText: __('No events to display'),
                    todayHint: function (unitText, unit) {
                        return unit === 'day' ? __('Today') : __('This %s', unitText);
                    },
                    prevHint: __('Previous $0'),
                    nextHint: __('Next $0'),
                    viewHint: __('$0 view'),
                    viewChangeHint: __('Change view'),
                    navLinkHint: __('Go to $0'),
                    moreLinkHint: function (count) {
                        return count === 1 ? __('Show 1 more event') : __('Show %d more events', count);
                    }
                }
            };
        },

        // intl-tel-input: country names through Intl, and its own words
        phoneInput: function () {
            if (english) {
                return {};
            }
            return {
                countryNameLocale: lang,
                uiTranslations: {
                    selectedCountryAriaLabel: __('Change country for phone number, currently selected ${countryName} (${dialCode})'),
                    noCountrySelected: __('Select country for phone number'),
                    countryListAriaLabel: __('List of countries'),
                    searchPlaceholder: __('Search'),
                    clearSearchAriaLabel: __('Clear search'),
                    searchEmptyState: __('No results found'),
                    searchSummaryAria: function (count) {
                        return count === 0 ? __('No results found') : (count === 1 ? __('1 result found') : __('%d results found', count));
                    }
                }
            };
        },

        // TinyMCE: its language pack, when ITFlow has one for the interface language
        tinyMce: function () {
            if (english || !tinyMceFiles[lang]) {
                return {};
            }
            return {language: lang, language_url: tinyMceFiles[lang]};
        }
    };
})();
