# Changelog — TeamBundle

## Contents

- [1.0.0](#100)

## 1.0.0

Not released yet.

 * `/departments/performance` — the organisation's own surface, wearing the
   AREA idiom: one header carrying the scope and the period, the Overview ·
   Topics · Briefing strip, one card a topic with its headline figure, and
   the matrix whose columns ARE the topics. Every figure on it is published
   by a topic; the page computes none of them. Topics and Briefing are
   routed and not yet drawn
 * the segmented MONTH · QUARTER · YEAR group, and the scope, in the ADDRESS
   rather than in a session — either page can be sent to somebody
 * a row in the sidebar under Observatory, with its three screens under it
 * `render_matrix()` — the DP·01 grammar, and the only table a topic's
   departments are drawn in: the shades a placing takes, the three absences
   told apart in words, the movement toned by the column and never by its
   sign, a sparkline whose holes stay open, and a legend that says what a
   shade is NOT. A cell that counts states draws chips through the same rule
   the figures use, so the two can never become two grids
 * where a department stands is the HOST's to work out, once: a column with no
   polarity is never tinted, a band is the boundary a placing is made inside,
   and fewer than three figures in a band place nothing
 * a second sheet, `bundles/team/performance.css`, carrying the board's own
   vocabulary, and a sort that runs INSIDE each band and never across one
 * the POSTINGS tab: who is posted to which station, across every area, with
   the house filter bar — area, zone, rank, whether the station has anybody —
   and the search. It writes nothing: a posting is made on the station, in the
   area that owns the ground, and a board that could post somebody would be a
   second write path for a fact one screen already owns. A station nobody
   stands at keeps its band and says so.

 * Departments wears the AREA IDIOM. It is a section now, not a single screen:
   the same header on every tab (the section's name), a subline that is that
   tab's own, one strip between the head and the body with exactly one tab lit,
   and the one Configure action at the right-hand end of the action row on
   every one of them. The tab set is Overview · Departments · Modules; the
   configure screens are Lists and Departments settings. Both are declared
   through the contracts a MODULE's tabs and configure sections already use —
   nothing here is a second implementation of a strip — and the sidebar row
   opens into the same three screens, because the tree and the strip are two
   readings of one list.

 * the section's OVERVIEW: the identity band, the five indexed KPI cards,
   positions filled per department and modules per department as ranked bars,
   and the bounded attention cards (departments reading no module, positions
   nobody holds, goals declared). It writes nothing and owns no figure on it:
   every one belongs to the register, to Team or to Performance.

 * the section's MODULES matrix: one row a department, one column an installed
   module, with the row and column totals. Absence is DRAWN, not left blank.
   The matrix reads and does not write — attaching is done on the department's
   own card, and a grid of checkboxes would be a second write path for one fact.

 * the section's CONFIGURE screens. Settings states the rules the model
   actually enforces and changes none of them; Lists edits the one list this
   section owns.

 * `DepartmentKind` — Operational, Scientific, Support: a way of grouping
   departments for READING. It grants nothing, confines nothing and changes no
   figure, which is why it is a row and not a PHP enum. A department with no
   kind is legal and reads as unkinded, and removing a kind leaves its
   departments standing.

 * the Attention & output topic: it adds up what the other topics publish, found
   by ROLE and never by label, and folds the departments with no computing
   module into one line a scope band — stating their seats and goals, because a
   nought there would say they were asked and answered none
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
