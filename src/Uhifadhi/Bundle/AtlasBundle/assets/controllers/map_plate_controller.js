/*
 * This file is part of the Uhifadhi core.
 *
 * (c) Ezekiel Mjema <https://github.com/eemjema>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

import { Controller } from '@hotwired/stimulus';
import { satelliteLayer, streetLayer } from 'uhifadhi/basemaps';
import { drawBoundary } from 'uhifadhi/boundary';
import { mountMapChrome } from 'uhifadhi/map-chrome';

/*
 * THE PLATE — the platform's ONE map controller.
 *
 * A module writes no JavaScript. It builds a map in PHP and calls
 * `render_map()`; this controller is what turns that into a map with the
 * deployment's imagery under it, the boundary drawn the platform's one way, the
 * control stack every map wears, and a legend whose rows actually switch
 * something.
 *
 * IT EXTENDS UX MAP, IT DOES NOT REPLACE IT. The map itself is created by the
 * Leaflet bridge's own controller on the element inside this one, and this
 * controller works entirely through the two extension points UX Map documents
 * for exactly this:
 *
 *   ux:map:pre-connect — the map has not been created yet, and its
 *                        `bridgeOptions` are still writable.
 *   ux:map:connect     — the map exists; `event.detail` carries it, the Leaflet
 *                        namespace, and the `extra` payload PHP sent.
 *
 *   https://symfony.com/bundles/ux-map/current/index.html#advanced-passing-extra-data-from-php-to-the-stimulus-controller
 *
 * Both events bubble, so this controller sits on the PLATE ROOT — the element
 * that goes fullscreen, and the element the filter row and the legend are
 * inside — and still hears a map created one level down.
 *
 * LEAFLET COMES FROM THE EVENT. `event.detail.L` is the very namespace the
 * bridge built the map with, so there is exactly one Leaflet on the page and
 * this file neither imports it nor reads a global.
 *
 * IT DISPATCHES ITS OWN LIFECYCLE — `atlas:map:connect` and
 * `atlas:map:layer:added` — so an installation can extend a plate without
 * forking it. A module should not need to.
 */

/** The one key of UX Map's `extra` payload the atlas owns. */
const ATLAS = 'atlas';

/** The id the drawn boundary is kept under, so a legend row can switch it. */
const BOUNDARY_LAYER = 'atlas.boundary';

/** How each shape is drawn. One answer for the whole platform. */
const STYLES = {
    line: (color) => ({ color, weight: 2.2, opacity: 0.95, fill: false }),
    fill: (color) => ({ color, weight: 1, fillColor: color, fillOpacity: 0.22 }),
    point: (color) => ({ radius: 6, color, weight: 1.5, fillColor: color, fillOpacity: 0.85 }),
};

export default class extends Controller {
    static targets = ['frame', 'legend'];

    connect() {
        this.layers = new Map();
        this.onPreConnect = (event) => this.beforeMap(event);
        this.onConnect = (event) => this.afterMap(event);

        this.element.addEventListener('ux:map:pre-connect', this.onPreConnect);
        this.element.addEventListener('ux:map:connect', this.onConnect);
    }

    disconnect() {
        this.element.removeEventListener('ux:map:pre-connect', this.onPreConnect);
        this.element.removeEventListener('ux:map:connect', this.onConnect);
        this.chrome?.destroy();
        this.chrome = null;
        this.layers.clear();
        this.map = null;
    }

    /**
     * The last word on how the map is constructed, before it is.
     *
     * The zoom control is refused here as well as in PHP: a plate wears one
     * control stack, and a second pair of buttons in the opposite corner is not
     * a thing a module should be able to reintroduce by handing the renderer
     * its own options.
     */
    beforeMap(event) {
        event.detail.bridgeOptions = { ...(event.detail.bridgeOptions ?? {}), zoomControl: false };
    }

    /**
     * The map exists. Draw the ground, the boundary, the layers, and dress it.
     *
     * What is DRAWN must never be able to kill the map: a throw while drawing
     * leaves the tiles and the chrome standing and the console says what broke.
     */
    afterMap(event) {
        const { map, L, extra } = event.detail;
        const atlas = extra?.[ATLAS] ?? {};

        this.map = map;
        this.L = L;
        this.bounds = L.latLngBounds([]);
        this.shouldFit = false !== atlas.fit;

        this.mountBases(atlas.baseLayers ?? []);

        try {
            this.drawBoundary(atlas.boundary);
            for (const layer of atlas.layers ?? []) {
                this.drawLayer(layer);
            }
        } catch (error) {
            console.error('[atlas] the plate failed to draw', error);
        }

        // After the drawing, so the DIM pill has this plate's scrim to switch.
        // Fullscreen expands the PLATE, not the map: the filter row and the
        // legend are inside it and must come along.
        this.chrome = mountMapChrome(L, map, this.frame(), {
            bases: this.bases,
            scrim: this.scrim ?? null,
            scrimOn: Boolean(this.scrim) && map.hasLayer(this.scrim),
            fullscreen: false !== atlas.fullscreen,
            fullscreenTarget: this.element,
            onResize: () => this.refit(),
        });

        this.refit();

        this.dispatch('connect', {
            prefix: 'atlas:map',
            detail: { map, L, layers: this.layers },
        });
    }

