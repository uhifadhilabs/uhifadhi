# The theme, the furniture's behaviour and the tab icon

The box model a module's stylesheet is measured in, the token set it is written
against, the Stimulus controllers the shell's own controls move by, and the mark
the browser tab draws.

## Contents

- [The box model](#the-box-model)
- [The theme](#the-theme)
- [The furniture moves](#the-furniture-moves)
- [A time reads in the reader's zone](#a-time-reads-in-the-readers-zone)
- [The tab icon](#the-tab-icon)

## The box model

**The frame guarantees `border-box`.** `public/shell.css` opens with

```css
*,
*::before,
*::after {
    box-sizing: border-box;
}
```

and every page the platform draws loads that sheet, so padding and border are
measured **inside** the width an element was given, everywhere, without a module
asking for it.

This is not a house style. It is the measurement the designs are drawn in: the
design workspace's replicas open with the same reset, so every number a design
hands a module is a border box — a 78px calendar cell is 78px *including* its
padding and its rule. Port that faithfully into a frame that reset nothing and
the cell comes out 108px: correct in the replica, wrong in the product, and
wrong by a different amount in every rule. Two shipped bugs came from exactly
that — a position rail that hung 24px out of the column it lived in, and a
sign-in card floating on 52px of scroll that was not there.

**What a module need not write:** `box-sizing: border-box` on its own rules.
The platform reset already guarantees it, so a module's own copy carries no
weight; keeping it is harmless, and where a module wants the assumption said out
loud at the point of use it is honest.

**What a module must never write:** `box-sizing: content-box`. A sheet that
takes the guarantee back for part of a page leaves the platform with a box model
that depends on which rule the reader found first.

## The theme

Twenty-three tokens, frozen the same way the blocks are, because a module's
stylesheet is written against these names: `rgb(var(--c-p1))` in a patrol card is
a promise the shell made.

| Group | Tokens |
|---|---|
| surfaces | `--c-cv` `--c-p1` `--c-p2` `--c-raised` |
| ink (three weights, and only three) | `--c-tx` `--c-fog` `--c-dim` |
| accent | `--c-acc` `--c-accT` |
| state | `--c-ok` `--c-warn` `--c-fail` |
| edges and depth | `--c-ln` `--c-ln2` `--glass` `--shadow` `--accGlow` |
| brand (derived) | `--logo-tile` `--logo-child` `--logo-accent` |
| type | `--font-display` `--font-body` `--font-mono` |

**Light and dark are both first-class** — two complete palettes, not a filter over
one. Every token in the first five groups must carry a dark value, and the test
is driven by the same frozen list, so a token cannot be added to light alone. The
brand tokens must **not** be restated per theme: they ride the channels of
`--c-acc` and `--c-cv`, landing deep jade on the light canvas and mint on the
dark one with no override, and a second definition is a second place the brand
colour is decided.

Also pinned: the dark selector is `html.dark` (module sheets write it too, so a
move to a data attribute would silently unstyle every module sheet); the theme is resolved
by an inline script in `<head>` **before first paint**, not by a controller that
connects after the first frame — a visitor who chose dark must not be shown a
white page first, and today they are; `system` is a real third answer, not a
synonym for light; and any custom property the shell declares that is not on the
list must be prefixed `--_` as private, because a token a module can read is a
token a module will read.

Deliberately **not** here: the map chrome tokens (`--z-ink`, `--z-paper`,
`--z-imagery`, `--z-aoi`). They belong to `AtlasBundle`, the module that
owns how a layer draws; a legend palette in the shell could not be changed
without a shell release.

**Tokens are not the whole of the sheet.** The same file ships the platform's
[component vocabulary](components.md) — `.kpi`, `table.tbl`, `.avatar`, the
card's tab, the pager — which is what a module writes on its own elements. Every
one of those rules spends the tokens above and names no colour of its own, which
is why there is no dark half of that section.

**What ships here and what does not.** Tokens are values — custom properties the
browser resolves — so the bundle ships them, in `public/shell.css`, along with
plain CSS for the classes its own templates emit. It ships no utility classes:
an application's Tailwind build maps these tokens into utilities (`bg-canvas`,
`text-fog`, `border-line`) in its own `app.css`, which is where a build-time
concern belongs. That file reads `rgb(var(--c-cv))` at runtime, so it keeps
working as long as this sheet lands first — which the frame guarantees and a
test enforces. A bundle that shipped its theme any other way would only theme
hosts that had the right build.

## The furniture moves

**The shell ships its controls' behaviour, not only their markup.** It used to
ship only the markup: the theme toggle, the sidebar's collapse and the tree's
carets each carried a `data-action` naming a Stimulus controller the host was
expected to write. The host this bundle was extracted from had written them, so
the defect was invisible there and total everywhere else — a fresh installation got a
sun button that did nothing, a collapse button that collapsed nothing, and
carets that folded nothing. Furniture that looks operable and is not is worse
than furniture that is absent.

The reasoning behind that split — a bundle cannot contribute an importmap entry
— is true and was never the whole story. A UX package ships Stimulus controllers
the way `symfony/ux-turbo` does: an AssetMapper path plus a `symfony.controllers`
block in `assets/package.json`, which Flex enables in the application's
`assets/controllers.json` on install. Nothing is built and nothing is copied into
an application.

| Control | Controller | What it does |
|---|---|---|
| the top bar's sun | `theme` | writes the choice; the head's pre-paint script still applies it |
| the sidebar's chevrons | `sidebar` | collapses to the icon rail, and remembers |
| a tree caret | `sidebar-tree` | folds one branch; never navigates, never persisted |

Three consequences worth naming:

- **The shell requires `symfony/asset-mapper` and `symfony/stimulus-bundle`.**
  Every installation has the shell, so the shell is where the platform's asset
  pipeline is declared; a module that adds controllers later requires the same
  packages and composer resolves one copy. This is the one place the "requires no
  other ring" ruling does not reach: those are Symfony's rings, not uhifadhi's.
- **The document renders `importmap('app')`** as the default content of the
  `importmap` socket. A document whose furniture needs Stimulus and which never
  emits an importmap ships controls that cannot work.
- **The identifiers are namespaced by the package**, because StimulusBundle
  derives them from the composer name Flex keys `controllers.json` by:
  `uhifadhi--shell-bundle--theme`, and so on. A template that types anything else
  binds to nothing, silently.

**What is remembered is applied before the first paint.** The theme already was;
the sidebar's width now is too, through a `shell-rail` class the head's inline
script puts on `<html>` and the stylesheet draws the rail from — otherwise a
remembered rail arrives when the controller connects and a 236px sidebar visibly
jumps to 66px on every load.

## A time reads in the reader's zone

**The frame localises every `<time>` on the page; a module names no controller.**
The three controls above are furniture the shell *draws* and wires by hand. The
`localtime` controller is different in kind: it is mounted **once, on the
`<body>`** the shell owns (in the bottom rung), and on connect it sweeps
`time[datetime]` across the whole page and rewrites each to the reader's own
zone. Every host that renders through this frame inherits it — the localisation
is the frame's, so a host needs nothing of its own.

It answers a defect every module shares: an instant is stored as UTC and printed
once, server-side, in whatever single zone the server runs in — so a ranger in
the field and an analyst three timezones away read the same printed wall-clock
and one of them reads it wrong.

**A module emits only semantic markup.** It prints the instant as an ISO-8601
`datetime`, with a human UTC fallback as the text:

```twig
<time datetime="{{ t|date('c') }}">{{ t|date('D j M')|lower }} · {{ t|date('H:i') }}</time>
```

That is the module's *whole* contribution, and it deliberately carries no
`data-controller` and no other dependency on the shell — so the identical
template renders in a host that has no shell installed, where it simply keeps its
server-rendered text. Coupling the element to a named controller would break that
host; not coupling it is the point.

On connect (and on `turbo:load`/`turbo:render`, and for nodes a `MutationObserver`
sees added later) the frame reads each element's machine `datetime` attribute —
never the visible text — and rewrites the text with `Intl.DateTimeFormat(undefined,
…)`: no locale and no `timeZone` argument, so it resolves to the *reader's* own
locale and zone, the one thing the server cannot know. With no JavaScript the
server-rendered text stays exactly as it is.

An element may hint the shape it wants with `data-localtime-format`, a plain data
attribute the frame reads (and a shell-less host harmlessly ignores):

| `data-localtime-format` | What it renders |
|---|---|
| absent / `datetime` | date and time — day, short month, year, hour, minute |
| `date` | day, short month, year |
| `time` | hour and minute |

A tight cell that printed only `05:55` asks for `time`, so localising it does not
blow the cell out to a full date.

Two things a caller must hold to. **The `datetime` attribute is the real
instant** — offset-qualified or `Z`. `{{ t|date('c') }}` on a stored instant is
already unambiguous; a *zoneless* value is one the browser parses in *its* own
zone, which reintroduces the bug. **The visible text is disposable**: the frame
overwrites it, so a design's own custom wording survives only when JavaScript is
off.

## The tab icon

**The favicon is the full masterbrand mark** — the knockout U tile with the child
tile in the cut corner — shipped at `public/favicon.svg` and linked by the
document. Until it existed, every page of every installation drew a `/favicon.ico`
request and every installation answered it with a 404 in its first minute.

One SVG, at every size, in both palettes: a favicon is fetched on its own, with
no `shell.css`, no `html.dark` and no tokens, so the paint is written into the
file as the literals `--c-acc` and `--c-cv` resolve to and switched with
`prefers-color-scheme`. It is the only place in the platform where the brand
colours are restated, and a test compares its geometry with
`_brand_mark.html.twig` path by path so the two cannot drift.
