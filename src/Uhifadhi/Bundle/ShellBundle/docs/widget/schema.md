# The schema

| Table | What it holds |
| --- | --- |
| `widget_preference` | one person's layout of one surface: the surface, the person, the optional area, which preset is active, and the composed layout |
| `widget_custom_preset` | a layout they saved under a name of their own, addressed by uuid |

Every table is prefixed, as every uhifadhi module's is.

**The area is a stored uuid; the person is a relation.** A saved layout is a UI
scrap, and deleting an area must never be blocked by one — so the area is held
as its uuid. A layout is not merely scoped to somebody, it is *theirs*, and it
has no meaning once the account is gone — so the person is an association with
`ON DELETE CASCADE` at the database.

**One row per person per surface per area**, and the database is what guarantees
it. Postgres counts NULLs as distinct in a unique index, so an org-wide surface
(one with no area) needs a second, partial index of its own; both tables carry
the pair.

**The shell ships entities, not migrations.** The tables are the shell's, but
the migration history is the installation's.

## Contents

- [`widget:prune`](#widgetprune)

## `widget:prune`

```bash
bin/console widget:prune --dry-run   # what would go
bin/console widget:prune             # lists them, asks, deletes
```

It compares the surfaces the database has rows for against the surfaces the
container currently claims, and offers to delete the difference. It is **only
ever as right as the container it asks**: run it while a module is temporarily
uninstalled and it will offer to delete that module's layouts, correctly and
irreversibly. Hence the list, the question, and `--dry-run`.
