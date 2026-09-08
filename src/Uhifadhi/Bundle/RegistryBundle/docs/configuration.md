# Configuration

The `registry:` config tree, how the area contract gets resolved — including
disagreeing with the bundle that answers it — and what the registry does and does
not ship for migrations.

## Contents

- [The `registry:` tree](#the-registry-tree)
- [An area is required, and a bundle answers it](#an-area-is-required-and-a-bundle-answers-it)
- [The tables, and whose migration history they are](#the-tables-and-whose-migration-history-they-are)

## The `registry:` tree

```yaml
# config/packages/registry.yaml (your application)
registry:
    default_category: operations   # where an unplaced module is filed
    dev_tools: false               # dev-only tooling; enable via when@dev / when@test
```

Both keys have defaults; the tree is closed, so an unknown key fails loudly
rather than being ignored. There is deliberately **no key listing modules** —
installing a module is the declaration, and a second place to enable one is
a second place for the two to disagree.

## An area is required, and a bundle answers it

The registry owns two tables and the per-area one has a `NOT NULL` foreign key to an
area, so until `AreaInterface` resolves to a class there is no schema to create —
every tool that walks the association stops:

```console
$ bin/console doctrine:schema:create
In MappingException.php line 72:
  Class 'Uhifadhi\Contracts\Entity\AreaInterface' does not exist
```

Booting is fine — an installation between `composer require` and its first entity
must still boot, and `Integration/InstallabilityTest` pins both halves of that.

**Whoever knows the answer states the resolution.** That is the fleet's rule and
it settles this one: the registry cannot name an area class, because it holds the
per-area table for installations whose area model is their own — but the bundle
that *provides* an area can, and does.

The contract itself is `Uhifadhi\Contracts\Entity\AreaInterface`, published
by uhifadhi/contracts alongside the user contract, because more than the
registry points at an area.

`AreaBundle` maps its own entity and prepends the resolution, exactly the way
`TeamBundle` answers the user contract; both ship in the core, so **your
installation writes no `doctrine.yaml` line at all** — a bare installation
reaches `doctrine:migrations:diff` with zero doctrine edits.

**This is deliberately not a hand-step.** A hand-step is for a decision only the
installation can make, and "what is an area" is not one while a bundle in the
same package ships an answer. A missed hand-step here would fail a long way from
its cause — the container compiles, the kernel boots, and the diff stops on the
message above with nothing pointing back at the paragraph that was skipped.

### Bringing your own area

You write a resolution line only to **disagree**. An installation whose areas are
its own entity — its own columns, its own name for the thing — names that class
in its own config and wins, because prepended configuration loses to the
application's by design:

```yaml
# config/packages/doctrine.yaml (your application)
doctrine:
    orm:
        resolve_target_entities:
            Uhifadhi\Contracts\Entity\AreaInterface: App\Entity\ManagementUnit
```

Merge it into the `doctrine:` block already in that file, under the existing
`orm:`, beside `mappings` — a second `doctrine:` key in one file is not valid
YAML. Your class needs `getId()` and nothing else, and it has to be in the
mapping chain: the stock doctrine-bundle recipe writes an `App\Entity` prefix, so
an entity in `src/Entity/` is already covered. If it is not, the line resolves and
the class it names is still missing from the chain:

```console
The class 'App\Entity\ManagementUnit' was not found in the chain
configured namespaces App\Entity, Uhifadhi\Bundle\RegistryBundle\Entity
```

`Uhifadhi\` on its own is the platform's, not an application's — this bundle is
`Uhifadhi\Bundle\RegistryBundle\` — so do not reach for it as your own root. The left-hand side is
the registry's and never changes.

## The tables, and whose migration history they are

`doctrine/doctrine-migrations-bundle` is a dependency of **this** bundle: the
bundle that adds tables brings the tool that creates them, the same way it has
always brought the ORM. An installed project that lacked it had no
`doctrine:migrations:*` commands at all and no hint that it should.

What the registry deliberately does **not** ship is migration versions. The tables
are the registry's; the migration history is the installation's, and a vendor
replaying its own versions into it would fight every `diff` the host ever runs.
