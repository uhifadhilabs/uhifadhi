# UPGRADE FROM 0.x to 1.0

## `/` is the organisation dashboard

**What changed.** The core ships a dashboard at `/` — a widget surface
composed from contributors, exactly as an area's overview is, one scope
wider. It is an ordinary attribute route on `AreaBundle`'s controllers, so an
installation that already imports them has it; the page that used to answer
`/` is the shell's welcome screen, whose content now lives at **Settings →
Overview**.

**The brandmark.** `shell.home_route` defaults to `organisation_dashboard`.
An installation with its own front door sets its own route name, as before.

**What a module contributes.** A new seam beside the area's, opted into
deliberately:

```php
// src/Org/PatrolOrgWidgets.php
final class PatrolOrgWidgets implements OrgOverviewContributorInterface
{
    public function moduleSlug(): string { return 'patrols'; }
    public function group(): WidgetGroup { /* your headed section */ }
    public function widgets(): array { /* your cells */ }
    public function partialPattern(): string { return '@Patrol/org/_w_%s.html.twig'; }
    public function figures(Scope $scope, \DateTimeImmutable $now): array { /* your NowTiles */ }
    public function context(Scope $scope, \DateTimeImmutable $now): array { /* what they read */ }
}
```

```php
$services->set('patrol.org_widgets', PatrolOrgWidgets::class)
    ->tag('uhifadhi.overview.org_widget_provider');
```

**Why it is a second interface rather than a wider first one.** The area
contract is answered against an area ENTITY by every installed module, and
widening its signature would break all of them for a screen most have no
org-level reading for. A module with nothing to say across areas says
nothing, and loses no cells on the area page.

**EVERY FIGURE IS THE PER-AREA READING ONE SCOPE WIDER — never a second
aggregate.** The `Scope` is handed in for exactly that: answer
`forScope($scope)` and let the organisation's answer BE the areas' answers.
The core holds itself to this (`PresenceService::forScope()`,
`AreaOverview::attentionForScope()`) and asserts it —
`OrgScopeIsTheSumOfAreasTest` proves the wide reading is the narrow ones
position for position, not two numbers that happen to match. A module that
grew a second aggregate would have two answers to one question and no way to
say which was right.

**A preset may name a cell you have not installed.** The five compositions
the dashboard ships name `watches`, `patrols`, `incidents`, `goals` and
`files`; the catalogue composes each design down to the cells this
installation actually has, so the same five get richer as it grows. Note the
distinction if you ship presets of your own: the DECLARATION is the design,
the CATALOGUE is this installation's composition of it, and a surface still
refuses a preset naming a cell it ships nothing for.

## A live position carries its own area's ping interval

**What changed.** `LivePosition::$pingIntervalMinutes` (new, optional, last)
and `LivePresence::isStale()` reads it in preference to the set's.
`LivePositionsInterface` gains `forScope(Scope, \DateTimeImmutable)`.

**Why.** A reading across areas holds positions expected at different rates.
One interval for all of them calls a thirty-minute area's rangers stale
beside a five-minute area's on the same silence — and then the
organisation's stale count is not the areas' stale counts added up, which is
the property the whole widened-reading rule depends on.

**What to change in a module.** Nothing: only the core implements that
interface, consumers call `liveIn()` as before, and a per-area reading still
states its interval once on the set.

## `/favicon.ico` is answered, where the application asks for it

**What changed.** The shell ships a fourth route resource, and it serves the
same file the document head has always linked:

```yaml
# config/routes/shell.yaml (your application)
shell_favicon:
    resource: '@ShellBundle/config/routes/favicon.php'
```

**Why.** Every page declares `<link rel="icon">` and every browser asks for
`/favicon.ico` anyway — before the first page, on a redirect, on an error
page, and whenever it has nothing cached. Nothing answered, so the request
became an exception: on a staging installation the ONLY error group telemetry
had ever captured was `NotFoundHttpException … /favicon.ico`. A log whose
single entry is noise is a log nobody reads, and the next real error goes
into it unseen.

