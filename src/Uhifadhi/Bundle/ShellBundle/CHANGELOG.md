# Changelog — ShellBundle

## Contents

- [1.0.0](#100)

## 1.0.0

Not released yet.

 * the document, the page frame, the navigation contracts and the theme
 * the design-system stylesheet every module's own sheet is written against
 * the widget machinery under `Widget/`: the surface registry, the stored
   layouts and the library component every dashboard is arranged through
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
