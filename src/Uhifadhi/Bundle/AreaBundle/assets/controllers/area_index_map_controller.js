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
import { mountMapChrome } from 'uhifadhi/map-chrome';

/*
 * THE MAP OF THE NETWORK — every area the org manages, drawn as a point on the
 * platform's one map.
 *
 * ONE MAP, EVERYWHERE. The imagery (uhifadhi/basemaps) and the chrome — zoom,
 * base-layer toggle, live scale, attribution, fullscreen (uhifadhi/map-chrome) —
 * are the map module's, so this plate reads identically to the overview's. This
 * controller reuses those shared pieces and adds only the one thing this view
 * owns: the org's areas, each boundary drawn and a marker dropped on it, the
 * whole set fitted into view. It is NOT a new map — it is the shared map, with a
 * different overlay.
 *
 * IT LIVES INSIDE A PREVIEW THAT STARTS HIDDEN. The widget library embeds five
 * layouts and shows one at a time, so this plate is built while its container has
 * no size (Leaflet then lays out at 0×0). A ResizeObserver watches the canvas and
 * re-fits the moment the preview is shown — no dependency on the preset
 * controller, so either can change without touching the other.
 *
 * Leaflet is self-hosted and read off window.L (the page loads it as a classic
 * <script> before this deferred module connects). MapLibre is deliberately not
 * used — raster tiles plus GeoJSON need no WebGL, which failed silently in
 * constrained setups.
 */

function parseGeometry(text) {
    if ('string' !== typeof text || '' === text) {
        return null;
    }
    try {
        const value = JSON.parse(text);

        return value && 'object' === typeof value && value.type ? value : null;
    } catch {
        return null;
    }
}

export default class extends Controller {
    static targets = ['canvas'];
    static values = { areas: Array };

    connect() {
        const L = window.L;
        if (!L) {
            console.error('[area] window.L (Leaflet) is not loaded — the map preview needs leaflet.js');

            return;
        }
        this.L = L;

        this.map = L.map(this.canvasTarget, { zoomControl: false, attributionControl: true });
        this.canvasTarget.classList.add('map-chrome-host');

        this.bases = {
            satellite: satelliteLayer(L, this.map),
            osm: streetLayer(L),
        };
        this.bases.satellite.addTo(this.map);

        // The view a plate opens on when nothing bounds it — the org's own ground.
        this.map.setView([-6, 35], 5);

        try {
            this.draw();
        } catch (error) {
            console.error('[area] the areas overlay failed to draw', error);
        }

        this.chrome = mountMapChrome(L, this.map, this.element, {
            bases: this.bases,
            fullscreenTarget: this.element.closest('.c') ?? this.element,
            onResize: () => this.refit(),
        });

        // The preview starts hidden, so the map was laid out at 0×0: re-fit the
        // moment the canvas is given a real size.
        this.observer = new ResizeObserver(() => this.onResize());
        this.observer.observe(this.canvasTarget);
    }

    disconnect() {
        this.observer?.disconnect();
        this.observer = null;
        this.chrome?.destroy();
        this.chrome = null;
        this.map?.stop();
        this.map?.remove();
        this.map = null;
    }

    /**
     * Each area as a point on the map: its boundary drawn (bold for a live area,
     * quiet for one only mapped) and a marker on it that opens the area. An area
     * with no boundary has no place on the map — it rides the dock beside it — so
     * it is simply not drawn here. The whole set is fitted into view.
     */
    draw() {
        const bounds = this.L.latLngBounds([]);
        const areas = this.hasAreasValue ? this.areasValue : [];

        for (const area of areas) {
            const geometry = parseGeometry(area.boundary);
            if (!geometry) {
                continue;
            }

            const shape = this.L.geoJSON(geometry, {
                style: area.live
                    ? { color: '#3ED9A8', weight: 1.8, opacity: 0.95, fillColor: '#3ED9A8', fillOpacity: 0.08 }
                    : { color: '#B9C8BD', weight: 1.4, opacity: 0.8, dashArray: '5 4', fill: false },
            }).addTo(this.map);

            const at = shape.getBounds().getCenter();
            const marker = this.L.circleMarker(at, {
                radius: area.live ? 7 : 5,
                color: area.live ? '#3ED9A8' : '#B9C8BD',
                weight: 2,
                fillColor: area.live ? '#3ED9A8' : 'transparent',
                fillOpacity: area.live ? 0.9 : 0,
            }).addTo(this.map);
            marker.bindTooltip(area.name, { direction: 'top', className: 'zone-label' });
            if (area.href) {
                marker.on('click', () => {
                    window.location.href = area.href;
                });
            }

            bounds.extend(shape.getBounds());
        }

        this.lastFit = bounds.isValid() ? bounds : null;
        if (this.lastFit) {
            this.map.fitBounds(this.lastFit, { padding: [30, 30] });
        }
    }

    onResize() {
        if (!this.map || 0 === this.canvasTarget.clientWidth) {
            return;
        }
        this.map.invalidateSize();
        this.refit();
    }

    refit() {
        if (this.lastFit) {
            this.map?.fitBounds(this.lastFit, { padding: [30, 30] });
        }
    }
}
