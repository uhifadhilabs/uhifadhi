# The component vocabulary

The class names a module writes on its own elements. The [theme](theming.md)
says what jade is; this says what a KPI plate is, and it turned out to matter
just as much.

## Contents

- [Why it is here](#why-it-is-here)
- [What passed the line, and what did not](#what-passed-the-line-and-what-did-not)
- [The list](#the-list)
- [Two rules the tests enforce](#two-rules-the-tests-enforce)
- [What is furniture, and not yours to write](#what-is-furniture-and-not-yours-to-write)
- [What you get without asking](#what-you-get-without-asking)
- [Adding to it](#adding-to-it)

## Why it is here

The design workspace has always kept the platform's shared vocabulary in one
vendor sheet, and every screen's own sheet says "not repeated here". The
platform had no vendor sheet. So the first module that needed `.kpi` restated it
in its own stylesheet, under a block marked *on loan — belongs in the shell*,
and the modules that did not restate it drew a strip of plates as one line of
running text on a live page.

Four independent modules had written `class="kpi"` against a rule that lived in
none of them. That is the whole argument: one definition, in the frame, is the
difference between a design system and four of them.

## What passed the line, and what did not

Every rule was asked one question:

> Could a third-party Sightings module use this class without the shell knowing
> Sightings exists?

A plate, a number, a table, a pager and a person's mark all pass — they are what
those things look like on this platform, whoever is drawing them. A rule
encoding one module's screens does not pass, and stays in that module's own
sheet whatever it is named: a shell that shipped `.pm-deptrow` would be a shell
that knows what a department is.

The question is about what a rule **is**, not what it is called. `.dp-kstrip`
carried a departments-era prefix and was a plain auto-fitting strip of plates
that two unrelated modules already used, so it hoisted — under a generic name,
`.kstrip`. A rule named `.grid-2` that only ever laid out an incident triage
board would not have.

## The list

Frozen in `LayoutContract::COMPONENTS` and typed out again in
`Integration/Theme/ComponentContractTest::contractV1()`, so the two disagree
loudly the day somebody renames one. These are the **entry** classes — the name
you write on an element; the parts each one brings are in the table.

| Component | Entry | Its parts |
|---|---|---|
| The plate | `.c` | — |
| The status pill | `.chip` | `.ok` `.warn` `.fail` `.idle` `.acc` |
| The call to action | `.cta` | — |
| The column system | `.grid` | `.g2` `.g3` `.g4` `.g32` |
| Type | `.mono` `.disp` | — |
| The colour words | `.fog` `.acc` `.g` `.w` `.r` `.d` `.muted` | — |
| The card's tab | `.tab` (direct child of `.c`) | `.src`, the qualifier |
| What the card is for | `.use` | `b` for the emphasis |
| The identity band | `.factband` | `.f` a fact, `.k`/`.v` its halves (`em` the unit), `.sp` then `.more`; wraps below 900px |
| The KPI plate | `.kpi` | `b`/`.disp` the number, `em` the unit, `.sub` the sub-line, `.hot` for the one that matters |
| The KPI strip | `.kstrip` | a modifier on `.grid`, never alone |
| The register table | `table.tbl` | `th` `td` `.num`, and the row's hover |
| The meta row | `.rln` | the two halves are yours (`.k`/`.v`, a `.mono` figure, a colour word); the last row drops the rule |
| The pager | `.rdf-foot` `.rdf-page` | `.pg`, the page you are on |
| The person's mark | `.avatar` | — |
| The row affordance | `.open-btn` | fills on the **row's** hover, not its own |
| The quiet button | `.tgl` | the secondary to `.cta` |

**The colour words are not the start of a utility set.** They colour one word of
a sentence, and they are the palette's own names. A fifth grey belongs in the
token list or nowhere.

## Two rules the tests enforce

**They spend tokens and name no colour.** No rule in the component section names
an `rgb()` or a hex value, which is what makes all of it correct in both
palettes without a single `html.dark` override. The first literal colour in
there is the first component that will look wrong after dark on somebody else's
page, so a test refuses one.

**They are unscoped, on purpose.** A module's stylesheet loads after the
shell's and may override anything here; what it must never have to do is opt in.
A vocabulary scoped to a shell wrapper would be a vocabulary only the shell's
own pages could speak, which is the opposite of the point.

## What is furniture, and not yours to write

`.pgbody`, `.pghead`, `.pgact`, `.crumb`, `.atabs`, `.page`, `.side`, `.topbar`
and the rest of the frame. The shell writes those from its own templates. A
module that typed one would be drawing the frame instead of filling it — fill a
[socket](blocks.md) instead.

`.pgact` is the page's action row, and the frame lays it out completely: a
wrapping line of controls that are all one height, in which only weight and fill
separate the primary from the quiet ones. Fill `shell_page_actions` with the
controls themselves — a `.cta`, a `.tgl`, a form with a `.fld` — and write no
rule for the row. **A module may not restate `.pgact`.** It cannot reach the
wrapper to put a class on it, so a module that lays out the row again has
written a second definition of one component, and the header renders whichever
sheet the page happened to link last.

`.pgbody` is worth one line of its own, because it was the frame's one class
that nobody styled. A wrapper with no rules is not neutral: a module's first
element collapsed its top margin straight through it and into the frame, so the
gap under a page's tabs moved depending on whether the module led with a
heading, a paragraph or a card. It is now a `flow-root` with the first child's
top margin zeroed, and nothing else — what goes inside stays the module's
business.

## What you get without asking

The same sheet that carries this vocabulary opens with the platform's [box
model](theming.md#the-box-model): `border-box`, on every element and both
pseudo-elements, for every page the frame draws. Each rule above is written
against it, and so is yours — the number in a design is a border box, and now so
is the box the browser gives you. A module may stop restating
`box-sizing: border-box` on its own rules; it must never write `content-box`.

## Adding to it

Same policy as the sockets and the tokens: see [changing the
contract](changing-the-contract.md). Adding a component is a minor version and a
new row in the frozen list. Renaming one is a major version — module templates
across the platform write these names, and a renamed class does not fail a
build, it just stops applying.
