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
- [Signing a field client in](#signing-a-field-client-in)
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

**Throttling the form is that file's too.** Five attempts a minute blunts the
credential-stuffing surface, and it is one line on the firewall the form lives
on:

```yaml
# config/packages/security.yaml (your application)
security:
    firewalls:
        main:
            form_login: { login_path: team_login, check_path: team_login, enable_csrf: true }
            login_throttling: { max_attempts: 5 }
```

The field endpoint is throttled differently, because it has no firewall to do it
— see [Signing a field client in](#signing-a-field-client-in).

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

Five tables: `team_user`, `team_position`, `team_department` — which carries a
nullable **area**, and that is what makes a department org-level or area-level —
`team_department_scope_change` (the trail of every scope change) and
`team_api_token`. This bundle ships no migration versions: the tables are the
bundle's, the migration history is the installation's.

### Then the first administrator

Every screen is behind the sign-in an installation does not have yet, so the one
account that cannot be made through a screen is made from the console:

```bash
printf '%s' "$PASSPHRASE" | bin/console team:user:create ada@example.test Ada Mwangi
```

The account is a **Super Admin**, verified and active — somebody who can sign in
and compose everything else. `--tier=super-admin|admin|staff` names another
tier, and `--password=…` passes the passphrase inline instead of on standard
input.

**That command exists in a development install only.** This bundle ships no
console commands; it ships an inert *provider* that names one, and
`uhifadhi/devkit-module` — installed through `require-dev` — is what collects it
and turns it into a real command. A production build has no devkit and no
`team:user:create`, so the first administrator is made where the deployment is
built and the account travels in the database.

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

## Signing a field client in

A field client signs in once and then carries a bearer token, because somebody
working out of signal cannot re-authenticate on demand. That token is a
**credential of a person**, so it lives here beside the account — issued,
rotated and withdrawn like a password — and so does the authenticator that
reads one: `Uhifadhi\Bundle\TeamBundle\Security\ApiTokenAuthenticator`, which
an installation names in its firewall as both `custom_authenticators` and
`entry_point`. The entry point is what makes "no token at all" a **401** rather
than the 403 an access rule would give, and a client shows a person different
things for the two.

`POST /api/auth/token` is the one endpoint reachable without a token, and it is
firewall-free on purpose: a handset whose token has expired still holds it and
still sends it, and that stale header must never be what stops somebody signing
in again.

```jsonc
// the request
{ "rangerId": "sl-0142", "passcode": "…", "deviceId": "…", "deviceName": "…" }
```

`rangerId` is a service number, or an email address for staff who were never
issued one. `deviceId` and `deviceName` are optional; where the body names no
device the `X-Doria-Device` header is accepted instead, so a client need not say
the same thing twice.

```jsonc
// 200
{
  "token": "…64 hex characters…",
  "expiresAt": "2027-03-08T09:41:22Z",
  "ranger": { "id": "sl-0142", "name": "…", "role": "…" },
  "permissions": ["area.view"]
}
```

The token is handed back **once**; only its hash is stored, so a leaked database
yields nothing a handset could present. `permissions` is always sent, including
empty — an empty array is a refusal, and a *missing* field would read as
"permitted".

```jsonc
// 401, and the same document for every refusal
{ "code": "invalid_credentials", "message": "…", "retryable": false, "details": {} }
```

No such person, the wrong passcode and a deactivated account answer identically:
telling them apart would turn the one endpoint reachable without a credential
into a directory of who works here.

### Every `/api` failure is that same document

Not only this endpoint's. A 401 from the firewall, a 404 from routing, a 422
from validation and a 500 from anywhere each answer in their own way, which for
a refusal is an HTML error page — and a client parsing that gets a stack trace
where it expected a `code`. So the document is a property of the **URL space**:
anything failing under `/api` is answered as `{code, message, retryable,
details}`, with the status left exactly as whoever refused set it.

| status | `code` | `retryable` |
|---|---|---|
| 400 | `invalid_request` | false |
| 401 | `unauthorized` | false |
| 403 | `forbidden` | false |
| 404 | `not_found` | false |
| 405 | `method_not_allowed` | false |
| 406 | `not_acceptable` | false |
| 409 | `conflict` | false |
| 415 | `unsupported_media_type` | false |
| 422 | `invalid_payload` | false |
| 429 | `rate_limited` | **true** |
| 5xx | `server_error` | **true** |

An endpoint that can say something more precise throws
`Uhifadhi\Bundle\TeamBundle\Exception\ApiProblemException`, which carries its
own code, its own `retryable` and any `details` a client can act on, and is
answered verbatim. Everything a page renders keeps its own error handling: this
is the machine door only.

### It is throttled, and it throttles itself

Having no firewall means nothing upstream counts attempts for it, so it counts
for itself: **five a minute per identifier and twenty a minute per address**,
spent before the credential is weighed — so a valid credential replayed in a
storm is throttled like any other traffic. Two budgets rather than one, because
per-identifier stops a targeted guess against one account and per-address stops
a spray across many, and either alone leaves the other attack untouched.

```jsonc
// 429 — the one refusal worth repeating, because only time fixes it
{ "code": "rate_limited", "message": "…", "retryable": true, "details": {} }
```

The bundle **prepends** the two limiters, so an installation writes no
rate-limiter configuration; a deployment that wants other numbers names them in
its own `framework.yaml` and its answer wins, with nothing to switch off first.

The web form's twin of this is `login_throttling`, which the firewall does —
see below.

Signing in again on the same handset **rotates** that handset's row rather than
adding another, so a wipe leaves no trail of live credentials. A different
handset gets its own row, which is what lets one be withdrawn alone.

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