**What it serves.** The bundle's own `public/favicon.svg`, with
`image/svg+xml` and a week's public cache — the same bytes the head links, so
the tab icon cannot drift from the brandmark and there is one file to replace
when an installation wants its own. It is not a redirect to the digested
asset: a redirect is a second round trip for exactly the browsers that have
nothing cached.

**An installation that serves its own icon** from its web server or a CDN in
front of it leaves the import out, and the address is its own — which is why
this is a resource of its own rather than a line in the welcome page's.

## The settings section, and the route an application imports for it

**What changed.** The core ships a Settings section — `/settings`, with
**Installation**, **Modules** and **Organisation** as its tabs — and it is
reachable only where the application asks for it, exactly like the welcome
page and the configure page:

```yaml
# config/routes/shell.yaml (your application)
shell_settings:
    resource: '@ShellBundle/config/routes/settings.php'
```

Without that line there is no section and no row in the sidebar for one; the
navigation source generates the address and yields nothing when it cannot.
The constant is `ShellBundle::SETTINGS_ROUTES`, for the reason the other two
are constants: the string is written in the recipe, in the skeleton and in
every installation.

**The sidebar's last group.** `NavGroup::SETTINGS` holds one row whose
children are the section's tabs — the shape an area and the files section
already wear. The shell derives what is open from the row marked `current`,
so nothing about this needs an installation to configure anything.

**What lives there.** The first screen says **what this installation gives
you** — the parts of the core (read from each one's own manifest, never
typed), what each installed module adds and how many areas run it, the rules
the rest follows, and the set-up checklist. Behind it: what is installed and
at what version, which areas run which modules, the installation's health
checks, and who the installation belongs to. **Two figures and two columns state their own absence on an
ordinary installation**, and that is the honest reading rather than a gap:
whether a package is BEHIND needs a release feed to compare against, and when
the installation last DEPLOYED needs whatever deployed it to have said so.
Neither is a thing a running application can read about itself.

**What a module can contribute.** Five new contracts in
`Uhifadhi\Contracts\Settings`, each tagged by hand as usual:

| Tag | Interface | What it puts on the section |
| --- | --- | --- |
| `shell.settings_figure` | `SettingsFigureSourceInterface` | a card in the figure row |
| `shell.settings_check` | `SettingsCheckSourceInterface` | a row in the health list |
| `shell.settings_decision` | `SettingsDecisionSourceInterface` | an item in the queue |
| `shell.settings_change` | `SettingsChangeSourceInterface` | a row in what changed |
| `shell.settings_step` | `SettingsStepSourceInterface` | a row in the set-up checklist |

