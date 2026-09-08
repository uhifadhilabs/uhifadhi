# TeamBundle

**Team**: who an installation's people are and how they sign in — the account,
the position that bundles permissions, the departments those positions are filed
under, and the screens a person meets before they are anybody.

One of the bundles of the uhifadhi core, `uhifadhi/uhifadhi`. It can be
installed on its own as `uhifadhi/team-bundle`.

## Contents

- [What it is](#what-it-is)
- [What it provides](#what-it-provides)
- [The firewall is the installation's](#the-firewall-is-the-installations)
- [Installation](#installation)
- [Modules point at your people](#modules-point-at-your-people)
- [Two axes: tier and position](#two-axes-tier-and-position)
- [The screens](#the-screens)
- [Configuration](#configuration)
- [License](#license)

## What it is

**Uhifadhi is one skeleton, one core and a set of modules.** The skeleton
(`uhifadhi/skeleton`) is copied once and never updated; the core
(`uhifadhi/uhifadhi`) arrives whole and is updated forever; everything a
deployment can *do* is a module.

This bundle is people, and only people. It is not the authorization runtime and
it is not the firewall — it is the account those two ask about.

## What it provides

| | What an installation gets |
|---|---|
| the provider entity | `Uhifadhi\Bundle\TeamBundle\Entity\User` — the class a `security.providers` entry names, addressed by email |
| the user checker | `team.user_checker`, public: a deactivated account is refused at the door with a reason, before the password is even weighed |
| the credential for a client | the API token a field client presents, the manager that issues and revokes it, and the endpoint that hands one out |
| the screens | sign in, forgotten password, reset, accept an invitation, the roster, one person's record, the permission matrix, departments |
| the widget surfaces | the roster and the matrix, each a catalogue the shell's widget machinery arranges |
| the sidebar row | one Team row, contributed to the shell's navigation where a shell is installed |

It also answers the platform's user contract. Registering the bundle prepends
`doctrine.orm.resolve_target_entities` for
`Uhifadhi\Contracts\Entity\UserInterface`, so every module that points a record
at a person has an entity to point at and the installation writes nothing.

## The firewall is the installation's

**This bundle ships no firewall and no access rule.** Which of an installation's
paths are public is a decision only that installation can make, so it is one
file in the installing project — `config/packages/security.yaml`, shipped by the
skeleton — and this bundle gives that file the three things it needs to name:
the provider entity above, `team.user_checker`, and the routes the sign-in form
posts to (`team_login`, `team_logout`).

Enforcement of a granular permission is not an access rule either: a permission
answers "may this person do X *here*", which a path pattern cannot express. It
is decided against the person the current token names, per action and per area.

## Installation

The core is one package:

```bash
composer require uhifadhi/uhifadhi
```

Flex adds `Uhifadhi\Bundle\TeamBundle\TeamBundle` to `config/bundles.php`,
copies `config/packages/team.yaml` in, and mounts the routes.

### Then the tables

```bash
bin/console doctrine:migrations:diff      # your history, your migration
bin/console doctrine:migrations:migrate
```

Four tables: `team_user`, `team_position`, `team_department` — which carries a
nullable **area**, and that is what makes a department org-level or area-level —
and `team_department_scope_change`, the trail of every scope change. This bundle
ships no migration versions: the tables are the bundle's, the migration history
is the installation's.

## Modules point at your people

A module that keeps a record with a person on it type-hints the contract, never
this bundle's class:

```php
#[ORM\ManyToOne(targetEntity: UserInterface::class)]
private ?UserInterface $recordedBy = null;
```

An installation writes a `resolve_target_entities` line only to **disagree**,
naming its own account class — application configuration beats a bundle's
prepend, which is what makes shipping the default safe rather than presumptuous.

## Two axes: tier and position

A person has a **tier** — Super Admin, Admin or Staff — and, separately, a
**position**. The tier is a coarse standing; the position is where granular
permissions come from, and it is the one an administrator composes on the matrix
screen. A Staff member holds exactly what their position carries.

A department's scope confines a Staff member's authority to one area, or leaves
it org-wide when the department has no area. Nothing else stores that reach: it
is derived from the position's department every time it is asked.

An installation always keeps one active Super Admin. Every write that would
lower the last one is refused before anything is stored.

## The screens

Sign in, forgotten password, reset and accept-an-invitation are the three a
stranger reaches with nobody to ask, so they work with no session at all. Behind
them: the roster, one person's record, the permission matrix and the
departments. There is no delete route and there will not be one — an account is
deactivated, never removed, so everything it recorded keeps its author.

## Configuration

```yaml
# config/packages/team.yaml (your application)
team:
    after_sign_in_path: '/'          # where an already-signed-in visitor at /login goes
    sign_in_lede: '…'                # the line under the mark on the sign-in card
    installation_name: '…'           # what the two letters sign themselves
    mail_from: ''                    # empty means this installation cannot send
```

`mail_from` is empty by default, and that is the honest default: it is what
makes the invite-by-email path refuse itself **in writing** rather than dropping
a colleague's invitation on the floor. A mailer with no transport reads the
same way.

## License

**AGPL-3.0-or-later** — see the core's [LICENSE](../../../../LICENSE). Use,
modify and self-host freely; if you offer a modified version to users over a
network, they are entitled to the source of what they're running.
