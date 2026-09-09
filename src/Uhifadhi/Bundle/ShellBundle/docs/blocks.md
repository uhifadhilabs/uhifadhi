# The named sockets, and how a module fills them

The block contract: every socket the three frames publish, who fills it, and the
shortest module page that is a correct platform page.

## Contents

- [The named sockets](#the-named-sockets)
- [How a module fills them](#how-a-module-fills-them)

## The named sockets

Twenty-three blocks, in three frames that inherit from one another. The list is
typed out literally in `Integration/Sockets/BlockContractTest::contractV1()` and
checked against `LayoutContract::BLOCKS`, so a rename fails the build from both
ends: the manifest disagrees with the frozen list, or the templates disagree
with the manifest. There is no way to move a socket without editing that test.

**The document** — `@Shell/document.html.twig`. The four standard block
names, unchanged; a module that knows Twig already knows them.

| Socket | Filled by |
|---|---|
| `title` | every page — the shell composes `<page> — <area> — <brand>` |
| `stylesheets` | module bases, calling `parent()` **first** |
| `javascripts` | module bases, calling `parent()` **last** when a classic script must beat the deferred importmap (the Leaflet rule) |
| `importmap` | the shell, by default — `importmap('app')`; a host with another entrypoint refills it |
| `body` | nobody: the shell owns it |

**The shell** — `@Shell/shell.html.twig`. Furniture. A module fills
none of these; they are the host's, because they need the viewer, the areas and
whether this response is an impersonation.

| Socket | Filled by |
|---|---|
| `shell_banner` | host — impersonation, maintenance, outage |
| `shell_sidebar` | host — replace the whole aside (rare) |
| `shell_sidebar_brand` | host — the mark and the wordmark |
| `shell_sidebar_nav` | host — overriding this opts out of the navigation contract |
| `shell_sidebar_footer` | host — settings, version, support |
| `shell_topbar` | host — replace the whole top bar |
| `shell_topbar_actions` | host — alerts, theme toggle, the user pill |
| `shell_main` | host — everything right of the nav |
| `content` | **the compatibility socket** — see below |
| `shell_footer` | host — the one line under every page |

**The page frame** — `@Shell/page.html.twig`. The module author's
sockets, and the reason the bundle exists.

| Socket | Filled by |
|---|---|
| `shell_breadcrumbs` | module — the trail, as text; the frame styles it |
| `shell_page_head` | module — replace title + subtitle + actions wholesale |
| `shell_page_title` | module — the `h1` |
| `shell_page_subtitle` | module — optional, and *genuinely* optional: empty renders no element |
| `shell_page_actions` | module — the buttons at the top right |
| `shell_page_tabs` | module/host — defaults to the area shell |
| `shell_flashes` | module — overridable, but the default is right and an override is a bug report |
| `shell_page` | module — **the body**, the one socket a page must fill |

`content` is in the contract on purpose and is the one socket named for
compatibility rather than clarity: every host page and every module page fills
it today against `layout.html.twig`. Renaming it would break five repositories in
one commit — exactly the casual rewiring this bundle exists to end. It stays as
the shell's main slot; the page frame fills it with the framed page, and a
full-bleed screen fills it directly.

Beyond the frames, the contract also pins **behaviour**: the vertical order
inside `.page` (crumb → page head → tabs → flashes → body), that an unfilled
socket leaves no empty element behind, that flashes are rendered once by the
frame so every module says "saved" the same way, and that the shell's stylesheet
always lands before a module's.

## How a module fills them

The whole point, in the shortest page that works. A module's page extends
the frame, fills sockets, and types no furniture:

```twig
{# templates/sightings/index.html.twig (your module) #}
{% extends '@Shell/page.html.twig' %}

{% block shell_page %}
    <p>Everything else — the sidebar, the top bar, the brand, the tab strip of
       whatever place this page is inside, the flashes, the footer — is already
       around this paragraph.</p>
{% endblock %}
```

That is a complete, correct platform page. `shell_page` is the only socket a
page **must** fill. A fuller one adds the parts it has and leaves out the parts
it does not — an empty block renders no element, so there is nothing to pay for
a subtitle you never wrote:

```twig
{# templates/sightings/index.html.twig (your module) #}
{% extends '@Shell/page.html.twig' %}

{% block title %}Sightings{% endblock %}

{% block shell_breadcrumbs %}
    <a href="{{ path('dashboard_index') }}">uhifadhi</a> /
    <a href="{{ area_url }}">{{ area.name|lower }}</a> / sightings
{% endblock %}

{% block shell_page_title %}Sightings{% endblock %}
{% block shell_page_subtitle %}Every record this month, by observer.{% endblock %}

{% block shell_page_actions %}
    <a class="cta" href="{{ path('sighting_new') }}">{{ ux_icon('shell:plus') }}New</a>
{% endblock %}

{% block shell_page %}
    {{ include('@Sightings/_table.html.twig') }}
{% endblock %}
```

Four rules a module author needs and nothing else:

**Attributes on `<body>` are the one variable, not a block.** A block cannot put
them there, so set `shell_body_attributes` at the top of your template, outside
any block, and the document's tag picks it up — that is how a platform-wide
setting the map controllers read rides on a document the shell owns. It is
emitted as markup, so escape your own values.

**Do not write `<div class="page">`, a `.crumb`, a `.pghead` or a flash loop.**
The frame owns all four. Every one of them was, at some point, copied into a
module from whichever host template was open at the time — which is the
reason this package exists.

**Your stylesheet goes after the shell's, and `parent()` is what puts it there.**

```twig
{# templates/sightings/_base.html.twig (your module) #}
{% block stylesheets %}
    {{ parent() }}
    <link rel="stylesheet" href="{{ asset('bundles/sightings/sightings.css') }}">
{% endblock %}
```

Call `parent()` **first**: your rules are written to override the shell's
tokens and furniture, and a base that forgets it ships a page with no theme at
all. Write your colours as `rgb(var(--c-acc))` and your dark rules as
`html.dark .your-card { … }` — both are contract, and the theme test is what
keeps them true. In `javascripts`, `parent()` goes **last** when a classic
script must run before the deferred importmap.

**You do not render the tab strip, and you do not have to.** If the page sits
inside a place the host knows about, `shell_page_tabs` already shows that place's
sibling screens, with the right one lit. A page outside any place says so by
saying nothing:

```twig
{# templates/sightings/index.html.twig (your module) #}
{% block shell_page_tabs %}{% endblock %}
```

**Pick your rung.** A full-bleed screen — a map that reaches the edges, a split
view — extends `@Shell/shell.html.twig` and fills `content` directly:
furniture, no `.page` wrapper, nothing to fight with negative margins. A print
view or an export extends `@Shell/document.html.twig` and fills `body`:
no furniture at all, but still the theme, because a printed page with no tokens
is black Times New Roman on white.

To contribute a row to the sidebar, implement `NavigationSourceInterface` and
tag it `shell.nav_section` — by hand, in your bundle's own extension, since a
reusable bundle's services are not autoconfigured. Gating is yours: a row the
viewer may not have is one you do not return.
