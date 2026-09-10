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
