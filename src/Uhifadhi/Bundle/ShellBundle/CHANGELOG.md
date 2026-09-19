# Changelog — ShellBundle

## Contents

- [1.0.0](#100)

## 1.0.0

Not released yet.

 * the vocabulary conformance base also answers for `shell:` icon names: a
   mark nobody shipped was an empty box on a deployment with fetching off and
   green in every suite
 * the action row's captions keep the design's own leading (`line-height:
   normal`), which is the three pixels the control row had grown
 * a nav row may say its children are its own SCREENS rather than places
   (`NavItem::$screens`), so a section with three tabs under it draws them at
   the screen rung instead of inventing a place between the two
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
