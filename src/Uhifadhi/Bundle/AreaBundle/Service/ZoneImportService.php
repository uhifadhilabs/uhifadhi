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

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\File;
use Uhifadhi\Bundle\AreaBundle\Entity\AreaOfInterest;
use Uhifadhi\Bundle\AreaBundle\Entity\ZoneImport;
use Uhifadhi\Bundle\AreaBundle\Exception\ZoneImportException;
use Uhifadhi\Bundle\AreaBundle\Exception\ZoneOverlapException;
use Uhifadhi\Bundle\AreaBundle\Model\ZoneImportResult;
use Uhifadhi\Bundle\AreaBundle\Repository\ZoneRepository;

/**
 * A FILE BECOMES AN AREA'S ZONING SCHEME — the sibling of {@see BoundaryImport},
 * and the one supported way a whole subdivision arrives at once.
 *
 * ONE FEATURECOLLECTION PER AREA, ONE FEATURE PER ZONE. That is the shape a
 * desktop GIS exports and the shape the ruling fixes, so it is the shape read
 * here: the geometry of each feature becomes a zone's MultiPolygon, and the
 * zone's name comes out of the feature's properties.
 *
 * NOBODY IS ASKED TO CLEAN A FILE. A scheme converted from KMZ carries
 * description, timestamp, begin, end, altitudeMode, tessellate, extrude,
 * visibility, drawOrder and icon on every feature; a layer merge adds `layer`
 * and `path`; and every position carries an altitude the column cannot hold.
 * None of that is an error and none of it is a reason to send somebody back to
 * a text editor: the name is taken from the first of {@see NAME_PROPERTIES}
 * that the file actually uses, the third ordinate is dropped by
 * {@see GeoJsonNormalizer}, and everything else is READ PAST and then NAMED in
 * the summary — so what was ignored is stated rather than silently lost.
 *
 * WGS84 OR NOTHING, AND A MISSING `crs` IS WGS84. RFC 7946 defines GeoJSON as
 * WGS84 and removed the `crs` member, so a file without one is right and a file
 * carrying the CRS84 urn is saying the same thing twice. A file that DECLARES a
 * projected system is a file whose numbers are metres: importing it would put
 * the scheme somewhere off the coast of Africa, so it is refused by name and
 * the person is told to export as 4326.
 *
 * ALL OR NOTHING. A subdivision half-imported is not a smaller subdivision, it
 * is a wrong one — the zone invariant would then hold over a scheme that does
 * not exist. Every structural refusal happens before anything is written, and
 * the writes themselves run in one transaction that is rolled back on the first
 * spatial refusal, so an area refused an import has exactly the zones it had.
 *
 * THE FILE IS NOT KEPT. It is read, validated, turned into geometry and let go;
 * what survives is the geometry in PostGIS and one {@see ZoneImport} row of
 * provenance beside it.
 */
