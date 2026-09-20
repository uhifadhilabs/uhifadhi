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

namespace Uhifadhi\Bundle\TeamBundle\Tests\Integration;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use Uhifadhi\Bundle\TeamBundle\Performance\RequiredPeriod;
use Uhifadhi\Bundle\TeamBundle\Tests\Integration\Fixtures\Resolution\ResolutionKernel;

/**
 * A KERNEL THAT TAKES THIS BUNDLE'S PEOPLE AND NOT ITS PERFORMANCE PAGES
 * BOOTS.
 *
 * THE DEFECT THIS PINS. This bundle's performance screens caption the period
 * they read, and for a moment they took it from a service of the ATLAS's, by
 * id. The container then required the atlas at COMPILE time — so a module's
 * own test kernel, registering team for its entities and its user provider
 * and nothing else, died with `The service "team.navigation.performance" has
 * a dependency on a non-existent service "atlas.periods"`. In somebody
 * else's suite, a long way from the change that caused it.
 *
 * THE RULE THAT WAS BROKEN: a bundle declares what it needs and never
 * assumes what a kernel registered. This bundle is two things at once — a
 * model an installation persists and a set of screens — and only the screens
 * want a calendar. So the dependency is on a CONTRACT
 * (`Uhifadhi\Contracts\Kpi\CurrentPeriodInterface`), tolerated when absent,
 * and the screens that genuinely need one say so in a sentence naming the
 * package to add.
 *
 * THE FIXTURE KERNEL IS THE POINT. It registers framework, doctrine,
 * security, the registry, the shell and this bundle — and no atlas. If
 * booting it ever needs one more bundle, this bundle has quietly grown a
 * requirement it did not declare.
 */
#[CoversNothing]
final class BootsWithoutTheAtlasTest extends TestCase
{
    /** The whole assertion: it compiles and boots, with no atlas anywhere. */
    public function testTheContainerCompilesWithoutTheAtlas(): void
    {
        $kernel = new ResolutionKernel();
        $kernel->boot();

        self::assertArrayNotHasKey(
            'AtlasBundle',
            $kernel->getBundles(),
            'The kernel under test must not register it — otherwise this proves nothing.',
        );
        $kernel->shutdown();
    }

    /**
     * A SCREEN THAT GENUINELY NEEDS A PERIOD SAYS WHICH PACKAGE TO ADD. The
     * failure moves from "non-existent service" at compile, in somebody
     * else's suite, to a sentence on the one page that wanted it.
     */
    public function testAScreenThatNeedsAPeriodNamesWhatIsMissing(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessageMatches('/CurrentPeriodInterface/');
        $this->expectExceptionMessageMatches('/AtlasBundle/');

        RequiredPeriod::of(null);
    }
}
