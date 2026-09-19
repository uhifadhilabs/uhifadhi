# Changelog — Contracts

## Contents

- [1.0.0](#100)

## 1.0.0

Not released yet.

 * the module provider contract, the permissions a module declares, the area and
   user entity contracts, the user badge and the devkit contracts
 * `Kpi\DepartmentKpiProviderInterface` and its value objects — how a module
   puts a figure on the performance surfaces of a department that attaches it
 * BREAKING: `Shell\AreaNavChild::$swatch` and `Performance\ChartSeries::$swatch`
   are gone; both now take `cat: ?int` (1..9), the category's position in its own
   declared order, and the HOST resolves it to the palette. A module never hands
   the host a colour — a hex is right in one theme and wrong in the other, and
   wrong again on imagery. Callers in the patrol and incident modules follow.
 * `KpiRole::ItemsRaised` and `::ItemsUnowned`, and a `MatrixColumn` that may
   carry a role — the host adds a figure up per department across topics by the
   role a column declares, never by the word a module chose for it
 * `Performance\CellMark`, and a `MatrixCell` that may count STATES rather than
   measure a figure — a department's goals pace is a chip a goal, and averaging
   four states would answer a question nobody asked
 * `Atlas\CalendarFeedInterface` and its value objects — how a module has a
   month drawn: days, bounded pills, a hue role rather than a colour, and the
   surface naming the feed it wants as it names a plate's subject
 * `Area\PersonWatch`, and `Area\PersonDay` as a LIST of them with the day's
   totals — a day holds any number of check-in/check-out pairs, so the shape
   that could hold only one from-to is gone
 * `Area\PresenceProviderInterface` and its value objects — how a day at a post
   reads, derived on every read and never stored
 * `Roster\WatchProviderInterface` — the watches an area expects, which the area
   reads and does not own
 * `Area\StationSectionsInterface` and its value objects — how a module puts a
   banded section on a station's record and a block on its configure card
 * `PermissionDeclarationInterface` — how a bundle that is not a module declares
   the permission it enforces
 * `Api\FieldErrorDocument` — the header that marks a refusal already written in
   the field API's own shape, so the URL space's safety net leaves it alone
 * `Devkit\CommandIo::readSecret()` — additive to the surface but breaking for an
   implementor: an existing implementation of the interface no longer satisfies it
   until it declares the method
