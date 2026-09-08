# Changing the contract

The policy for changing a socket, a theme token or a component class — the
sockets listed in [the block contract](blocks.md), the tokens listed in [the
theme](theming.md) and the classes listed in [the component
vocabulary](components.md).

## Contents

- [The policy](#the-policy)

## The policy

All three are a public API. The policy is short, and it is enforced by the
frozen lists refusing to agree with anything else:

- **Adding** a socket, a token or a component: append it to the frozen list in
  the test, in the group it belongs to, with the comment saying who fills it;
  bump the minor version. `LayoutContract::VERSION` exists so a module can
  require a shell that has the sockets it fills.
- **Renaming**: major version, a deprecation cycle, and the old name kept as an
  alias block for one release. `content` is the worked example. A renamed
  *component* deserves particular care: unlike a socket, it does not fail a
  module's build — it silently stops applying, and the report is a screenshot.
- **Removing**: as renaming, plus a note in this document.

**`LayoutContract::VERSION` numbers the sockets, and only the sockets.** It is
what a module asserts against when it fills one, so it moves when the socket
list moves and stays put otherwise — 0.6 added a component list and no socket,
and left it at 1. The package version is what carries everything else: a module
that needs `.kpi` from the frame requires `^0.6`.

Editing a frozen list to make a build pass is the failure mode the lists exist to
catch.
