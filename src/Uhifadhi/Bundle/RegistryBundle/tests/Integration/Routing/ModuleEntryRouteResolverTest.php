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

namespace Uhifadhi\Bundle\RegistryBundle\Tests\Integration\Routing;

use Uhifadhi\Bundle\RegistryBundle\Service\ModuleEntryRouteResolver;
use Uhifadhi\Bundle\RegistryBundle\Tests\Integration\InstallationTestCase;

/**
 * WHERE A MODULE'S TILE LINKS — the one piece of routing the registry carries, and
 * it carries no routes of its own.
 *
 * This is a resolution, not a redirect: the registry answers "what route name, if
 * any, does this module own?" and something in the shell turns that into a
 * link with the area's uuid. The registry itself never renders a tile.
 *
 * READ FROM THE PROVIDER, NEVER FROM A COLUMN. A route name is code: it changes
 * when a module refactors its controllers, and if the catalogue had stored it at
 * seed time then the tile would point at a dead route until someone re-ran a
 * command. The live reading is the design.
 */
final class ModuleEntryRouteResolverTest extends InstallationTestCase
{
    private function resolver(): ModuleEntryRouteResolver
    {
        $resolver = $this->service('registry.entry_routes');
        \assert($resolver instanceof ModuleEntryRouteResolver);

        return $resolver;
    }

    public function testAModuleThatOwnsItsPagesNamesTheRoute(): void
    {
        $this->install(['sightings' => ['entryRoute' => 'sightings_area']]);

        self::assertSame('sightings_area', $this->resolver()->entryRouteFor('sightings'));
    }

    /**
     * Null means the generic module page — the answer every module gives before
     * it has screens of its own, and a perfectly good permanent answer too.
     */
    public function testAModuleWithoutItsOwnPagesRendersGenerically(): void
    {
        $this->install(['sightings']);

        self::assertNull($this->resolver()->entryRouteFor('sightings'));
    }

    /**
     * An unknown slug is null, not an exception. The catalogue can outlive a
     * provider (a bundle removed while its rows remain), and a tile for a module
     * nobody has installed must degrade to the generic page rather than take the
     * page down.
     */
    public function testAnUnknownModuleResolvesToNothingRatherThanThrowing(): void
    {
        $this->install([]);

        self::assertNull($this->resolver()->entryRouteFor('nothing-installed'));
    }

    /**
     * The reading is live: uninstall the bundle and the route it named is gone
     * on the next request, without a seed run in between.
     */
    public function testUninstallingTheBundleTakesItsRouteWithIt(): void
    {
        $this->install(['sightings' => ['entryRoute' => 'sightings_area']]);
        $this->install([], freshDatabase: false);

        self::assertNull($this->resolver()->entryRouteFor('sightings'));
    }
}