final readonly class ZoneImportService
{
    /**
     * THE ACCEPTED SPELLINGS, IN PRECEDENCE ORDER. Every tool in the chain names
     * the same column differently — QGIS keeps the source's case, ogr2ogr
     * upper-cases, a KML conversion writes `name`, a merge writes `layer` — and
     * the file is read rather than the exporter interrogated. The FIRST of these
     * that names every feature in the file is the one used, and the summary says
     * which it was.
     *
     * `layer` is last on purpose: it is a merge artefact that happens to be
     * usable, so it answers only when nothing better does.
     */
    public const array NAME_PROPERTIES = ['name', 'Name', 'NAME', 'zone', 'Zone', 'title', 'layer'];

    /** RFC 7946's own media type, plus what a browser sends for a `.geojson`. */
    private const array EXTENSIONS = ['geojson', 'json'];

    /**
     * WGS84, WRITTEN THE FOUR WAYS FILES WRITE IT. CRS84 is WGS84 with the axes
     * in longitude/latitude order, which is what GeoJSON uses; EPSG:4326 names
     * the same datum. Anything else is a declaration that the numbers are not
     * degrees.
     */
    private const array WGS84 = [
        'urn:ogc:def:crs:ogc:1.3:crs84',
        'urn:ogc:def:crs:ogc::crs84',
        'urn:ogc:def:crs:epsg::4326',
        'epsg:4326',
        'crs84',
    ];

    public function __construct(
        private EntityManagerInterface $entityManager,
        private ZoneService $zones,
        private ZoneRepository $zoneRepository,
    ) {
    }

    /**
     * THE SCHEME, ONTO AN AREA THAT ALREADY EXISTS.
     *
     * $importedBy is the identifier of whoever is importing where that is known
     * — a screen knows, a fixture loader does not — and is recorded as
     * provenance rather than used for anything.
     *
     * @throws ZoneImportException when the file, or any one feature in it, cannot become a zone
     */
    public function importInto(AreaOfInterest $area, File $file, string $originalName, ?string $importedBy = null): ZoneImportResult
    {
        $features = $this->featuresOf($file, $originalName);
        $nameProperty = $this->namePropertyOf($features);

        /** @var list<array{name: string, geom: string}> $candidates */
        $candidates = [];
        foreach ($features as $feature) {
            $name = $this->nameOf($feature, $nameProperty);
            $candidates[] = ['name' => $name, 'geom' => $this->geometryOf($feature, $name)];
        }

        $this->assertNamesAreFree($area, $candidates);

        $import = new ZoneImport()
            ->setArea($area)
            ->setFileName($originalName)
            ->setImportedAt(new \DateTimeImmutable())
            ->setImportedBy($importedBy)
            ->setZoneCount(\count($candidates))
            ->setNameProperty($nameProperty);

        /*
         * ONE TRANSACTION. The zone invariant is measured against what is
         * already stored, so the features are written one at a time and each is
         * checked against the ones before it — which is also what makes the
         * message name the pair. A refusal rolls the whole thing back, so the
         * intermediate rows never existed.
         */
        $this->entityManager->wrapInTransaction(function () use ($area, $candidates, $import): void {
            $this->entityManager->persist($import);

            foreach ($candidates as $candidate) {
                if (!$this->zoneRepository->stAreaCovers($area, $candidate['geom'])) {
                    throw ZoneImportException::outsideTheBoundary($candidate['name'], $area->getName() ?? 'this area');
                }

                try {
                    $zone = $this->zones->create($area, $candidate['name'], $candidate['geom']);
                } catch (ZoneOverlapException $e) {
                    throw new ZoneImportException($e->getMessage(), previous: $e);
                }

                $zone->setImport($import);
            }

            $this->entityManager->flush();
        });

        return new ZoneImportResult(
            array_column($candidates, 'name'),
            $nameProperty,
            $this->ignoredPropertiesOf($features, $nameProperty),
            $originalName,
        );
    }

    /**
     * THE FEATURES, IN FILE ORDER. A FeatureCollection is the ruled shape; a
     * bare Feature is a scheme of one and is accepted rather than explained away;
     * anything else is a file exported from the wrong layer and is named.
     *
     * @return list<array<array-key, mixed>>
     */
    private function featuresOf(File $file, string $originalName): array
    {
        /*
         * REFUSED ON THE EXTENSION, BEFORE THE FILE IS READ — the same guard
         * BoundaryImport uses, and for the same reason: a shapefile is not going
         * to become GeoJSON by being json_decode()d, and what somebody needs to
         * be told is the format.
         */
        if (!\in_array(strtolower(pathinfo($originalName, \PATHINFO_EXTENSION)), self::EXTENSIONS, true)) {
            throw ZoneImportException::notGeoJson(\sprintf('"%s" is not one of those.', $originalName));
        }

        try {
            $document = json_decode((string) file_get_contents($file->getPathname()), true, 512, \JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw ZoneImportException::notGeoJson($e->getMessage().'.', $e);
        }

        if (!\is_array($document)) {
            throw ZoneImportException::notGeoJson('The file did not contain a GeoJSON object.');
        }

        $this->assertWgs84($document);

        $type = $document['type'] ?? null;
        if ('Feature' === $type) {
            return [$document];
        }

        if ('FeatureCollection' !== $type) {
            throw ZoneImportException::notAFeatureCollection(\is_string($type) ? $type : 'file with no GeoJSON type');
        }

        $features = $document['features'] ?? null;
        if (!\is_array($features)) {
            throw ZoneImportException::notAFeatureCollection('FeatureCollection with no features');
        }

        $features = array_values(array_filter($features, \is_array(...)));
        if ([] === $features) {
            throw ZoneImportException::noFeatures();
        }

        return $features;
    }

    /**
     * @param array<array-key, mixed> $document
     *
     * @throws ZoneImportException when the file declares a system that is not WGS84
     */
    private function assertWgs84(array $document): void
    {
        $crs = $document['crs'] ?? null;
        if (!\is_array($crs)) {
            // RFC 7946 removed `crs` altogether: no member means WGS84.
            return;
        }

        $properties = $crs['properties'] ?? null;
        $name = \is_array($properties) ? ($properties['name'] ?? null) : null;
        if (!\is_string($name) || \in_array(strtolower($name), self::WGS84, true)) {
            return;
        }

        throw ZoneImportException::projectedCrs($name);
    }

    /**
     * WHICH PROPERTY NAMES THE ZONES — decided once for the whole file rather
     * than per feature, because a scheme whose names came out of three different
     * columns is a scheme nobody can check.
     *
     * @param list<array<array-key, mixed>> $features
     */
    private function namePropertyOf(array $features): string
    {
        foreach (self::NAME_PROPERTIES as $property) {
            foreach ($features as $feature) {
                if ('' === $this->propertyOf($feature, $property)) {
                    continue 2;
                }
            }

            return $property;
        }

        // Nothing named every feature. The refusal points at the first feature
        // that no accepted spelling names, since that is the row to go and fix.
        foreach ($features as $position => $feature) {
            foreach (self::NAME_PROPERTIES as $property) {
                if ('' !== $this->propertyOf($feature, $property)) {
                    continue 2;
                }
            }

            throw ZoneImportException::noName($position + 1, self::NAME_PROPERTIES);
        }

        throw ZoneImportException::noName(1, self::NAME_PROPERTIES);
    }

    /** @param array<array-key, mixed> $feature */
    private function nameOf(array $feature, string $nameProperty): string
    {
        return $this->propertyOf($feature, $nameProperty);
    }

    /** The property as a trimmed string, or '' where it is absent, null or not scalar. */
    private function propertyOf(mixed $feature, string $property): string
    {
        $properties = \is_array($feature) ? ($feature['properties'] ?? null) : null;
        $value = \is_array($properties) ? ($properties[$property] ?? null) : null;

        return \is_string($value) || \is_int($value) || \is_float($value) ? trim((string) $value) : '';
    }

    /**
     * The feature's geometry as the MultiPolygon string a zone's column takes.
     *
     * @param array<array-key, mixed> $feature
     */
    private function geometryOf(array $feature, string $name): string
    {
        if (!\is_array($feature['geometry'] ?? null)) {
            throw ZoneImportException::noGeometry($name);
        }

        try {
            return new GeoJsonNormalizer()->toMultiPolygon($feature);
        } catch (\InvalidArgumentException $e) {
            throw ZoneImportException::unusableGeometry($name, $e->getMessage(), $e);
        }
    }

    /**
     * NAMES ARE UNIQUE WITHIN AN AREA, and both ways of breaking that are worth
     * separate sentences: two features called the same thing is a file to fix,
     * a name the area already carries is a zone to rename or remove.
     *
     * @param list<array{name: string, geom: string}> $candidates
     */
    private function assertNamesAreFree(AreaOfInterest $area, array $candidates): void
    {
        $seen = [];
        foreach ($candidates as $candidate) {
            if (isset($seen[$candidate['name']])) {
                throw ZoneImportException::duplicateInFile($candidate['name']);
            }
            $seen[$candidate['name']] = true;

            if (null !== $this->zoneRepository->findOneForName($area, $candidate['name'])) {
                throw ZoneImportException::nameAlreadyUsed($candidate['name']);
            }
        }
    }

    /**
     * EVERY PROPERTY THE FILE CARRIED THAT THE IMPORT DID NOT USE, as one sorted
     * set across the whole file — the summary's second half, so nobody is left
     * wondering whether a description was kept somewhere.
     *
     * @param list<array<array-key, mixed>> $features
     *
     * @return list<string>
     */
    private function ignoredPropertiesOf(array $features, string $nameProperty): array
    {
        $ignored = [];
        foreach ($features as $feature) {
            $properties = $feature['properties'] ?? null;
            if (!\is_array($properties)) {
                continue;
            }

            foreach (array_keys($properties) as $key) {
                if ($nameProperty !== $key) {
                    $ignored[(string) $key] = true;
                }
            }
        }

        $names = array_keys($ignored);
        sort($names);

        return $names;
    }
}
