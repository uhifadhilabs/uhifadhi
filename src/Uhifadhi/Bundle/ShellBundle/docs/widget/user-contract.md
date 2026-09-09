# Resolve the user contract

Every stored layout points at `Uhifadhi\Contracts\Entity\UserInterface`
rather than at any bundle's account class — a module that named one would be a
module nobody can install without it. So something has to say what the interface
means, and **the shell is not the one that can**: it stores layouts, it does
not know who your people are.

**If `TeamBundle` is installed, it is already answered and you write
nothing.** Team provides the account class, so team states the resolution
itself — its bundle prepends

```yaml
doctrine:
    orm:
        resolve_target_entities:
            Uhifadhi\Contracts\Entity\UserInterface: Uhifadhi\Team\Entity\User
```

and there is no step here to perform.

**Without team, or with an account class of your own, it is yours to write** —
and it is one line:

```yaml
# config/packages/doctrine.yaml
doctrine:
    orm:
        resolve_target_entities:
            Uhifadhi\Contracts\Entity\UserInterface: App\Entity\Person
```

Your class has to answer the contract's seven questions — `getId`,
`getUuidString`, `getEmail`, `getFirstName`, `getLastName`, `getFullName`,
`getRangerCode` — and be in the mapping chain. **This wins even when team is
installed:** team's answer is *prepended*, and prepended configuration loses to
the application's, so naming your own class is all it takes and there is nothing
to disable first.

**Merge it into the block already there.** `config/packages/doctrine.yaml`
already opens with `doctrine:`, and a second `doctrine:` key in the same file is
not valid YAML — Symfony refuses to boot with `Duplicate key "doctrine"
detected`. Put `resolve_target_entities` under the existing `orm:`, beside
`mappings`.

**One map, every module.** `resolve_target_entities` is a single mapping, so the
area contract's `AreaInterface` line and this one sit side by side under the same
key — and a module installed later that points at a person needs nothing added.
The area line is always yours: only your installation knows what it calls an area.

**How far you get without it** — the bundle installs, the container compiles and
the kernel boots. What you cannot do is touch the schema: anything that walks the
metadata stops with

```
In MappingException.php line 72:
  Class 'Uhifadhi\Contracts\Entity\UserInterface' does not exist
```

That is not a bug to route around. A layout with no owner is not half a layout.

## Contents

- [Configuration](#configuration)

## Configuration

**There is none, and there is no configuration tree.** Where a dashboard's
widgets live, what they are called and how wide they sit are the declaring
module's to say. A second place to say them would be a second place for the two
to disagree.

The recipe's `config/packages/widget.yaml` therefore carries the one thing that
may still be yours: the `resolve_target_entities` step above, which
`TeamBundle` answers for you when it is installed.
