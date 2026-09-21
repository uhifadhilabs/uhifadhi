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

namespace Uhifadhi\Core\Tests\Core;

use PHPUnit\Framework\Attributes\CoversNothing;
use Uhifadhi\Bundle\AreaBundle\Access\AreaConcerns;
use Uhifadhi\Bundle\AreaBundle\AreaBundle;
use Uhifadhi\Bundle\RegistryBundle\Access\RegistryConcerns;
use Uhifadhi\Bundle\RegistryBundle\RegistryBundle;
use Uhifadhi\Bundle\TeamBundle\Access\TeamConcerns;
use Uhifadhi\Bundle\TeamBundle\TeamBundle;
use Uhifadhi\Bundle\TeamBundle\Test\AccessConformanceTestCase;
use Uhifadhi\Contracts\Access\ConcernSourceInterface;

/**
 * THE CORE RUNS THE CONFORMANCE IT ASKS MODULES TO RUN.
 *
 * {@see AccessConformanceTestCase} is the base a module extends in its own
 * CI, and a base nobody has watched pass is a base that might be asserting
 * nothing. So the core's own three declarations go through it, exactly as a
 * module's would — which also means a change that makes the base wrong is
 * caught here rather than in somebody else's repository.
 *
 * THE CORE'S CONCERNS BELONG TO NO MODULE, deliberately: the ground, the
 * directory and the catalogue are the installation's, not any one
 * department's, and that is what makes the third question a check asks — does
 * the placement cover the department — not arise for them.
 */
#[CoversNothing]
final class TheCoreRunsTheModuleConformanceTest extends AccessConformanceTestCase
{
    protected static function source(): ConcernSourceInterface
    {
        return new AreaConcerns();
    }

    protected static function bundlePath(): string
    {
        return \dirname((string) (new \ReflectionClass(AreaBundle::class))->getFileName());
    }
}

/**
 * The team's own declarations, through the same base.
 *
 * PERSONAL DETAILS ARE THE ONE SENSITIVE ROW, and naming it here is a second
 * statement of the same thing the declaration makes, on purpose: a fact about
 * a person that quietly stopped being sensitive would otherwise be a silent
 * widening of what everybody can read.
 */
#[CoversNothing]
final class TheTeamRunsTheModuleConformanceTest extends AccessConformanceTestCase
{
    protected static function source(): ConcernSourceInterface
    {
        return new TeamConcerns();
    }

    protected static function bundlePath(): string
    {
        return \dirname((string) (new \ReflectionClass(TeamBundle::class))->getFileName());
    }

    protected static function sensitiveConcerns(): array
    {
        return ['personal-details'];
    }
}

/**
 * The catalogue's one concern, through the same base.
 */
#[CoversNothing]
final class TheRegistryRunsTheModuleConformanceTest extends AccessConformanceTestCase
{
    protected static function source(): ConcernSourceInterface
    {
        return new RegistryConcerns();
    }

    protected static function bundlePath(): string
    {
        return \dirname((string) (new \ReflectionClass(RegistryBundle::class))->getFileName());
    }
}
