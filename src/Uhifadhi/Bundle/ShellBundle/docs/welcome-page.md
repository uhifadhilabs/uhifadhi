# The welcome page

What a fresh installation shows at `/`, who may read it, and the one line an
application writes to accept it.

## Contents

- [What a fresh installation shows](#what-a-fresh-installation-shows)

## What a fresh installation shows

Before a host has implemented either contract — and before it has a user, a
security bundle or a single route — the shell still renders: the brand tile and
wordmark, an empty sidebar, the top bar with its theme toggle, the page frame,
and whatever the page put in `shell_page`. Nothing is a placeholder and nothing
is a stub.

**And it has one page of its own**, `@Shell/welcome.html.twig` — the
screen an installation serves at `/` before it has grown a home screen. Until it
existed, an installation of the registry and the shell answered `/` with Symfony's
welcome-404: a correct installation looking like a broken one, on its first
minute. The page lists what is installed, says why the sidebar beside it is
empty, and says that `composer require uhifadhi/<name>-module` is what fills it.

**Who can read it is not the shell's question.** On an installation with no
firewall — the skeleton, the registry and the shell and nothing else — the page is
open because nothing is closed. On one that has installed identity
(`TeamBundle`), whose documented `security.yaml` is default-closed, the
same page is a signed-in view and a stranger asking for `/` is sent to `/login`
instead. Neither is something this bundle does or could do: the shell renders
what it is asked for, and `access_control` is the application's file.

**The list is read from composer at request time** — `Composer\InstalledVersions`,
through `src/Service/Installation.php` — because a page whose whole job is to
report on an installation cannot report a list somebody typed: the first module
anybody installs makes it wrong, and installing one is the instruction this same
page gives. Two rows carry a line of description, the shell's own and the contract's,
and no others do: a shell that described a module would be a shell that knew what
modules are.

`WelcomeController` reads that list and hands it to the template as an ordinary
variable. There is no `shell_packages()` Twig function: one page's data has no
business being in scope on every page in the platform.

**The route is the shell's; the address is yours.** The shell ships the route as
a resource and loads it nowhere. The page is reachable because the application
imports it, in one line, in `config/routes/shell.yaml` — which the project
skeleton ships:

```yaml
# config/routes/shell.yaml (your application)
shell:
    resource: '@ShellBundle/config/routes/welcome.php'
```

That defines the route `welcome` at `/`. Point `/` at your own home screen by
editing that file, or delete the import, the day the installation has a real home
screen. Nothing in the shell depends on the route name or on the route existing.
See [the boundary](boundaries.md#the-one-url-and-the-one-line-that-grants-it) for
why the mechanism is an import rather than something the bundle does for you.

The rest is a boundary, not a courtesy. The shell's own furniture asks for no viewer:
it never touches `app.user`, never calls `is_granted`, and generates the brand's
home link only if the configured route actually exists, falling back to `/`. The
account chrome — the user pill, sign-out, an impersonation banner — is the host's,
through `shell_topbar_actions` and `shell_banner`, because those are the sockets
whose content needs to know who is signed in. A shell that reached for a viewer
would fail on every installation that has not grown a team yet, which is every
installation on its first day.
