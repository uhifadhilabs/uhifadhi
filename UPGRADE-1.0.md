# UPGRADE FROM 0.x to 1.0

## A performance topic publishes FOUR headline figures, not five

**What changed.** `Uhifadhi\Contracts\Performance\PerformanceTopicProviderInterface::kpis()`
now returns **exactly four** `TopicKpi`s, in one row. A figure row is four to
a row everywhere in the product (ruled 2026-09-21), and a record does not get
two rows of four either: the fifth card wrapped an orphan onto a second line
on a small laptop.

The host's three topics were ported from the design, label for label and in
order:

| topic | the four | what went, and where |
| --- | --- | --- |
| Staffing | Positions · Filled · Vacant · Over threshold | **People** — a position is one post held by one person, so it was the same number and the same movement as Filled |
| Goals | Declared · Met · Off track · No figure yet | **At risk** and **Missed** folded into one card; the fragment keeps them apart ("2 at risk · 1 missed") and the Briefing still asks about them separately |
| Attention & output | Items raised · Unowned · Resolved · Records | **Measuring** and **Folded** became the first card's caption — they say how much of the organisation the other figures are about, which is what a caption is for |

**What a module topic must change.** Return four. The design named the drop
for the two shipped module topics, and each is that module's own commit:

  - **incident-module** drops *Claims open*
  - **patrol-module** drops *Out right now*

A topic with less to say fills the fourth slot with a figure that states its
own absence (a null value with a caption), rather than returning three — a
short row and a quiet month look identical otherwise. A topic with more to say
FOLDS two verdicts a reader acts on the same way into one card and keeps both
in the fragment.

**One new role.** `KpiRole::ItemsResolved` — items the module closed in this
period. A module that raises items should publish it: without it a rising
count of raised items cannot be read as either a rising workload or a standing
one being worked through, and those call for opposite decisions. Until a
module publishes it the card states its own absence rather than reading zero.

## A module with two widget surfaces has one library page

**What changed.** `@Shell/widget/_library.html.twig` now takes either shape.
The single-surface call is exactly what it was — `catalog`, `builtins`,
`customPresets`, `active`, `widgets`, `partial`, `widgetContext`, `urls`,
`csrfToken`, passed flat — and renders exactly what it rendered before, with no
section wrapped around it. A module with more than one surface passes
`surfaces` instead: a list of those same bags, each optionally carrying `label`
and `intro`, and gets one `h2.zone` + `p.pgsub` section per surface.

The body moved to `@Shell/widget/_library_surface.html.twig`. Nothing includes
that by hand — the section chrome and the arming of several roots on one page
are the entry point's business — but a sheet or a test that pointed at the old
file's contents should point at the new one.

**Why.** The roster's Live plate rail is a widget surface beside the module's
Overview (ruled), and a module's widgets are configured in one place. Two
library pages for one module is two answers to "where do I change my widgets".

**What to change in a module.** Nothing, unless you have a second surface. If
you do:

```diff
-{{ include('@Shell/widget/_library.html.twig') }}
+{{ include('@Shell/widget/_library.html.twig', {surfaces: surfaces}) }}
```

and give every `[data-widget-reset]` button in the page header the surface it
resets: `data-widget-reset="roster-live-rail"`. A bare one is still honoured
where the page has a single library, and ignored where it would be ambiguous —
a button that does not say which surface it resets is not one anything can act
on safely.

## The sidebar decides what is open, and a page no longer can

**What changed.** `ShellBundle\Model\NavItem::$open` is DERIVED. The shell
reads where the viewer is from the row a source marked `current` and opens the
path to it and nothing else; whatever a source passed for `$open` is
overwritten. The argument is still on the constructor for this release so an
installed module that names it does not fail to construct a row, and it goes in
the next one.

Two classes come out of that derivation, and they replace the old pair:

| was | is now |
| --- | --- |
| `current` on every rung of the path | `on` on the row the viewer is on, once per sidebar |
| `.nta.cur`, the place rung's own marking | `.path` — accent ink, no ground — at every rung above the current row |

`.nta.cur` is gone from `shell.css`. A module stylesheet that drew something on
it should read `.path`, which is the same rows at every depth rather than only
at the second.

**Why.** Each source derived its own subtree, so a page's tree read differently
depending on which bundle drew it, and everything a source could open was open:
Areas stayed unfolded while you were in Files. Ruled 2026-09-20 with the
nav-states frames — only the ancestor path of the current page is open, one rung
at a time, one ground per tree.

**What to change in a module.** Drop `open:` from the rows you contribute and
mark the path with `current`, which you were doing anyway. A manual fold is the
viewer's, kept for the tab's session by the shell's own controller against the
`data-nav-key` the shell prints; nothing to do for it.

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
