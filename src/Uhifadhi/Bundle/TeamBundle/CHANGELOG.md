# Changelog — TeamBundle

## Contents

- [1.0.0](#100)

## 1.0.0

Not released yet.

 * the Goals topic: every department is a row because any department can declare
   a goal, each goal's state derived at the moment of asking rather than stored,
   and "no figure yet" kept apart from a miss

 * no category bar on a permission group: a left mark on a card means focus, so
   a module's colour there said "you are here" on every group at once — the
   provenance is the heading's tag, its hue dot and its tint, as it already was

 * the account, the position that bundles permissions, and departments
 * the modules a department attaches — the lens that decides which modules
   lead its view and whose KPIs its performance surfaces roll up, granting
   nothing and hiding nothing
 * the user-provider entity and the `team.user_checker` a firewall names
 * the sign-in, forgotten-password, reset and invite-acceptance screens
 * the roster and the permission matrix, as widget surfaces
 * the API token a field client signs in with: the credential, the manager
   that issues and revokes it, and `POST /api/auth/token`
 * `GET /api/me`: the bearer account and every permission it holds, re-read on
   every sync so a grant made in the web app reaches a handset with no sign-out
 * `team:user:create`: the first administrator, made from the console once on a
   deployment — the one console command the core ships
