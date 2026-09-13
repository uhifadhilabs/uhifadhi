import { Controller } from '@hotwired/stimulus';

/*
 * Every stored instant on the page, read in the READER'S OWN timezone — in the
 * browser, by the frame.
 *
 * WHY THE FRAME DOES THIS, AND NOT EACH ELEMENT. A module records a moment,
 * stores it as UTC and prints it once, server-side, in whatever single zone the
 * server runs in. So a ranger in the field and an analyst three timezones away
 * read the same printed wall-clock and one of them reads it wrong. The fix is
 * identical for every module, so the shell mounts THIS ONE controller on the
 * document it owns and it localises every `<time datetime>` on the page.
 *
 * A MODULE NAMES NO CONTROLLER. That is the whole point of doing it here. A
 * module's template emits only semantic markup —
 *
 *     <time datetime="2026-09-05T02:55:00+00:00" data-localtime-format="stamp">5 sep · 02:55</time>
 *
 * — which is correct and readable with no JavaScript, and carries no dependency
 * on the shell. The same template renders unchanged in a host that has no shell
 * at all; there it simply keeps its server-rendered UTC text. The coupling that
 * would break that host is exactly the coupling this design removes.
 *
 * THE SOURCE IS THE `datetime` ATTRIBUTE, NEVER THE TEXT. `Intl.DateTimeFormat`
 * with NO locale and NO `timeZone` option resolves to the reader's own locale
 * and zone — the one thing a server cannot know. Re-localising is idempotent —
 * it always reads the machine attribute, never the text it last wrote — and a
 * WeakSet keeps it from redoing work or chasing its own mutations.
 *
 * THE SHAPE IS `data-localtime-format`, AND THE COMPACT ONES ARE WHY MODULES
 * USE THIS AT ALL. The three shapes this shipped with are Intl's own readings —
 * "Sep 7, 2026, 11:00 PM" — and every design in the product draws a compact
 * monospace stamp instead. A module asked to choose between the right zone and
 * the right typography chose the typography and printed server-side, which is
 * the defect this controller exists to end. So the compact shapes live here:
 * `stamp`, `daystamp`, `clock`, `clocks`, `day`, `daylong`. They are assembled
 * from Intl PARTS rather than from a format string, because the month and
 * weekday NAMES are the locale's to produce while the order, the separators and
 * the case are the house's.
 */

const Shape = {
    /* Intl's own three, unchanged: whatever the reader's locale writes. */
    DATE: 'date',
    TIME: 'time',
    DATETIME: 'datetime',
    /* The house's six, spelled exactly as the designs draw them. */
    STAMP: 'stamp',
    DAYSTAMP: 'daystamp',
    CLOCK: 'clock',
    CLOCKS: 'clocks',
    DAY: 'day',
    DAYLONG: 'daylong',
};

/* The six the shell assembles itself. A shape not in here — including one a
 * template misspelt — falls through to Intl's own reading rather than to
 * nothing, so a typo is a verbose cell and never an empty one. */
const COMPACT = [Shape.STAMP, Shape.DAYSTAMP, Shape.CLOCK, Shape.CLOCKS, Shape.DAY, Shape.DAYLONG];

/* The separator between a date and a clock in every compact shape: a middle dot
 * with a space on either side. One constant, so six shapes cannot disagree. */
const DOT = ' · ';

/* WHAT IS ASKED OF INTL, AND WHY IT IS ASKED ALL AT ONCE. Two formatters serve
 * every compact shape — one for the calendar names, one for the clock — and each
 * shape picks the parts it wants out of them. `hourCycle: 'h23'` because the
 * product reads a 24-hour clock and a locale does not get to turn it into
 * "1:49 PM"; note it is NOT `hour12: false`, which some engines answer with a
 * 24-hour cycle that writes midnight as "24:00". */
const CALENDAR = { weekday: 'short', day: 'numeric', month: 'short', year: 'numeric' };
const CLOCK = { hour: '2-digit', minute: '2-digit', second: '2-digit', hourCycle: 'h23' };

