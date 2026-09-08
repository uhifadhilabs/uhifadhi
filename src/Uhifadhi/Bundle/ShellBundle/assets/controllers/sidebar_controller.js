import { Controller } from '@hotwired/stimulus';

/*
 * The collapsible left navigation: the sidebar shrinks to an icon-only rail,
 * reclaiming width for maps and wide tables. The choice is remembered.
 *
 * TWO CLASSES, ONE STATE, AND THE SECOND ONE IS THE INTERESTING ONE. `rail` on
 * the sidebar itself is what a reader expects; `shell-rail` on <html> is what
 * the document's inline pre-paint script can set, because <html> is the only
 * element that exists before the sidebar is parsed. Without it a remembered
 * rail is applied on connect — after the first frame — and a 236px sidebar
 * visibly jumps to 66px on every page load. The stylesheet draws the rail from
 * either, so this controller keeps both true and the page never flashes.
 */
export default class extends Controller {
    connect() {
        this.element.classList.toggle('rail', this.remembered());
        document.documentElement.classList.toggle('shell-rail', this.remembered());
    }

    toggle() {
        const rail = this.element.classList.toggle('rail');
        document.documentElement.classList.toggle('shell-rail', rail);
        try {
            localStorage.setItem('shell-sidebar', rail ? 'rail' : 'full');
        } catch (e) {
            // Storage unavailable — the toggle still works for this page view.
        }
    }

    remembered() {
        try {
            return localStorage.getItem('shell-sidebar') === 'rail';
        } catch (e) {
            // Storage unavailable — start expanded.
            return false;
        }
    }
}
