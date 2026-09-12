# The atlas components

The atlas is the component library every module's visuals are drawn with. A module states what
is on a visual in PHP and calls one Twig function; it writes no JavaScript, holds no opinion
about imagery, chrome or fullscreen, and cannot make its own map look different from anybody
else's.

Today the atlas ships **maps**. Charts and calendars are the same shape and are coming; their
APIs are not written here because they are not written yet.

## Contents

- [How a module gets a map](#how-a-module-gets-a-map)
- [The map builder](#the-map-builder)
- [Layers](#layers)
- [Styling a layer's features](#styling-a-layers-features)
- [Tooltips and popups](#tooltips-and-popups)
- [Spotlighting a feature from elsewhere on the page](#spotlighting-a-feature-from-elsewhere-on-the-page)
- [How tall a plate is](#how-tall-a-plate-is)
- [The boundary](#the-boundary)
- [The legend](#the-legend)
  - [Where it is drawn](#where-it-is-drawn)
- [Base layers, fullscreen and fitting](#base-layers-fullscreen-and-fitting)
- [What UX Map already models](#what-ux-map-already-models)
- [render_map()](#render_map)
  - [A filter change keeps fullscreen](#a-filter-change-keeps-fullscreen)
- [The events](#the-events)
- [A whole module template](#a-whole-module-template)
- [What a module must not do](#what-a-module-must-not-do)

## How a module gets a map

Four steps, and the fourth is a line of Twig.

1. Take `MapBuilderInterface` in a service's constructor and call `createMap()`.
2. Add layers, a boundary, legend rows.
3. Hand the map to the template.
4. `{{ render_map(map) }}`.

## The map builder

```php
// src/Service/SightingsMap.php (your module)
namespace YourVendor\Sightings\Service;

use Uhifadhi\Bundle\AtlasBundle\Map\MapBuilderInterface;
use Uhifadhi\Bundle\AtlasBundle\Model\AtlasMap;

final readonly class SightingsMap
{
    public function __construct(private MapBuilderInterface $maps)
    {
    }

    public function forArea(string $areaUuid): AtlasMap
    {
        return $this->maps->createMap();
    }
}
```

Wire it the way a reusable bundle wires anything — explicitly, in the bundle's own PHP config:

```php
// config/services.php (your module)
$services->set('sightings.map', SightingsMap::class)
    ->args([service(MapBuilderInterface::class)]);
```

What comes back is an `AtlasMap`: a UX Map map with the deployment's imagery underneath, the
bridge's own tiles and zoom control switched off, and the atlas's control stack, legend and
fullscreen around it. It opens on a neutral view and re-frames itself on whatever gets drawn.

## Layers

A layer is a whole GeoJSON FeatureCollection that shares one colour, one meaning and one switch.
It names exactly one source: `features`, which the server already has, or `url`, which the plate
fetches once it is mounted. Naming both, or neither, is refused where you wrote it.

```php
use Uhifadhi\Bundle\AtlasBundle\Model\GeoJsonLayer;
use Uhifadhi\Bundle\AtlasBundle\Model\LayerShape;

$map->addLayer(new GeoJsonLayer(
    id: 'sightings.recent',      // <module>.<layer>, and the id its legend row switches
    label: 'This week',
    features: $collection,       // …or url: '/sightings/features.geojson'
    swatch: '#E5C15A',
    shape: LayerShape::Point,    // Line | Fill | Point
    visible: true,
    count: 42,                   // what the legend prints after the label
    group: 'Sightings',          // the legend heading the row sits under
));
```

Three shapes and no more. A module says what geometry MEANS; stroke widths, opacities and radii
are the plate's, so two modules cannot disagree about what "a line" looks like. A feature may
override its own colour with a `color` property, and a feature with a `label` property wears it
as a permanent halo over the shape.

A layer with `visible: false` is still built, so its first switch costs no round trip. A layer
with a `url` is built empty and filled when the fetch answers — the plate never waits on a
request to become a map.

## Styling a layer's features

A shape settles what a line, a fill and a point look like for the whole platform. On top of that
a layer states the few things that carry MEANING rather than house style — a hollow mark for a
closed case, a dashed ring for the serious end, a wider stroke for one route.

Two statements, and both are data:

- **`style`** — a `LayerStyle` every feature of the layer wears, merged over the shape's answer;
- **`rules`** — `StyleRule`s keyed on the features' own properties, merged over `style` in the
  order they are written.

```php
use Uhifadhi\Bundle\AtlasBundle\Model\LayerStyle;
use Uhifadhi\Bundle\AtlasBundle\Model\StyleRule;

$map->addLayer(new GeoJsonLayer(
    id: 'sightings.recent',
    label: 'This week',
    features: $collection,
    swatch: '#E5C15A',
    shape: LayerShape::Point,
    style: new LayerStyle(radius: 5.5, weight: 1.6, fillOpacity: 0.85),
    rules: [
        // a finished sighting is HOLLOW
        StyleRule::when('open', false)->fillOpacity(0.0),
        // and the serious end wears a dashed ring
        StyleRule::when('severity', ['high', 'critical'])->radius(7.0)->weight(2.4)->dashArray('3 3'),
    ],
));
```

A style states **only what it changes**; an unstated property keeps the shape's own answer. The
vocabulary is Leaflet's own path options, so what you write and what the browser receives are the
same word:

| Statement | What it sets |
|---|---|
| `color(string)` | the stroke colour |
| `weight(float)` | the stroke width, in pixels |
| `opacity(float)` | the stroke opacity, 0–1 |
| `fill(bool)` | whether the shape is filled at all |
| `fillColor(string)` / `fillOpacity(float)` | the fill, where it differs from the stroke |
| `dashArray(string)` | an SVG dash pattern, e.g. `'4 3'` |
| `radius(float)` | the circle radius of a point feature |
| `zIndex(int)` | which pane it is drawn in — higher is nearer the reader |

`zIndex` is the only way to say "this layer sits over that one" independently of the order the
layers were added in: the plate builds one Leaflet pane per stated value.

**No callback crosses the wire.** A rule is a property name, the values that satisfy it and the
style they earn — which is a thing JSON can carry and one controller can evaluate. That is
precisely why your module ships no map JavaScript.

## Tooltips and popups

Both are stated as **property names**. The plate reads the property, writes the markup and escapes
the value, so a headline somebody typed into a form cannot become an element on a map.

```php
use Uhifadhi\Bundle\AtlasBundle\Model\FeaturePopup;

new GeoJsonLayer(
    id: 'sightings.recent',
    label: 'This week',
    features: $collection,
    // read on HOVER — a floating label, following the cursor
    tooltip: 'summary',
    // opened on CLICK
    popup: new FeaturePopup(
        title: 'title',
        lines: ['category', 'statusLabel'],
        href: 'href',
        linkLabel: 'Open the sighting →',
    ),
);
```

`FeaturePopup::of('title', 'href')` is the short way to say the two properties a popup nearly
always has. A popup whose `title` property is empty on a given feature is not drawn: an empty
bubble says less than no bubble.

A feature that carries a **`label`** property still wears it as a permanent halo over the shape —
that is how a zone is read on imagery, and it is unrelated to `tooltip`.

## Spotlighting a feature from elsewhere on the page

A log row beside a map should lift the track it is about. That needs no JavaScript from you: the
layer declares which property identifies a feature, and any element on the page carrying
`data-atlas-highlight="<layer id>:<feature id>"` spotlights it on hover or focus.

```php
new GeoJsonLayer(id: 'patrol.tracks.foot', label: 'foot', features: $tracks, featureId: 'ref');
```

```twig
<a class="row" href="{{ path('patrol_show', {…}) }}" data-atlas-highlight="patrol.tracks.foot:{{ patrol.ref }}">…</a>
```

The plate raises that feature's stroke and pushes its siblings back while the cursor is on the
element, and puts every one of them back on leave. How far it lifts and how far the rest fall
back is the plate's answer, so a spotlit patrol track and a spotlit anything else read alike.

The listeners are delegated from `document`, so rows re-rendered, paginated or swapped in after
the map mounted still work.

## How tall a plate is

**A plate is as tall as it says it is, never as tall as the row it sits in.** It has a real
`height` — never `auto`, and never an `align-self`, which inside a card that stacks a plate under
a heading would read across the other axis and shrink the plate off its width — so a stretch row
cannot grow it; the only thing that does is fullscreen.

```css
--map-plate-height   /* default: min(58vh, 560px) */
```

**What the property sizes is the MAP: the filter row and the imagery under it (`.map-body`). The
legend is drawn below that box and adds its own height to the plate.** So a screen that states
400px gets 400px of map, and the plate it sits in is 400px plus the legend's rows — a card that
gives a plate a fixed box gets a box of that size plus the legend, which is therefore never
clipped. A plate with no legend is exactly its stated height.

Set it wherever it inherits from — the card the plate is in:

```css
.your-module .case-file-where { --map-plate-height: min(46vh, 440px); }
```

or hand it to `render_map()`, which lifts any custom property in the attributes onto the plate
rather than onto the map element inside it:

```twig
{{ render_map(map, {'--map-plate-height': 'min(46vh,440px)', 'role': 'img', 'aria-label': 'Where'}) }}
```

Never size a plate with `min-height` plus `flex: 1` on your own card. That is the rule this
replaced, and it is how a map ends up over a thousand pixels tall beside a long column of facts.

## The boundary

The ground a plate is about, with everything outside it dimmed:

```php
use Uhifadhi\Bundle\AtlasBundle\Model\Boundary;

$map->boundary(new Boundary($geoJson, scrim: true));
```

It is not a layer: it has the platform's one treatment (a white casing under a jade line, no
fill), and its scrim covers the world with the outline punched out of it, so the scrim's bounds
are the planet and fitting a map to them would zoom every plate out to nothing. The DIM control
switches the scrim; `scrim: false` only decides whether it starts on.

## The legend

Every layer states its own row. A map adds rows for colours that have no layer behind them:

```php
use Uhifadhi\Bundle\AtlasBundle\Model\LegendItem;

$map->addLegendItem(new LegendItem(label: 'Boundary only', swatch: '#B9C8BD'));
```

A row with a `layerId` is a switch; a row without one is a key. The boundary is reachable as a
switch under `AtlasMap::BOUNDARY_LAYER_ID`:

```php
$map->addLegendItem(new LegendItem(
    label: 'Boundary',
    swatch: '#49E6B4',
    shape: LayerShape::Line,
    group: 'The area',
    layerId: AtlasMap::BOUNDARY_LAYER_ID,
));
```

Rows sharing a `group` are drawn together under that heading, in the order their first row
appeared — which is how a plate with four contributors stays readable.

### Where it is drawn

**Below the map, as a wrapping row of groups** — the design workspace's `.maplegend` under its
`.viewer`: 22px between groups, 12px under the plate, on the page's own ground. A legend floating
in the imagery covers the ground it describes, and on a short plate — an incident report's 176px
rail, a thumbnail — it covers most of it.

**In fullscreen it floats**, back in the imagery's bottom-right corner on a dark panel, under the
control stack's z-index so the controls stay clickable: there the screen is all map, so the legend
has imagery to spare and nothing on a page to sit beside. It is the same element and the same
switches in both — only the stylesheet changes, and a module says nothing about either.

## Base layers, fullscreen and fitting

```php
use Uhifadhi\Bundle\AtlasBundle\Model\BaseLayer;

$map
    ->baseLayers(BaseLayer::Satellite, BaseLayer::Street)  // the first is what it opens on
    ->fullscreen(true)   // off for a plate that already is the whole screen
    ->fit(true)          // off for a plate whose centre and zoom are the point
;
```

With `fit(false)`, say where to look through the UX Map map underneath.

## What UX Map already models

Markers, polygons, polylines, circles and rectangles stay UX Map's. Use its classes and add them
to the map underneath:

```php
use Symfony\UX\Map\InfoWindow;
use Symfony\UX\Map\Marker;
use Symfony\UX\Map\Point;

$map->ux()->addMarker(new Marker(
    position: new Point(-3.2, -29.5),
    title: 'The eastern station',
    infoWindow: new InfoWindow(content: '<a href="/stations/7">Open the station &rarr;</a>'),
));
```

`ux()` is also where centre, zoom, min and max zoom live.

Your own data for the browser rides beside the atlas's, never underneath it:

```php
$map->extra(['sightings' => ['season' => 'dry']]);
```

## render_map()

```twig
{{ render_map(map) }}
```

The second argument is attributes for the map element — an aria-label, a role, a data attribute
of your own:

```twig
{{ render_map(map, {'role': 'img', 'aria-label': 'Sightings this week'}) }}
```

The third is the filter row, one row above the map and inside the plate, so it comes along into
fullscreen:

```twig
{% set filters %}
    <a class="chip on" href="?since=week">This week</a>
    <a class="chip" href="?since=month">This month</a>
{% endset %}

{{ render_map(map, {'role': 'img', 'aria-label': 'Sightings'}, filters) }}
```

What it emits is the plate: a flex column carrying the map body — the filter row and the imagery
frame with the UX Map element inside it — and the legend below it. The plate root is the
fullscreen element and the body grows to fill it, which is why a module never writes those rules
— the atlas stylesheet owns them, and a card that clamps a height cannot break a plate inside it.

### A filter change keeps fullscreen

A filter row needs nothing else to work on an expanded map, whether its chips are a GET form or
same-origin links. While the plate is fullscreen it answers the submission — or the click — itself:
it fetches that address, swaps its own three subtrees — the filter row, the map element and the
legend — out of the answer, and writes the new address into the bar without navigating. Leaving
fullscreen then navigates once, because the log and the counts outside the plate are still
answering the query the page was served with. Outside fullscreen nothing is intercepted and the
row behaves as its markup says.

A chip clicked with a modifier, with the middle button, or carrying a `target` of its own is left
to the browser: that is somebody asking for a second page, not for a different filter.

A module writes no JavaScript for this and no attribute either. What it must not do is take the
plate's hook (`data-atlas-plate`) off the root or wrap the filter row in another element of its
own — the swap finds this plate in the fetched page by that hook, and its subtrees by their
classes.

## The events

The plate dispatches its own lifecycle, for an installation that needs to extend a map without
forking anything. A module should not need these.

| Event | Detail | When |
|---|---|---|
| `atlas:map:connect` | `{map, L, layers}` | the plate has drawn everything and mounted its chrome |
| `atlas:map:layer:added` | `{id, layer}` | one layer has been built, before it may have been filled |

`layers` is a `Map` keyed by layer id, with the boundary under `atlas.boundary`.

UX Map's own events — `ux:map:pre-connect`, `ux:map:connect`, `ux:map:*:before-create` — fire on
the same element and are documented upstream.

## A whole module template

```twig
{# templates/sightings/plate.html.twig (your module) #}
{% extends '@Shell/page.html.twig' %}

{% block stylesheets %}
    {{ parent() }}
    {# The platform's one map stylesheet: the plate, the chrome, the legend and
       the fullscreen rules. Leaflet's own sheet arrives with the map. #}
    <link rel="stylesheet" href="{{ asset(constant('Uhifadhi\\Bundle\\AtlasBundle\\AtlasBundle::STYLESHEET')) }}">
{% endblock %}

{% block shell_page %}
    <div class="c">
        <span class="tab">Sightings<span class="src">&middot; this week</span></span>

        {% set filters %}
            <a class="chip on" href="?since=week">This week</a>
            <a class="chip" href="?since=month">This month</a>
        {% endset %}

        {{ render_map(map, {'role': 'img', 'aria-label': 'Sightings this week'}, filters) }}
    </div>
{% endblock %}
```

And the screen behind it:

```php
// src/Controller/SightingsController.php (your module)
return new Response($this->twig->render('@Sightings/sightings/plate.html.twig', [
    'map' => $this->map->forArea($areaUuid),
]));
```

That is the whole of it. No controller file in `assets/`, no `controllers.json` entry, no
Leaflet, no chrome markup, no legend markup.

## What a module must not do

- **Do not create a map yourself.** `new Map()` from UX Map skips the imagery, the control stack
  and the fullscreen rules, and the result looks like a different product.
- **Do not ship a map Stimulus controller.** If a plate cannot say what you need, the gap is in
  the atlas and belongs here.
- **Do not link Leaflet.** There is one on the page and the UX Map bridge brings it.
- **Do not style the plate.** `.map-plate`, `.map-body`, `.viewer`, `.map-filters`, `.map-legend` and the
  chrome classes are the atlas's vocabulary; a module that restyles them makes its own map the
  odd one out, and a module that clamps a height around one breaks its fullscreen.
