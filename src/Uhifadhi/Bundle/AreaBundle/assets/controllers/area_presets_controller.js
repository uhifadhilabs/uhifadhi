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
 * THE AREAS-INDEX WIDGET LIBRARY — adopt-only, and self-contained.
 *
 * Five whole-page layouts ship with the areas surface; each is embedded on the
 * page as a preview view. A preset card PREVIEWS its layout inline; Apply adopts
 * it as the landing. This mirrors the shared widget-framework's preset component
 * in its adopt-only form — no "my presets", no compose-your-own, no link out to
 * the design scratchboard the layouts were graduated from.
 *
 * THE PREVIEW IS THIS CONTROLLER'S, THE ADOPTION IS THE SERVER'S. Swapping the
 * inline layout is a local matter and nothing else needs to know. Applying one is
 * a stored widget preference, because the LANDING has to read it back — so Apply
 * submits the layout's own form and the page comes back from the server with the
 * new layout already marked Active. Nothing is remembered in the browser: a note
 * kept here would be a second answer to "which layout is on", and the register
 * would go on drawing the first one.
 */

const ICON_CHECK =
    '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>';
const ICON_EYE =
    '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.1" stroke-linecap="round" stroke-linejoin="round"><path d="M2.062 12.348a1 1 0 0 1 0-.696 10.75 10.75 0 0 1 19.876 0 1 1 0 0 1 0 .696 10.75 10.75 0 0 1-19.876 0"/><circle cx="12" cy="12" r="3"/></svg>';
const ICON_LAYOUT =
    '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="7" x="3" y="3" rx="1"/><rect width="9" height="7" x="3" y="14" rx="1"/><rect width="5" height="7" x="16" y="14" rx="1"/></svg>';
const ICON_ARROW =
    '<svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>';

export default class extends Controller {
    static targets = ['card', 'bar', 'view', 'applyForm'];
    static values = { active: { type: String, default: 'wall' } };

    connect() {
        // WHICH LAYOUT IS ON IS THE SERVER'S ANSWER, rendered into the element.
        // Reading it from anywhere else is how the library and the landing come to
        // disagree.
        this.active = this.activeValue;
        this.selected = this.active;

        this.render();
    }

    /** A card was clicked — look at its layout. */
    select(event) {
        this.selected = event.currentTarget.dataset.view;
        this.render();
        if (this.selected !== this.active && this.hasBarTarget) {
            this.barTarget.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
        }
    }

    /** A card is a button — Enter or Space activates it. */
    key(event) {
        if ('Enter' === event.key || ' ' === event.key) {
            event.preventDefault();
            event.currentTarget.click();
        }
    }

    /**
     * Apply adopts the previewed layout — by submitting that layout's own form, so
     * the write is the server's and the page returns with the new layout Active.
     * The forms are on the page for a no-script viewer anyway; this only presses
     * the right one.
     */
    apply() {
        if (!this.hasApplyFormTarget) {
            return;
        }
        const form = this.applyFormTarget;
        form.action = form.dataset.urlTemplate.replace(form.dataset.urlPlaceholder, this.selected);
        form.requestSubmit();
    }

    cancel() {
        this.selected = this.active;
        this.render();
    }

    render() {
        this.paintCards();
        this.paintBar();
        this.showView();
    }

    paintCards() {
        for (const card of this.cardTargets) {
            const view = card.dataset.view;
            const isActive = view === this.active;
            const isSelected = view === this.selected && !isActive;

            card.classList.toggle('w-preset-active', isActive);
            card.classList.toggle('w-preset-on', isSelected);
            card.setAttribute('aria-pressed', isActive || isSelected ? 'true' : 'false');

            const top = card.querySelector('.w-presettop');
            if (top) {
                let flag = '';
                if (isActive) {
                    flag = `<span class="w-presetflag w-presetflag-active">${ICON_CHECK} Active</span>`;
                } else if (isSelected) {
                    flag = `<span class="w-presetflag w-presetflag-sel">${ICON_EYE} Previewing</span>`;
                }
                top.innerHTML = `<span class="w-presetname">${card.dataset.name}</span>${flag}`;
            }

            const go = card.querySelector('.w-presetgo');
            if (go) {
                go.innerHTML = `${isActive ? 'On your dashboard' : 'Preview'} ${ICON_ARROW}`;
            }
        }
    }

    paintBar() {
        if (!this.hasBarTarget) {
            return;
        }
        const bar = this.barTarget;

        if (this.selected === this.active) {
            bar.className = 'w-previewbar w-previewbar-active';
            bar.innerHTML = `<span class="w-barstatus">${ICON_CHECK}<span>The areas landing shows <b>${this.nameOf(this.active)}</b>. Preview any design above to see it whole, then apply it to make it the landing.</span></span>`;

            return;
        }

        bar.className = 'w-previewbar';
        bar.innerHTML =
            `<span class="w-barstatus">${ICON_EYE}<span>Previewing <b>${this.nameOf(this.selected)}</b>. Your dashboard still shows <b>${this.nameOf(this.active)}</b>.</span></span>` +
            '<div class="w-baracts">' +
            `<button type="button" class="cta" data-action="uhifadhi--area-bundle--area-presets#apply">${ICON_LAYOUT}Apply this design</button>` +
            '<button type="button" class="tgl" data-action="uhifadhi--area-bundle--area-presets#cancel">Cancel</button>' +
            '</div>';
    }

    showView() {
        for (const view of this.viewTargets) {
            view.hidden = view.dataset.view !== this.selected;
        }
    }

    viewFor(key) {
        return this.viewTargets.find((view) => view.dataset.view === key);
    }

    nameOf(key) {
        const card = this.cardTargets.find((c) => c.dataset.view === key);

        return card ? card.dataset.name : key;
    }
}
