# The glyphs the core draws

One prefix — `shell:` — registered by `ShellBundle::prependExtension()` and
resolved from the files beside this page, so every core screen draws itself on
an installation that has configured no icon set at all and has no network
access.

## Contents

- [`shell:` — every glyph the core draws](#shell--every-glyph-the-core-draws)
- [One prefix per package](#one-prefix-per-package)
- [Adding one](#adding-one)

## `shell:` — every glyph the core draws

Two kinds of mark share the directory because they are reached by the same
name: the furniture's own — the sidebar's collapse chevron, the tree's caret,
the theme toggle, the alerts bell and the module grid's lens marker — and the
drawn vocabulary the shell, the team screens and the area screens all use.

Every file here answers a name some template or navigation row asks for, and
`tests/Core/IconsResolveOfflineTest.php` fails the build if a name is added
without its file — with on-demand fetching off, so what is proved is that these
files alone are enough.

They are **not** a design system. A deployment names its own icons in its own
set.

## One prefix per package

An icon set maps a prefix to a single `path`, and a prefix mapped that way is
answered **only** from that path: `LocalSvgIconRegistry::get()` does not fall
back to the application's `icon_dir` for it. Registering a prefix is therefore
taking that word away from everybody else in the installation.

So the core claims exactly one, and it is its own. Every well-known public
prefix stays free, which is what lets an installation answer one from its
`assets/icons/…` and lets an installer drop files there.

A module does the same in its own bundle, under its own alias:

```php
$container->extension('ux_icons', [
    'icon_sets' => [
        'sightings' => ['path' => __DIR__.'/assets/icons/sightings'],
    ],
]);
```

and then draws with `ux_icon('sightings:binoculars')`. A glyph it wants from a
public icon library is **copied into that directory**, never reached for under
the library's own prefix.

<https://symfony.com/bundles/ux-icons/current/index.html#full-configuration>

## Adding one

Import it with the command that wrote these, pointing the icon directory at
this one, then commit the result under the bare name so it is drawn as
`shell:<name>` — committing it is what ux-icons calls *locking* an icon:

```bash
bin/console ux:icons:import lucide:<name>
```

Paths are from [Lucide](https://lucide.dev) (ISC License, © Lucide Contributors).

<https://symfony.com/bundles/ux-icons/current/index.html#importing-icons>
