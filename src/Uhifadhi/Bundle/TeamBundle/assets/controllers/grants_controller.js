import { Controller } from '@hotwired/stimulus';

/*
 * THE GRANTS EDITOR — one module's bulk controls, and the change preview.
 *
 * TWO SCOPES, ONE CONTROLLER. It is mounted on each `<details class="pmfg">`,
 * where `all`/`none` tick that group's boxes and nothing else's; and once on
 * the save bar, where `changed` rewrites the bar's left text. A group's
 * element has no preview target and the bar has no boxes, so each instance
 * does only its own half.
 *
 * IT WRITES NOTHING. Ticking a box here is the same event as ticking it by
 * hand: a change to the form, and no more. Nothing reaches the database until
 * Save is pressed, which is the sentence the bar carries and the reason this
 * editor has no surprises in it. There is no fetch in this file on purpose.
 *
 * "GRANT ALL" CAN NEVER GRANT SOMETHING THAT MEANS NOTHING, because there is
 * no box for a verb a concern does not declare — the server drew a cell only
 * where the declaration put one, so ticking every box in a group ticks
 * exactly the pairs that group offers.
 *
 * A DISABLED BOX IS LEFT ALONE. It is a pair beyond a bounded administrator's
 * own position (§5.6(c)); bulk-ticking it would be the escalation the server
 * then refuses, so the refusal is honoured here rather than reported back.
 *
 * THE PREVIEW IS FRAGMENTS, NOT PROSE: "2 changes · + record on Check-ins ·
 * − export on Live positions · reaches 6 holders". Each box carries what it
 * WAS in `data-was`, so the preview is a comparison with the saved state
 * rather than a count of clicks — pressing a box twice is no change, and the
 * bar says so.
 */
export default class extends Controller {
    static targets = ['group', 'box', 'preview'];
    static values = { reach: Number };

    /** Every box in this group that the reader is allowed to touch. */
    #boxes() {
        return [...this.element.querySelectorAll('input[type="checkbox"]:not(:disabled)')];
    }

    all(event) {
        event.preventDefault();
        this.#set(true);
    }

    none(event) {
        event.preventDefault();
        this.#set(false);
    }

    #set(on) {
        this.#boxes().forEach((box) => {
            box.checked = on;
            box.dispatchEvent(new Event('change', { bubbles: true }));
        });
    }

    /* The bar listens for every box on the form, wherever it was ticked. */
    connect() {
        if (this.hasPreviewTarget) {
            this.#form = this.element.closest('form');
            this.#redraw();
            this.#form?.addEventListener('change', () => this.#redraw());
        }
    }

    #form = null;

    changed() {
        /* The bubbled `change` above is what redraws; this keeps the action
           attribute honest for a box outside a form. */
        this.#redraw();
    }

    #redraw() {
        if (!this.hasPreviewTarget || !this.#form) {
            return;
        }

        const moved = [...this.#form.querySelectorAll('input[type="checkbox"][data-was]')]
            .filter((box) => box.checked !== (box.dataset.was === '1'));

        const fragment = (box) =>
            `${box.checked ? '+' : '−'} ${box.dataset.verb} on ${box.dataset.concern}`;

        const reach = this.hasReachValue ? this.reachValue : 0;
        const reachText = `reaches <b>${reach}</b> holder${reach === 1 ? '' : 's'}`;

        if (moved.length === 0) {
            this.previewTarget.innerHTML = `<b>no changes</b><span class="to">${reachText}</span>`;

            return;
        }

        const added = moved.filter((box) => box.checked).length;
        const removed = moved.length - added;

        this.previewTarget.innerHTML = [
            `<b>${moved.length} change${moved.length === 1 ? '' : 's'}</b>`,
            ...moved.map((box) => `<span class="${box.checked ? 'add' : 'rem'} nm">${fragment(box)}</span>`),
            `<span class="cnt" title="${moved.map(fragment).join(' · ')}">+${added} · −${removed}</span>`,
            `<span class="to">${reachText}</span>`,
        ].join('');
    }
}
