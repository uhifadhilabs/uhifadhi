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

namespace Uhifadhi\Bundle\AreaBundle\Tests\Integration;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Uhifadhi\Bundle\AreaBundle\Tests\Integration\Fixtures\CollectedModules;

/**
 * AN AREA IS NOT A MODULE OF ITSELF — so this bundle carries no
 * `uhifadhi.module` tag, and the absence is pinned rather than left to be
 * noticed.
 *
 * Every capability module declares itself and the registry files it in a
 * catalogue. The catalogue is indexed BY AREA: the registry's `area_module` row says
 * "this area has this bundle switched on". A provider here would put an entry in
 * that table for every area saying that the area has areas — a row that means
 * nothing, that an admin could switch off with no effect, and that would appear
 * in the module grid of the very page it is the subject of.
 *
 * Areas are the AXIS the catalogue is indexed by, not an entry in it. That is a
 * different thing from being a BASE module: base means "a capability seeded on
 * rather than parked" (the map is one), and it is still a capability an area
 * has. This is the thing an area IS.
 *
 * WHAT THE BUNDLE ANSWERS FOR THE REGISTRY INSTEAD is the only thing the registry
 * ever asked of an installation: the answer to its area contract, prepended (see
 * Resolution\ResolveTargetEntitiesTest). That is a deeper integration than a
 * catalogue tile, not a shallower one — without it the registry has no schema at
 * all.
 */
final class CatalogueAbstentionTest extends KernelTestCase
{
    protected static function getKernelClass(): string
    {
        return TestKernel::class;
    }

    public function testTheBundleDeclaresNoModule(): void
    {
        self::bootKernel();

        $collected = self::getContainer()->get(CollectedModules::class);
        \assert($collected instanceof CollectedModules);

        self::assertSame([], $collected->slugs());
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        while (true) {
            $previous = set_exception_handler(static fn () => null);
            restore_exception_handler();
            if (null === $previous) {
                break;
            }
            restore_exception_handler();
        }
    }
}
