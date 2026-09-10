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
 * WHAT A FEATURE LOOKS LIKE, SAYS AND ANSWERS TO IS ALSO DATA. A module states
 * a base style and rules keyed on a feature's own properties, the property a
 * hover reads, the properties a popup reads, and the property that identifies a
 * feature; this controller evaluates them. No callback crosses the wire, which
 * is exactly why one controller can draw every module's layers.
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

/*
 * THE SPOTLIGHT, ONE ANSWER FOR THE WHOLE PLATFORM. Hovering a row in a list
 * beside a map lifts the feature that row is about and pushes the rest back. How
 * far it is lifted and how far the rest fall back is the plate's, not a
 * module's, so a hovered patrol track and a hovered anything else read alike.
 */
const SPOTLIGHT = { weight: 1.6, opacity: 1 };
const SHADOW = { opacity: 0.25 };

/** The attribute any element on the page wears to spotlight a feature: "<layer>:<id>". */
const HIGHLIGHT = 'data-atlas-highlight';

/** The pane a stated z-index is drawn in. One pane per value, built on demand. */
const PANE = 'atlas-z-';

export default class extends Controller {
    static targets = ['frame', 'legend'];

    connect() {
        this.layers = new Map();
        // What the map said about each layer, kept because a spotlight has to
        // put a feature back exactly the way it was stated.
        this.specs = new Map();
        // layer id → (feature id → the drawn features carrying it), so an
        // element anywhere on the page can spotlight a feature by name.
        this.byFeatureId = new Map();
        this.spotlit = null;
        this.onPreConnect = (event) => this.beforeMap(event);
        this.onConnect = (event) => this.afterMap(event);

        this.element.addEventListener('ux:map:pre-connect', this.onPreConnect);
        this.element.addEventListener('ux:map:connect', this.onConnect);

        /*
         * THE SPOTLIGHT IS WIRED ONCE, BY DELEGATION, so a module writes an
         * attribute and no JavaScript. The listeners are on the document rather
         * than on the rows because the rows are somebody else's widget — a log,
         * a feed, a table — which may be re-rendered, paginated or swapped by
         * Turbo long after this controller connected.
         *
         * mouseover/mouseout rather than mouseenter/mouseleave: only the former
         * bubble, and delegation needs them to.
         */
        this.onOver = (event) => this.spotlightFrom(event.target);
        this.onOut = (event) => this.releaseFrom(event);
        document.addEventListener('mouseover', this.onOver);
        document.addEventListener('mouseout', this.onOut);
        document.addEventListener('focusin', this.onOver);
        document.addEventListener('focusout', this.onOut);
    }

