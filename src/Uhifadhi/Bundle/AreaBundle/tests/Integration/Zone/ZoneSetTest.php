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
use Uhifadhi\Bundle\AreaBundle\Entity\Zone;
use Uhifadhi\Bundle\AreaBundle\Exception\ZoneNameException;
use Uhifadhi\Bundle\AreaBundle\Service\ZoneExportService;
use Uhifadhi\Bundle\AreaBundle\Service\ZoneService;
use Uhifadhi\Bundle\AreaBundle\Tests\Integration\IntegrationTestCase;

/**
 * THE THREE THINGS THAT CHANGE A LIVE ZONE SET, none of which is an import.
 *
 * A RENAME TOUCHES NO GEOMETRY and moves no record: a zone is the ground, and
 * the ground did not move. A REMOVAL leaves its ground unzoned, which is a
 * legal state and not a hole to be filled. REMOVING THEM ALL is one explicit
 * act with the count in it, never a loop of deletions — and it is offered
 * beside the export, because a set nobody kept a copy of is a set nobody can
 * put back.
 */
#[CoversClass(ZoneService::class)]
#[CoversClass(ZoneExportService::class)]
final class ZoneSetTest extends IntegrationTestCase
{
    private const string AN_EAST_HALF = '{"type":"MultiPolygon","coordinates":[[[[-29.5,-3.6],[-29.0,-3.6],[-29.0,-2.8],[-29.5,-2.8],[-29.5,-3.6]]]]}';

    public function testAZoneIsRenamedWithoutTouchingItsGround(): void
    {
        $area = $this->anArea();
        $zone = $this->aZone($area, 'Oldean', self::A_WEST_HALF);
        $this->em->clear();
        /** @var Zone $zone */
        $zone = $this->em->getRepository(Zone::class)->findOneBy(['area' => $area, 'name' => 'Oldean']);
        $before = $zone->getGeom();

        $this->zones()->rename($zone, 'Oldiani');

        $this->em->clear();
        $stored = $this->em->getRepository(Zone::class)->findOneBy(['area' => $area, 'name' => 'Oldiani']);
        self::assertNotNull($stored);
        self::assertSame($before, $stored->getGeom());
    }

    public function testARenameToANameTheAreaAlreadyCarriesIsRefusedAndNamed(): void
    {
        $area = $this->anArea();
        $this->aZone($area, 'Crater', self::A_WEST_HALF);
        $zone = $this->aZone($area, 'Oldean', self::AN_EAST_HALF);

        $this->expectException(ZoneNameException::class);
        $this->expectExceptionMessageMatches('/already has a zone called "Crater"/');

        $this->zones()->rename($zone, 'Crater');
    }

    /** Renaming a zone to what it is already called is not a clash with itself. */
    public function testAZoneMayBeRenamedToItsOwnName(): void
    {
        $zone = $this->aZone($this->anArea(), 'Crater', self::A_WEST_HALF);

        self::assertSame('Crater', $this->zones()->rename($zone, 'Crater')->getName());
    }

    public function testAnEmptyNameIsRefused(): void
    {
        $zone = $this->aZone($this->anArea(), 'Crater', self::A_WEST_HALF);

        $this->expectException(ZoneNameException::class);

        $this->zones()->rename($zone, '   ');
    }

    /** The two names of one area are its own; another area may use either. */
    public function testTheSameNameInAnotherAreaIsNotAClash(): void
    {
        $this->aZone($this->anArea('First Reserve'), 'Crater', self::A_WEST_HALF);
        $zone = $this->aZone($this->anArea('Second Reserve'), 'Oldean', self::A_WEST_HALF);

        self::assertSame('Crater', $this->zones()->rename($zone, 'Crater')->getName());
    }

    public function testRemovingOneZoneLeavesTheRest(): void
    {
        $area = $this->anArea();
        $this->aZone($area, 'West', self::A_WEST_HALF);
        $east = $this->aZone($area, 'East', self::AN_EAST_HALF);

        $this->zones()->remove($east);

        $this->em->clear();
        self::assertCount(1, $this->em->getRepository(Zone::class)->findBy(['area' => $area]));
    }

    public function testRemovingThemAllAnswersTheCountItRemoved(): void
    {
        $area = $this->anArea();
        $this->aZone($area, 'West', self::A_WEST_HALF);
        $this->aZone($area, 'East', self::AN_EAST_HALF);
        $other = $this->anArea('Second Reserve');
        $this->aZone($other, 'West', self::A_WEST_HALF);

        self::assertSame(2, $this->zones()->removeAll($area));

        $this->em->clear();
        self::assertCount(0, $this->em->getRepository(Zone::class)->findBy(['area' => $area]));
        // Only this area's. Removing one area's zones is not a platform event.
        self::assertCount(1, $this->em->getRepository(Zone::class)->findBy(['area' => $other]));
    }

    public function testTheLiveSetComesBackAsOneFeatureCollectionInDegrees(): void
    {
        $area = $this->anArea();
        $this->aZone($area, 'West', self::A_WEST_HALF);
        $this->aZone($area, 'East', self::AN_EAST_HALF);

        $document = json_decode($this->export()->featureCollection($area), true, 512, \JSON_THROW_ON_ERROR);

        self::assertIsArray($document);
        self::assertSame('FeatureCollection', $document['type']);
        self::assertIsArray($document['features']);
        self::assertCount(2, $document['features']);
        $first = $document['features'][0];
        self::assertIsArray($first);
        self::assertSame(['name' => 'East'], $first['properties']);
        self::assertIsArray($first['geometry']);
        self::assertSame('MultiPolygon', $first['geometry']['type']);
        // RFC 7946 is WGS84 by definition and removed `crs`; saying so twice is
        // what makes a reader wonder which of the two the file meant.
        self::assertArrayNotHasKey('crs', $document);
    }

    /** An area with no zones exports an empty collection, never a refusal. */
    public function testAnAreaWithNoZonesExportsAnEmptyCollection(): void
    {
        $document = json_decode($this->export()->featureCollection($this->anArea()), true, 512, \JSON_THROW_ON_ERROR);

        self::assertIsArray($document);
        self::assertSame([], $document['features']);
    }

    public function testTheExportIsNamedAfterTheArea(): void
    {
        self::assertSame('sample-area-zones.geojson', $this->export()->fileName($this->anArea('Sample Area')));
    }

    private function zones(): ZoneService
    {
        /** @var ZoneService $service */
        $service = static::getContainer()->get('test_public.area.zones');

        return $service;
    }

    private function export(): ZoneExportService
    {
        /** @var ZoneExportService $service */
        $service = static::getContainer()->get('test_public.area.zone_export');

        return $service;
    }
}
