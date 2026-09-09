# uhifadhi/uhifadhi

**The uhifadhi core.** One package, one version, several bundles: each bundle
under `src/Uhifadhi/Bundle/` is its own PSR-4 root with its own `composer.json`,
and one tag on this repository is the platform version.

The core arrives whole and is never picked apart. What a deployment can *do* —
patrols, incidents, rosters — arrives as **modules**, each its own package.

New to uhifadhi? Start with [uhifadhi/skeleton](https://github.com/uhifadhilabs/skeleton),
which says what the platform is and how an installation is created. This
repository is the core that installation runs on, written for the developer who
updates it or builds a module against it.

## Contents

- [What the core is](#what-the-core-is)
- [The packages](#the-packages)
- [Installation](#installation)
- [The controllers an installation registers](#the-controllers-an-installation-registers)
- [Development](#development)
- [Versioning](#versioning)
- [License](#license)

## What the core is

Each core bundle can be installed into a Symfony application that also has the
registry and the shell; Composer enforces that dependency, and a bundle listed
in an application's bundle list is not a promise that it runs alone.

That is why "the core" is the word an installer document uses, and "bundle" is
a word for developers. An admin installs *the core* and then *modules*.

## The packages

| Package | Installable alone as | What it is |
|---|---|---|
| `src/Uhifadhi/Contracts` | `uhifadhi/contracts` | the interfaces a module declares itself with — MIT, and depended on by modules that want nothing else |
| `RegistryBundle` | `uhifadhi/registry-bundle` | the module catalogue, the per-area install ledger, parking, declared permissions |
| `ShellBundle` | `uhifadhi/shell-bundle` | the document, the page frame, navigation, the theme, widget surfaces |
| `TeamBundle` | `uhifadhi/team-bundle` | people: the account, positions, departments, the sign-in and invitation screens |
| `AtlasBundle` | `uhifadhi/atlas-bundle` | maps, charts and the chrome every one of them wears |
| `AreaBundle` | `uhifadhi/area-bundle` | the ground: areas, their boundaries, the zones inside them and the overview |

The contracts stay a package of their own so a capability module can depend on
interfaces alone, and stay MIT while the runtime around them is AGPL: an
interface anybody may implement should cost nobody anything.

## Installation

```bash
composer require uhifadhi/uhifadhi
```

Flex writes one `config/bundles.php` line per core bundle and copies one
`config/packages/<bundle>.yaml` each. Then the tables:

```bash
bin/console doctrine:migrations:diff      # your history, your migration
bin/console doctrine:migrations:migrate
bin/console cache:clear                   # the registry reconciles itself
```

The core ships **no console commands** and **no migration versions**: the tables
are the core's, the migration history is the installation's, and devkit — a
development-only package — owns every command the platform has.

## The controllers an installation registers

The core's Stimulus controllers reach an application through its
`assets/controllers.json`, under **one** name — the package's own:

```json
{
    "controllers": {
        "@uhifadhi/uhifadhi": {
            "theme": { "enabled": true, "fetch": "eager" },
            "sidebar": { "enabled": true, "fetch": "eager" }
        }
    }
}
```

That name is the only one that can appear there. StimulusBundle resolves a
`controllers.json` key by stripping the `@`, asking Composer for the install
path of the package left over, and reading `assets/package.json` underneath it
(`Symfony\UX\StimulusBundle\Ux\UxPackageReader::readPackageMetadata()`). The
names in this package's `replace` block have no install path of their own, so
only `uhifadhi/uhifadhi` resolves — and the root [`assets/package.json`](assets/package.json)
is where every core controller is declared, each `main` pointing into the bundle
that owns the file.

Each entry also declares the `name` the markup uses, so a controller is called
`uhifadhi--shell-bundle--theme` whether it was resolved through this manifest or
through the bundle's own — which is why each bundle keeps its `assets/package.json`
too: after a split, that file is what the bundle's own package answers with, and
the templates do not change.

## Development

```bash
composer install
composer check   # cs:check -> phpstan (max) -> require-check -> the suite
```

- PHP 8.4+, PHPStan level **max** over `src` and `tests`, php-cs-fixer
  `@Symfony` + `@Symfony:risky`.
- **Tests first, always.** A behaviour change starts as a failing test naming
  the class or service id it wants.
- The suite is one PHPUnit testsuite per bundle, each in that bundle's `tests/`
  directory, against a real PostGIS database (`uhifadhi_core_test`, port 5434
  locally, the CI service in the workflow).
- `tests/Application/` is an embedded throwaway app — a real kernel with the
  core bundles on it — for the specifications that are about the bundles working
  together. It is export-ignored: nobody installs it.
- **`composer require-check` is the boundary.** Each bundle's `composer.json`
  declares what that bundle may use, and `composer-require-checker` fails the
  build on any symbol it did not declare. That is what makes splitting this
  repository a mechanical step rather than a refactor.

## Versioning

One tag on this repository is the platform version. The `replace` block names
the six packages this repository can be split into — the contracts and the five
bundles — each at `self.version`, so a `require` line naming one of them
resolves against the core and reports the core's version. Nothing else belongs
in that block: a name there is a promise that this package *is* that package.

The root [CHANGELOG-1.0.md](CHANGELOG-1.0.md) and [UPGRADE-1.0.md](UPGRADE-1.0.md)
are the release notes; each bundle also keeps its own `CHANGELOG.md`.

## License

**AGPL-3.0-or-later** — see [LICENSE](LICENSE). Use, modify and self-host
freely; if you offer a modified version to users over a network, they are
entitled to the source of what they're running.
