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

namespace Uhifadhi\Bundle\AreaBundle\Service;

/**
 * WHATEVER POLYGONAL GEOJSON ARRIVED, AS ONE MULTIPOLYGON.
 *
 * The boundary column is `geometry(MULTIPOLYGON,4326)`, and almost nothing in
 * the wild is shaped like that on arrival: a hand-drawn export is a bare
 * Polygon, a QGIS save is a Feature, a registry download is a FeatureCollection
 * whose features are the enclaves and outlying blocks of ONE gazetted area. All
 * four are the same boundary, so all four are accepted and flattened to the one
 * shape the column takes.
 *
 * STRUCTURE ONLY, DELIBERATELY. This asks whether the document HAS polygonal
 * coordinates, never whether they describe a sane piece of ground: no ring
 * closure, no winding order, no self-intersection, no coordinate range. Those
 * are PostGIS's questions and PostGIS answers them at insert with the whole
 * geometry in hand — re-asking them here in PHP would be a second opinion that
 * can only ever disagree with the one that decides.
 *
 * NO SRID IS WRITTEN, EITHER. RFC 7946 says GeoJSON is WGS84 and nothing else,
 * so a GeoJSON boundary is 4326 by definition; the column's typmod states it and
 * the file is trusted rather than reprojected. A dataset in some other
 * projection is a dataset that has to be converted before it gets here.
 *
 * PURE, and that is why it is unit-tested rather than integration-tested: no
 * entity manager, no filesystem, no container. Given an array it returns an
 * array or throws.
 */
final readonly class GeoJsonNormalizer
{
    /**
     * @param array<array-key, mixed> $document a decoded GeoJSON object
     *
     * @return list<mixed> the `coordinates` value for a MultiPolygon
     *
     * @throws \InvalidArgumentException when the document holds no polygonal geometry
     */
    public function toMultiPolygonCoordinates(array $document): array
    {
        $type = $document['type'] ?? null;
        if (!\is_string($type)) {
            throw new \InvalidArgumentException('GeoJSON object is missing a "type".');
        }

        return match ($type) {
            'FeatureCollection' => $this->fromFeatures($document['features'] ?? null),
            'Feature' => $this->toMultiPolygonCoordinates($this->geometryOf($document)),
            'Polygon' => [$this->coordinatesOf($document)],
            'MultiPolygon' => array_values($this->coordinatesOf($document)),
            // NAMED, not "unsupported geometry": somebody who exported the wrong
            // layer needs to be told it was points, not that something was wrong.
            default => throw new \InvalidArgumentException(\sprintf('Unsupported geometry type "%s" (need Polygon/MultiPolygon).', $type)),
        };
    }

    /** @return list<mixed> */
    private function fromFeatures(mixed $features): array
    {
        if (!\is_array($features)) {
            throw new \InvalidArgumentException('FeatureCollection has no "features" array.');
        }

        $polygons = [];
        foreach ($features as $feature) {
            if (\is_array($feature)) {
                array_push($polygons, ...$this->toMultiPolygonCoordinates($this->geometryOf($feature)));
            }
        }

        return $polygons;
    }

    /**
     * @param array<array-key, mixed> $feature
     *
     * @return array<array-key, mixed>
     */
    private function geometryOf(array $feature): array
    {
        $geometry = $feature['geometry'] ?? null;
        if (!\is_array($geometry)) {
            throw new \InvalidArgumentException('Feature has no "geometry".');
        }

        return $geometry;
    }

    /**
     * @param array<array-key, mixed> $geometry
     *
     * @return array<array-key, mixed>
     */
    private function coordinatesOf(array $geometry): array
    {
        $coordinates = $geometry['coordinates'] ?? null;
        if (!\is_array($coordinates)) {
            throw new \InvalidArgumentException('Geometry has no "coordinates".');
        }

        return $coordinates;
    }
}
