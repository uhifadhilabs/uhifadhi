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
use Uhifadhi\Bundle\AreaBundle\Exception\BoundaryImportException;

/**
 * A FILE BECOMES AN AREA'S BOUNDARY — the one supported way an area gets a
 * gazetted edge from outside. The area exists already; this adds or replaces
 * its geometry, whether that happens the moment it is created or long after.
 *
 * GEOJSON, AND THIS MODULE SAYS SO PLAINLY. The application this was ported from
 * accepted zipped Shapefiles, GeoPackages, KML/KMZ and File Geodatabases too, by
 * shelling out to GDAL's `ogr2ogr` — which means an installation that has not
 * got the `gdal` binaries on its host cannot import at all. A reusable bundle
 * must not put a system package between an installation and its first area, so
 * the format list here is the one format that needs nothing but PHP, and the
 * screen's caption names it rather than listing five and failing on four. The
 * conversion path is a later ring's work and belongs behind an optional
 * collaborator, not behind a hard dependency.
 *
 * NOTHING IS REPROJECTED, and that is a statement rather than an omission. RFC
 * 7946 defines GeoJSON as WGS84 and nothing else, so a GeoJSON boundary is
 * already 4326 — which is what the column's typmod declares. A file in some
 * other projection is a file that lies about its own format, and no amount of
 * guessing here would make it honest.
 *
 * THE GEOMETRY IS NEVER PARSED IN PHP. `setGeom()` takes a GeoJSON string and
 * the postgis type turns it into geometry with `ST_GeomFromGeoJSON` in SQL, so
 * validity is decided by the one thing that can decide it — with the whole
 * boundary in hand, at the moment of the insert.
 */
final readonly class BoundaryImport
{
    /**
     * WHERE THE BOUNDARY CAME FROM, as the register prints it. The entity's
     * `source` column is free text — "WDPA", a shapefile's name, "drawn" — and
     * a boundary that arrived through this screen is honestly none of those.
     */
    public const string SOURCE = 'upload';

    /** RFC 7946's own media type, plus what a browser actually sends for a `.geojson`. */
    private const array EXTENSIONS = ['geojson', 'json'];

    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    /**
     * A BOUNDARY, ONTO AN AREA THAT ALREADY EXISTS — the one operation this
     * service performs now that creating an area no longer means importing one.
     *
     * The area is created from its identity first; the boundary is added here,
     * now (straight after creation) or later (from the overview's no-boundary
     * state or the edit screen). All three call THIS, so a boundary arrives one
     * way and is validated one way. On a replace the geometry is superseded in
     * place; the new provenance is {@see SOURCE} because it came through an
     * upload however the area first got its edge.
     *
     * @throws BoundaryImportException when the file is not GeoJSON or holds no polygon
     */
    public function importInto(AreaOfInterest $area, File $file, string $originalName): AreaOfInterest
    {
        $area
            ->setGeom($this->readMultiPolygon($file, $originalName))
            ->setSource(self::SOURCE);

        $this->entityManager->flush();

        return $area;
    }

    /**
     * THE FILE, READ TO A MULTIPOLYGON GEOJSON STRING — validation and parsing
     * with no persistence, so the create screen can prove a boundary is sound
     * BEFORE it decides whether to create the area, and no bad file ever leaves
     * a half-made area behind.
     *
     * @throws BoundaryImportException when the file is not GeoJSON or holds no polygon
     */
    public function readMultiPolygon(File $file, string $originalName): string
    {
        /*
         * REFUSED ON THE EXTENSION, BEFORE THE FILE IS READ. A 200 MB shapefile
         * is not going to become GeoJSON by being json_decode()d, and the
         * message somebody needs is the format list, not a parse error from
         * halfway through a binary.
         */
        if (!\in_array(strtolower(pathinfo($originalName, \PATHINFO_EXTENSION)), self::EXTENSIONS, true)) {
            throw new BoundaryImportException('The boundary must be a GeoJSON file (.geojson or .json).');
        }

        return $this->multiPolygonFrom($file);
    }

    private function multiPolygonFrom(File $file): string
    {
        try {
            $document = json_decode((string) file_get_contents($file->getPathname()), true, 512, \JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new BoundaryImportException('The file is not valid GeoJSON: '.$e->getMessage(), previous: $e);
        }

        if (!\is_array($document)) {
            throw new BoundaryImportException('The file did not contain a GeoJSON object.');
        }

        try {
            $polygons = new GeoJsonNormalizer()->toMultiPolygonCoordinates($document);
        } catch (\InvalidArgumentException $e) {
            throw new BoundaryImportException('No polygon boundary found in the file: '.$e->getMessage(), previous: $e);
        }

        /*
         * A FEATURECOLLECTION WITH NO POLYGONAL FEATURE IN IT reaches here empty
         * rather than throwing — the normalizer walks features and simply finds
         * nothing. An empty MultiPolygon would insert happily and produce an
         * area with no ground, which is the worst of the three outcomes.
         */
        if ([] === $polygons) {
            throw new BoundaryImportException('No polygon boundary found in the file.');
        }

        return (string) json_encode(['type' => 'MultiPolygon', 'coordinates' => $polygons], \JSON_THROW_ON_ERROR);
    }
}