**A step is evergreen, and the contract is shaped so it cannot be otherwise.**
`SettingsStep` carries `standing` — *where this installation is* ("4
registered", "18 of 22 posted", "1 of 4 areas running") — and no "has it
begun" flag, because the first screen of Settings is where the welcome page's
content went and a page that is true only once is a page everybody stops
opening. `done` means nothing is outstanding TODAY: an installation that adds
a fifth area is not done with zones again until that area has some, and the
row says so the same day.

Two more facts have exactly ONE answer, so they are aliases rather than
collected lists — two things claiming to know which modules run in which
areas would be a disagreement with nothing to settle it:

```php
$services->alias(ModuleMatrixSourceInterface::SERVICE, MyMatrix::class);
$services->alias(OrganisationIdentitySourceInterface::SERVICE, MyIdentity::class);
```

Both are optional. An installation where nobody answers gets a screen that
says so — the identity falls back to the wordmark the shell was configured
with, and every other field reads "not set".

**A source that throws does not take the page.** This is the one screen
somebody opens to find out whether anything is wrong, so a check source that
fails becomes a row saying it could not be run, with the reason, and every
other check still answers. Write a check that is cheap: it runs on a page
render, and anything expensive is measured on a schedule and reported here
from what was stored.

**One class cannot carry two of these tags.** PHP refuses a class that
implements two interfaces each declaring a `TAG` constant. Where one fact has
two readings — a health row and a queue item — publish it as two small
sources over one shared reading, which is what the core does for the areas
that run no module.

## `.ao-att` is the frame's now

**What changed.** The needs-attention row moved out of `area.css` into
`shell.css` and joined `Contract\LayoutContract::COMPONENTS`. Three surfaces
draw one — an area's overview, the organisation dashboard and the settings
section — from three different owners' items, and a copy in a second sheet
would have been two rows that drift, whichever sheet happened to load last.

**What to change in a module.** Nothing. The markup and the three states
(`.now`, `.soon`, `.watch`) are what they were; a cell already writing them
keeps working, and now works on a page that does not load the area's sheet.
`OverviewVocabulary::HOST_CLASSES` no longer lists it, because it is no
longer the area overview's to lend.

## A mark says whose set it is

**What changed.** `VocabularyConformanceTestCase` gains
`testNoIconIsNamedWithoutSayingWhoseItIs`: no template, controller or
provider in a bundle may name an icon without its set —
`ux_icon('calendar-clock')`, `<twig:ux:icon name="plus">`, `icon: 'clock'`.

**Why.** A bare name resolves in the HOST's default set, so a module writing
one is betting that every installation happens to ship that glyph, and the
bet fails silently until the name is rendered somewhere that does not. A nav
row is rendered by the SHELL, in whatever application mounted the module —
one bare `calendar-clock` in a roster nav row took that module's whole suite
down in a fixture application with no icon directory at all.

**What to change.** Name the set: `<your-alias>:<name>` for a mark your
bundle ships in `assets/icons/<alias>/`, `shell:<name>` for one the shell
ships. The rule sits below the three that were already there — that a prefix
is one the bundle may use, and that a name under its own prefix resolves to a
file it ships.

## The scope control has a default, and the core ships it

**What changed.** `AreaBundle` ships `Shell\AreasTheViewerMayOpen`, tagged
`shell.scope_source`: the organisation plus every area the viewer holds
`area.view` on, asked WITH THE AREA AS SUBJECT — the same authority
`/api/areas/mine` asks. An installation now gets the scope control on
organisation-level pages with nothing wired.

**Why.** The shell draws the control only when something tags a
`ScopeSourceInterface`, and no installation did: the roster's organisation
pages rendered with the row, the strip and the figures right and no control
at all. A seam whose default is "nothing" ships a feature that passes its own
suite and is missing on every real page.

**One area is not a choice.** Where the viewer may open exactly one area, only
that area is offered, and the shell's existing rule (a control of one row is
not a control) leaves the action row empty.

**A host may still replace it** by redefining the `area.scopes` service — a
host that tags a second source of its own gets both lists, which is not what
anybody wants.

## The map stylesheet is in every head — delete your link

**What changed.** `bundles/atlas/map.css` is published through the shell's
head contract (`StylesheetSourceInterface`, tag `shell.stylesheet`), beside
`chart.css` and `calendar.css`, so every page in the product carries it.

**Why.** The old rule was "a page that draws a plate links `map.css`", and it
held only while a page could know. A plate is now drawn by WIDGETS: on a
composed surface any cell may draw one, and the organisation Overview
composed a map cell onto a page that linked no map sheet — `.map-plate`,
`.map-legend` and `.lay .sw` had no rules, the plate came apart, the page
returned 200 and nothing failed. The head cannot be decided by what a page
happens to compose.

**What to change in a module.** Delete the link, everywhere it appears:

```diff
 {% block stylesheets %}
     {{ parent() }}
-    <link rel="stylesheet" href="{{ asset(constant('Uhifadhi\Bundle\AtlasBundle\AtlasBundle::STYLESHEET')) }}">
     <link rel="stylesheet" href="{{ asset(constant('Uhifadhi\Roster\UhifadhiRosterBundle::STYLESHEET')) }}">
 {% endblock %}
```

