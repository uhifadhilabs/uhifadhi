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
 * THE AREA OVERVIEW'S OPERATIONAL PLATE — the area's own base content over the
 * platform's shared imagery: the AOI boundary and the area's zones.
 *
 * ONE MAP, EVERYWHERE. The imagery (uhifadhi/basemaps), the boundary treatment
 * (uhifadhi/boundary) and the chrome — zoom, DIM, base-layer toggle, live scale,
 * attribution, fullscreen, the Ctrl/-scroll bargain — (uhifadhi/map-chrome) all
 * come from AtlasBundle, so this plate reads identically to a patrol plate.
 * This controller draws no chrome and holds no second opinion about what
 * satellite or a boundary looks like; it only stitches the shared pieces and
 * adds the one thing that is the area's own: its zones.
 *
 * Leaflet is self-hosted and read off window.L (the overview template loads it
 * as a classic <script>, from AtlasBundle's public/leaflet, before this
 * deferred module connects). MapLibre is deliberately not used — raster tiles
 * plus GeoJSON need no WebGL, and WebGL failed silently in constrained setups.
 */

/** Parse a geometry column's GeoJSON text; anything unusable is simply not drawn. */
function parseGeometry(text) {
    if (typeof text !== 'string' || text === '') {
        return null;
    }
    try {
        const value = JSON.parse(text);

        return value && typeof value === 'object' && value.type ? value : null;
    } catch {
        return null;
    }
}

export default class extends Controller {
    static targets = ['canvas'];
    static values = { payload: Object };

    connect() {
        const L = window.L;
        if (!L) {
            console.error('[area] window.L (Leaflet) is not loaded — the overview template must include leaflet.js');

            return;
        }
        this.L = L;

        // Controls, the live scale bar, attribution and the scroll bargain all
        // come from the shared chrome module, mounted after the overlay is drawn.
        this.map = L.map(this.canvasTarget, { zoomControl: false, attributionControl: true });
        this.canvasTarget.classList.add('map-chrome-host');

        this.bases = {
            satellite: satelliteLayer(L, this.map),
            osm: streetLayer(L),
        };
        this.bases.satellite.addTo(this.map); // the design's default

        // A plate with nothing to fit still has to be a map: the view it opens on
        // when neither the boundary nor a zone bounds it.
        this.map.setView([-3.2, -29.5], 8);

        const payload = this.hasPayloadValue ? this.payloadValue : {};

        // What is DRAWN must never be able to kill the map itself: a throw here
        // leaves the tiles and the chrome standing and the console says what broke.
        try {
            this.draw(payload);
        } catch (error) {
            console.error('[area] the map overlay failed to draw', error);
        }

        // After draw(), so the DIM pill has this plate's scrim to switch, and
        // fullscreen takes the whole card so the legend rides along.
        this.chrome = mountMapChrome(L, this.map, this.element, {
            bases: this.bases,
            scrim: this.scrimLayer,
            scrimOn: Boolean(this.scrimLayer) && this.map.hasLayer(this.scrimLayer),
            fullscreenTarget: this.element.closest('.c') ?? this.element,
            onResize: () => this.refit(),
        });
    }

    disconnect() {
        this.chrome?.destroy();
        this.chrome = null;
        // stop() before remove(): a pan/zoom animation in flight fires its
        // transitionend on a pane remove() has already detached and Leaflet
        // throws; navigating away mid-zoom is an ordinary thing to do.
        this.map?.stop();
        this.map?.remove();
        this.map = null;
    }

