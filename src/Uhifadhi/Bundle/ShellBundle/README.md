# ShellBundle

The **shell**: what an uhifadhi installation looks like — the document, the page
frame, the navigation contracts and the theme every module's pages mount into.

One of the bundles of the uhifadhi core, `uhifadhi/uhifadhi`. It can be
installed on its own as `uhifadhi/shell-bundle`.

## Contents

- [What it is](#what-it-is)
- [Installation](#installation)
- [Getting started](#getting-started)
- [Icons](#icons)
- [Learn more](#learn-more)
- [License](#license)

## What it is

**The shell shows; it does not carry.** It owns five things and no more:

- **The frames** — the document, the shell (sidebar + top bar) and the page
  frame (breadcrumbs, page head, actions, tabs, flashes, body). Three rungs of
  one ladder; a page steps onto whichever it needs.
- **The contracts** — how a nav row and an area's tab strip get their content
  from outside, without the shell knowing what an area or a module is.
- **The theme** — one token set, two complete palettes, both first-class.
- **The shared pictures** — the module grid and the cards it is made of: the
  drawings of answers composed elsewhere, which would otherwise be redrawn once
  per page that needs them.
- **The widget machinery** (`Widget/`) — the surface registry a module declares
  a dashboard into, the layout each person adopted, and the one library
  component every dashboard in an installation is arranged through. It is the
  only thing the shell remembers, and it is furniture: which widgets somebody
  adopted is the same kind of fact as which theme they chose.

Block names, contract interfaces and theme tokens are a versioned, test-enforced
API, not a convention — see [the architecture](docs/architecture.md).

## Installation

The core is one package:

```bash
composer require uhifadhi/uhifadhi
```

Flex adds `Uhifadhi\Bundle\ShellBundle\ShellBundle` to `config/bundles.php`
and copies `config/packages/shell.yaml` in.

The widget machinery maps its own entities, so an installation writes no
doctrine mappings block for the `widget_*` tables. What it cannot answer for
itself is what `Uhifadhi\Contracts\Entity\UserInterface` means — a layout
belongs to a person, and the shell does not know who an installation's people
are. `TeamBundle`, in the same package, states that resolution.

## Getting started

Every code block below opens with a comment naming the file it belongs in. Where
a block belongs to the application rather than to a module, the comment
says so.

A host implements the two contracts and points the shell at them:

```php
// config/services.php (your application)
$services->set(App\Shell\HostNavigation::class)->tag('shell.nav_section');
$services->alias('shell.area_shell_source', App\Shell\AreaShell::class);
```

…and its pages extend the frame:

```twig
{# templates/zones/index.html.twig (your application) #}
{% extends '@Shell/page.html.twig' %}
{% block shell_page_title %}Zones{% endblock %}
{% block shell_page %}…{% endblock %}
```

To serve the shell's own welcome page at `/` — the screen an installation shows
before it has grown a home screen — import the route the bundle ships, in one
line, in a file the application owns:

```yaml
# config/routes/shell.yaml (your application)
shell:
    resource: '@ShellBundle/config/routes/welcome.php'
```

Configuration, all of it optional:

```yaml
# config/packages/shell.yaml
shell:
    brand_name: Uhifadhi           # the wordmark beside the brand tile
    home_route: dashboard_index    # where the tile links
    default_theme: light           # light | dark | system
```

Every key has a default and the tree is closed, so an unknown key fails loudly
rather than being ignored. Each one is something the shell genuinely **cannot**
know: the deployment's name, the host's route names, a first-visit preference.
There is deliberately **no key listing nav entries, area tabs or modules** —
those arrive as data through the contracts, because a YAML nav is a nav no permission
check ever reaches.

## Icons

**The core draws with one prefix, `shell:`, and registers no other.** Every
glyph any core screen uses is a file in this bundle's `assets/icons/shell/`,
registered as an icon set in `ShellBundle::prependExtension()`, so the whole
core renders on an installation with no icon configuration and no network.

A prefix registered as an icon set is answered **only** from that set's
directory — the lookup never falls back to an application's `icon_dir`. Claiming
a second one here would take that word away from every installation and every
module. So the rule is one prefix per package:

- **An application** answers whatever it keeps in `assets/icons/`, including any
  public library it vendors there.
- **A module** registers `ux_icons.icon_sets.<its alias>.path` in its own bundle
  and draws with `<alias>:…`; a glyph it wants from a public icon library is
  copied into that directory rather than reached for under the library's own
  prefix.
- **Never an inline `<svg>` in a template, and never an emoji.** A drawing that
  is not a locked icon file is a drawing nothing can check.

The glyph list, how one is imported and the attribution are in
[`assets/icons/README.md`](assets/icons/README.md).

<https://symfony.com/bundles/ux-icons/current/index.html#full-configuration>

## Learn more

- [The architecture](docs/architecture.md) — where the shell sits in the
  platform, what it guarantees, what is in this repository, and how to work on it.
- [The named sockets](docs/blocks.md) — all twenty-three blocks, who fills each,
  and how a module page fills them.
- [The navigation contract and the area shell](docs/navigation.md) — how rows reach the
  sidebar and tabs reach a page.
- [The theme](docs/theming.md) — the box model every page is measured in, the
  token set, the Stimulus controllers the furniture moves by, and the tab icon.
- [The component vocabulary](docs/components.md) — the classes a module writes on
  its own elements: the plate, the card's tab, the KPI, the register table, the
  pager and the person's mark.
- [Boundaries](docs/boundaries.md) — what the shell is not: the one URL it ships,
  why it requires no registry, and how much of the module grid it claims.
- [Changing the contract](docs/changing-the-contract.md) — the policy for adding,
  renaming or removing a socket or a token.
- [The welcome page](docs/welcome-page.md) — what a fresh installation shows.
- [The widget machinery](docs/widget/architecture.md) — surfaces, catalogues,
  presets and the stored layout, and [declaring a surface](docs/widget/declaring-a-surface.md).

## License

**AGPL-3.0-or-later** — see the core's [LICENSE](../../../../LICENSE). Use,
modify and self-host freely; if you offer a modified version to users over a
network, they are entitled to the source of what they're running.
