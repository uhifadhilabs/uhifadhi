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
use Symfony\Component\HttpFoundation\File\File;
use Uhifadhi\Bundle\AreaBundle\Entity\AreaOfInterest;
use Uhifadhi\Bundle\AreaBundle\Entity\Zone;
use Uhifadhi\Bundle\AreaBundle\Model\ZoneFeaturePlan;
use Uhifadhi\Bundle\AreaBundle\Model\ZoneImportPlan;
use Uhifadhi\Bundle\AreaBundle\Service\ZoneImportService;
use Uhifadhi\Bundle\AreaBundle\Tests\Integration\IntegrationTestCase;

/**
 * AN IMPORT ADDS AND NEVER OVERWRITES.
 *
 * ON AN EMPTY AREA every valid feature lands. On an area that already has
 * zones, a feature whose name is taken, or whose ring shares interior with a
 * zone that is already there, is FLAGGED with its reason and left out — and the
 * rest still arrive. A partial import is legitimate, because nothing was
 * destroyed to make room for it.
 *
 * A WHOLE FILE IS REFUSED FOR THREE REASONS AND NO OTHERS: it cannot be read as
 * GeoJSON, it carries no usable name property, or its coordinates are projected
 * rather than degrees. Everything else is a per-feature verdict the preview
 * states and the person confirms.
 *
 * THE PREVIEW IS THE THING THAT IS CONFIRMED, not a rehearsal of the write:
 * {@see ZoneImportService::plan()} lists every feature, the person chooses the
 * subset, and {@see ZoneImportService::apply()} writes exactly that subset in
 * one transaction, re-checking each one against the area as it stands.
 */
#[CoversClass(ZoneImportService::class)]
#[CoversClass(ZoneImportPlan::class)]
#[CoversClass(ZoneFeaturePlan::class)]
final class ZoneAdditiveImportTest extends IntegrationTestCase
{
    public function testAnEmptyAreaTakesEveryFeature(): void
    {
        $area = $this->anArea();

        $plan = $this->plan($area, $this->twoHalves());

        self::assertSame(['Western Sector', 'Eastern Sector'], $plan->arrivingNames());
        self::assertSame([], $plan->flagged());
        self::assertSame('Name', $plan->nameProperty);
    }

    public function testConfirmingThePlanWritesTheZonesAndTheirProvenance(): void
    {
        $area = $this->anArea();
        $plan = $this->plan($area, $this->twoHalves());

        $result = $this->importer()->apply($area, $plan, $plan->arrivingNames(), 'n.kileo@example.org');

        self::assertSame(['Western Sector', 'Eastern Sector'], $result->added);
        self::assertSame([], $result->skipped);

        $stored = $this->em->getRepository(Zone::class)->findBy(['area' => $area], ['name' => 'ASC']);
        self::assertCount(2, $stored);
        self::assertStringContainsString('MultiPolygon', (string) $stored[0]->getGeom());

        $provenance = $stored[0]->getImport();
        self::assertNotNull($provenance);
        self::assertSame('zones.geojson', $provenance->getFileName());
        self::assertSame('n.kileo@example.org', $provenance->getImportedBy());
        self::assertSame('Name', $provenance->getNameProperty());
        self::assertSame(2, $provenance->getZoneCount());
    }

    /** The subset is the person's, so a feature they left out is not written. */
    public function testOnlyTheConfirmedSubsetIsWritten(): void
    {
        $area = $this->anArea();
        $plan = $this->plan($area, $this->twoHalves());

        $result = $this->importer()->apply($area, $plan, ['Western Sector']);

        self::assertSame(['Western Sector'], $result->added);
        self::assertSame(1, $this->countZones($area));
    }

    public function testANameTheAreaAlreadyCarriesIsFlaggedAndTheRestArrive(): void
    {
        $area = $this->anArea();
        $this->aZone($area, 'Western Sector', self::A_WEST_HALF);

        $plan = $this->plan($area, $this->twoHalves());

        self::assertSame(['Eastern Sector'], $plan->arrivingNames());
        $flagged = $plan->flagged();
        self::assertCount(1, $flagged);
        self::assertSame('Western Sector', $flagged[0]->name);
        self::assertStringContainsString('name already here', $flagged[0]->why());
    }

