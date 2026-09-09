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

namespace Uhifadhi\Bundle\AreaBundle\Tests\Unit\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Uhifadhi\Bundle\AreaBundle\Service\GeoJsonNormalizer;

/**
 * WHAT COUNTS AS A BOUNDARY, and what is turned away at the door.
 *
 * The column is a MultiPolygon, so everything polygonal has to arrive as one:
 * a lone Polygon is wrapped, a Feature is unwrapped, a FeatureCollection is
 * flattened. Anything that is not polygonal at all is refused HERE — with a
 * sentence naming what was found — rather than handed to PostGIS to fail on
 * with a message nobody importing a file would recognise.
 */
#[CoversClass(GeoJsonNormalizer::class)]
final class GeoJsonNormalizerTest extends TestCase
{
    private const array A_RING = [[[-30.0, -3.6], [-29.0, -3.6], [-29.0, -2.8], [-30.0, -2.8], [-30.0, -3.6]]];
    private const array ANOTHER_RING = [[[-25.0, -1.0], [-24.0, -1.0], [-24.0, -0.5], [-25.0, -0.5], [-25.0, -1.0]]];

    /** A lone Polygon is the commonest hand-drawn export; it becomes a one-part multi. */
    public function testAPolygonBecomesASinglePartMultiPolygon(): void
    {
        self::assertSame(
            [self::A_RING],
            new GeoJsonNormalizer()->toMultiPolygonCoordinates(['type' => 'Polygon', 'coordinates' => self::A_RING]),
        );
    }

    public function testAMultiPolygonPassesThroughUnchanged(): void
    {
        self::assertSame(
            [self::A_RING, self::ANOTHER_RING],
            new GeoJsonNormalizer()->toMultiPolygonCoordinates([
                'type' => 'MultiPolygon',
                'coordinates' => [self::A_RING, self::ANOTHER_RING],
            ]),
        );
    }

    public function testAFeatureIsUnwrappedToItsGeometry(): void
    {
        self::assertSame(
            [self::A_RING],
            new GeoJsonNormalizer()->toMultiPolygonCoordinates([
                'type' => 'Feature',
                'properties' => ['name' => 'Northern Reserve'],
                'geometry' => ['type' => 'Polygon', 'coordinates' => self::A_RING],
            ]),
        );
    }

    /**
     * A GAZETTED BOUNDARY IS REGULARLY MORE THAN ONE FEATURE — an enclave, an
     * outlying block — and a download that ships them as separate features is
     * still ONE area. They are merged rather than the first one taken.
     */
    public function testAFeatureCollectionIsFlattenedIntoOneMultiPolygon(): void
    {
        self::assertSame(
            [self::A_RING, self::ANOTHER_RING],
            new GeoJsonNormalizer()->toMultiPolygonCoordinates([
                'type' => 'FeatureCollection',
                'features' => [
                    ['type' => 'Feature', 'geometry' => ['type' => 'Polygon', 'coordinates' => self::A_RING]],
                    ['type' => 'Feature', 'geometry' => ['type' => 'Polygon', 'coordinates' => self::ANOTHER_RING]],
                ],
            ]),
        );
    }

    public function testADocumentWithNoTypeIsRefused(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('missing a "type"');

        new GeoJsonNormalizer()->toMultiPolygonCoordinates(['coordinates' => self::A_RING]);
    }

    /**
     * THE REFUSAL NAMES WHAT WAS FOUND. "Unsupported geometry" leaves somebody
     * guessing; "Point" tells them they exported the wrong layer.
     */
    public function testANonPolygonalGeometryIsRefusedByName(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/Point/');

        new GeoJsonNormalizer()->toMultiPolygonCoordinates(['type' => 'Point', 'coordinates' => [-30.0, -3.6]]);
    }

    public function testAFeatureWithoutGeometryIsRefused(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('no "geometry"');

        new GeoJsonNormalizer()->toMultiPolygonCoordinates(['type' => 'Feature', 'properties' => []]);
    }

    public function testAGeometryWithoutCoordinatesIsRefused(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('no "coordinates"');

        new GeoJsonNormalizer()->toMultiPolygonCoordinates(['type' => 'Polygon']);
    }

    public function testAFeatureCollectionWithoutFeaturesIsRefused(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('no "features"');

        new GeoJsonNormalizer()->toMultiPolygonCoordinates(['type' => 'FeatureCollection']);
    }
}
