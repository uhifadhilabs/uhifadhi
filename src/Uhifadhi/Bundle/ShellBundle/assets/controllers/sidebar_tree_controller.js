import { Controller } from '@hotwired/stimulus';

/*
 * The sidebar location tree. The server renders it already open at the
 * viewer's location and marks the row they are on; this controller owns
 * nothing but the folds they make themselves.
 *
 * WHAT IS OPEN IS DERIVED, NOT REMEMBERED. Only the path to the current row is
 * open on a fresh load, and that is decided in PHP from the matched route, so
 * every page of the app reads the same and nothing flashes open and folds
 * again. A fold made HERE is the viewer's own, and it is kept for the tab's
 * session and no longer: sessionStorage, keyed by the row's place in the tree
 * (`data-nav-key`), so it follows them from page to page and dies with the tab.
 * Nothing is written to the account, and a key that matches no row on the next
 * page simply decides nothing.
 *
 * ONE HANDLER FOR EVERY DEPTH. The shell renders one row grammar at all five
 * levels — a row, then the group of its children directly after it — so folding
 * is one behaviour rather than five near-copies that have to be kept in step.
 *
 * Three invariants: a caret NEVER navigates (it lives inside the row's link, so
 * the click is stopped); every fold is honest both ways, because the children
 * stay in the document; and OPENING MEANS ONE RUNG, so opening a row forgets
 * where its own descendants were left.
 */
const SESSION_KEY = 'uhifadhi-nav-tree';

export default class extends Controller {
    connect() {
        this.#restore();
        this.#reveal();
    }

    fold(event) {
        event.preventDefault();
        event.stopPropagation();

        const row = event.currentTarget.parentElement;
        const group = row?.nextElementSibling;
        if (!group) {
            return;
        }

        const closed = group.classList.toggle('closed');
        row.classList.toggle('closed', closed);
        this.#remember(row.getAttribute('data-nav-key'), !closed);
    }

    /* The viewer's own folds, laid over the derived tree. A row they did not
       touch keeps what the server decided. */
    #restore() {
        const folds = this.#folds();

        this.element.querySelectorAll('[data-nav-key]').forEach((row) => {
            const state = folds[row.getAttribute('data-nav-key')];
            if (undefined === state) {
                return;
            }

            const group = row.nextElementSibling;
            if (group) {
                group.classList.toggle('closed', !state);
                row.classList.toggle('closed', !state);
            }
        });
    }

    /* The tree can be taller than the sidebar's scroll region, so bring the row
       they are standing on into view — nearest, never centred, so a short tree
       does not jump. */
    #reveal() {
        this.element.querySelector('.on')?.scrollIntoView({ block: 'nearest' });
    }

    #folds() {
        try {
            const stored = window.sessionStorage.getItem(SESSION_KEY);

            return stored ? JSON.parse(stored) : {};
        } catch (error) {
            /* Private mode, or a value somebody else wrote: the tree still
               works, it just forgets. */
            return {};
        }
    }

    #remember(key, open) {
        if (!key) {
            return;
        }

        const folds = this.#folds();
        if (open) {
            Object.keys(folds).forEach((held) => {
                if (held.startsWith(`${key}/`)) {
                    delete folds[held];
                }
            });
        }
        folds[key] = open;

        try {
            window.sessionStorage.setItem(SESSION_KEY, JSON.stringify(folds));
        } catch (error) {
            /* The fold simply lasts the page. */
        }
    }
}
