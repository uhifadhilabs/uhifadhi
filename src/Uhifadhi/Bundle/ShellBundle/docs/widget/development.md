# Development

## Contents

- [The standard](#the-standard)
- [When a library port is finished](#when-a-library-port-is-finished)

## The standard

The core is one repository; `composer check` at its root is the whole verdict.

```bash
composer install
composer check   # cs:check -> phpstan (max) -> require-check -> the suite
```

The widget suite needs the core's Postgres database (`UHIFADHI_TEST_DATABASE_URL`,
see `phpunit.dist.xml`). It boots a real installation — framework, doctrine,
security, Twig — and resolves `Uhifadhi\Contracts\Entity\UserInterface` to an
account class of its own, which is exactly what an installation whose people are
its own entity does. It cannot boot `TeamBundle` to do it: Team depends on the
shell, and a suite that inverted that would be testing a dependency the core
forbids.

## When a library port is finished

**A widget library port is not complete until, on the rendered page, a preset can
be previewed, switched, and the page shows the new preset as active.** Stored
rows being right, and the resolver returning the right layout, are two different
questions and neither of them is that one — a library has shipped with both green
and cards that did nothing when clicked.

`tests/Widget/Functional` is where that question is asked. It boots an
installation with a page in it, signs a real person in, and drives an
**area-scoped** surface through the real routes: render the library, read the
token off the page, apply a design, read the surface's own page, read the library
again. What it pins:

- the library opens on the design the surface ships, and exactly one card wears
  **Active**;
- the page carries everything a preview needs without a round trip — every
  preset's layout in the embedded catalogue, and a cloneable render of every
  widget;
- applying a design changes the surface's page to that composition, and the
  library then marks that design active;
- copy, rename and delete travel the same page, and the toolbar changes with what
  is on;
- a write without the token the page rendered is refused and changes nothing.

Add the same coverage for a surface you port. A unit test that never renders the
page cannot see this class of miss.
