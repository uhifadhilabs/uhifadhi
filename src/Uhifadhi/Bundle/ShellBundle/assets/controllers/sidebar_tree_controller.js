import { Controller } from '@hotwired/stimulus';

/*
 * The sidebar location tree. The server renders it already open at the
 * viewer's location and marks the row they are on; this controller owns
 * nothing but the folds they make themselves, and only until the next page.
 *
 * WHAT IS OPEN IS DERIVED, NEVER REMEMBERED (ruled 2026-09-22). Only the path
 * to the current row is open on load, decided in PHP from the matched route,
 * so every page of the app reads the same and nothing flashes open and folds
 * again. A fold made here is a look, not a state: it lasts the page and the
 * next navigation derives the tree afresh. Nothing is written anywhere.
 *
 * ONE HANDLER FOR EVERY DEPTH. The shell renders one row grammar at all five
 * levels — a row, then the group of its children directly after it — so folding
 * is one behaviour rather than five near-copies that have to be kept in step.
 *
 * Two invariants: a caret NEVER navigates (it lives inside the row's link, so
 * the click is stopped), and every fold is honest both ways, because the
 * children stay in the document.
 */
export default class extends Controller {
    connect() {
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
    }

    /* The tree can be taller than the sidebar's scroll region, so bring the row
       they are standing on into view — nearest, never centred, so a short tree
       does not jump. */
    #reveal() {
        this.element.querySelector('.on')?.scrollIntoView({ block: 'nearest' });
    }
}
