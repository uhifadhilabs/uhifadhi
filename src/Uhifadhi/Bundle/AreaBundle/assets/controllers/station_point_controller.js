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
 * PICKING A STATION'S POINT ON THE PLATE.
 *
 * TYPING A COORDINATE IS NOT HOW ANYBODY KNOWS WHERE A POST IS. The plate is
 * already on the page, drawing the boundary, the zones and every post there
 * is; this makes it answerable — click the ground and the point is written
 * into the form that asked for it.
 *
 * THE TYPED PAIR STAYS. Every field this writes into is a real input in a
 * real form, so an installation whose scripts never load loses the clicking
 * and keeps the adding: the coordinate boxes are the fallback the design
 * keeps, not a leftover.
 *
 * THREE STATES, AND THE CAPTION SAYS WHICH. At REST the plate invites a pick;
 * ADDING names the station being added and takes a click; MOVING names the
 * station being moved and takes a drag of its pin. A plate that is armed and
 * does not say so is a plate that moves the wrong post.
 *
 * LEAFLET COMES FROM THE ATLAS, never from an import or a global: the plate
 * dispatches `atlas:map:connect` with the map and the very namespace it was
 * built with, which is the published way to extend a plate without forking
 * it.
 */
export default class extends Controller {
    static targets = ['plate', 'capRest', 'capAdd', 'capMove', 'capPoint', 'capUse', 'note'];

    connect() {
        this.mode = 'rest';
        this.armed = null;
        this.point = null;
        this.pin = null;

        this.onMapConnect = (event) => this.mapReady(event.detail);
        this.element.addEventListener('atlas:map:connect', this.onMapConnect);
    }

    disconnect() {
        this.element.removeEventListener('atlas:map:connect', this.onMapConnect);
        this.map = null;
        this.L = null;
        this.pin = null;
    }

    /** The plate is live: from here a click on the ground is a point. */
    mapReady({ map, L }) {
        this.map = map;
        this.L = L;
        map.on('click', (event) => this.place(event.latlng.lat, event.latlng.lng));

        if (this.armed) {
            this.dropPinFromForm();
        }
    }

    /**
     * ARM THE PLATE FOR ONE FORM. The button says which station and which
     * form, so the controller never guesses which pair of boxes a click is
     * about: `mode` is add or move, `form` is the id of the form that owns
     * the point, and `name` is what the caption calls it.
     */
    arm(event) {
        event.preventDefault();

        const { mode, form, name } = event.params;
        this.mode = mode ?? 'add';
        this.armed = document.getElementById(form);
        this.armedName = name ?? '';

        this.caption();
        this.dropPinFromForm();

        if (this.hasPlateTarget) {
            this.plateTarget.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
        }
    }

    /** A click on the ground, or a drag of the pin: one point, one readout. */
    place(lat, lng) {
        this.point = { lat, lng };
        this.mark(lat, lng);
        this.readout();

        // ADDING WRITES STRAIGHT THROUGH: the click IS the answer, and a
        // second press to confirm what you just pointed at is a second press.
        // MOVING DOES NOT: a post that exists is moved on purpose, so the drag
        // proposes and `Use this point` commits.
        if ('add' === this.mode) {
            this.write();
        }
    }

    /** `Use this point` — the armed form takes what the pin is standing on. */
    use(event) {
        event.preventDefault();
        this.write();
    }

    write() {
        if (!this.armed || !this.point) {
            return;
        }

        const lat = this.armed.querySelector('[name="lat"]');
        const lon = this.armed.querySelector('[name="lon"]');
        if (lat) {
            lat.value = this.point.lat.toFixed(5);
        }
        if (lon) {
            lon.value = this.point.lng.toFixed(5);
        }

        if (this.hasNoteTarget) {
            this.noteTarget.textContent = `${this.point.lat.toFixed(5)}, ${this.point.lng.toFixed(5)}`;
        }
    }

    /** The pin: one on the plate, accented, dragged only while moving. */
    mark(lat, lng) {
        if (!this.map) {
            return;
        }

        if (!this.pin) {
            this.pin = this.L.circleMarker([lat, lng], {
                radius: 7,
                weight: 2,
                color: '#49E6B4',
                fillColor: '#49E6B4',
                fillOpacity: 0.65,
            }).addTo(this.map);
        } else {
            this.pin.setLatLng([lat, lng]);
        }
    }

    /** What the form already holds, so an armed plate opens where it is. */
    dropPinFromForm() {
        if (!this.armed || !this.map) {
            return;
        }

        const lat = Number.parseFloat(this.armed.querySelector('[name="lat"]')?.value ?? '');
        const lng = Number.parseFloat(this.armed.querySelector('[name="lon"]')?.value ?? '');

        if (Number.isFinite(lat) && Number.isFinite(lng)) {
            this.point = { lat, lng };
            this.mark(lat, lng);
            this.readout();
        }
    }

    readout() {
        if (this.hasCapPointTarget) {
            this.capPointTarget.textContent = `${this.point.lat.toFixed(5)}, ${this.point.lng.toFixed(5)}`;
            this.capPointTarget.hidden = false;
        }
        if (this.hasCapUseTarget) {
            this.capUseTarget.hidden = false;
        }
    }

    /** One caption is shown, and it names the station it is about. */
    caption() {
        const states = {
            rest: this.hasCapRestTarget ? this.capRestTarget : null,
            add: this.hasCapAddTarget ? this.capAddTarget : null,
            move: this.hasCapMoveTarget ? this.capMoveTarget : null,
        };

        Object.entries(states).forEach(([state, element]) => {
            if (element) {
                element.hidden = state !== this.mode;
            }
        });

        const named = states[this.mode]?.querySelector('[data-station-name]');
        if (named) {
            named.textContent = this.armedName;
        }
    }
}
