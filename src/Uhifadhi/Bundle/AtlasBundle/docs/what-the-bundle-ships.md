# What the bundle ships

## Contents

- [The PHP](#the-php)
- [The assets](#the-assets)
- [What it does not ship](#what-it-does-not-ship)

## The PHP

| Class | What it is |
|---|---|
| `Map\MapBuilderInterface` / `Map\MapBuilder` | the one entry point: `createMap(): AtlasMap` |
| `Model\AtlasMap` | a UX Map map plus the atlas's layers, boundary, legend and chrome |
| `Model\GeoJsonLayer` | one FeatureCollection — inline or fetched — with its colour, shape and legend row |
| `Model\LayerStyle`, `Model\StyleRule` | what a layer's features are drawn with, and the per-feature rules on top of it |
| `Model\FeaturePopup` | which of a feature's properties a click opens, and where its link goes |
| `Model\Boundary` | the outline and the scrim outside it |
| `Model\LegendItem` | one legend row: a switch when it names a layer, a key when it does not |
| `Model\BaseLayer`, `Model\LayerShape` | the grounds a plate offers; how a layer's features are drawn |
| `Model\SatelliteSource` | the configured imagery, as the browser needs to hear it |
| `Twig\MapExtension` | `map_basemap_attributes()`, `map_basemap_payload()`, `render_map()` |
| `Twig\MapPlateRuntime` | what `render_map()` renders: the plate, the filter row and the legend |

How a module uses them is [the atlas components](components.md).

## The assets

| Path | Import name / asset path | What it is |
|---|---|---|
| `assets/controllers/map_plate_controller.js` | `uhifadhi--atlas-bundle--map-plate` | the platform's one map controller |
| `assets/basemaps.js` | `uhifadhi/basemaps` | street + satellite base layers, the provider contract |
| `assets/boundary.js` | `uhifadhi/boundary` | the AOI outline, its casing and its outside-the-area scrim |
| `assets/chrome.js` | `uhifadhi/map-chrome` | zoom, DIM, base-layer menu, fullscreen, scale, Ctrl/⌘-scroll |
| `public/map.css` | `bundles/atlas/map.css` | the plate column, the imagery frame, the chrome, the legend, fullscreen |
| `assets/package.json` | — | the `symfony.controllers` and `symfony.importmap` blocks Flex reads |

How the import names and the controller reach a host is
[shipping importmap assets from a bundle](importmap-assets.md).

## What it does not ship

**Leaflet.** The map is created by `symfony/ux-leaflet-map`'s own Stimulus controller, which
imports `leaflet` from the host's importmap — vendored locally by AssetMapper, never a CDN — and
imports Leaflet's stylesheet with it. A second copy served from this bundle would be a second
Leaflet namespace: layers built against one and added to a map built by the other fail in ways
nobody can read.

**MapLibre**, deliberately: raster tiles plus GeoJSON need no WebGL, and WebGL failed silently —
a blank map, no error — in constrained environments.

**A renderer of its own.** UX Map already models markers, polygons, polylines, circles and
rectangles, and already has the events a bridge fires while it builds a map. The atlas adds what
UX Map has no model for and reaches it through those events.
