/*
 * This file is part of the Uhifadhi core.
 *
 * (c) Ezekiel Mjema <https://github.com/eemjema>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

import { Controller } from '@hotwired/stimulus';

/*
 * THE REGISTER'S WORKING CONTROLS — search, the filter pills, the sort, over the
 * rows that are already on the page.
 *
 * ONE CONTROLLER, TWO SHAPES. The wall of workspaces is a grid of cards with a
 * fixed last-activity sort and no column headers; the register preview is a table
 * with sortable headers; the map preview's dock is a searchable list. All three
 * are the SAME rows narrowed the same way, so all three wear this one controller.
 * A shape simply brings the parts it has: no [data-sortcol] header means the sort
 * stays fixed at last activity, exactly as the card gallery wants.
 *
 * CLIENT-SIDE ON PURPOSE. An installation's register is tens of areas, not
 * thousands: a round trip per keystroke would be slower than filtering in place
 * and would lose the typed text on every reload. If a deployment ever has
 * hundreds, this becomes a server-side query and the markup does not change.
 *
 * A ROW THAT DOES NOT MATCH LEAVES; IT IS NEVER GREYED. A dimmed row still reads
 * as data — it says "this area is somehow lesser" rather than "not in your search".
 *
 * NOTHING HERE KNOWS WHAT AN AREA IS. It searches, filters and sorts by the data
 * attributes the template stamped on each row, so a figure added to a row needs
 * no change in this file.
 */
export default class extends Controller {
    static targets = ['search', 'list', 'count', 'sortlabel'];

    connect() {
        this.filterName = 'all';
        // The default sort is last activity, descending — the card gallery's fixed
        // order and the table's initial sorted column both.
        this.sortKey = 'activity';
        this.sortDir = -1;

        this.searchTarget?.addEventListener('input', () => this.apply());

        // The whole row opens its area where the template made it clickable (the
        // table rows carry data-href; the cards and dock rows are real anchors and
        // need nothing here).
        for (const row of this.cards()) {
            if (row.dataset.href) {
                row.addEventListener('click', (event) => {
                    if (event.target.closest('a')) {
                        return;
                    }
                    window.location.href = row.dataset.href;
                });
            }
        }

        this.apply();
    }

    filter(event) {
        event.preventDefault();

        const pill = event.currentTarget;
        this.filterName = pill.dataset.filter || 'all';

        pill.parentElement
            .querySelectorAll('a')
            .forEach((a) => a.classList.toggle('on', a === pill));

        this.apply();
    }

    /** A sortable column header — click to sort by it, click again to reverse. */
    sort(event) {
        const th = event.currentTarget;
        const key = th.dataset.sortcol;
        if (!key) {
            return;
        }

        if (key === this.sortKey) {
            this.sortDir = -this.sortDir;
        } else {
            this.sortKey = key;
            this.sortDir = -1;
        }

        const arrow = -1 === this.sortDir ? '↓' : '↑';
        this.element.querySelectorAll('[data-sortcol]').forEach((header) => {
            const on = header === th;
            header.classList.toggle('sorted', on);
            const mark = header.querySelector('.sarrow');
            if (mark) {
                mark.innerHTML = on ? arrow : '↓';
            }
        });
        if (this.hasSortlabelTarget) {
            this.sortlabelTarget.innerHTML = `sort: ${th.dataset.label || key} ${arrow}`;
        }

        this.apply();
    }

    apply() {
        const rows = [...this.cards()];
        const needle = (this.hasSearchTarget ? this.searchTarget.value : '').trim().toLowerCase();

        let shown = 0;
        for (const row of rows) {
            const matchesText = '' === needle || (row.dataset.name || '').includes(needle);
            const visible = matchesText && this.matchesFilter(row);

            row.hidden = !visible;
            if (visible) {
                shown += 1;
            }
        }

        const list = this.hasListTarget ? this.listTarget : rows[0]?.parentElement;
        if (list) {
            rows
                .sort((a, b) => this.compare(a, b))
                .forEach((row) => list.appendChild(row));
            // The add tile has no sort key and stays last, whatever the order.
            const tail = list.querySelector('[data-tail]');
            if (tail) {
                list.appendChild(tail);
            }
        }

        if (this.hasCountTarget) {
            this.countTarget.textContent = String(shown);
        }
    }

    compare(a, b) {
        if ('name' === this.sortKey) {
            return this.sortDir * (a.dataset.name || '').localeCompare(b.dataset.name || '');
        }

        return this.sortDir * (this.num(a) - this.num(b));
    }

    /** A row's value for the current sort key, as a number — "14/22" reads as 14. */
    num(row) {
        const raw = row.dataset[this.sortKey];
        const value = parseFloat(raw);

        return Number.isFinite(value) ? value : 0;
    }

    matchesFilter(row) {
        switch (this.filterName) {
            case 'all':
                return true;
            case 'setup':
                return '0' === row.dataset.live;
            case 'live':
                return '1' === row.dataset.live;
            case 'alerts':
                return '1' === row.dataset.alerts;
            default:
                return true;
        }
    }

    cards() {
        return this.hasListTarget ? this.listTarget.querySelectorAll('[data-row]') : [];
    }
}