    public function testARingThatOverlapsAStoredZoneIsFlaggedWithThatZonesNameAndTheOverlap(): void
    {
        $area = $this->anArea();
        $this->aZone($area, 'Crater', self::A_WEST_HALF);

        $plan = $this->plan($area, $this->collection([
            $this->feature(['Name' => 'Crater North'], self::A_STRADDLING_RING),
        ]));

        self::assertSame([], $plan->arrivingNames());
        $flagged = $plan->flagged();
        self::assertSame('Crater', $flagged[0]->whySubject);
        self::assertStringContainsString('overlaps Crater by', $flagged[0]->why());
        self::assertStringContainsString('km²', $flagged[0]->why());
    }

    /** Two zones may share an EDGE. The halves of one rectangle do exactly that. */
    public function testAZoneThatOnlyTouchesAnEdgeArrives(): void
    {
        $area = $this->anArea();
        $this->aZone($area, 'Western Sector', self::A_WEST_HALF);

        $plan = $this->plan($area, $this->collection([
            $this->feature(['Name' => 'Eastern Sector'], self::A_EAST_HALF_RING),
        ]));

        self::assertSame(['Eastern Sector'], $plan->arrivingNames());
    }

    /** Identical geometry is the extreme case of overlap, not a special case. */
    public function testAnIdenticalRingIsFlaggedAsAnOverlap(): void
    {
        $area = $this->anArea();
        $this->aZone($area, 'Crater', self::A_WEST_HALF);

        $plan = $this->plan($area, $this->collection([
            $this->feature(['Name' => 'Crater Copy'], self::A_WEST_HALF_RING),
        ]));

        self::assertStringContainsString('overlaps Crater', $plan->flagged()[0]->why());
    }

    public function testTwoFeaturesOfOneFileThatShareInteriorFlagTheSecond(): void
    {
        $plan = $this->plan($this->anArea(), $this->collection([
            $this->feature(['Name' => 'Western Sector'], self::A_WEST_HALF_RING),
            $this->feature(['Name' => 'Straddler'], self::A_STRADDLING_RING),
        ]));

        self::assertSame(['Western Sector'], $plan->arrivingNames());
        self::assertSame('Western Sector', $plan->flagged()[0]->whySubject);
        self::assertStringContainsString('in this same file', $plan->flagged()[0]->why());
    }

    public function testADuplicateNameInTheFileFlagsTheSecondOccurrence(): void
    {
        $plan = $this->plan($this->anArea(), $this->collection([
            $this->feature(['Name' => 'Western Sector'], self::A_WEST_HALF_RING),
            $this->feature(['Name' => 'Western Sector'], self::A_EAST_HALF_RING),
        ]));

        self::assertSame(['Western Sector'], $plan->arrivingNames());
        self::assertStringContainsString('used twice in the file', $plan->flagged()[0]->why());
    }

    public function testAFeatureOutsideTheAreaBoundaryIsFlaggedAndNamed(): void
    {
        $area = $this->anArea();

        $plan = $this->plan($area, $this->collection([
            $this->feature(['Name' => 'Western Sector'], self::A_WEST_HALF_RING),
            $this->feature(['Name' => 'Elsewhere'], self::A_FAR_AWAY_RING),
        ]));

        self::assertSame(['Western Sector'], $plan->arrivingNames());
        self::assertStringContainsString('outside the boundary', $plan->flagged()[0]->why());
    }

    public function testAFeatureWithNoUsableGeometryIsFlaggedRatherThanRefusingTheFile(): void
    {
        $plan = $this->plan($this->anArea(), (string) json_encode([
            'type' => 'FeatureCollection',
            'features' => [
                $this->feature(['Name' => 'Western Sector'], self::A_WEST_HALF_RING),
                ['type' => 'Feature', 'properties' => ['Name' => 'Nothing'], 'geometry' => null],
            ],
        ], \JSON_THROW_ON_ERROR));

        self::assertSame(['Western Sector'], $plan->arrivingNames());
        self::assertStringContainsString('not a polygon', $plan->flagged()[0]->why());
    }

