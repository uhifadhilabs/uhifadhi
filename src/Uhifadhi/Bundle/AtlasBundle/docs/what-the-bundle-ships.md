# What the bundle ships

## Contents

- [The assets](#the-assets)

## The assets

| Path | Import name / asset path | What it is |
|---|---|---|
| `assets/basemaps.js` | `uhifadhi/basemaps` | street + satellite base layers, the provider contract |
| `assets/boundary.js` | `uhifadhi/boundary` | the AOI outline, its casing and its outside-the-area scrim |
| `assets/chrome.js` | `uhifadhi/map-chrome` | zoom, DIM, base-layer menu, fullscreen, scale, Ctrl/⌘-scroll |
| `public/leaflet/` | `bundles/atlas/leaflet/…` | the self-hosted Leaflet build and its images |
| `assets/package.json` | — | the `symfony.importmap` block Flex writes the three entries from |

MapLibre is deliberately not used: raster tiles plus GeoJSON need no WebGL, and WebGL failed
silently — a blank map, no error — in constrained environments.

How the three import names reach a host's `importmap.php` is
[shipping importmap assets from a bundle](importmap-assets.md).
