# `Entity\UserInterface`

The person a module's record points at, and how an installation resolves it to a real class.

## Contents

- [Why a module type-hints an interface](#why-a-module-type-hints-an-interface)
- [Whoever knows the answer states the resolution](#whoever-knows-the-answer-states-the-resolution)
- [A measured surface](#a-measured-surface)

## Why a module type-hints an interface

Almost every module keeps records with a name on them — who reported the incident, who led the
patrol, whose dashboard layout this is — and the accounts themselves belong to **TeamBundle**, one
of the five bundles in the core (`uhifadhi/uhifadhi`). A module that type-hinted that bundle's
`User` would be a module bound to one concrete account class, so it takes the contract and the
installation resolves it:

```php
// src/Entity/Sighting.php (your bundle)
use Uhifadhi\Contracts\Entity\UserInterface;

#[ORM\ManyToOne(targetEntity: UserInterface::class)]
private ?UserInterface $recordedBy = null;
```

```yaml
# what TeamBundle prepends for you — no installation writes this
doctrine:
    orm:
        resolve_target_entities:
            Uhifadhi\Contracts\Entity\UserInterface: Uhifadhi\Bundle\TeamBundle\Entity\User
```

## Whoever knows the answer states the resolution

The bundle that provides the entity is the bundle that names it, so **TeamBundle** prepends that
line and an installation writes nothing. The area contract
(`Uhifadhi\Contracts\Entity\AreaInterface`, which lives in this package beside this one) is
answered the same way, by **AreaBundle**. Both ship in the core, so a bare installation reaches
`doctrine:migrations:diff` with zero doctrine edits.

You write a `resolve_target_entities` line only to **disagree** — naming your own
class, which wins, because prepended configuration loses to the application's. It
is one map under the `doctrine:` block your file already opens with, so merge
into it rather than adding a second `doctrine:` key, which is not valid YAML.

## A measured surface

Seven questions — `getId`, `getUuidString`, `getEmail`, `getFirstName`, `getLastName`,
`getFullName`, `getRangerCode` — and no more. It is a measured surface, not the account class with
the word `interface` after it: passwords, tokens, roles and positions stay with whoever owns
accounts. It imports nothing, carries no mapping of its own, and is **not**
`Symfony\Component\Security\Core\User\UserInterface` — that one answers "who is signed in" and
still comes from the token storage.
