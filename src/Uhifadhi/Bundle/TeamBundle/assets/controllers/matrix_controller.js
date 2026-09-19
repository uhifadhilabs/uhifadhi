import { Controller } from '@hotwired/stimulus';

/*
 * THE MATRIX'S ONE ENHANCEMENT: SORTING, AND NOTHING ELSE.
 *
 * The server renders the whole matrix — every row, every shade, every word —
 * and this only reorders what is already on the page. Without JavaScript the
 * matrix is complete and in the provider's own order, which is an order
 * somebody chose; with it, a reader can ask "who is last on this column" and
 * get an answer without a round trip.
 *
 * A BAND IS NEVER BROKEN. Org-wide departments are ranked among org-wide ones
 * and an area's among that area's, because a rank across two kinds of
 * department is a rank of nothing — so the sort runs INSIDE each band and the
 * band rules stay where they are. This is the same rule the shades are
 * computed under on the server, and the two must not disagree.
 *
 * AN ABSENCE SORTS LAST, WHICHEVER WAY THE COLUMN IS TURNED. A cell nobody
 * published is not a nought: sorting it as one would put the departments that
 * published nothing at the top of "fewest vacancies", which is a lie the
 * table would tell silently.
 *
 * IT WRITES NOTHING — no fetch, no form, no state that outlives the page.
 */
export default class extends Controller {
    connect() {
        this.headers = Array.from(this.element.querySelectorAll('th.sortable'));
        this.onHeader = (event) => this.sortBy(event.currentTarget);
        this.onKey = (event) => {
            if (event.key === 'Enter' || event.key === ' ') {
                event.preventDefault();
                this.sortBy(event.currentTarget);
            }
        };

        this.headers.forEach((header) => {
            header.addEventListener('click', this.onHeader);
            header.addEventListener('keydown', this.onKey);
        });
    }

    disconnect() {
        this.headers.forEach((header) => {
            header.removeEventListener('click', this.onHeader);
            header.removeEventListener('keydown', this.onKey);
        });
    }

    sortBy(header) {
        const index = this.headers.indexOf(header);
        if (index < 0) {
            return;
        }

        // Clicking the column that is already sorted turns it over.
        const descending = header.getAttribute('aria-sort') !== 'descending';
        this.headers.forEach((other) => other.setAttribute('aria-sort', 'none'));
        header.setAttribute('aria-sort', descending ? 'descending' : 'ascending');

        const body = this.element.querySelector('tbody');
        if (!body) {
            return;
        }

        // One band at a time: a band rule and the rows under it, reordered
        // among themselves and put back where the band was.
        let band = [];
        const flush = () => {
            if (band.length === 0) {
                return;
            }

            band
                .sort((a, b) => this.compare(a, b, index, descending))
                .forEach((row) => body.appendChild(row));
            band = [];
        };

        Array.from(body.children).forEach((row) => {
            if (row.classList.contains('pfscope')) {
                flush();
                body.appendChild(row);

                return;
            }

            band.push(row);
        });
        flush();
    }

    compare(a, b, index, descending) {
        const left = this.readCell(a, index);
        const right = this.readCell(b, index);

        // The absences go to the end either way round.
        if (left === null && right === null) {
            return 0;
        }
        if (left === null) {
            return 1;
        }
        if (right === null) {
            return -1;
        }

        const order = typeof left === 'string' ? left.localeCompare(right) : left - right;

        return descending ? -order : order;
    }

    readCell(row, index) {
        const cell = row.children[index];
        const raw = cell ? cell.getAttribute('data-v') : null;
        if (raw === null) {
            return null;
        }

        const figure = Number(raw);

        return Number.isNaN(figure) ? raw : figure;
    }
}