    /** A point layer is counted and named, not turned into a zone and not refused. */
    public function testFeaturesThatAreNotAreasAreCountedAsSkippedGeometries(): void
    {
        $plan = $this->plan($this->anArea(), (string) json_encode([
            'type' => 'FeatureCollection',
            'features' => [
                $this->feature(['Name' => 'Western Sector'], self::A_WEST_HALF_RING),
                ['type' => 'Feature', 'properties' => ['Name' => 'A post'], 'geometry' => ['type' => 'Point', 'coordinates' => [-29.8, -3.2]]],
                ['type' => 'Feature', 'properties' => ['Name' => 'Another post'], 'geometry' => ['type' => 'Point', 'coordinates' => [-29.7, -3.2]]],
                ['type' => 'Feature', 'properties' => ['Name' => 'A track'], 'geometry' => ['type' => 'LineString', 'coordinates' => [[-29.8, -3.2], [-29.6, -3.1]]]],
            ],
        ], \JSON_THROW_ON_ERROR));

        self::assertSame(['Western Sector'], $plan->arrivingNames());
        self::assertSame(['Point' => 2, 'LineString' => 1], $plan->skippedGeometries);
        self::assertSame([], $plan->flagged());
    }

    /**
     * THE PLAN IS NOT THE AUTHORITY, the database is. A zone written between the
     * preview and the confirm takes the name back, and the confirm says so
     * rather than writing a duplicate.
     */
    public function testAConfirmRecheckseachFeatureAgainstTheAreaAsItStands(): void
    {
        $area = $this->anArea();
        $plan = $this->plan($area, $this->twoHalves());

        $this->aZone($area, 'Western Sector', self::A_WEST_HALF);

        $result = $this->importer()->apply($area, $plan, $plan->arrivingNames());

        self::assertSame(['Eastern Sector'], $result->added);
        self::assertArrayHasKey('Western Sector', $result->skipped);
        self::assertSame(2, $this->countZones($area));
    }

    /** A confirm naming nothing writes nothing, and no provenance row is left behind. */
    public function testAConfirmOfNothingWritesNothing(): void
    {
        $area = $this->anArea();
        $plan = $this->plan($area, $this->twoHalves());

        $result = $this->importer()->apply($area, $plan, []);

        self::assertSame([], $result->added);
        self::assertSame(0, $this->countZones($area));
    }

    // ---------------------------------------------------------------- fixtures

    private const array A_WEST_HALF_RING = [[[-30.0, -3.6, 1200.0], [-29.5, -3.6, 1200.0], [-29.5, -2.8, 1200.0], [-30.0, -2.8, 1200.0], [-30.0, -3.6, 1200.0]]];
    private const array A_EAST_HALF_RING = [[[-29.5, -3.6], [-29.0, -3.6], [-29.0, -2.8], [-29.5, -2.8], [-29.5, -3.6]]];
    private const array A_STRADDLING_RING = [[[-29.75, -3.6], [-29.25, -3.6], [-29.25, -2.8], [-29.75, -2.8], [-29.75, -3.6]]];
    private const array A_FAR_AWAY_RING = [[[10.0, 10.0], [11.0, 10.0], [11.0, 11.0], [10.0, 11.0], [10.0, 10.0]]];

    private function importer(): ZoneImportService
    {
        /** @var ZoneImportService $service */
        $service = static::getContainer()->get('test_public.area.zone_import');

        return $service;
    }

    private function plan(AreaOfInterest $area, string $document, string $originalName = 'zones.geojson'): ZoneImportPlan
    {
        $directory = sys_get_temp_dir().'/zone-import-'.bin2hex(random_bytes(6));
        mkdir($directory, 0o777, true);
        $path = $directory.'/'.$originalName;
        file_put_contents($path, $document);

        return $this->importer()->plan($area, new File($path), $originalName);
    }

    private function twoHalves(): string
    {
        return $this->collection([
            $this->feature(['Name' => 'Western Sector'], self::A_WEST_HALF_RING),
            $this->feature(['Name' => 'Eastern Sector'], self::A_EAST_HALF_RING),
        ]);
    }

    private function countZones(AreaOfInterest $area): int
    {
        return \count($this->em->getRepository(Zone::class)->findBy(['area' => $area]));
    }

    /**
     * @param array<string, mixed>        $properties
     * @param list<list<list<float|int>>> $ring
     *
     * @return array<string, mixed>
     */
    private function feature(array $properties, array $ring): array
    {
        return ['type' => 'Feature', 'properties' => $properties, 'geometry' => ['type' => 'Polygon', 'coordinates' => $ring]];
    }

    /** @param list<array<string, mixed>> $features */
    private function collection(array $features): string
    {
        return (string) json_encode(['type' => 'FeatureCollection', 'features' => $features], \JSON_THROW_ON_ERROR);
    }
}