export default class extends Controller {
    connect() {
        this.done = new WeakSet();
        this.localizeAll();

        // Turbo swaps the body without a full load; async widgets add times
        // later. Both must localise too, or a navigated-to page reads in UTC.
        this.rescan = () => this.localizeAll();
        document.addEventListener('turbo:load', this.rescan);
        document.addEventListener('turbo:render', this.rescan);

        // AND EVERYTHING THAT ARRIVES WITHOUT A TURBO EVENT: a filter that
        // swaps a region, a plate that goes fullscreen and redraws its chrome,
        // a canvas that clones its cards out of `<template>` elements, a tail a
        // fetch fills. A `<time>` inside a template's content is inert until it
        // is cloned INTO the document, and the clone is an insertion like any
        // other, so this sees it then — which is the only moment it could be
        // localised anyway.
        this.observer = new MutationObserver((mutations) => {
            for (const mutation of mutations) {
                for (const node of mutation.addedNodes) {
                    if (node.nodeType !== Node.ELEMENT_NODE) {
                        continue;
                    }
                    if (node.matches?.('time[datetime]')) {
                        this.localize(node);
                    }
                    node.querySelectorAll?.('time[datetime]').forEach((time) => this.localize(time));
                }
            }
        });
        this.observer.observe(this.element, { childList: true, subtree: true });
    }

    disconnect() {
        document.removeEventListener('turbo:load', this.rescan);
        document.removeEventListener('turbo:render', this.rescan);
        this.observer?.disconnect();
    }

    localizeAll() {
        this.element.querySelectorAll('time[datetime]').forEach((time) => this.localize(time));
    }

    localize(time) {
        if (this.done.has(time)) {
            return;
        }

        const machine = time.getAttribute('datetime');

        // A CALENDAR DAY IS NOT AN INSTANT. `datetime="2026-08-19"` names a day
        // — a day key, a calendar cell, a date a form collected — and a day is
        // the same day everywhere. Localising it would move it: read as an
        // instant it is midnight UTC, which west of Greenwich is the evening
        // before, so a reader in Lima would be shown the 18th. Only a value
        // carrying a time part is an instant, so only that is rewritten.
        if (!machine?.includes('T')) {
            return;
        }

        const instant = new Date(machine);
        if (Number.isNaN(instant.getTime())) {
            return;
        }

        try {
            const shape = time.dataset.localtimeFormat;

            time.textContent = this.compact(instant, shape)
                ?? new Intl.DateTimeFormat(undefined, this.options(shape)).format(instant);
            this.done.add(time);
        } catch (e) {
            // Intl missing, or an option it rejects — the server's UTC text stays readable.
        }
    }

    /*
     * THE HOUSE SHAPES, ASSEMBLED. The names come out of Intl in the page's
     * locale and are lower-cased; the order, the spaces and the dot are written
     * here, because a design draws "12 sep · 13:49" whatever the reader's locale
     * would have punctuated. Returns null for a shape that is not one of these,
     * which is how the three Intl readings — and an absent or misspelt
     * attribute — fall through to `options()`.
     */
    compact(instant, format) {
        if (!COMPACT.includes(format)) {
            return null;
        }

        const calendar = this.parts(instant, CALENDAR);
        const clock = this.parts(instant, CLOCK);
        // "12 sep": the day as a number, never padded, then the locale's own
        // short month. "13:49": a 24-hour clock, both fields padded.
        const date = calendar.day + ' ' + calendar.month;
        const time = clock.hour + ':' + clock.minute;

        switch (format) {
            case Shape.STAMP:
                return date + DOT + time;
            case Shape.DAYSTAMP:
                return calendar.weekday + ' ' + date + DOT + time;
            case Shape.CLOCK:
                return time;
            case Shape.CLOCKS:
                return time + ':' + clock.second;
            case Shape.DAY:
                return date + ' ' + calendar.year;
            default:
                return calendar.weekday + ' ' + date + ' ' + calendar.year;
        }
    }

    /*
     * One formatter's output as a plain object keyed by part type, with every
     * value lower-cased. Digits are unaffected by the lower-casing; the month
     * and weekday names are exactly what it is for.
     */
    parts(instant, options) {
        const fields = {};
        for (const part of new Intl.DateTimeFormat(undefined, options).formatToParts(instant)) {
            fields[part.type] = part.value.toLocaleLowerCase();
        }

        return fields;
    }

    /*
     * Intl's own three readings, unchanged. The options are given; the locale,
     * the zone and the separators are the reader's, because that is what
     * `Intl.DateTimeFormat(undefined, …)` resolves them to.
     */
    options(format) {
        switch (format) {
            case Shape.DATE:
                return { year: 'numeric', month: 'short', day: 'numeric' };
            case Shape.TIME:
                return { hour: '2-digit', minute: '2-digit' };
            case Shape.DATETIME:
            default:
                return { year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' };
        }
    }
}
