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
use Uhifadhi\Bundle\TeamBundle\Access\TeamConcerns;
use Uhifadhi\Bundle\TeamBundle\TeamBundle;
use Uhifadhi\Bundle\TeamBundle\Test\AccessConformanceTestCase;
use Uhifadhi\Contracts\Access\ConcernSourceInterface;

/**
 * THE TEAM'S OWN DECLARATIONS, through the base a module extends.
 *
 * PERSONAL DETAILS ARE THE ONE SENSITIVE ROW, and naming it here is a second
 * statement of the same thing the declaration makes, on purpose: a fact about
 * a person that quietly stopped being sensitive would otherwise be a silent
 * widening of what everybody can read.
 *
 * IT IS ITS OWN FILE, AND THAT IS NOT TIDINESS. PHPUnit collects the one
 * class whose name matches the file; three conformance classes in one file
 * meant two of them never ran, and a conformance nobody runs asserts
 * nothing.
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
