# Changelog — Contracts

## Contents

- [1.0.0](#100)

## 1.0.0

Not released yet.

 * the module provider contract, the permissions a module declares, the area and
   user entity contracts, the user badge and the devkit contracts
 * `Kpi\DepartmentKpiProviderInterface` and its value objects — how a module
   puts a figure on the performance surfaces of a department that attaches it
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