**It is now a conformance failure.** `VocabularyConformanceTestCase` gains
`testNoTemplateLinksASheetTheHeadAlreadyCarries`: a template linking
`map.css`, `chart.css` or `calendar.css` fails with "the shell carries it",
because a second link is a second copy of those rules at a different point in
the load order. **roster-module, patrol-module and incident-module** each
carry such links today and will fail this rule until they are deleted — one
line per template, no other change.

Leaflet's own sheet is unaffected: the UX Map Leaflet bridge's controller
imports it on the pages that build a map, which is a script's business rather
than the head's.

## Extend the shell's org base, draw no strip

**What changed.** An organisation-level screen extends
`@Shell/org_page.html.twig`, and the shell draws the page-level tab strip —
from the same `orgPages()` declaration the sidebar row is mounted from,
filtered to the routes this application actually mounted, with the screen the
viewer is on lit. The page-level strip is the shell's, exactly as the sidebar
row is: two copies of it drift, and a tab and a sidebar row then disagree
about which screens a module has.

**What a module drops.** Its own org base: the `.atabs` loop, the
`_scope_control` include, the trail, and the `orgTabs` / `scopeOptions`
variables its controllers were passing for them. They are gone, not moved —
the shell reads the declaration itself.

```diff
-{% extends '@Shell/page.html.twig' %}
-{% block shell_breadcrumbs %}uhifadhi / roster / overview{% endblock %}
-{% block shell_page_actions %}
-    {{ include('@Shell/_scope_control.html.twig', {scopes: scopeOptions, current: scope}) }}
-{% endblock %}
-{% block shell_page %}
-    <div class="atabs">{% for tab in orgTabs %}…{% endfor %}</div>
-    {% block roster_org_page %}{% endblock %}
-{% endblock %}
+{% extends '@Shell/org_page.html.twig' %}
+{% block shell_page %}{% block roster_org_page %}{% endblock %}{% endblock %}
```

**What it fills.** `shell_page` (its body), `shell_page_subtitle`, and
`shell_org_actions` where it has an action of its own — which lands beside
the scope control, with Configure still last. `shell_org_trail_tail`
overrides the trail's last segment; the trail itself names the module and the
screen and **never an area**, because an organisation-level screen is the
area screen one scope wider.

**Reading the frame.** `shell_org()` returns the module's name, the current
`OrgPage`, the strip, the scopes and the current `Scope` — or null on any
page in no module's org set, where the base renders as a plain page rather
than failing.

**Two rules it brings with it.** A strip that would be one tab is not drawn
(the rule the area strip already keeps), and the tabs carry the address's own
`?area=` so the slice survives a tab change.

## The sidebar has four groups, and a module joins one by constant

**What changed.** The sidebar's groups are published in the contracts —
`Uhifadhi\Contracts\Shell\NavGroup` — and there are four of them, in this
order:

| Constant | Label | What it holds |
| --- | --- | --- |
| `NavGroup::OBSERVATORY` | Observatory | what the organisation WATCHES: Areas, Performance, a module's organisation-level pages |
| `NavGroup::ORGANIZATION` | Organization | what it IS AND HOLDS: Departments, Team, Files |
| `NavGroup::SYSTEM` | System | what the system RAISES TO YOU: Alerts, Telemetry |
| `NavGroup::SETTINGS` | Settings | configuration, and it comes last |

The shell refuses a label that is not one of the four, naming them in the
message. A near-miss — `Organisation`, `Org`, `system` — used to grow a fifth
heading that nobody designed, in whoever's installation had that module.

**What a module does.** Name the group by constant:

```diff
-public const string SECTION = 'System';
+public const string SECTION = NavGroup::SYSTEM;
```

The string VALUES are unchanged, so a module still on a literal keeps working
exactly as before — until it misspells one, which is the point.

**Order is the shell's now.** The four headings are drawn in the contract's
order whatever position a contribution declared; `NavSection::$position` still
orders the ROWS inside a group, which is the question a contributing module
can answer. A module that leaned on a low position to sit its whole group
first no longer does.

