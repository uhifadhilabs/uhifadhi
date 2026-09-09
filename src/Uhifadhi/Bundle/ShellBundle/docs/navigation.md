# The navigation contract and the area shell

How rows reach the sidebar and tabs reach a page, without the shell knowing what
an area or a module is.

## Contents

- [The navigation contract](#the-navigation-contract)
- [The area shell](#the-area-shell)

## The navigation contract

The shell owns the nav's **shape** — sections, rows, the location tree, carets,
the current-row treatment, the collapsed rail. It owns none of its **content**.
Content arrives through `NavigationSourceInterface`, tagged `shell.nav_section`:

- the **host** implements one, and that is where contract data enters the shell. It
  has the areas, the viewer, the permission voters and the contract's per-area
  ledger; folding those four into "these rows, in this order" is a reading for a
  person on a page.
- a **module** may implement one too, for the rare platform-wide row that
  belongs to nobody's area.

Pinned by test: **sections with the same label are one section** — a label names a
place in the sidebar rather than something a source owns, so storage's Files row
and telemetry's Telemetry row, both filed under "System", draw one heading with
both rows under it, at the earliest position any of its contributors asked for,
its rows ordered by the position each contribution declared; sections come out in
declared-position order with registration as the tie-break; **gating is the
source's job** (the shell holds no
`AuthorizationChecker` and calls `is_granted` on nothing — a withheld row is
absent, never hidden); a row with no destination renders inert rather than
disappearing; folding is a class, never an omission (a caret that folds by not
rendering has nothing to reopen); **exactly one row is current** or the shell
refuses; and the nav is read live per render, so switching a module off takes its
row with it the same day.

## The area shell

The honest split, decided rather than fudged:

| The shell owns | The shell owns *not* |
|---|---|
| that sibling screens are an underlined tab strip | Overview, Modules, Zones, Settings |
| that it sits between the page head and the body | which of them this viewer has |
| that exactly one tab is lit | which one is current |
| that an unavailable tab is **absent, not disabled** | the gating decision behind that |
| that one tab is no strip at all | |

Tabs arrive through `AreaShellSourceInterface`, which the host aliases to
`shell.area_shell_source`. Today the list is hardcoded **twice** in the host —
`dashboard/_area_tabs.html.twig` and `SidebarRuntime::tabs()` — and the two have
to be edited together, which is the tell. One source, two renderings, and a test
that they cannot disagree.

Two things this fixes rather than ports: a module page inside an area currently
loses the area's tabs entirely, because the strip lives in a partial the host
includes by hand; under the frame the strip is the *default* content of
`shell_page_tabs`, so a module page that fills only its body still shows you
where you are. And "absent, not disabled" is now a rule the value object
enforces — `AreaTab` has no url-less form, so there is nothing to grey out.

*Why not an area module?* The strip has no behaviour to own: it is markup plus a
rule about lighting, and carving it into a bundle of its own would only give the
shell something to require — and the shell requires nothing it renders. If an
area module is ever created, it implements this
source; it does not take the strip.
