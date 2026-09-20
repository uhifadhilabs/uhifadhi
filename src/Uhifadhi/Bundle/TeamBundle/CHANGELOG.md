# Changelog — TeamBundle

## Contents

- [1.0.0](#100)

## 1.0.0

Not released yet.

 * a section's children are drawn as its own SCREENS and not as places inside
   it: Team and Departments now say so (`NavItem::$screens`), so their rows
   sit on the screen rung like Performance's and the department records below
   the register keep the place rung to themselves
 * a department's CARD wears its own hue, the same category its row in the
   sidebar wears: the card carries the index and the mark reads it. The mark
   was accent-tinted for every ACTIVE department before this, which said what
   the module chips already say and left all nine identical.

 * the positions register creates a position in the HOUSE CREATE CARD at the
   top of its own page, always open, the department first — a name typed
   before the department is a name unique against nothing. The header's
   action jumps to it instead of holding a form of its own.

 * an invitation nobody opened is CHASED FROM THE RECORD. It rotates the
   token, so an old email in an inbox stops working, and it never touches the
   password — the person still chooses their own, which is the whole
   difference between an invitation and a handover. Offered and refused where
   there is no transport, never hidden.

 * the Team overview's KPI movements are READ FROM THE PERIOD HISTORY, which
   `team:performance:snapshot` now writes for the installation as well as for
   each department: three of the five figures belong to no department — an
   account with no position is in none, a posting is the area's, and the
   tiers are the installation's — so summing the department rows would drop
   exactly the loose bucket the overview draws as its own line. A period
   nobody wrote gets no pill.

 * the departments register's band carries what the departments DID — the
   modules' own figures through the performance seam, then the areas, the
   seats and the goals — and the counts of the cards moved under the filters,
   where a count of what is being listed belongs
 * the register wears the section's two controls, scope and window, and a
   register narrowed to one area narrows its band with it
 * **a quarter's and a year's movements were measured against the previous
   MONTH** — every topic read its comparison from a hardcoded one-month-back
   key. They read `FigurePeriod::against()` now, through a key chosen by the
   period's length, so a length nobody has stored reads as no history rather
   than as another period's number
 * "Compare with" — the previous period, or the same period last year, in the
   address like the scope and the window. The design's third option is not
   offered: "the declared target" is not a period, and the ledger's Pace
   column already answers it
 * performance DECLARES ITSELF A SECTION (`PerformanceSectionTabs`,
   `PerformanceSectionConfiguration`), so the shell draws its strip and its
   one Configure action — the pages were building a strip of their own and
   therefore never had a Configure at all
 * `/departments/performance/settings` — what the section opens on, and the
   rules its figures are read under, with the placing's own number read from
   the one place that holds it
 * the organisation's six-figure band, on the Overview and on every record,
   picked by key from the host's own three topics
 * the briefing leads with the LEDGER and puts what changed and what to decide
   beside it as two cards of equal height — a director opens the page to see
   where the goals stand, and the other two are readings of that
 * Staffing, Goals and Attention each raise their own decisions: a post past
   the threshold (fill it or close it), a goal missed and a goal still open
   and behind (two different asks, so two decisions), and the items no
   position owns (a gap in the org chart, not a workload)
 * `DepartmentMark` — the two letters a department is drawn by, written out in
   three places before this and therefore on its way to being three rules
 * `/departments/performance/briefing` — what changed, and what to decide: the
   goals' own figures as a band, one movement a topic, and the goal ledger in
   the one matrix grammar. It adds up nothing of its own
 * Staffing, Goals and Attention each publish their own movement — the seats
   against the posts past the threshold, a goal that CROSSED a state rather
   than a count that drifted, and the items nobody owns
 * `/departments/performance/topics` and `/departments/performance/topics/{key}`
   — the register of topic records, and the record. An INDEX and not an
   accordion (ruled 09-20): a card answers "is there anything here for me this
   period" and the record answers the topic. ONE record page for every topic,
   host's and module's alike — five figures, the charts, the matrix — so a
   module that publishes a topic gets a record the day it is installed
 * the sidebar unfolds Topics to the topics while a reader is inside them, a
   module's wearing its own dot
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
 * the MEMBER RECORD is a split: the record in a main column, and what happened
   to it and what may be done to it in a rail beside it. It gains a HISTORY —
   derived from the stored facts that carry a date, because there is no audit
   trail in this release — a POSTINGS card read through the person-postings
   seam, and a password-reset link an administrator can send (it writes a token
   and mails a link; it does not change the password). The position card is
   stacked, its permission ledger in two balanced columns. It names no delete:
   a screen does not name an action that does not exist.

 * Team wears the AREA IDIOM. It is a section now, not two screens: the same
   header on every tab (the section's name), a subline that is that tab's own,
   one strip between the head and the body with exactly one tab lit, and the
   one Configure action at the right-hand end of the action row on every one of
   them. The tab set is Overview · People · Positions · Postings · Roles; the
   configure screens are the widget library, Positions vocabulary and Team
   settings. Both are declared through the contracts a MODULE's tabs and
   configure sections already use, and the sidebar row opens into the same five
   screens, because the tree and the strip are two readings of one list.

 * the section's OVERVIEW: the identity band, the five KPI cards, people by
   department and positions held and unheld as ranked bars, postings by area as
   a plotted figure, and the bounded attention cards (stations with nobody
   posted, people holding nothing, accounts that have never signed in). It
   writes nothing and owns no figure on it.

 * the section's CONFIGURE screens. Team settings states the rules the model
   actually enforces and changes none of them — and names no action the product
   does not have, so there is no "deleting an account" row; Positions
   vocabulary edits the one list this section owns.

 * `PositionTitle` — what a position may be CALLED, offered to every
   department. A title grants nothing and is not a position: two departments
   may spell the same job the same way and still mean two different jobs, which
   is why a shared word is not a shared post.

 * the ROLES tab: what authority exists on this installation and who holds it —
   the three tiers with the people in them, and every permission there is,
   banded by who declared it, with how many positions carry it and how many
   people sit in those positions. A tier is not a role and the page says so:
   administering the team is an ordinary permission. There is no Role entity,
   none is proposed, and the tab assumes none; it writes nothing, because the
   matrix is edited on Positions.

 * a department in the sidebar wears ITS OWN HUE: the tree's dot carries the
   department's category, resolved by the shell's `[data-cat]` mechanism, so
   nine departments read as nine things rather than nine accents. The row hands
   over an index and never a colour — a hex is right in one theme and wrong in
   the other — and one service says which category a department is, so every
   surface that marks one marks it the same.

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
