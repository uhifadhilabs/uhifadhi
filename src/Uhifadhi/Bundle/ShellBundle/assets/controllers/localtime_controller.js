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
 *     <time datetime="2026-09-05T02:55:00+00:00">05 sep · 05:55</time>
 *
 * — which is correct and readable with no JavaScript, and carries no dependency
 * on the shell. The same template renders unchanged in a host that has no shell
 * at all; there it simply keeps its server-rendered UTC text. The coupling that
 * would break that host is exactly the coupling this design removes.
 *
 * THE SOURCE IS THE `datetime` ATTRIBUTE, NEVER THE TEXT. `Intl.DateTimeFormat`
 * with NO locale and NO `timeZone` option resolves to the reader's own locale
 * and zone — the one thing a server cannot know. An element may hint the shape
 * it wants with `data-localtime-format` ("datetime" | "date" | "time"); a tight
 * table cell that printed only "05:55" asks for "time" so it does not blow out
 * to a full date. Re-localising is idempotent — it always reads the machine
 * attribute, never the text it last wrote — and a WeakSet keeps it from redoing
 * work or chasing its own mutations.
 */
export default class extends Controller {
    connect() {
        this.done = new WeakSet();
        this.localizeAll();

        // Turbo swaps the body without a full load; async widgets add times
        // later. Both must localise too, or a navigated-to page reads in UTC.
        this.rescan = () => this.localizeAll();
        document.addEventListener('turbo:load', this.rescan);
        document.addEventListener('turbo:render', this.rescan);

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

        const instant = new Date(time.getAttribute('datetime'));
        if (Number.isNaN(instant.getTime())) {
            return;
        }

        try {
            time.textContent = new Intl.DateTimeFormat(undefined, this.options(time.dataset.localtimeFormat)).format(instant);
            this.done.add(time);
        } catch (e) {
            // Intl missing, or an option it rejects — the server's UTC text stays readable.
        }
    }

    /*
     * The shape a `<time>` asks for. The options are given; the locale, the
     * zone and the separators are the reader's, because that is what
     * `Intl.DateTimeFormat(undefined, …)` resolves them to.
     */
    options(format) {
        switch (format) {
            case 'date':
                return { year: 'numeric', month: 'short', day: 'numeric' };
            case 'time':
                return { hour: '2-digit', minute: '2-digit' };
            case 'datetime':
            default:
                return { year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' };
        }
    }
}
