# UPGRADE FROM 0.x to 1.0

## A layer names a token, and a zone names a category

**What changed.** Nothing that draws on a map or in a legend takes a colour any
more. `AtlasBundle\Model\GeoJsonLayer::$swatch`, `AreaBundle\Overview\MapLayer::$swatch`
and `AreaBundle\Overview\PulseEvent::$swatch` now REFUSE a hex and take a token
name — `Uhifadhi\Contracts\Atlas\PlatePalette::OK`, or
`PlatePalette::category($position)` for one of a set. The plate resolves the
name in the browser where it draws, and again when the theme flips.

**Why.** A module cannot know what green is here: the palette turns over with
the theme and again on imagery, so a colour published across the seam was right
on one basemap and wrong on the next with nothing on the page to say so.

**What to change in a module.** Replace every literal you hand to a layer, a
legend row or a pulse event:

```diff
-new MapLayer(..., swatch: '#E05B41', ...)
+new MapLayer(..., swatch: PlatePalette::FAIL, ...)

-new MapLayer(..., swatch: $this->palette->hueFor($n), ...)
+new MapLayer(..., swatch: PlatePalette::category($position), ...)
```

A module with a `HousePalette`-style class of its own can delete it.

**Deprecated, and removed in the NEXT release.** `ZoneRow::$hue`,
`ZoneListRow::$hue`, `StationRow::$zoneHue` and `FilterOption::$hue` are still
there and still readable; each now resolves from its category and returns the
token the palette would have given it. Read `$cat` / `$zoneCat` instead — an
`int` 1 to 9, the thing's position in its own declared order. They go in the
release after this one, on the two-release rule: a shipped module reading a
property that vanished is a 500 on somebody else's page.

## PostGIS comes from `utafitilabs/postgis-bundle`

The spatial repository base and the geometry DBAL types the area's entities are
mapped with now come from `utafitilabs/postgis-bundle`. Both packages register
the same DBAL type names, so the old one cannot stay alongside it:

```bash
composer remove fundistadi/postgis-bundle
composer require utafitilabs/postgis-bundle:^0.1
```

In `config/bundles.php`:

```diff
-FundiStadi\PostGISBundle\FundiStadiPostGISBundle::class => ['all' => true],
+UtafitiLabs\PostGISBundle\UtafitiLabsPostGISBundle::class => ['all' => true],
```

And if the installation configures the bundle, its root key is now
`utafiti_labs_post_gis`, not `fundi_stadi_post_gis`. Nothing about the database
changes: the extension, the columns and the indexes are the same.
