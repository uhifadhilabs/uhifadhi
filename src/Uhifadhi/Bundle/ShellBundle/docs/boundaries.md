# Boundaries: what the shell is not

The rulings that keep the shell a shell: what it ships none of, the one URL it
ships and the one line that grants it, why it requires no other uhifadhi
package, and how much of the module grid it claims.

Concretely, this bundle ships **no entities, no repositories, no doctrine
dependency and no migrations**, and `tests/Unit/BoundaryTest` fails the build if
that changes. It also ships no `src/Domain`, `src/Application`,
`src/Infrastructure` or `src/UI` — folders are named by technical kind.
`templates/` is not an exception to that rule: it is not a domain folder, it is
this bundle's entire subject.

It ships **one route and one controller**, which is not an exception either —
see below for the rule they live under.

## Contents

- [The one URL, and the one line that grants it](#the-one-url-and-the-one-line-that-grants-it)
- [Why the shell does not require the contract](#why-the-shell-does-not-require-the-contract)
- [The module grid](#the-module-grid)

## The one URL, and the one line that grants it

The boundary is **consent, not abstinence**: the shell claims no URL an
application has not asked it to claim.

The shell ships the welcome page's route as an importable resource and loads it
nowhere. An application accepts it in one line, in a file the application owns:

```yaml
# config/routes/shell.yaml (your application)
shell:
    resource: '@ShellBundle/config/routes/welcome.php'
```

That import defines the route `welcome` at `/`. Edit it to point `/` at your own
home screen, or delete it and the address is yours again — nothing is left
behind, because nothing in the shell loads that resource or depends on the route
existing. `tests/Integration/Routing/RouteResourceTest` boots the same host with
and without the line and asserts exactly that: 404 without it, the welcome page
with it.

The resource is **PHP, not YAML**, for the reason `config/services.php` is: a
reusable bundle must not force `symfony/yaml` onto the hosts that install it.

Behind that route is `Uhifadhi\Bundle\ShellBundle\Controller\WelcomeController`, and the
rule its successors live under is:

- **Presentation only.** A shell controller reads what the shell can read for
  itself — Composer's runtime metadata, the shell's own configured state — and
  renders one of the shell's own templates. It reads no entity, opens no
  connection and requires no contract; domain data reaches the shell through the
  tagged source interfaces in `src/Contract`, exactly as the sidebar's rows do.
- **Reachable only through the import.** A controller no application has
  imported a route for is a controller nothing can call.
- **No base class.** It does not extend `AbstractController`; it takes its
  dependencies in its constructor and is wired explicitly in
  `config/services.php`, like every other service here.

A second `Uhifadhi\Bundle\ShellBundle\Controller\*` under those terms is ordinary work, not
a change to this ruling. The area URL space, its permission gates and its entity
resolution stay the host's: `/areas/{uuid}/modules` is the host's, gated by the
host's permissions and resolving the host's area entity — a controller here would
drag all three across the boundary to save one template include.

## Why the shell does not require the contract

This is the ruling worth arguing, because the obvious defence runs the other way.

One bundle may depend on another, and nothing about the platform's shape forbids
it; the rule is what the dependency is *for*. A bundle may depend on another for
**machinery** — the kernel, the config pass, the container conventions — never to
read its **domain data**, and never for its **views**. The shell would be
requiring the registry to read domain data.

Three consequences decide it:

1. **The data is not usable raw anyway.** A nav row needs the area, the viewer
   and the permission decision; a grid card needs the department lens. The registry
   has none of those, so *something* has to compose the answer before the shell
   can draw it — and whatever composes it can hand over a value object as easily
   as the shell could fetch a row. The require buys nothing.
2. **Entities in templates are interrogated.** The moment a contract entity is in
   scope inside a Twig file, somebody writes `{% if module.slug == 'overview' %}`,
   and the module-blindness the registry and the shell both promise is gone. A `ModuleCard` value
   object cannot be interrogated that way — there is nothing on it to switch on
   that is not also a thing every card has.
3. **A shell that requires a contract runtime cannot shell an installation that has
   no modules.** `tests/Integration/TestKernel` is the proof: framework + twig +
   this bundle, booting and rendering, with no contract under it at all.

So: **`composer.json` requires no module and no contract runtime**, and a test
asserts it, because this is precisely the kind of ruling that gets quietly
reversed by one `composer require` during an extraction.

The one `uhifadhi/*` package it does require is **`uhifadhi/contracts`**,
and that proves rather than dents the ruling. Contracts is pure interfaces and
value objects with no runtime of its own — `php` is its only dependency — so
requiring it drags in no module and cannot bring domain data with it. It is the
home of a contract the shell *consumes* rather than *draws*: the top bar's user
badge. The card needs the account, its position and its tier, which are
`TeamBundle`'s to know; so the registry interface and its `UserBadge` value object
live in contracts, `TeamBundle` implements the interface depending only on
contracts (the shell stays in its `require-dev`), and the shell consumes the
same contract to draw whatever it is handed. A contract the shell draws but must not
require (the module grid, the nav rows) is a different animal from a contract the
shell consumes through a runtime-free contract, and the boundary test spells out
which is which.

## The module grid

**The ruling: the shell claims the picture, not the grouping, not the URL, and
not the customize screen.** The registry declined the grid and named the shell as
its claimant, so this had to be answered rather than inherited. The independent-life
test splits it:

- **the picture lives alone.** Cards in category groups, a status chip, a lens
  marker on the group a department leads, an empty state — a layout, complete
  given a list of groups, and the same layout wherever it appears (the area's
  Modules tab, a department page, a future search result). One implementation, at
  `@Shell/_module_grid.html.twig`.
- **the grouping does not.** Which cards, in which groups, in which order, and
  which department leads one is a reading of the catalogue for a particular
  viewer on a particular area — it needs the area, the viewer and the department
  lens, none of which the shell has or should acquire. The host's
  `ModuleGridService` stays where it is.
- **the URL does not.** `/areas/{uuid}/modules` is the host's URL space, gated by
  the host's `module.view`, resolving the host's area entity. A controller here
  would drag all three across the boundary to save an include.
- **the customize screen is not the grid's neighbour but its opposite.** It is a
  form that *writes* per-area install state: a POST, a CSRF token, an
  authorization decision, a flush. The shell has no writes anywhere in it, and
  the first one would end "the layout renders from a fixture with no database".
  A test greps the templates for `method="post"` and `csrf_token`.