    /**
     * The ground, in the order the map asked for it. The first entry is what
     * the plate opens on; the rest are what the base-layer menu offers.
     */
    mountBases(names) {
        this.bases = {};
        for (const name of names) {
            const layer = 'satellite' === name ? satelliteLayer(this.L, this.map) : streetLayer(this.L);
            this.bases[name] = layer;
        }
        this.bases[names[0]]?.addTo(this.map);
    }

    /**
     * The area outline and the scrim outside it — the platform's one treatment,
     * so it is unmistakable where the area is on any imagery.
     */
    drawBoundary(boundary) {
        if (!boundary?.geojson) {
            return;
        }

        const drawn = drawBoundary(this.L, this.map, boundary.geojson, { scrim: false !== boundary.scrim });
        if (!drawn) {
            return;
        }

        // The scrim covers the world, so it is never part of what the plate is
        // framed on; only the outline is.
        this.scrim = drawn.scrimLayer ?? null;
        this.layers.set(BOUNDARY_LAYER, drawn);
        this.bounds.extend(drawn.getBounds());
    }

    /**
     * One GeoJSON layer, drawn from the colour and shape the module stated. A
     * feature may carry its own `color` where a module colours features
     * individually; everything else about how it looks is the platform's.
     *
     * A layer with a url is BUILT empty and filled when the fetch answers, so a
     * plate never waits on a request to become a map.
     */
    drawLayer(layer) {
        const paint = STYLES[layer.shape] ?? STYLES.fill;
        const colorOf = (feature) => feature?.properties?.color ?? layer.swatch;

        const drawn = this.L.geoJSON(layer.features ?? null, {
            style: (feature) => paint(colorOf(feature)),
            pointToLayer: (feature, latlng) => this.L.circleMarker(latlng, paint(colorOf(feature))),
            // A feature that names itself wears its name: a permanent halo
            // label over the shape, which is how a zone is read on imagery.
            onEachFeature: (feature, drawnFeature) => {
                const label = feature?.properties?.label;
                if (label) {
                    drawnFeature.bindTooltip(label, { permanent: true, direction: 'center', className: 'zone-label' });
                }
            },
        });

        this.layers.set(layer.id, drawn);
        if (false !== layer.visible) {
            drawn.addTo(this.map);
        }
        if (layer.features) {
            this.extend(drawn);
        }

        if (layer.url) {
            this.fetchLayer(layer, drawn);
        }

        this.dispatch('layer:added', { prefix: 'atlas:map', detail: { id: layer.id, layer: drawn } });
    }

    /**
     * A layer whose features live behind a url. A refusal leaves the layer
     * empty and the plate standing: one layer that did not arrive is not a
     * broken map.
     */
    async fetchLayer(layer, drawn) {
        try {
            const response = await fetch(layer.url, { headers: { Accept: 'application/geo+json, application/json' } });
            if (!response.ok) {
                return;
            }
            drawn.addData(await response.json());
        } catch (error) {
            console.error(`[atlas] the layer "${layer.id}" could not be fetched`, error);

            return;
        }

        this.extend(drawn);
        this.refit();
    }

    /**
     * A LEGEND ROW IS A SWITCH. Clicking one shows or hides its layer and the
     * row says which it now is, so what the legend claims and what the plate
     * draws cannot drift apart.
     */
    toggleLayer(event) {
        const drawn = this.layers.get(event.params.layer);
        if (!drawn) {
            return;
        }

        const showing = this.map.hasLayer(drawn);
        if (showing) {
            this.map.removeLayer(drawn);
        } else {
            drawn.addTo(this.map);
        }

        const row = event.currentTarget;
        row.classList.toggle('off', showing);
        row.setAttribute('aria-pressed', showing ? 'false' : 'true');
        const state = row.querySelector('.tog');
        if (state) {
            state.textContent = showing ? 'off' : 'on';
        }
    }

    /** Re-frame the plate on everything it drew. */
    refit() {
        if (this.shouldFit && this.bounds?.isValid()) {
            this.map?.fitBounds(this.bounds, { padding: [26, 26] });
        }
    }

    /** The imagery frame the chrome is mounted in; the plate root if there is none. */
    frame() {
        return this.hasFrameTarget ? this.frameTarget : this.element;
    }

    /** A drawn layer with no features has no bounds to widen anything with. */
    extend(drawn) {
        const bounds = drawn.getBounds();
        if (bounds.isValid()) {
            this.bounds.extend(bounds);
        }
    }
}
