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
- [The boundary](#the-boundary)
- [The legend](#the-legend)
- [Base layers, fullscreen and fitting](#base-layers-fullscreen-and-fitting)
- [What UX Map already models](#what-ux-map-already-models)
- [render_map()](#render_map)
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

What it emits is the plate: a flex column carrying the filter row, the imagery frame with the UX
Map element inside it, and the legend floating over the frame's bottom-right corner. The plate
root is the fullscreen element and the frame grows to fill it, which is why a module never
writes those rules — the atlas stylesheet owns them, and a card that clamps a height cannot
break a plate inside it.

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
- **Do not style the plate.** `.map-plate`, `.viewer`, `.map-filters`, `.map-legend` and the
  chrome classes are the atlas's vocabulary; a module that restyles them makes its own map the
  odd one out, and a module that clamps a height around one breaks its fullscreen.
