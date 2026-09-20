# Changelog — ShellBundle

## Contents

- [1.0.0](#100)

## 1.0.0

Not released yet.

 * a strip of figures is FOUR to a row and eight is two rows of four (ruled):
   `.kstrip` states its columns instead of folding on content width, which is
   how five came to be drawn — four plates and an orphan on a small laptop
 * a sheet that does not declare the palette may name no colour of its own —
   the fleet rule, with the shell and the atlas's ground exempt because they
   declare one
 * `--lift`, the shadow an overlay casts, beside `--scrim`
 * `--c-crit`/`--crit` and `--scrim` — the fourth state (a thing that is still
   going wrong, not a louder `fail`) and an overlay's ground, dark in both
   themes. Both were in the design's palette and in no sheet here, so modules
   were declaring one and spending a literal for the other
 * `.cal-nav .sp` — the stepper row's spacer, so a surface's own picker sits at
   the trailing end of the month's one line of chrome
 * `Contract\StylesheetSourceInterface` — a package whose COMPONENTS are drawn
   inside other people's pages publishes the sheets they need and the head
   links them, because a stylesheet link outside the head is not conforming
   HTML and a page cannot link one for a component it has never heard of
 * every caption ported from a design `font:` shorthand states its leading:
   the shorthand resets line-height to normal and the longhand port inherited
   1.5, which is where the action row's three pixels came from
 * the vocabulary conformance base also answers for `shell:` icon names: a
   mark nobody shipped was an empty box on a deployment with fetching off and
   green in every suite
 * the action row's captions keep the design's own leading (`line-height:
   normal`), which is the three pixels the control row had grown
 * a nav row may say its children are its own SCREENS rather than places
   (`NavItem::$screens`), so a section with three tabs under it draws them at
   the screen rung instead of inventing a place between the two
 * AN ORG-LEVEL SECTION GETS ITS `Configure` ACTION. The shell's own configure
   page renders a section into an AREA's frame, so a surface with no area in
   its address got no action at all and read as a place you could not set up.
   A surface that declares configure screens of its own now has its action
   open the first of them — the same control, in the same place, opening the
   same kind of screen as everywhere else.

 * THE HOUSE CREATE CARD is shell vocabulary: `.dcadd` and `.crcard` with their
   parts. Three sheets carried a copy of it and the three had drifted apart by
   a border radius and a label size.

 * TOP-LEVEL SECTION MARKS — the ranked bars, the bound a bounded card ends on,
   the attachment matrix and its dot, the doors at the foot of an overview, a
   card's footer strip and its lead, a grouped table's band row, a vocabulary
   row's quiet edit, and the strip entry for a section that is named but not
   drawn yet. A section is a house surface, so its marks are house marks.

 * THE DELTA PILL: a figure's movement against the previous period, said once
   here instead of in three sheets.

 * the document, the page frame, the navigation contracts and the theme
 * the design-system stylesheet every module's own sheet is written against
 * the widget machinery under `Widget/`: the surface registry, the stored
   layouts and the library component every dashboard is arranged through
 * the chosen chip is FILLED, not outlined (`.mchip.on`, with the grip and the
   remove cross taking the accent's own ink), and `.staddrow` — a form's
   actions as the last row of its body — is the frame's rather than the area's
 * `.btn`, `.cta` and `.tgl` share one base: one line box, one 32px minimum,
   so the three sit on one baseline whatever element each is written on
 * ONE PALETTE for every category in the product: `--cat-1..9` in both themes,
   `--cat-p-1..9` for imagery, the `[data-cat]` indirection, the `.viewer`
   repaint rules, `.catsw`, the plate tokens, and the nine `--dept-*` as
   aliases — a module writes an index and never a colour
 * `.focusline`, the one left mark a card may carry — it means focus, it is
   paint rather than box, and the conformance suite now fails a module sheet
   that draws a left rail on a card of its own
 * `.btn` and `.cta` render identically on `<a>`, `<button>` and
   `<input type="submit">` — appearance, font and line box neutralised, so a
   Discard beside a Save is not the browser's grey button (`.cta` named no
   font at all), and the duplicated `.btn` block is one block again
 * `.c > .more`, the card's one quiet door pinned to its top edge, so a module
   that draws a way out of a card does not pin it with a rule of its own
 * `.mchip.ghost`, the quiet chip a month stepper's arrows wear, and the mark's
   hue read from `--pill-hue` so a finished problem is a hollow red mark
 * no library door on a surface: `.w-addtile` is gone and the dashed add tile
   belongs to the library's composer as `.w-addwidget`, so a module that draws
   "Add widgets — open the library" at the foot of a page writes a class the
   shell does not ship and fails its vocabulary test — a page reaches its
   library through the action in its page header
 * every `<time>` on the page read in the viewer's own timezone, in the compact
   stamps the designs draw, with a conformance base a module adopts to keep an
   instant from ever being formatted server-side
