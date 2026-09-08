# Development

## Contents

- [The standard](#the-standard)

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
