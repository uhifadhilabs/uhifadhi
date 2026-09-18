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

namespace Uhifadhi\Bundle\AreaBundle\Tests\Integration\Zone;

use PHPUnit\Framework\Attributes\CoversClass;
use Uhifadhi\Bundle\AreaBundle\Service\ZoneFigureService;
use Uhifadhi\Bundle\AreaBundle\Tests\Integration\Fixtures\TaggedFigureProvider;
use Uhifadhi\Bundle\AreaBundle\Tests\Integration\IntegrationTestCase;
use Uhifadhi\Contracts\Kpi\FigurePeriod;
use Uhifadhi\Contracts\Kpi\ZoneFigureProviderInterface;
use Uhifadhi\Contracts\Kpi\ZoneRef;

/**
 * THE TAG IS ACTUALLY COLLECTED, through a real container.
 *
 * THIS IS THE ONE THAT CATCHES THE CLASSIC BUG. A unit test proves the
 * collector's rule; only a booted kernel proves that a class tagged
 * {@see ZoneFigureProviderInterface::TAG} reaches it. Every "the module never
 * appears in the catalogue" defect in this platform's history has been a tag
 * that was declared and never wired, and the symptom is always the same: a
 * page that renders perfectly with nothing on it.
 */
#[CoversClass(ZoneFigureService::class)]
final class ZoneFigureSeamTest extends IntegrationTestCase
{
    public function testAProviderTaggedInTheContainerReachesTheCollector(): void
    {
        /** @var ZoneFigureService $figures */
        $figures = static::getContainer()->get('test_public.area.zone_figures');

        $set = $figures->collect(
            [new ZoneRef('01a0-crater', '01a0-area', 'Crater')],
            FigurePeriod::month(new \DateTimeImmutable('2026-08-14')),
            static fn (string $slug): bool => true,
        );

        self::assertSame(
            [TaggedFigureProvider::COVERAGE],
            array_map(static fn (object $k): string => $k->key, $set->forZone('01a0-crater')),
            'the tagged provider did not reach the collector: the tag is declared somewhere and wired nowhere',
        );
    }

    /** And the named key survives the trip, which is what three surfaces read. */
    public function testTheCoveredFigureArrivesUnderItsNamedKey(): void
    {
        /** @var ZoneFigureService $figures */
        $figures = static::getContainer()->get('test_public.area.zone_figures');

        $set = $figures->collect(
            [new ZoneRef('01a0-crater', '01a0-area', 'Crater')],
            FigurePeriod::month(new \DateTimeImmutable('2026-08-14')),
            static fn (string $slug): bool => true,
        );

        self::assertSame(82.0, $set->covered('01a0-crater')?->value);
    }

    /** The ledger decides: a module the area does not run is not asked. */
    public function testAModuleTheAreaDoesNotRunIsNotAsked(): void
    {
        /** @var ZoneFigureService $figures */
        $figures = static::getContainer()->get('test_public.area.zone_figures');

        $set = $figures->collect(
            [new ZoneRef('01a0-crater', '01a0-area', 'Crater')],
            FigurePeriod::month(new \DateTimeImmutable('2026-08-14')),
            static fn (string $slug): bool => false,
        );

        self::assertTrue($set->isEmpty());
    }
}
