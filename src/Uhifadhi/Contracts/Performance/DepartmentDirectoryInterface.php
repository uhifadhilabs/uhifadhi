<?php

declare(strict_types=1);

/*
 * This file is part of the Uhifadhi core.
 *
 * (c) Ezekiel Mjema <https://github.com/eemjema>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Uhifadhi\Contracts\Performance;

/**
 * HOW A TOPIC LEARNS WHICH DEPARTMENTS IT IS ABOUT.
 *
 * A MODULE PUBLISHING A TOPIC HAS TO ENUMERATE DEPARTMENTS, and before
 * this it could not: the first module to try read the team bundle's
 * entity and the registry's ledger by hand, across two package
 * boundaries it does not depend on. This is the published way, and it
 * answers the whole question in one read — who they are, what they are
 * placed among, what each attaches, and since when each of those modules
 * has been running somewhere they can see it.
 *
 * ONE READ RATHER THAN TWO SEAMS, deliberately. The facts live in two
 * bundles; joining them is the host's job, done once, rather than every
 * module's, done differently. A module asks for the page's scope and
 * filters the answer.
 *
 * IT IS READ-ONLY AND IT IS NOT A SEAM TO IMPLEMENT. Exactly one
 * implementation exists, in the bundle that owns departments; a module
 * type-hints the interface and is wired to it by name.
 */
interface DepartmentDirectoryInterface
{
    public function forScope(PerformanceScope $scope): DepartmentDirectory;
}
