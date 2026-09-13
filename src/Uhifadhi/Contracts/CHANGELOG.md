# Changelog — Contracts

## Contents

- [1.0.0](#100)

## 1.0.0

Not released yet.

 * the module provider contract, the permissions a module declares, the area and
   user entity contracts, the user badge and the devkit contracts
 * `Kpi\DepartmentKpiProviderInterface` and its value objects — how a module
   puts a figure on the performance surfaces of a department that attaches it
 * `Devkit\CommandIo::readSecret()` — additive to the surface but breaking for an
   implementor: an existing implementation of the interface no longer satisfies it
   until it declares the method