**Storage: Files moves to Organization.** Files is a standing fact about the
organisation, not something the system raises to you, so
`FilesNavigation::SECTION` becomes `NavGroup::ORGANIZATION` — a change in
`uhifadhi/storage-module`, in its own release. Until then Files renders under
System as it does today; nothing breaks either way.

**Settings.** The group exists now for the Settings section that follows: one
row with its tabs as children, and it may grow. A module has no reason to
file under it yet.

## A module can answer at organisation level

**What changed.** A module may now contribute an ORGANISATION-LEVEL page set
— its own screens once across every area — and the shell mounts it: a row in
Observatory after Performance, the screens as its tabs, and the scope control
in the page's action row. The module writes no sidebar item, no tab strip and
no scope control, and the host writes no module code.

**What a module does.** Implement `Uhifadhi\Contracts\Shell\OrgPagesInterface`
beside `ModuleProviderInterface` and tag the provider `shell.org_pages`:

```php
public function orgPages(): array
{
    return [
        new OrgPage('overview', 'Overview', 'roster_org_overview'),
        new OrgPage('today', 'Today', 'roster_org_today'),
    ];
}
```

Route NAMES, never paths — the application mounts them, and a screen whose
route this installation has not mounted is left out rather than drawn as a
link to a 404. A module that has no organisation-level reading simply does
not implement the interface; nothing is missing.

**Every figure is the area query one scope wider.** The module's own service
takes a `Uhifadhi\Contracts\Shell\Scope` — the organisation, or one area —
and the area page passes one area where the org page passes the organisation.
A module that grew a second aggregate for this would have two numbers for one
question and no way to say which was right.

**What a host does.** Fill the scope control by tagging a
`Uhifadhi\Contracts\Shell\ScopeSourceInterface` with `shell.scope_source`.
The shell holds no areas and no voters, so the list is yours and is already
narrowed to what the account may open: somebody scoped to one area gets that
area and no control at all, and the page is the same page. The current slice
is resolved off `?area=<uuid>` (`Shell\Service\Scopes::PARAMETER`).

**`.ov-ctl` is unscoped now.** It was `.pgact .ov-ctl` in the shell's sheet,
which made it a control only the page action row could draw. A stylesheet
that restated it for its own surface should drop that copy.

## A chart series states a category, not a colour

**What changed.** `AtlasBundle\Model\ChartSeries` takes `cat` — its position
in the palette, 1 to 18 — and `ChartBuilder` writes `var(--cat-n)` into the
dataset instead of one of six hex colours it used to own. The new
`uhifadhi--atlas-bundle--chart-plate` controller resolves the token against the
element the chart is mounted on, at mount and again when the theme flips.

**Why.** Chart.js paints onto a canvas, and a canvas does not resolve a custom
property the way an element does: a token handed to the 2D context draws
nothing, which is why the builder shipped colours at all. They were a seventh
palette beside the one the product has — right on the night canvas, wrong on
paper, and never the same mark as the zone of the same category on the plate
beside them.

**What to change in a module.** Nothing, unless you set a series' colour:

```diff
-new ChartSeries('Patrols', $points, swatch: '#49E6B4')
+new ChartSeries('Patrols', $points, cat: 1)
```

A series that states nothing takes the next category in order, which is what
most want. `$swatch` still wins where it is set and is **deprecated, removed in
the next release** — the two-release rule, since a shipped module reading a
property that vanished is a 500 on somebody else's page.

An installation that lists Stimulus controllers by hand adds `chart-plate`
beside `map-plate`; one whose importmap is written by Flex gets it with the
recipe.

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
resets: `data-widget-reset="roster-live-rail"`. A surface may also carry an
`anchor`, rendered as the section's `id`, so a door elsewhere in the product
lands on that surface (`…/widgets#rail`) instead of at the top of the page. A bare one is still honoured
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
