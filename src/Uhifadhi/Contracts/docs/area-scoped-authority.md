# Area-scoped authority

Until now a permission answered one question: *may this person do X?* The answer
was the same everywhere — a ranger who could record a patrol could record one
against any area in the installation. This document introduces the second half of
the question: *may this person do X **here**?* — where *here* is a specific area.

It states the model, decides which permissions carry an area and which are global,
describes how the voter becomes area-aware, maps the call sites that change, and
reshapes the permissions the mobile API hands out.

The scope-declaration rule (a module says whether its permission is area-scoped) is
a **contract** — module authors depend on it, so it lives here. The voter, the sweep,
and the API shape are **how the host keeps that contract**; they are described here
so the whole picture reads in one place, but the host implementation plan is
`AUTH_PLAN.md` in the ops repository.

## Contents

- [1. The model](#1-the-model)
- [2. Which permissions carry an area](#2-which-permissions-carry-an-area)
- [3. How a module declares scope](#3-how-a-module-declares-scope)
- [4. The area-aware voter](#4-the-area-aware-voter)
- [5. The is_granted sweep](#5-the-is_granted-sweep)
- [6. The permissions the API hands out](#6-the-permissions-the-api-hands-out)
- [7. What is deliberately left open](#7-what-is-deliberately-left-open)

## 1. The model

A person holds one **position**. A position is born in one **department**. A
department carries a **scope**:

- **org-level** — the department has no area (`area = null`). Its people have
  authority in **every** area.
- **area-level** — the department has an area (`area = X`). Its people have
  authority in **area X only**.

A person's **authority-area** is therefore a derived fact, not a stored one:

```
authority-area(person) = person.position.department.scope
```

Nothing else stores it. There is no `user.area`, no `position.area`, no separate
grant table. The department's scope — already designed in the area-aware
departments work, where `area = null` means org-level and `area = X` means
area-level — *is* the authority boundary. This is what makes an area-level
department mean something: it is the unit that confines authority.

Two tiers stand outside this entirely:

- **Super Admin** and **Admin** bypass area-scoping. They hold every permission in
  every area, always. This is the existing escape hatch (`TeamRoleEnum::canManageContent()`)
  and it is unchanged. Area-scoping only ever narrows a **Staff** member.

The change rests on the area-aware department model. The `Department` entity gains
its `area` (the area-aware departments design); `Position` keeps its single
department FK. No new authorization entity is introduced.

## 2. Which permissions carry an area

Every permission is one of two kinds:

- **area-scoped** — the grant is per-area. Holding it means "in the areas my
  department's scope covers." The voter compares the target area against the
  actor's authority-area.
- **inherently global** — the grant has no area to compare against, or is about the
  installation as a whole. The voter grants it on the permission alone, area
  ignored.

The catalogue today is seven core permissions (TeamBundle's `PermissionEnum`)
plus whatever modules declare. Here is each, with the ruling and the reasoning.

| permission | kind | why |
|---|---|---|
| `area.view` | **area-scoped** | You see the areas your authority covers. The areas list filters to them; an org-level department sees all. |
| `area.create` | **global** | A new area has no area yet — there is nothing to scope the check against. Creating areas is a fleet act; only org-level (or a tier) can. This one is *structurally* global, not a judgement call. |
| `area.edit` | **area-scoped** | Edit the areas you have authority in. |
| `area.delete` | **area-scoped** *(fork — see §7)* | Consistency says an area admin may delete their own area; destructiveness argues org-only. No call site exists yet, so the cost of deciding later is low. |
| `module.view` | **area-scoped** | Module data is per area; you view it where you have authority. |
| `module.create` (configure a module's settings + visualisations) | **area-scoped** | A module is configured per area (`AreaModule` taxonomy/config). Note **module composition** — enable/disable/reorder — is Admin-gated and therefore global regardless. |
| `team.manage` | **area-scoped** *(ruled)* | This is the one that creates **area admins**: a `team.manage` holder scoped to one area manages team **within that area only**. What exactly they may touch is constrained — see §7, the open structural fork. |
| `patrols.record` | **area-scoped** | A patrol happens in an area. This is the thesis case. |
| `incidents.record` | **area-scoped** | An incident is filed against an area. |
| `incidents.manage` | **area-scoped** | Triage the incidents of the areas you cover — this creates per-area incident managers. |

Only `area.create` and module composition are global. Everything operational is
area-scoped. That asymmetry is deliberate: **operational power is local by
default; only fleet-shaping acts are global.**

## 3. How a module declares scope

A module already declares its permissions and never grants them
(`ModuleProviderInterface::permissions()` returning `ModulePermission` value
objects). Scope is one more thing the declaration states.

`ModulePermission` gains a scope axis. The default is **area-scoped** — the safe,
operational default — and a module opts a permission into global explicitly:

```php
// src/Module/SightingsModuleProvider.php (your bundle)
public function permissions(): array
{
    return [
        // area-scoped is the default — a sighting is recorded in an area
        ModulePermission::areaScoped('sightings.record', 'Sightings', 'Record',
            'Record a wildlife sighting against an area.'),

        // opt into global explicitly, and only when the act has no area
        ModulePermission::global('sightings.export_registry', 'Sightings', 'Export',
            'Export the whole-installation sightings registry.'),
    ];
}
```

The rule for the author: **if the act names an area, it is area-scoped; if it acts
on the installation as a whole, it is global.** The host reads the scope off the
declaration and the voter enforces it — a module never enforces area-scoping
itself. `PatrolEvidenceVoter` (which gates photo bytes) is the one place a module
resolves an area on its own, following the chain photo → observation → patrol →
area; it already carries a written note anticipating exactly this change.

The existing "Permissions" section of `module-development.md` says a permission
carries no meaning beyond its namespace. Once the scope axis is ruled and lands on
`ModulePermission`, that section gains a pointer here — a permission will also carry
a scope. It is left unedited until then, so the shipped guide keeps describing what
is actually true today.

## 4. The area-aware voter

Today `PermissionVoter` votes on a bare attribute string and ignores the subject:
Super Admin / Admin short-circuit to granted; a Staff member is granted iff their
position carries the permission value. The change adds an **area comparison** after
the permission is confirmed.

The voter receives the **target area as the subject**:

```php
// what a call site passes (see §5)
$this->isGranted('patrols.record', $area);   // $area may be null
```

Deriving the actor's authority-area and comparing:

```
voteOnAttribute(attribute, subject, token):
    user = token.user
    if user is not a User:                      DENY

    # 1. tier escape hatch — unchanged, area never consulted
    if user.teamRole.canManageContent():        GRANT      # Super Admin / Admin

    # 2. does the position carry this permission at all?
    if user.position is null:                    DENY
    if not user.position.hasPermissionValue(attribute):  DENY

    # 3. is the permission even area-scoped?
    if catalogue.scopeOf(attribute) is GLOBAL:   GRANT      # area check skipped

    # 4. area comparison
    authority = user.position.department.scope   # an Area, or null = org-level
    if authority is null:                         GRANT      # org-level → every area

    target = subject as Area
    if target is null:                            GRANT-IF-ANY   # see below
    GRANT if target == authority else DENY
```

Four things are load-bearing:

1. **The tier short-circuit stays first.** Admins never touch the area logic.
2. **A global permission skips the comparison** — this is the whole point of the
   scope axis. `area.create` reaches step 3 and grants.
3. **An org-level department (`scope = null`) grants for any target.** Org-level
   authority is "all areas" expressed as the absence of a boundary.
4. **A null subject means "no area in context"** — a navigation question, a "may I
   ever record a patrol?" flag. The recommendation is **grant if the actor has
   authority in any area** (an area-level Staff member does), and let the real
   per-area gate apply once the area is known (the record screen, the API call
   naming an area). Denying on null would black out nav for area admins; granting
   on null with a genuine gate downstream keeps the UI honest without leaking. This
   is a genuine choice — see §7.

Whether the subject is **passed explicitly** (above) or **derived from the route**
(the voter reads the `{uuid}` area param off the request) is the one structural
fork in the enforcement design — §7.

## 5. The is_granted sweep

Every call site becomes one of: *pass the target area*, *stay global*, or *filter a
list*. There are ~40 today, none passing a subject. This table is the map the build
follows.

| call site | permission | becomes | area context passed |
|---|---|---|---|
| `AreaController` view (`:61,:79`), `ZoneController` (`:48`) | `area.view` | area-scoped | the `{uuid}` Area being viewed |
| `AreaController` edit (`:99`) | `area.edit` | area-scoped | the `{uuid}` Area |
| `AreaCreateController` (`:69`) | `area.create` | **stays global** | none |
| `area/index.html.twig` (`:49,:90`) — areas list | `area.create` / `area.view` | list filters | page gate global; each row scoped to its area |
| `AreaModulesController` view (`:92`) | `module.view` | area-scoped | the Area whose modules are shown |
| `AreaModulesController` compose (`:110,:134,:151,:168`) | `module.create` | **stays global** (composition is Admin-gated) | none |
| `modules.html.twig` (`:47,:55`) | `module.create` | area-scoped (config) | the Area |
| all `team.manage` sites — `Position`, `Member`, `Invite`, `Department`, `Team`, `*WidgetsController` | `team.manage` | area-scoped | the area of the department/position being managed (org-wide holders skip) — see §7 constraints |
| `PatrolRecordController` (`:272`), `PatrolController` (`:138`), `PatrolHoldController` (`:139`), `PatrolDetailController` (`:378,:504`), `ObservationAmendmentController` (`:225`) | `patrols.record` | area-scoped | the patrol's area (route `{uuid}` or patrol → area) |
| `PatrolApiContext::requireRecorder()` (`:66`) | `patrols.record` | area-scoped | the area named in the API request |
| `IncidentReportController` (`:356`), `IncidentController` (`:149`) | `incidents.record` | area-scoped | the area being filed against |
| `IncidentDetailController` (`:195,:201`), `IncidentTransitionToken` (`:50`) | `incidents.manage` | area-scoped | the incident's area |
| `PatrolEvidenceVoter::mayRead()` | (evidence key) | area-scoped | the patrol's area via photo → observation → patrol → area |

Two shapes recur:

- **A detail/action route** already has an area in scope (the `{uuid}`, or the
  record's area) — pass it.
- **A list/index route** has no single area — the page-level gate stays global
  ("may this person ever view areas?") and the **rows filter** to the authority-area
  (an area admin sees only their area's rows). Filtering is a query concern, not a
  voter concern; the voter answers per-row where a row action needs it.

## 6. The permissions the API hands out

The mobile API today sends a flat, global list — `TokenResponse.permissions` and
`MeResponse.permissions` are `List<String>?`, and the app reads `patrols.record`
from it as a single yes/no. Area-scoping breaks that: the answer is now "yes in
Harbour Reach, no in Tern Bank."

The payload becomes **area-qualified**:

```json
{
  "authority": { "scope": "area", "areaIds": ["<area-uuid-harbour-reach>"] },
  "global": ["area.view"],
  "areaCapabilities": {
    "<area-uuid-harbour-reach>": ["patrols.record", "incidents.record"]
  }
}
```

- `authority` states the shape: `scope` is `"org"` or `"area"`; `areaIds` lists the
  covered areas. An **org-level** person is `{ "scope": "org", "areaIds": ["*"] }`
  — the `"*"` wildcard means "every area," so the payload does not enumerate a
  fleet-sized installation's worth of areas (fork — §7).
- `global` holds the inherently-global permissions the person has.
- `areaCapabilities` maps each covered area to the permissions the person holds
  **there**. The server precomputes this — the client does not intersect authority
  against a permission list itself (fork — §7). Super Admin / Admin come back as
  `scope: "org"` with the wildcard and the full capability set.

The `List<String>?` field is retired in favour of this object; the null-means-permit
rule (host predates the field) carries over as "no `areaCapabilities` ⇒ treat as
permitted," so an old host still works.

### The consequence for the mobile app: a per-area takeover

The mobile app's "you don't have permission" takeover was whole-app: one missing string
blacked out the feature everywhere. It now becomes **per-area**. The same ranger is
permitted in one area and blocked in another, and the app must express that:

- The **area picker** annotates each area with what you may do there — *record* /
  *view only* / *no access* — read straight from `areaCapabilities`.
- The **record action** is enabled only when the selected area is in the
  `patrols.record` set. In an area you cannot record in, the button is not a dead
  end that errors on tap — it is absent or clearly disabled, with the picker
  showing where you *can* act.
- The takeover screen, when it appears, names the area: "You can record patrols in
  Harbour Reach, not in Tern Bank" — not a blanket lockout.

The server voter (§4) remains the authority; `areaCapabilities` only tells the app
what to show so it never offers an action the server will refuse.

## 7. What is deliberately left open

These are the forks. Each has a recommendation but is not self-decided here.

1. **Voter subject: passed vs route-derived.** Recommend the subject is
   **passed explicitly** (`isGranted('patrols.record', $area)`) as the contract —
   explicit, unit-testable, works off-route (commands, API) — with a convenience
   argument-resolver that turns a `{uuid}` route param into the Area for
   controllers and templates. The alternative (voter reaches into the request stack
   for `{uuid}`) is less churn but implicit and untestable off-route.
2. **`area.delete`: scoped vs org-only.** Recommend **area-scoped** for
   consistency; flagged because destructiveness plus the fleet "deactivate, never
   delete" leaning argues org-only. No call site today.
3. **Null-subject semantics.** Recommend **grant-if-any-authority** with a real
   per-area gate downstream (§4), so nav/flags work for area admins without leaking.
4. **API org-level encoding: `"*"` wildcard vs enumerated area list.** Recommend
   the **wildcard**, to keep the payload small at fleet scale.
5. **API computation: server precomputes `areaCapabilities` vs client intersects.**
   Recommend the **server precomputes** — one authoritative shape, a dumb client.
6. **Area-scoped `team.manage` — what may an area admin actually touch?** This is
   the genuine structural fork. Departments and positions are org-wide entities
   that now carry a scope; an area admin must not be able to mint an org-level
   department, promote a department to org, or grant a permission wider than their
   own authority — any of those is escalation. Recommended constraints: an
   area-X `team.manage` holder may (a) assign and unassign people to positions whose
   department is scoped to area X; (b) create, rename, and deactivate **area-level**
   departments in area X and their positions; (c) **not** create org-level
   departments, **not** change any department's scope, **not** touch org-level or
   other areas' departments, and **not** grant a permission their own position does
   not hold. This needs a ruling before the `team.manage` sweep lands.
7. **Promotion as a privilege change.** The area-aware departments design ruled
   promotion (area → org) "safe, no warning," because scope was a *lens*. Scope is
   now *authority*, so promoting a department **grants every member org-wide power**
   — a real escalation. Recommend a lightweight **privilege-gain notice** on
   promotion (it informs, it never guards — consistent with the footprint rule), and
   reframing the demotion footprint as authority **loss** ("N people lose authority
   in areas A, B, C"). This reconciles the authority dimension with the existing
   "footprint informs, never guards" ruling.
8. **Secondment (multi-position).** Assumed single-position throughout. If one
   person may ever hold two positions (e.g. an org role and an area posting), the
   authority-area becomes the **union** of their departments' scopes, and the voter's
   step 4 iterates positions instead of reading one. Flagged, not designed.
