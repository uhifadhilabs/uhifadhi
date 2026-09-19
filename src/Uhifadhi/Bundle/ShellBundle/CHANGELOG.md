# Changelog — ShellBundle

## Contents

- [1.0.0](#100)

## 1.0.0

Not released yet.

 * the document, the page frame, the navigation contracts and the theme
 * the design-system stylesheet every module's own sheet is written against
 * the widget machinery under `Widget/`: the surface registry, the stored
   layouts and the library component every dashboard is arranged through
 * no library door on a surface: `.w-addtile` is gone and the dashed add tile
   belongs to the library's composer as `.w-addwidget`, so a module that draws
   "Add widgets — open the library" at the foot of a page writes a class the
   shell does not ship and fails its vocabulary test — a page reaches its
   library through the action in its page header
 * every `<time>` on the page read in the viewer's own timezone, in the compact
   stamps the designs draw, with a conformance base a module adopts to keep an
   instant from ever being formatted server-side
