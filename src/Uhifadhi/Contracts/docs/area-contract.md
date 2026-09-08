# `Entity\AreaInterface`

The area a module's record points at, and how an installation resolves it to a real class.

## Contents

- [Why a module type-hints an interface](#why-a-module-type-hints-an-interface)
- [Whoever knows the answer states the resolution](#whoever-knows-the-answer-states-the-resolution)
- [The precedent: it works exactly like the user contract](#the-precedent-it-works-exactly-like-the-user-contract)
- [A measured surface](#a-measured-surface)

## Why a module type-hints an interface

Several modules keep records that belong to an area — the registry's record of which modules an
area has switched on, a department confined to one area, a patrol walked inside one — and the
area itself belongs to **AreaBundle**, one of the five bundles in the core (`uhifadhi/uhifadhi`).
A module that type-hinted that bundle's `AreaOfInterest` would be a module that could never be
pointed at an installation's own area class. So it takes the contract and the installation
resolves it:

```php
// src/Entity/Department.php (TeamBundle)
use Uhifadhi\Contracts\Entity\AreaInterface;

#[ORM\ManyToOne(targetEntity: AreaInterface::class)]
private ?AreaInterface $area = null;
```

```yaml
# what AreaBundle prepends for you — no installation writes this
doctrine:
    orm:
        resolve_target_entities:
            Uhifadhi\Contracts\Entity\AreaInterface: Uhifadhi\Bundle\AreaBundle\Entity\AreaOfInterest
```

`resolve_target_entities` is Doctrine's own mechanism: an association mapped to the interface is
built against the concrete class it names, at compile time. The module that declares the relation
never imports the area class, and no module-to-bundle dependency is created — the coupling runs
through the contract, not between the packages.

## Whoever knows the answer states the resolution

The bundle that provides the entity is the bundle that names it, so **AreaBundle** prepends that
line and an installation writes nothing. The user contract
(`Uhifadhi\Contracts\Entity\UserInterface`, which lives in this package beside this one) is
answered the same way, by **TeamBundle**. Both ship in the core, so a bare installation reaches
`doctrine:migrations:diff` with zero doctrine edits.

You write a `resolve_target_entities` line only to **disagree** — naming your own class, which
wins, because prepended configuration loses to the application's. It is one map under the
`doctrine:` block your file already opens with, so merge into it rather than adding a second
`doctrine:` key, which is not valid YAML. Your class has to answer `getId()` and be in the
mapping chain.

## The precedent: it works exactly like the user contract

A module shows a person's name without naming TeamBundle's `User`, by pointing at `UserInterface`
and letting TeamBundle resolve it. An area is referenced the same way, for the same reason: a
record belongs to an area the way it belongs to a person, and neither reference should drag the
concrete class of the bundle that owns the thing into a module's `use` block.

The contract sits in this package rather than in AreaBundle because of who exchanges it. Pointing
at an area is something *many* parties do — the registry's install ledger, a department, a patrol,
and a module written by somebody who has never read this repository. A promise exchanged between
modules and the platform lives with the contracts; a promise about one bundle's own material lives
with that bundle. This one is the first kind.

## A measured surface

Three questions — `getId`, `getName`, `getUuidString` — and no more. Each is one a consumer was
found to ask:

- **`getId(): ?int`** — the persistence identity Doctrine's association is built on, and the one
  thing that tells two areas apart. Null before the area has ever been stored; never put in a URL
  or an API payload.
- **`getName(): ?string`** — the name to **render**: what a module prints to say which area a
  record belongs to. Null before the area has been given one.
- **`getUuidString(): ?string`** — the **public address**, a UUIDv7 in RFC 4122 form. This is what
  crosses a module boundary: a URL, an export column, a foreign-key surface in a record not worth a
  relation. A string, not a `Symfony\Component\Uid\Uuid`, so the package stays dependency-free; the
  caller that wants the object builds it.

That is enough to **reference** an area (the id, through the relation), **render** it (the name),
**address** it (the uuid), and **enumerate** areas via the ORM against the interface itself — a
repository can query `AreaInterface::class` and read every area's name and uuid without ever
naming `AreaOfInterest`:

```php
// enumerating areas without naming AreaBundle's entity
$areas = $em->getRepository(AreaInterface::class)->findAll();
foreach ($areas as $area) {
    // $area->getName(), $area->getUuidString()
}
```

Everything else an area has — its boundary geometry, its protection category, its designation year —
is the area owner's business and is deliberately absent, because a contract is only as portable as
it is narrow. It imports nothing and carries no mapping of its own: the attributes belong on the
owning side, in the module that declares the association.
