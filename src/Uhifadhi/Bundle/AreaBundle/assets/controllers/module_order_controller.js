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
 * THE ORDER AN AREA SHOWS ITS MODULES IN, SET BY DRAGGING.
 *
 * TWO LISTS, ONE ORDER. The shop draws the active set twice — as pills across the
 * top and as detailed rows below — and dragging in either has to move both, or
 * the page would show a person two different answers to the same question. They
 * are matched by SLUG, which is also what the reorder route takes, so nothing
 * here knows an assignment's identity.
 *
 * THE ONLY WRITE THAT NEEDS SCRIPTING, and the only one that does. Switching a
 * module on or off is a real form with a real submit button and works with
 * scripting off; a shop whose only way to park a module was a drag would be
 * unreachable from a keyboard. What is lost without this file is the ordering,
 * which is a preference — not the composition, which is the point of the screen.
 *
 * POSTED, NOT QUEUED. The new order goes to the server as soon as a drag ends,
 * because the alternative is a "Save" button somebody leaves the page without
 * pressing. The response is ignored: the DOM already shows the outcome, and
 * re-rendering from a reply would fight a drag that had already started.
 */
export default class extends Controller {
    static targets = ['list', 'row', 'handle'];
    static values = { url: String, token: String };

    connect() {
        this.dragging = null;
        this.draggedList = null;
        this.listTargets.forEach((list) => this.arm(list));
    }

    arm(list) {
        list.querySelectorAll('[data-slug]').forEach((row) => {
            row.draggable = true;
            row.addEventListener('dragstart', () => this.start(row, list));
            row.addEventListener('dragend', () => this.end(row));
            row.addEventListener('dragover', (event) => this.moveOver(event, list, row));
        });
    }

    start(row, list) {
        this.dragging = row;
        this.draggedList = list;
        row.classList.add('dragging');
    }

    end(row) {
        row.classList.remove('dragging');
        this.dragging = null;
        this.mirror();
        this.persist();
        this.draggedList = null;
    }

    moveOver(event, list, row) {
        event.preventDefault();
        if (!this.dragging || this.dragging === row || this.dragging.parentElement !== list) {
            return;
        }
        const box = row.getBoundingClientRect();
        const after = box.top + row.offsetHeight / 2 < event.clientY;
        list.insertBefore(this.dragging, after ? row.nextSibling : row);
    }

    /* Every list that was NOT dragged in is re-sorted to match the one that was. */
    mirror() {
        const order = this.order();
        this.listTargets
            .filter((list) => list !== this.draggedList)
            .forEach((list) => {
                order.forEach((slug) => {
                    const row = list.querySelector(`[data-slug="${slug}"]`);
                    if (row) {
                        list.appendChild(row);
                    }
                });
            });
    }

    /*
     * THE ORDER IS THE LIST THE DRAG HAPPENED IN. Both lists are `list` targets,
     * so a fixed one — the first in the template — would answer for its own drags
     * only and overwrite every drag made in the other.
     */
    order() {
        return this.draggedList
            ? Array.from(this.draggedList.querySelectorAll('[data-slug]')).map((row) => row.dataset.slug)
            : [];
    }

    persist() {
        if (!this.hasUrlValue) {
            return;
        }
        const body = new URLSearchParams();
        body.append('_token', this.tokenValue);
        this.order().forEach((slug) => body.append('order[]', slug));

        fetch(this.urlValue, { method: 'POST', body, credentials: 'same-origin' });
    }
}
