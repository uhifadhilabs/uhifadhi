# Why one package?

Why the registration contracts and the data-shape contracts ship together in one package, why that
package sits inside the core monorepo rather than beside the bundles, and the test that would split
it.

## Contents

- [One package, because there is one consumer species](#one-package-because-there-is-one-consumer-species)
- [One package inside the monorepo](#one-package-inside-the-monorepo)
- [The test that would split it](#the-test-that-would-split-it)

## One package, because there is one consumer species

Symfony ships its contracts split per domain — `symfony/cache-contracts`,
`symfony/event-dispatcher-contracts` — because outsiders consume single domains: Monolog
wants the log interfaces and nothing else, and making it drag in the cache ones would be rude.

This package does not split, because its consumer species is singular: **modules**. A module
needs the registration contract simply to exist — that is what makes it a module rather than a
bundle — so nobody wants the overview-contribution interfaces *without* registration. Split
packages here would always be installed together, and two packages that never travel apart are
a boundary drawn in the wrong place. The test is whether either half has an independent life,
and here neither does.

## One package inside the monorepo

One package is not the same as one *repository*. `uhifadhi/contracts` lives at
`src/Uhifadhi/Contracts/` inside the core monorepo, next to the bundles it describes, with its own
`composer.json` and its own PSR-4 root. It is split-ready like everything else there: one directory,
one package, readable and releasable on its own.

Living in the monorepo is what keeps a contract and the bundle that reads it honest with each other
— they are tagged in lockstep, so a promise and its keeper can never drift a release apart. Staying
a **separate package from the bundles** is what keeps the contracts cheap: a capability module
requires the core for the runtime it renders into, but the classes it `implements` and type-hints
come from a package that holds interfaces, value objects and traits and nothing else. That is why
the licence boundary can sit here too — the contracts are MIT while the bundles around them are
AGPL-3.0-or-later — and why a module author's `implements` clause never obliges them to adopt the
platform's licence.

## The test that would split it

The day the singular-consumer claim stops being true, split. If a consumer appears that is not a
module — an external tool that wants only the data-shape contracts and has no interest in
registering with a host — then that domain has an audience of its own and has earned its own
package. Not before.
