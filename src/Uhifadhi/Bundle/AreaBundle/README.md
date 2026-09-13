# AreaBundle

**Area**: the named piece of ground an installation manages — its gazetted
boundary, the zones inside it, the overview every module contributes to, and the
answer to the platform's area contract so nothing has to be written by hand.

One of the bundles of the uhifadhi core, `uhifadhi/uhifadhi`. It can be
installed on its own as `uhifadhi/area-bundle`.

## Contents

- [What it is](#what-it-is)
- [What it provides](#what-it-provides)
- [Installation](#installation)
- [The area](#the-area)
- [The zone](#the-zone)
- [Modules point at your ground](#modules-point-at-your-ground)
- [What a module contributes to an area](#what-a-module-contributes-to-an-area)
- [What a field client caches](#what-a-field-client-caches)
- [The screens](#the-screens)
- [Where you are, and what is in the sidebar](#where-you-are-and-what-is-in-the-sidebar)
- [An area is not a module of itself](#an-area-is-not-a-module-of-itself)
- [The stylesheet](#the-stylesheet)
- [License](#license)

## What it is

**Uhifadhi is one skeleton, one core and a set of modules.** The skeleton
(`uhifadhi/skeleton`) is copied once and never updated; the core
(`uhifadhi/uhifadhi`) arrives whole and is updated forever; everything a
deployment can *do* is a module.

This bundle is **where**. An area is the axis the whole product is filed under:
a patrol happens in one, an incident is reported in one, a module is switched on
for one. The registry carries the modules, the shell is what you see, team is
who is looking, and this is the ground they are all talking about.

## What it provides

| | What an installation gets |
|---|---|
| the area | `Uhifadhi\Bundle\AreaBundle\Entity\AreaOfInterest` — a name, gazetted facts and a boundary as a PostGIS multipolygon |
| the zone | a named polygon subdividing one area, held to a no-shared-interior invariant no column constraint can express |
| the area contract's answer | `doctrine.orm.resolve_target_entities` for `Uhifadhi\Contracts\Entity\AreaInterface`, prepended, so a module that points a record at an area has an area to point at |
| the overview | one area's page, composed from what the modules that area runs contribute — tiles, attention rows, map layers, pulse events and copy |
| the register | every area as a wall of cards, with the widget library behind it |
| the screens | the register, create, one area's overview, its module grid, its module shop, its zones and its settings |
| the sidebar section | Areas, and every area unfolding to its own screens, where a shell is installed |
| the KPI contract | the figures a department's performance surfaces read from the modules attached to it |
| the field cache | `GET /api/areas/mine` — the areas an account may work in, with their size, roster and boundary, for a client that works offline |

## Installation

The core is one package:

```bash
composer require uhifadhi/uhifadhi
```

Flex adds `Uhifadhi\Bundle\AreaBundle\AreaBundle` to `config/bundles.php` and
mounts the routes. There is **no `config/packages/area.yaml`**, because there is
nothing here an installation would set.

### Then the tables

Your database needs PostGIS:

```bash
bin/console doctrine:migrations:migrate
```

Two tables, `area_of_interest` and `zone`, and the bundle ships the versions
that create them — `migrations/`, namespace
`Uhifadhi\Bundle\AreaBundle\Migrations`, registered from the bundle's own
`prependExtension()`, so an installation configures nothing. The first of them
is `CREATE EXTENSION IF NOT EXISTS postgis`, dated before every version in the
core, because both geometry columns and their GiST indexes need the extension to
exist first; a hosted database that will not grant `CREATE EXTENSION` needs
PostGIS enabled by its provider, after which that version runs and does nothing.
`doctrine:migrations:diff` stays what an installation runs for the entities IT
writes.

## The area

| Field | What it is |
|---|---|
| `name` | What the area is called |
| `geom` | The boundary — a **MultiPolygon** in WGS84, exchanged as GeoJSON, and **nullable** |
| `source` | Where the boundary came from: a register's name, a file's name, `drawn` |
| `iucnCategory` | IUCN protected-area category (`II`, `VI`, …), optional |
| `establishedYear` | Year gazetted, optional |
| `uuid` | The public identifier — every URL names an area by this |
| `createdAt` / `updatedAt` | Stamped by lifecycle callbacks |

**MultiPolygon, not Polygon**, because a gazetted boundary is regularly more
than one ring: an enclave, an outlying block, a lake excluded from the middle.
The column is `fundistadi/postgis-bundle`'s geometry type, so PostGIS is a
requirement of the database.

**The boundary is nullable, and the gazetted facts are optional.** An area is
named and gazetted before anybody has its edge, and an installation that drew
its own boundary on a map has no IUCN category and no gazettement year. A
consumer asks `hasBoundary()` and treats "unmeasured" as its own answer — never
as zero.

**Addressed by UUID.** The sequential `id` exists so foreign keys are cheap and
never appears in a URL. The repository extends the PostGIS bundle's spatial
base, so `stAreaKm2()` and `findStIntersecting()` are there without a line of
SQL here.

## The zone

A **zone** is a named polygon subdividing one area — the spatial lens, the way a
department is the organisational one. Zones are data an admin draws or uploads,
never code: a module asks generic questions of them ("which zone is this point
in?") and never names one, because the names are one installation's geography.

| Field | What it is |
|---|---|
| `name` | What the zone is called — unique **per area**, so two areas may each have a "North" |
| `area` | The area it subdivides — `NOT NULL`, and the foreign key cascades |
| `geom` | A **MultiPolygon** in WGS84, like the area's |
| `uuid` | The public identifier |
| `createdAt` / `updatedAt` | Stamped by lifecycle callbacks |

**Sibling zones never share interior.** Adjacency is legal — two zones may meet
along an edge — and so are gaps, because an area is regularly only partly zoned.
That is the DE-9IM pattern `T********` and *not* `ST_Overlaps`, which PostGIS
defines as false when one geometry contains another; containment is a conflict
the invariant has to catch. The rule is not expressible as a column constraint,
so **`ZoneService` is the only supported way a zone gets a geometry** — anything
that writes one around it writes an overlap nobody notices until a point falls
in two zones at once.

**An area with no zones is the normal state.** `ZoneService::zoneOf()` answers
`null` without complaint.

## Modules point at your ground

A module that keeps a record filed under an area type-hints the contract, never
this bundle's class:

```php
#[ORM\ManyToOne(targetEntity: AreaInterface::class)]
private ?AreaInterface $area = null;
```

Registering this bundle prepends the resolution:

```yaml
# what the bundle prepends for you — you do not write this
doctrine:
    orm:
        resolve_target_entities:
            Uhifadhi\Contracts\Entity\AreaInterface: Uhifadhi\Bundle\AreaBundle\Entity\AreaOfInterest
```

An installation writes a `resolve_target_entities` line only to **disagree**,
naming its own class — application configuration beats a bundle's prepend, which
is what makes shipping the default safe rather than presumptuous. That the
override wins is tested rather than assumed
(`tests/Integration/Resolution/ResolveTargetEntitiesTest`).

## What a module contributes to an area

`/areas/{uuid}` is the surface whose widgets are not written by whoever owns the
page. This bundle owns the surface, the grid and the identity of the area; every
operational widget arrives from a module installed in that area.

| Interface | Tag | What a module puts on the page |
|---|---|---|
| `Overview\OverviewContributorInterface` | `uhifadhi.overview.widget_provider` | Its own widgets, and the library section they sit under |
| `Overview\NowTileProviderInterface` | `uhifadhi.overview.now_tile` | A tile in the right-now strip |
| `Overview\AttentionProviderInterface` | `uhifadhi.overview.attention` | A row in "Needs attention" |
| `Overview\MapLayerProviderInterface` | `uhifadhi.map.layer` | A layer on the operational plate, with its legend |
| `Overview\PulseProviderInterface` | `uhifadhi.overview.pulse` | Its moves in the area pulse |
| `Overview\OverviewCopyProviderInterface` | `uhifadhi.overview.copy` | Its own words inside a sentence somebody else writes |
| `Overview\ContributesStylesheetInterface` | — | The stylesheet its markup needs, since somebody else renders it |

**Absent is never zero.** Every one of these may answer `[]`, and that is the
right answer rather than a gap: a module with nothing to say puts no tile in the
strip instead of a tile reading 0, and an unmeasured figure renders as a dashed
slot instead of a `0%` that claims a measurement nobody took.

**Tag explicitly, and write the tag as a literal.** A reusable bundle is not
autoconfigured, so a contributing module tags its provider by hand in its own
extension — and writes `'uhifadhi.overview.now_tile'` rather than reading
`NowTileProviderInterface::TAG`, because reading the constant loads a class from
a package that need not be on its build classpath. The constant and the literal
are kept equal by `tests/Unit/Overview/ContributionContractTest.php`; change one
without the other and contributions land in a tag nobody collects, with no error
anywhere.

**A department's figures are a different seam, published elsewhere.** A module
puts numbers on a department's performance surfaces through
`Uhifadhi\Contracts\Kpi\DepartmentKpiProviderInterface` (`uhifadhi.department_kpi`),
which `uhifadhi/contracts` publishes because the department that owns those
surfaces is TeamBundle's, not this bundle's. It takes a `DepartmentRef` — an id,
a uuid and a name — since nothing published describes a department: typing it
against Team's entity would make every module that reports a figure depend on
Team, and typing it against nothing would hand providers an `object` to guess
at.

## What a field client caches

A handset works for days out of signal, so it fills a cache at sign-in and then
asks nothing. `GET /api/areas/mine` is that cache:

```jsonc
// 200
{
  "areas": [
    {
      "id": "0192f3c1-…",                    // the public address, never the sequential key
      "name": "Northern Conservation Reserve",
      "areaKm2": 9903.4,                     // ST_Area on the spheroid, to a tenth
      "stations": [],
      "team": [{ "id": "sl-0142", "name": "…" }],
      "boundary": { "type": "MultiPolygon", "coordinates": [ /* lon, lat */ ] }
    }
  ]
}
```

**"Mine" is the platform's own authority question, asked once per area.** An area
is in the answer when `area.view` is granted *for that area* — the same question
every screen here asks — so a tier and an org-level position are handed every
area, somebody whose department is confined to one area is handed that one, and an
account that may see nothing is handed an **empty list rather than a 403**: a
refusal here would stop a client syncing instead of showing an empty picker.

**`boundary` is GeoJSON in lon/lat order** (RFC 7946, which is also PostGIS's
order), simplified in the database to roughly 55 m before it is sent — a phone
draws it at zooms where a vertex every few metres is invisible. It is a
`MultiPolygon` or a `Polygon` depending on whether the ground is one piece, and
`null` for an area whose edge has not been imported, which also measures `0.0`.

**`stations` is present and empty.** This platform has no station record;
publishing the station names typed on past work as a register would hand a client
a list of guesses with positions nobody holds.

**`team` is the roster, and it is asked of the contract.** This bundle owns ground,
not people: it reads `Uhifadhi\Contracts\Entity\UserInterface`, so whichever
entity an installation resolved that interface to is what answers. Everybody is
listed, the deactivated included — the list is who may be *named* on a record, not
who may sign in.

The endpoint is registered **only where both ApiPlatformBundle and SecurityBundle
are in the kernel**. Without api-platform there is no `/api` to attach to; without
security there is no authorization checker, and a list of the areas somebody may
work in must never widen because the thing that narrows it was missing.

## The screens

Seven, mounted from `config/routes/area.yaml` and yours to prefix, restrict or
remove.

| Screen | Route | Gate |
|---|---|---|
| The register — every area | `area_index` · `/areas` | `area.view` |
| The register's widget library | `area_widgets` · `/areas/widgets` | `area.view` |
| Create an area | `area_new` · `GET,POST /areas/new` | `area.create` |
| One area's overview | `area_show` · `/areas/{uuid}` | `area.view` |
| Its module grid | `area_modules` · `/areas/{uuid}/modules` | `module.view` |
| Its module shop | `area_module_customize` · `/areas/{uuid}/modules/customize` | `module.create` |
| Its zones | `area_zones` · `/areas/{uuid}/zones` | `area.view` |
| Its settings | `area_settings` · `/areas/{uuid}/settings` | `area.edit` |
| Edit its identity or boundary | `area_edit` · `GET,POST /areas/{uuid}/edit` | `area.edit` |

Three POST addresses sit under the shop and carry the same `module.create` gate
plus a CSRF token scoped to the area: `area_module_install`,
`area_module_uninstall` and `area_module_reorder`.

**Gated on the platform's permission strings and on nothing else.**
`area.view`, `area.create`, `area.edit`, `area.delete`, `module.view` and
`module.create` are in the catalogue TeamBundle ships, and its voter answers them
at runtime. This bundle names them and depends on Team for nothing, so an
installation may answer them with something else without touching a screen.

**Addressed by UUID.** Every route carries the uuid requirement, so `/areas/2`
is a 404 rather than a sequential key anybody can walk.

**The screens are conditional; the model is not.** The wiring for them lives in
`config/screens.php` and is imported only where the application has both
TwigBundle and SecurityBundle — an installation that wants the area MODEL and no
pages (a console importer, an API) still boots, where a controller depending on
a non-existent `twig` service would have failed at compile time.

### Creating an area

A name and a **GeoJSON** boundary, gated on `area.create` — and the button on
the register carries the same gate, because a control that opens onto a refusal
is a worse answer than no control.

The file may be a `Polygon`, a `MultiPolygon`, a `Feature` or a
`FeatureCollection`; all four become the one `MultiPolygon` the column takes, and
several features are **merged into one boundary** rather than the first one
taken.

**Nothing is reprojected.** RFC 7946 defines GeoJSON as WGS84, which is what the
column's typmod declares. **Nothing is parsed in PHP either**: the geometry
reaches PostGIS as GeoJSON and `ST_GeomFromGeoJSON` decides validity at the
insert, with the whole boundary in hand.

A refusal is **the same page with a sentence on it and a 422** — never a
redirect and never an error page. The typed name survives it.

**Every write carries a CSRF token.** `area.create` answers *who* may create an
area; the token answers whether *this page* asked, and a permission is no
defence against a form on somebody else's site posting here with the viewer's
own cookie.

### The module grid and the shop

The grid is a reading of the registry's catalogue against one area's ledger.
"An area" is this bundle's word — the registry holds that ledger for
installations whose area model is their own and cannot name an area class, let
alone draw a page about one — so the registry publishes the data and this bundle
draws it.

**The picture is the shell's.** Tiles render through
`@Shell/_module_grid.html.twig`, the same partial a department page would use,
because the catalogue picture must look identical wherever it appears. What is
not the shell's is *which* cards, in *which* groups, with *which* URLs: that
needs the area, the viewer and the ledger.

**The grid shows what is ON.** A tile for a parked module would open onto a page
the area has switched off; parked modules live in the shop, which is the screen
for changing your mind about them. A tile whose module declares no entry route,
or whose route this installation has not mounted, is **inert rather than a
link**.

**Every write goes through the registry's `AreaModuleService`** — nothing here
writes an `area_module` row by hand, because the rule that a pinned module
cannot be parked lives there and a second writer would eventually disagree with
it.

## Where you are, and what is in the sidebar

This bundle answers two of the shell's contracts, and **both read one list** so
they cannot disagree:

- `AreaShellSourceInterface` — the tab strip above an area, and the area's name
  for the page title. Aliased to `shell.area_shell_source`; an installation
  whose areas are its own model aliases that id to its own class instead.
- `NavigationSourceInterface` — the **Observatory → Areas** section, the
  register and every area unfolding to its own screens. Tagged
  `shell.nav_section`.

Both are **route-tolerant**: unmount a route and its tab or row is simply absent
rather than every page failing, so removing a screen is a supported thing to do.

**A tab or row the viewer may not have is ABSENT, never greyed out.** A disabled
"Settings" tells a ranger a screen exists and they are not trusted with it,
which is a worse product than not mentioning it.

## An area is not a module of itself

A capability module carries the `uhifadhi.module` tag and takes a tile in the
catalogue. That catalogue is indexed **by area** — the registry's `area_module`
row says "this area has this bundle switched on" — so a provider here would
write a row for every area saying that the area has areas: switchable,
meaningless, and shown in the module grid of the page it is the subject of.

Areas are the **axis** the catalogue is indexed by, not an entry in it: this is
the thing an area *is*, not a capability an area *has*. The absence of the tag is
pinned by `tests/Integration/CatalogueAbstentionTest`.

## The stylesheet

`bundles/area/area.css`, named by `AreaBundle::STYLESHEET` and linked by
`templates/_stylesheets.html.twig` after the shell's own. It declares **no
colours and no fonts of its own** — every value is one of the shell's `--c-*`
tokens or `--font-mono` — so theming the shell themes these screens and dark
mode needs nothing here. It also declares no class the shell already ships;
`tests/Unit/Template/StylesheetVocabularyTest` fails the build on a name spent
by nobody or stated twice.

## License

**AGPL-3.0-or-later** — see the core's [LICENSE](../../../../LICENSE). Use,
modify and self-host freely; if you offer a modified version to users over a
network, they are entitled to the source of what they're running.
