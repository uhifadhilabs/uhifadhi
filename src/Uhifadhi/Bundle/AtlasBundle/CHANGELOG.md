# Changelog — AtlasBundle

## Contents

- [1.0.0](#100)

## 1.0.0

Not released yet.

 * the map builder and the map model: GeoJSON layers, the boundary, the base
   layers, the legend, fullscreen — a UX Map map with the atlas around it
 * render_map(): the plate, its filter row and its legend, rendered through the
   configured UX Map renderer
 * one Stimulus controller for every map in the product, driven by UX Map's own
   pre-connect and connect events
 * the basemap contract: a configurable satellite provider, published to the
   browser as one attribute
 * the boundary treatment and the map chrome every map in the product wears
 * the plate stylesheet, which owns the fullscreen layout so a consumer cannot
   break it
 * per-feature styling, declared: a layer's base `LayerStyle` and `StyleRule`s
   keyed on the features' own properties — stroke colour, width and opacity,
   fill on/off/colour/opacity, dash array, point radius and z-index — which is
   what replaced the `style(feature)` callback a module used to write in
   JavaScript
 * a layer states the property a hover reads (`tooltip`) and the properties a
   click opens (`popup`, a `FeaturePopup`); the plate writes the markup and
   escapes the values
 * the spotlight: any element carrying
   `data-atlas-highlight="<layer>:<featureId>"` lifts that feature and pushes
   its siblings back, wired by the plate by delegation — no module JavaScript
 * a plate is as tall as it says it is and never as tall as the row it sits in:
   one custom property, `--map-plate-height`, which a card sets or `render_map()`
   is handed — and what it sizes is the MAP BODY (the filter row and the imagery),
   with the legend adding its own height below it
 * the legend is drawn BELOW the map, as the design's `.maplegend` row under a
   `.viewer`, and floats over the imagery's bottom-right corner in fullscreen
   only — a floating legend covered the ground it described on a short plate
 * removed the `.viewer .ol` caption rule, which no template writes
