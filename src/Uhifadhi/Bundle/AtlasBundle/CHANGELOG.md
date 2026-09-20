# Changelog — AtlasBundle

## Contents

- [1.0.0](#100)

## 1.0.0

Not released yet.

 * WHERE PEOPLE ARE, ON THE PLATE: `AtlasMap::livePositions()` draws a
   `LivePresence` as the shell's live dot — one marker a position, stale where
   the contract says stale, nothing at all for a person with no fix — and adds
   the key the map-legend contract requires ("On the plate": the live position,
   the stale one, and the people who are on no ground at all)
 * `LayerShape::Live`/`LiveStale`/`LiveAbsent`, one mark in three states, and a
   legend row whose swatch is a drawing rather than a colour
 * a calendar pill's dot is painted with a COLOUR token and not a channel one
   — the roles mapped to `--c-acc`/`--c-ok`/…, which resolve to a bare `62 217
   168` in a `background` and painted nothing at all, so every pill dot on
   every calendar drew empty while the markup was exactly right
 * the plate RESOLVES a token swatch where it draws — `var(--plate-ok)` handed
   to Leaflet painted nothing at all, so a legend read right while the map drew
   empty — and resolves it again when the theme flips
 * `atlas_calendar()` takes the surface's own control, drawn at the trailing
   end of the stepper row: a month is ONE line of chrome, and a component with
   nowhere to put a ranger picker made every caller draw a second toolbar
 * the month and the chart get sheets of their own, `bundles/atlas/calendar.css`
   and `bundles/atlas/chart.css`, published to the shell so they reach every
   head: both are drawn INSIDE somebody else's page, which cannot link a sheet
   for a component it has never heard of — the roster's Calendar tab drew a
   month as a list of days, and no test in the fleet could see it. The map's
   sheet stays the one a page links for itself, because a page that draws a map
   knows it does

 * the month grid as a component, the plate's and the chart's third sibling:
   `atlas_calendar(feed, '2026-09')` draws the grid, the day heads, the cells
   at one fixed height, the day numbers, the "+N more" and the stepper, and a
   module implements `Atlas\CalendarFeedInterface` to say what happened on
   which day and what each thing should read as

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
