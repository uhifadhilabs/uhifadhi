# RegistryBundle

The **registry**: the runtime every uhifadhi module registers with. It carries
the module catalogue, the per-area record of what is switched on, the
permissions modules declare, and the automatic sync that keeps the catalogue in
step with what is installed. It renders nothing.

One of the bundles of the uhifadhi core, `uhifadhi/uhifadhi`. It can be
installed on its own as `uhifadhi/registry-bundle`.

## Contents

- [What it is](#what-it-is)
- [Installation](#installation)
- [Parking a module closes its routes](#parking-a-module-closes-its-routes)
- [Configuration](#configuration)
- [Learn more](#learn-more)
- [License](#license)

## What it is

**Uhifadhi is one skeleton, one core and a set of modules.** The skeleton
(`uhifadhi/skeleton`) is copied once and never updated; the core
(`uhifadhi/uhifadhi`) arrives whole and is updated forever; everything a
deployment can *do* — patrols, incidents, rosters — is a module.

A module **registers with the registry** (this bundle) and **renders in the
shell** (`ShellBundle`). Each core bundle can be installed into a Symfony
application that also has the registry and the shell; Composer enforces that
dependency, and a bundle listed in an application's bundle list is not a promise
that it runs alone.

## Installation

The core is one package:

```bash
composer require uhifadhi/uhifadhi
```

Flex adds `Uhifadhi\Bundle\RegistryBundle\RegistryBundle` to
`config/bundles.php` and copies `config/packages/registry.yaml` in.

### An area is required, and a bundle answers it

The per-area table has a `NOT NULL` foreign key to an area, so until
`AreaInterface` resolves to a class there is no schema to create. `AreaBundle`,
which ships in the same package, states the resolution for you.

**Your installation writes no `doctrine.yaml` line at all.** You write a
resolution line only to disagree — see
[docs/configuration.md](docs/configuration.md) for that, and for why the registry
cannot name an area class itself.

### Then the tables

```bash
bin/console doctrine:database:create
bin/console doctrine:migrations:diff      # your history, your migration
bin/console doctrine:migrations:migrate
bin/console cache:clear                   # the registry reconciles itself
```

The registry ships no migration versions: the tables are the registry's, the
migration history is the installation's. There is **no seed command** — the
catalogue is reconciled by a `kernel.cache_warmer`, which Symfony runs on
`cache:warmup`, on `cache:clear`, and on the first request if neither has.

## Parking a module closes its routes

Where an area has parked a module, that module's pages answer **404** there —
enforced once, in the registry, before any controller runs. Nothing is asked of
the module, but a module that says which one it is gets read precisely rather
than inferred from its URL. One line per controller:

```php
use Uhifadhi\Bundle\RegistryBundle\RegistryBundle;

#[Route(defaults: [RegistryBundle::MODULE_ROUTE_DEFAULT => 'your-slug'])]
final class YourModuleController { /* every route below is yours */ }
```

404 and not 403: parking withholds nothing, it means the area is not running the
module. See [docs/guarantees.md](docs/guarantees.md) for the recognition rules,
the cost, and what the gate deliberately does not do.

## Configuration

```yaml
# config/packages/registry.yaml (your application)
registry:
    default_category: operations   # where an unplaced module is filed
    dev_tools: false               # dev-only tooling; enable via when@dev / when@test
```

Both keys have defaults and the tree is closed. There is deliberately no key
listing modules — see [docs/configuration.md](docs/configuration.md).

## Learn more

- [docs/architecture.md](docs/architecture.md) — what the registry owns, why zero
  modules is a working installation, and where each piece lives.
- [docs/boundaries.md](docs/boundaries.md) — what the registry is not: why the
  module grid and the customize screen belong to the shell, with the split in a
  table.
- [docs/guarantees.md](docs/guarantees.md) — the behaviour table, every row a
  test, and the attention-list promise behind it.
- [docs/configuration.md](docs/configuration.md) — the `registry:` tree, resolving
  the area contract, bringing your own area, and whose migration history the
  tables are.
- [docs/development.md](docs/development.md) — the standard, tests-first, and
  the two test kernels.

## License

**AGPL-3.0-or-later** — see the core's [LICENSE](../../../../LICENSE). Use,
modify and self-host freely; if you offer a modified version to users over a
network, they are entitled to the source of what they're running.
