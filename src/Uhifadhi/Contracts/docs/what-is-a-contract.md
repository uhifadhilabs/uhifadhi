# What a contract is

"Contract" is used constantly around this platform — the module contract, the socket contract,
the theme contract — and it means one thing every time. This page explains what that thing is,
why some contracts live in this package and others live with the bundle they describe, and how
to tell which kind you are writing.

## Contents

- [A promise, separated from whatever keeps it](#a-promise-separated-from-whatever-keeps-it)
- [An interface is a contract written as code](#an-interface-is-a-contract-written-as-code)
- [What this package holds, and what it does not](#what-this-package-holds-and-what-it-does-not)
- [The shell's contract is a different kind of promise](#the-shells-contract-is-a-different-kind-of-promise)
- [Where a contract lives, and when it moves](#where-a-contract-lives-and-when-it-moves)

## A promise, separated from whatever keeps it

A contract is a **promise, written down in a place that is not the thing keeping it**.

That separation is the whole idea. If the promise lives inside the code that keeps it, only that
code can be trusted to keep it, and anything else that wants the same guarantee has to depend on
that code — its constructor, its dependencies, its database, its release schedule. Pull the
promise out into its own place and two things become possible at once: many implementations can
keep it, and many callers can rely on it, without any of them knowing about the others.

Everything below is that idea applied twice, in two different materials.

## An interface is a contract written as code

A PHP interface is the ordinary form. `ModuleProviderInterface` is a list of questions:

> What is your slug? Your name? Your category? Your status? Do you own your own pages, and if so
> at which route?

It contains no answers and no machinery. It cannot be instantiated, it reads nothing and it
writes nothing. It only states what a module must be able to say about itself.

When a Sightings module wants to appear in the catalogue, it implements that interface — it
**answers the questions**. `RegistryBundle`, which builds the catalogue, does not know Sightings
exists. It asks every service tagged `uhifadhi.module` those same questions and files the answers.

So the arrows both point at the promise, and neither points sideways:

```
  sightings-module  ──implements──▶  ModuleProviderInterface  ◀──reads──  RegistryBundle
```

Sightings depends on the interface. The registry depends on the interface. **Neither depends on the
other**, which is why a module written by somebody outside this repository installs into a
deployment that has never heard of it, and why the registry can be rewritten without touching a
single module.

The interface is the contract. The implementation is the keeping of it.

## What this package holds, and what it does not

`uhifadhi/contracts` is the place where the promises exchanged **between modules and the
platform** are kept, and it holds nothing else: interfaces, the value objects that cross them,
and the small traits that supply defaults. No services, no entities, no database, no framework
wiring. It ships inside the core, at `src/Uhifadhi/Contracts/`, and can be installed on its own as
`uhifadhi/contracts`.

That emptiness is the point. A module depends on this package, and depending on it must
cost nothing — no runtime, no configuration, no transitive machinery, nothing to boot. A package
of promises can be depended on by anything.

It is also why the license boundary sits here. This package is **MIT**, deliberately: implementing
a contract must never oblige anybody to adopt the platform's own license. The implementations
around it — the registry, the shell, the rest of the core — are **AGPL-3.0-or-later**. The two
licenses share a repository and a release tag and remain two licenses, because the package boundary
is where the obligation stops. Anyone may write a module against MIT promises and license their own
work however they choose.

Put in one line: **every module carries the DNA; none carries the organs.**

## The shell's contract is a different kind of promise

`ShellBundle` promises things a PHP interface has no way to say.

A module page fills a Twig block called `shell_page`. Its stylesheet writes
`background: rgb(var(--c-p1))` and `html.dark .sightings-card { … }`. Those are promises exactly
as binding as a method signature — rename the block and every module page renders blank, rename
the token and every module's cards render transparent on a screen nobody happens to be looking at
— but there is no interface in PHP that can express "a template declares a block named this" or
"a stylesheet defines a custom property named that".

So that contract is enforced by a different mechanism, and it lives with the shell rather than
here. `LayoutContract` is a frozen manifest: the twenty-three block names and the twenty-three
theme tokens, as data. Beside it sits a test that types **the same two lists out again by hand**.

The duplication is deliberate. A list derived from the templates would agree with whatever the
templates happen to say, and would have nothing to report the day somebody renames a block. Two
lists written independently **disagree loudly on purpose**: rename a block in the manifest and the
hand-typed list refuses; rename it in a template and the manifest refuses. There is no way to move
a socket without editing the test that exists to notice.

Different material, same idea: the promise is written down somewhere that is not the thing keeping
it.

## Where a contract lives, and when it moves

The shell also publishes two ordinary PHP interfaces — `NavigationSourceInterface` and
`AreaShellSourceInterface` — and they live in `ShellBundle` rather than in this package. The reason
is who implements them: today, mostly the host does. They are a promise between the shell and the
application that mounts it, not a promise between modules and the platform, so they belong with the
shell's own material.

The day modules implement one routinely — a module contributing its own sidebar row, say — that
interface is **hoisted into this package**, because it has become something modules and the
platform exchange. The Area contract is the worked example of that rule, and it sits here.

> **Rule of thumb.** A promise exchanged between modules and the platform lives in
> `uhifadhi/contracts`. A promise about one bundle's own material lives with that bundle, enforced
> by that bundle's tests.

The two contracts about *things a module points at* are the clearest illustration of the rule.
`Uhifadhi\Contracts\Entity\AreaInterface` is here because pointing at an area is something MANY
parties do — the registry's ledger of which modules an area switched on, a department confined to
one area, a patrol walked inside one — so the promise is exchanged between modules and the platform.
`Uhifadhi\Contracts\Entity\UserInterface` is here for the same reason: pointing at a person is what
modules do, from patrol records to incident reports to saved dashboard layouts, and so will a module
written by somebody who has never read this repository. Same mechanism on the Doctrine side —
`resolve_target_entities`, stated by whoever knows the answer — and the same escape hatch, an
installation naming its own class and winning.

The test is never "is this interface useful to more than one class?" but "who are the two parties
exchanging it?" An interface only the shell and the host trade lives with the shell however many
classes touch it; an interface a stranger's module must be able to name lives here even if exactly
one bundle answers it today.

The picture that holds it together: **`uhifadhi/contracts` is the shape of the plugs. ShellBundle is
the wall they plug into. RegistryBundle is the fuse box that knows what is switched on. And the
tests are the inspector who checks that none of the three quietly changed overnight.**
