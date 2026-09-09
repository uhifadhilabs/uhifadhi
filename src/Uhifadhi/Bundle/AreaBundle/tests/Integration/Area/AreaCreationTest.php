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

namespace Uhifadhi\Bundle\AreaBundle\Tests\Integration\Area;

use Symfony\Component\HttpFoundation\File\File;
use Uhifadhi\Bundle\AreaBundle\Entity\AreaOfInterest;
use Uhifadhi\Bundle\AreaBundle\Exception\AreaCreationException;
use Uhifadhi\Bundle\AreaBundle\Service\AreaCreator;
use Uhifadhi\Bundle\AreaBundle\Service\BoundaryImport;
use Uhifadhi\Bundle\AreaBundle\Tests\Integration\IntegrationTestCase;

/**
 * AN AREA IS BORN FROM ITS IDENTITY, AND ITS BOUNDARY IS OPTIONAL — the ring
 * that stopped the create flow from making the boundary import BE the creation.
 *
 * Against the real PostGIS database, because the whole claim is that a
 * boundary-less area persists: a `geom` column that was still NOT NULL would let
 * every one of these pass in isolation and fail at the insert.
 */
final class AreaCreationTest extends IntegrationTestCase
{
    /** A lone polygon — the create screen's own coercion wraps it to a MultiPolygon. */
    private const string A_POLYGON = '{"type":"Polygon","coordinates":[[[-30.0,-3.6],[-29.0,-3.6],[-29.0,-2.8],[-30.0,-2.8],[-30.0,-3.6]]]}';

    public function testAnAreaIsCreatedFromItsIdentityWithNoBoundary(): void
    {
        $area = new AreaCreator($this->em)->create('A place with no gazetted edge yet');

        self::assertNotNull($area->getId());
        self::assertFalse($area->hasBoundary(), 'a just-named area has no edge yet');
        self::assertNull($area->getGeom());
        self::assertNull($area->getSource(), 'provenance is a fact about a boundary, and there is none');

        // And it is really boundary-less in the database, not merely in memory.
        $id = $area->getId();
        $this->em->clear();
        $reloaded = $this->em->find(AreaOfInterest::class, $id);
        self::assertInstanceOf(AreaOfInterest::class, $reloaded);
        self::assertFalse($reloaded->hasBoundary());
        self::assertNull($reloaded->getGeom());
    }

    public function testTheGazettedFactsAreKeptWhenGiven(): void
    {
        $area = new AreaCreator($this->em)->create('Northern Conservation Reserve', 'VI', 1959);

        self::assertSame('VI', $area->getIucnCategory());
        self::assertSame(1959, $area->getEstablishedYear());
        self::assertFalse($area->hasBoundary());
    }

    public function testAnAreaCannotBeCreatedWithoutAName(): void
    {
        $this->expectException(AreaCreationException::class);
        $this->expectExceptionMessage('name');

        new AreaCreator($this->em)->create('   ');
    }

    /**
     * THE WHOLE POINT OF THE SPLIT: hasBoundary() is false at birth and true
     * after the boundary is imported onto the area that already exists —
     * exactly what the overview's no-boundary state and the edit screen do.
     */
    public function testABoundaryAddedLaterFlipsHasBoundaryTrue(): void
    {
        $area = new AreaCreator($this->em)->create('Northern Conservation Reserve');
        self::assertFalse($area->hasBoundary());

        $path = tempnam(sys_get_temp_dir(), 'area-boundary-');
        self::assertIsString($path);
        file_put_contents($path, self::A_POLYGON);

        new BoundaryImport($this->em)->importInto($area, new File($path), 'northern.geojson');

        self::assertTrue($area->hasBoundary());
        self::assertSame(BoundaryImport::SOURCE, $area->getSource());

        // And the geometry survived the round trip as a real MultiPolygon.
        $id = $area->getId();
        $this->em->clear();
        $reloaded = $this->em->find(AreaOfInterest::class, $id);
        self::assertInstanceOf(AreaOfInterest::class, $reloaded);
        self::assertTrue($reloaded->hasBoundary());
        /** @var array{type?: string} $decoded */
        $decoded = json_decode((string) $reloaded->getGeom(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertSame('MultiPolygon', $decoded['type'] ?? null);

        unlink($path);
    }
}
