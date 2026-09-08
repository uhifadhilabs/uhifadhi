# AtlasBundle

The **atlas**: the platform's map machinery — self-hosted Leaflet, one basemap contract with a
configurable satellite provider, the boundary drawing, and the chrome every map in the product
wears.

One of the bundles of the uhifadhi core, `uhifadhi/uhifadhi`. It can be installed on its own as
`uhifadhi/atlas-bundle`.

## Contents

- [What it is](#what-it-is)
- [Install](#install)
- [Getting started](#getting-started)
- [Learn more](#learn-more)
- [License](#license)

## What it is

Mechanism, not a screen: this bundle owns no entities and no pages. What it owns is everything a
map is made of before anyone decides what to draw on it.

It is **infrastructure**, not a capability. The platform has two tiers:

- a **capability** module (patrol, incident) is the per-area grid — an admin switches it on, it
  arrives default-off, and it is ledgered per area in `area_module`;
- **infrastructure** is machinery every relevant screen already imports — installed means on,
  everywhere, never a per-area choice, never in the catalogue, the grid, or the ledger.

The atlas is infrastructure because patrol plates, incident plates, the area overview and the zones
editor all draw with its assets: an installation without it does not have fewer features, it has
broken screens. That is not an opt-in, so it is not offered as one. It contributes **no**
`uhifadhi.module` provider — nothing for the registry to collect, no catalogue tile, no ledger row,
no route to gate. It is guaranteed present by the composer graph instead: `AreaBundle` requires it.

## Install

The core is one package:

```bash
composer require uhifadhi/uhifadhi
```

Flex registers the bundle, writes `config/packages/atlas.yaml`, and writes the three importmap
entries.

The bundle registration (`config/bundles.php`) — the recipe's `bundles` block:

```php
Uhifadhi\Bundle\AtlasBundle\AtlasBundle::class => ['all' => true],
```

The three importmap entries are written by Flex, not by the recipe; what lands in a host, and the
two conditions it depends on, are in [shipping importmap assets from a
bundle](docs/importmap-assets.md).

## Getting started

**Publish the configured provider on your `<body>`** (`templates/base.html.twig`) — the one
line still yours to write, because it goes in a template only you own:

```twig
<body {{ map_basemap_attributes() }}>
```

Every map on every page then draws the configured imagery — the host's own maps and each module's
plates alike. There is no per-template and no per-module wiring, which is the point: the same layer
must render identically everywhere.

Leaflet needs no step at all. Link it from your layout with the bundle's own constants, so the path
is never typed twice:

```twig
<link rel="stylesheet" href="{{ asset(constant('Uhifadhi\\Bundle\\AtlasBundle\\AtlasBundle::LEAFLET_CSS')) }}">
<script src="{{ asset(constant('Uhifadhi\\Bundle\\AtlasBundle\\AtlasBundle::LEAFLET_JS')) }}"></script>
```

Leaflet is a classic script that publishes `window.L`, and the map controllers read it from there —
a `<script>` in `<head>` has run before the deferred importmap modules connect, which is exactly
the ordering they rely on.

The satellite imagery is `esri` by default and needs no key; choosing another provider is
[choosing the satellite imagery](docs/satellite-imagery.md).

## Learn more

- [Choosing the satellite imagery](docs/satellite-imagery.md) — the `esri` / `google` / `custom`
  providers, their configuration, and why the default is keyless.
- [What the bundle ships](docs/what-the-bundle-ships.md) — the assets, their import names, and why
  MapLibre is not among them.
- [Shipping importmap assets from a bundle](docs/importmap-assets.md) — how the three entries reach
  a host's `importmap.php`, and the sharp edges of doing it from a package.
- [Development](docs/development.md) — the standard, and running `composer check`.

## License

**AGPL-3.0-or-later** — see the core's [LICENSE](../../../../LICENSE).