    /**
     * The area's own base content — the boundary and the zones — then every
     * module layer the registry handed down. Each drawn layer is kept by its legend
     * id, so a legend row can switch it on and off; a layer off by default is
     * BUILT but not added, so its first toggle draws it with no round-trip.
     */
    draw(payload) {
        // Kept by the same id the legend row carries, so toggleLayer() can reach
        // the drawn layer a person clicked to switch.
        this.layers = {};

        const bounds = this.L.latLngBounds([]);

        // The AOI boundary — the platform's one treatment (scrim, white casing,
        // jade line), so it is unmistakable where the area is.
        const boundary = drawBoundary(this.L, this.map, parseGeometry(payload.boundary), { scrim: true });
        this.scrimLayer = boundary?.scrimLayer ?? null;
        if (boundary) {
            this.layers['area.boundary'] = boundary;
            bounds.extend(boundary.getBounds());
        }

        // The zones — the area's own polygons, drawn as a quiet grey outline with
        // the zone name as a permanent halo label (the .zone-label treatment).
        // Collected into one group so "Zones" is a single legend toggle.
        const zones = this.L.featureGroup();
        for (const zone of payload.zones ?? []) {
            const geometry = parseGeometry(zone.geom);
            if (!geometry) {
                continue;
            }
            const layer = this.L.geoJSON(geometry, {
                style: { color: '#B9C8BD', weight: 1.4, opacity: 0.9, dashArray: '5 4', fill: false },
            });
            if (zone.name) {
                layer.bindTooltip(zone.name, { permanent: true, direction: 'center', className: 'zone-label' });
            }
            layer.addTo(zones);
            bounds.extend(layer.getBounds());
        }
        if (payload.zones && payload.zones.length > 0) {
            zones.addTo(this.map);
        }
        this.layers['area.zones'] = zones;

        // MODULE LAYERS — one plate, many owners. The area page draws each from the
        // colour and style the module stated and knows nothing of what the
        // geometry IS; a per-feature `color` overrides the layer swatch where a
        // module colours its features individually (a track by patrol type).
        for (const layer of payload.layers ?? []) {
            this.layers[layer.id] = this.buildModuleLayer(layer);
            if (layer.on) {
                this.layers[layer.id].addTo(this.map);
            }
        }

        this.lastFit = bounds.isValid() ? bounds : null;
        if (this.lastFit) {
            this.map.fitBounds(this.lastFit, { padding: [26, 26] });
        }
    }

    /**
     * One module layer as a Leaflet layer, drawn generically from its swatch and
     * style: a line is a stroke, a fill is a filled shape, and a point is a
     * circle marker. A malformed feature draws nothing rather than throwing the
     * plate away.
     */
    buildModuleLayer(layer) {
        const line = layer.style === 'line';
        const swatch = layer.swatch;
        const colorOf = (feature) => feature?.properties?.color ?? swatch;

        return this.L.geoJSON(layer.features ?? { type: 'FeatureCollection', features: [] }, {
            style: (feature) => line
                ? { color: colorOf(feature), weight: 2.2, opacity: 0.95, fill: false }
                : { color: colorOf(feature), weight: 1, fillColor: colorOf(feature), fillOpacity: 0.22 },
            pointToLayer: (feature, latlng) => this.L.circleMarker(latlng, {
                radius: 6, color: colorOf(feature), weight: 1.5, fillColor: colorOf(feature), fillOpacity: 0.85,
            }),
        });
    }

    /**
     * A LEGEND ROW IS A TOGGLE. Clicking one shows or hides its layer on the
     * plate and flips the row's own state, so the legend always says what the
     * plate is actually drawing.
     */
    toggleLayer(event) {
        // The row is an <a href="#"> so it works as a focusable, keyboard-
        // activatable control; the jump it would otherwise make is not wanted.
        event.preventDefault();

        const id = event.params.layer;
        const layer = this.layers?.[id];
        if (!layer) {
            return;
        }

        const row = event.currentTarget;
        const showing = this.map.hasLayer(layer);
        if (showing) {
            this.map.removeLayer(layer);
        } else {
            layer.addTo(this.map);
        }

        row.classList.toggle('off', showing);
        const tog = row.querySelector('.tog');
        if (tog) {
            tog.textContent = showing ? 'off' : 'on';
        }
    }

    /** Re-frame whatever the plate opened on when the viewport changes size. */
    refit() {
        if (this.lastFit) {
            this.map?.fitBounds(this.lastFit, { padding: [26, 26] });
        }
    }
}
