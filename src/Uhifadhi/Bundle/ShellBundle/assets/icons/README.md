# The glyphs the core draws

Two prefixes, both registered by `ShellBundle::prependExtension()` and both
resolved from these files, so every core screen draws itself on an installation
that has configured no icon set at all and has no network access.

## Contents

- [`shell:` — the furniture's own marks](#shell--the-furnitures-own-marks)
- [`lucide:` — the set every core screen shares](#lucide--the-set-every-core-screen-shares)
- [Why one directory per prefix](#why-one-directory-per-prefix)
- [Adding one](#adding-one)

## `shell:` — the furniture's own marks

The glyphs the shell's own chrome needs: the sidebar's collapse chevron, the
tree's caret, the theme toggle, the alerts bell and the module grid's lens
marker. They are **not** a design system — a deployment names its own icons in
its own set.

## `lucide:` — the set every core screen shares

The drawn vocabulary the shell, the team screens and the area screens all use.
Every file here answers a name some template or navigation row asks for, and
`tests/Core/IconsResolveOfflineTest.php` fails the build if a name is added
without its file — with on-demand fetching off, so what is proved is that these
files alone are enough.

## Why one directory per prefix

`ux_icons.icon_dir` is a single scalar and an icon set is **one path per
prefix**, so `lucide:` cannot be answered from three bundles at once. The shell
is the bundle the others already depend on, so the shared set lives here.

A consequence worth knowing: a prefix registered as an icon set is answered
**only** from its directory — `LocalSvgIconRegistry::get()` does not fall back
to the application's `icon_dir` for it. So anything needing a glyph outside the
shipped set ships a **prefix of its own**, exactly as `shell:` is one, rather
than dropping a file into an application's `assets/icons/lucide/`.

## Adding one

Import it with the command that wrote these, pointing the icon directory at
this one, and commit the result — that is what ux-icons calls *locking* an icon:

```bash
bin/console ux:icons:import lucide:<name>
```

Paths are from [Lucide](https://lucide.dev) (ISC License, © Lucide Contributors).

<https://symfony.com/bundles/ux-icons/current/index.html#importing-icons>
