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

use Uhifadhi\Bundle\AreaBundle\Exception\ZoneOverlapException;
use Uhifadhi\Bundle\AreaBundle\Service\ZoneService;
use Uhifadhi\Bundle\AreaBundle\Tests\Integration\IntegrationTestCase;

/**
 * THE ZONE INVARIANT, measured by PostGIS rather than argued about: sibling
 * zones of one area never share INTERIOR.
 *
 * Two things that look like violations are not. Adjacency is legal — zones that
 * meet along an edge share boundary, not interior, and an admin who draws a
 * sensible subdivision produces exactly that. Gaps are legal too: an area is
 * regularly only partly zoned, and "in no zone" is a first-class answer rather
 * than a configuration anybody forgot.
 */
final class ZoneInvariantTest extends IntegrationTestCase
{
    private function zones(): ZoneService
    {
        /** @var ZoneService $service */
        $service = static::getContainer()->get('test_public.area.zones');

        return $service;
    }

    public function testTwoZonesMayTouchAlongAnEdge(): void
    {
        $area = $this->anArea();

        $west = $this->zones()->create($area, 'West', self::A_WEST_HALF);
        $east = $this->zones()->create($area, 'East', self::A_EAST_HALF);

        self::assertNotNull($west->getId());
        self::assertNotNull($east->getId());
    }

    public function testAZoneThatWouldShareInteriorIsRefusedAndNamesTheZoneItHit(): void
    {
        $area = $this->anArea();
        $this->zones()->create($area, 'West', self::A_WEST_HALF);

        $this->expectException(ZoneOverlapException::class);
        // "somewhere else" is useless to the admin drawing it — the message has
        // to name the other zone, because one of the two has to be fixed.
        $this->expectExceptionMessageMatches('/"Straddler".*"West"/');

        $this->zones()->create($area, 'Straddler', self::A_STRADDLING_MIDDLE);
    }

    /** A zone wholly inside another shares interior too — ST_Overlaps alone would say no. */
    public function testAZoneContainedInAnotherIsRefused(): void
    {
        $area = $this->anArea();
        $this->zones()->create($area, 'West', self::A_WEST_HALF);

        $this->expectException(ZoneOverlapException::class);
        $this->zones()->create($area, 'Inner', self::A_INSIDE_WEST);
    }

    /** Zones of DIFFERENT areas are not siblings; identical footprints are fine. */
    public function testTheInvariantIsPerAreaAndNotGlobal(): void
    {
        $this->zones()->create($this->anArea('First'), 'North', self::A_WEST_HALF);
        $zone = $this->zones()->create($this->anArea('Second'), 'North', self::A_WEST_HALF);

        self::assertNotNull($zone->getId());
    }

    public function testRedrawingAZoneDoesNotCollideWithItself(): void
    {
        $area = $this->anArea();
        $zone = $this->zones()->create($area, 'West', self::A_WEST_HALF);

        $this->zones()->replaceGeometry($zone, self::A_INSIDE_WEST);

        self::assertSame(self::A_INSIDE_WEST, json_encode(
            json_decode((string) $zone->getGeom(), true, 512, \JSON_THROW_ON_ERROR),
        ));
    }

    public function testRedrawingIntoASiblingIsStillRefused(): void
    {
        $area = $this->anArea();
        $this->zones()->create($area, 'West', self::A_WEST_HALF);
        $east = $this->zones()->create($area, 'East', self::A_EAST_HALF);

        $this->expectException(ZoneOverlapException::class);
        $this->zones()->replaceGeometry($east, self::A_STRADDLING_MIDDLE);
    }

    /**
     * POINT IN ZONE is the question every other module asks — "which zone did
     * this patrol waypoint / this incident fall in?" — and it must answer null
     * without complaint, because most installations start with no zones at all.
     */
    public function testItAnswersWhichZoneAPointFallsIn(): void
    {
        $area = $this->anArea();
        $west = $this->zones()->create($area, 'West', self::A_WEST_HALF);

        self::assertSame($west->getId(), $this->zones()->zoneOf($area, -29.8, -3.2)?->getId());
    }

    public function testAPointInNoZoneIsNullRatherThanAnError(): void
    {
        $area = $this->anArea();
        $this->zones()->create($area, 'West', self::A_WEST_HALF);

        self::assertNull($this->zones()->zoneOf($area, -29.2, -3.2));
    }

    public function testAnUnzonedAreaAnswersNull(): void
    {
        self::assertNull($this->zones()->zoneOf($this->anArea(), -29.8, -3.2));
    }

    /**
     * A point exactly on the edge two zones share is covered by BOTH. The answer
     * is settled by name then id so that repeated calls agree — an arbitrary but
     * DOCUMENTED tiebreak beats whatever order the planner happens to return.
     */
    public function testAPointOnASharedEdgeAnswersTheSameZoneEveryTime(): void
    {
        $area = $this->anArea();
        $this->zones()->create($area, 'West', self::A_WEST_HALF);
        $east = $this->zones()->create($area, 'East', self::A_EAST_HALF);

        // "East" sorts before "West".
        self::assertSame($east->getId(), $this->zones()->zoneOf($area, -29.5, -3.2)?->getId());
        self::assertSame($east->getId(), $this->zones()->zoneOf($area, -29.5, -3.2)?->getId());
    }

    /** What an import needs: two candidate geometries compared before either is stored. */
    public function testItComparesTwoUnstoredGeometries(): void
    {
        self::assertFalse($this->zones()->conflicts(self::A_WEST_HALF, self::A_EAST_HALF));
        self::assertTrue($this->zones()->conflicts(self::A_WEST_HALF, self::A_STRADDLING_MIDDLE));
    }
}