    disconnect() {
        this.element.removeEventListener('ux:map:pre-connect', this.onPreConnect);
        this.element.removeEventListener('ux:map:connect', this.onConnect);
        document.removeEventListener('mouseover', this.onOver);
        document.removeEventListener('mouseout', this.onOut);
        document.removeEventListener('focusin', this.onOver);
        document.removeEventListener('focusout', this.onOut);
        this.chrome?.destroy();
        this.chrome = null;
        this.layers.clear();
        this.specs.clear();
        this.byFeatureId.clear();
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
     * One GeoJSON layer, drawn from what the module stated about it: the shape,
     * the colour, the base style over that, and the rules each feature's own
     * properties earn. A feature may carry its own `color` where a module
     * colours features individually.
     *
     * A layer with a url is BUILT empty and filled when the fetch answers, so a
     * plate never waits on a request to become a map. The options below are the
     * layer's, so features arriving later are dressed exactly like the ones that
     * came in the page.
     */
    drawLayer(layer) {
        const drawn = this.L.geoJSON(layer.features ?? null, {
            style: (feature) => this.styleFor(layer, feature),
            pointToLayer: (feature, latlng) => this.L.circleMarker(latlng, this.styleFor(layer, feature)),
            onEachFeature: (feature, drawnFeature) => this.dressFeature(layer, feature, drawnFeature),
        });

        this.layers.set(layer.id, drawn);
        this.specs.set(layer.id, layer);
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
     * WHAT ONE FEATURE IS DRAWN WITH — the shape's own answer, then the layer's
     * base style, then every rule the feature's properties satisfy, each merged
     * over the last in the order the module wrote them.
     *
     * NO CALLBACK CROSSED THE WIRE to get here. A rule is a property name, the
     * values that satisfy it and the style they earn, which is why one
     * controller can draw every module's layers and no module ships a second.
     */
    styleFor(layer, feature) {
        const properties = feature?.properties ?? {};
        const paint = STYLES[layer.shape] ?? STYLES.fill;
        let style = { ...paint(properties.color ?? layer.swatch), ...(layer.style ?? {}) };

        for (const rule of layer.rules ?? []) {
            if ((rule.values ?? []).includes(properties[rule.property])) {
                style = { ...style, ...(rule.style ?? {}) };
            }
        }

        // z-index is a PANE, which is the only way Leaflet lets one vector sit
        // above another regardless of the order they were added in.
        const { zIndex, ...options } = style;

        return undefined === zIndex ? options : { ...options, pane: this.pane(zIndex) };
    }

    /** The pane for a stated z-index, built the first time one is asked for. */
    pane(zIndex) {
        const name = PANE + zIndex;
        if (!this.map.getPane(name)) {
            this.map.createPane(name).style.zIndex = String(zIndex);
        }

        return name;
    }

    /**
     * WHAT A FEATURE SAYS AND ANSWERS TO: the permanent halo a feature that
     * names itself wears, the floating label a hover reads, the popup a click
     * opens, and the id an element elsewhere on the page spotlights it by.
     *
     * The popup's markup is written HERE, from property names — never handed
     * over as a string by a module — so a property carrying a stray angle
     * bracket cannot become an element on somebody's map.
     */
    dressFeature(layer, feature, drawnFeature) {
        const properties = feature?.properties ?? {};

        // A feature that names itself wears its name: a permanent halo label
        // over the shape, which is how a zone is read on imagery.
        if (properties.label) {
            drawnFeature.bindTooltip(properties.label, { permanent: true, direction: 'center', className: 'zone-label' });
        }

        const hovered = layer.tooltip ? properties[layer.tooltip] : null;
        if (hovered) {
            drawnFeature.bindTooltip(String(hovered), { sticky: true, direction: 'top' });
        }

        if (layer.popup) {
            const content = popupMarkup(layer.popup, properties);
            if (content) {
                drawnFeature.bindPopup(content);
            }
        }

        if (layer.featureId && undefined !== properties[layer.featureId]) {
            const index = this.byFeatureId.get(layer.id) ?? new Map();
            const key = String(properties[layer.featureId]);
            index.set(key, [...(index.get(key) ?? []), { feature, drawnFeature }]);
            this.byFeatureId.set(layer.id, index);
        }
    }

    /**
     * SPOTLIGHT WITHOUT A LINE OF MODULE JAVASCRIPT. Any element on the page
     * carrying data-atlas-highlight="<layer>:<featureId>" lifts that feature and
     * pushes its siblings back while the cursor (or the focus) is on it.
     */
    spotlightFrom(target) {
        const source = target?.closest?.(`[${HIGHLIGHT}]`);
        if (!source) {
            return;
        }

        const [layerId, ...rest] = String(source.getAttribute(HIGHLIGHT)).split(':');
        const index = this.byFeatureId.get(layerId);
        if (!index) {
            return;
        }

        this.spotlight(layerId, rest.join(':'));
    }

    /** The cursor left the row it was on — every feature back to how it was drawn. */
    releaseFrom(event) {
        const source = event.target?.closest?.(`[${HIGHLIGHT}]`);
        if (source && !source.contains(event.relatedTarget)) {
            this.release();
        }
    }

    spotlight(layerId, featureId) {
        const drawn = this.layers.get(layerId);
        const index = this.byFeatureId.get(layerId);
        if (!drawn || !index) {
            return;
        }

        // Moving from a row about one layer to a row about another leaves the
        // first layer dimmed unless it is put back first.
        if (this.spotlit && this.spotlit !== layerId) {
            this.release();
        }

        this.spotlit = layerId;
        const lifted = new Set((index.get(featureId) ?? []).map((entry) => entry.drawnFeature));

        drawn.eachLayer((drawnFeature) => {
            const base = this.styleFor(this.specFor(layerId), drawnFeature.feature);
            drawnFeature.setStyle?.(lifted.has(drawnFeature)
                ? { ...base, weight: (base.weight ?? 1) * SPOTLIGHT.weight, opacity: SPOTLIGHT.opacity }
                : { ...base, ...SHADOW });
            if (lifted.has(drawnFeature)) {
                drawnFeature.bringToFront?.();
            }
        });
    }

    /** Every feature of the spotlit layer back to the style it was drawn with. */
    release() {
        if (!this.spotlit) {
            return;
        }

        const spec = this.specFor(this.spotlit);
        this.layers.get(this.spotlit)?.eachLayer((drawnFeature) => {
            drawnFeature.setStyle?.(this.styleFor(spec, drawnFeature.feature));
        });
        this.spotlit = null;
    }

    /** What the map SAID about a layer, kept so a restored style is the stated one. */
    specFor(layerId) {
        return this.specs.get(layerId) ?? {};
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

/**
 * A POPUP, WRITTEN FROM PROPERTY NAMES. A module says which properties to read;
 * the markup and the escaping are the plate's, so a value somebody typed into a
 * form cannot become an element on a map.
 *
 * A popup whose title property is empty on this feature is no popup at all —
 * an empty bubble says less than no bubble.
 */
function popupMarkup(popup, properties) {
    const title = properties[popup.title];
    if (undefined === title || null === title || '' === title) {
        return null;
    }

    const lines = (popup.lines ?? [])
        .map((name) => properties[name])
        .filter((value) => undefined !== value && null !== value && '' !== value)
        .map((value) => escapeHtml(value));

    let markup = `<b>${escapeHtml(title)}</b>`;
    if (lines.length > 0) {
        markup += `<br><small>${lines.join(' &middot; ')}</small>`;
    }

    const href = popup.href ? properties[popup.href] : null;
    if (href) {
        markup += `<br><a href="${escapeHtml(href)}">${escapeHtml(popup.linkLabel ?? href)}</a>`;
    }

    return markup;
}

function escapeHtml(value) {
    return String(value).replace(/[&<>"']/g, (character) => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
    }[character]));
}
