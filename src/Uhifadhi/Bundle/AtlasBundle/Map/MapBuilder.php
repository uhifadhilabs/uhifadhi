<?php

declare(strict_types=1);

/*
 * This file is part of the Uhifadhi core.
 *
 * (c) Ezekiel Mjema <https://github.com/eemjema>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Uhifadhi\Bundle\AtlasBundle\Map;

use Symfony\UX\Map\Bridge\Leaflet\LeafletOptions;
use Symfony\UX\Map\Bridge\Leaflet\Option\AttributionControlOptions;
use Symfony\UX\Map\Bridge\Leaflet\Option\ControlPosition;
use Symfony\UX\Map\Map as UxMap;
use Symfony\UX\Map\Point;
use Uhifadhi\Bundle\AtlasBundle\Model\AtlasMap;

/**
 * The platform's map, before anyone has decided what to draw on it.
 *
 * @see vendor/symfony/ux-chartjs/src/Builder/ChartBuilder.php
 */
final class MapBuilder implements MapBuilderInterface
{
    /**
     * THE VIEW A PLATE OPENS ON WHEN NOTHING BOUNDS IT.
     *
     * `Map::toArray()` refuses a map with neither a centre nor
     * `fitBoundsToMarkers`, and neither is the atlas's answer: a plate is framed
     * on what it drew — a boundary, a layer's features — which is known in the
     * browser and not here. So a neutral opening view is set, on open water at
     * a zoom that shows an ocean, and the plate re-frames itself the moment it
     * has something to be about.
     *
     * @see vendor/symfony/ux-map/src/Map.php
     */
    public const float DEFAULT_LATITUDE = 0.0;
    public const float DEFAULT_LONGITUDE = 0.0;
    public const float DEFAULT_ZOOM = 2.0;

    public function createMap(): AtlasMap
    {
        return new AtlasMap(new UxMap(
            options: self::bridgeOptions(),
            center: new Point(self::DEFAULT_LATITUDE, self::DEFAULT_LONGITUDE),
            zoom: self::DEFAULT_ZOOM,
        ));
    }

    /**
     * NEITHER THE BRIDGE'S TILES NOR THE BRIDGE'S CONTROLS.
     *
     * `tileLayer: false` because the ground is a deployment's configured
     * imagery, built by the basemap module — the bridge's OpenStreetMap default
     * would draw underneath it and be paid for on every tile.
     *
     * `zoomControl: false` because the plate wears one control stack, in one
     * place, looking the same on every map in the product. Leaflet's own zoom
     * buttons in the opposite corner are a second answer to a settled question.
     *
     * The attribution control stays on — it is a licence obligation, not chrome
     * — but it moves to the BOTTOM-LEFT corner, along with the scale bar the
     * chrome mounts there. Bottom-right is where the plate floats its legend,
     * and Leaflet puts both of those controls there by default; a scale bar
     * under a legend is a scale bar nobody can read.
     *
     * @see vendor/symfony/ux-leaflet-map/src/LeafletOptions.php
     * @see vendor/symfony/ux-leaflet-map/assets/src/map_controller.ts
     * @see assets/chrome.js — the scale bar's own corner
     */
    private static function bridgeOptions(): LeafletOptions
    {
        return new LeafletOptions(
            tileLayer: false,
            attributionControlOptions: new AttributionControlOptions(ControlPosition::BOTTOM_LEFT, prefix: false),
            zoomControl: false,
        );
    }
}
