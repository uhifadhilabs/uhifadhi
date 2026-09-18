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

namespace Uhifadhi\Bundle\AreaBundle\Repository;

use Doctrine\Persistence\ManagerRegistry;
use FundiStadi\PostGISBundle\Repository\SpatialEntityRepository;
use Uhifadhi\Bundle\AreaBundle\Entity\AreaOfInterest;
use Uhifadhi\Bundle\AreaBundle\Entity\Zone;

/**
 * Zone lookups, including the two predicates the zone invariant is built on.
 * Both are DE-9IM / coverage questions the PostGIS bundle's DQL surface does not
 * expose, so they are expressed as native SQL — here, in the repository, and
 * nowhere else in the bundle.
 *
 * @extends SpatialEntityRepository<Zone>
 */
class ZoneRepository extends SpatialEntityRepository
{
    /**
     * "The interiors of A and B intersect" — the first cell of the DE-9IM
     * matrix, every other cell free. This is exactly the zone rule: two zones
     * sharing only boundary (adjacent zones) score F in that cell and pass,
     * while an overlap, a containment and an identical footprint all score T and
     * fail. ST_Overlaps alone would not do: PostGIS defines it as FALSE when one
     * geometry contains the other, which is a case the invariant must catch.
     */
    private const string INTERIORS_INTERSECT = 'T********';

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Zone::class);
    }

    /**
     * Every zone of one area, by name. An area with no zones — the default
     * state — is an empty list, not an error.
     *
     * @return list<Zone>
     */
    public function zonesFor(AreaOfInterest $area): array
    {
        /** @var list<Zone> $result */
        $result = $this->createQueryBuilder('z')
            ->where('z.area = :area')
            ->setParameter('area', $area)
            ->orderBy('z.name', 'ASC')
            ->getQuery()
            ->getResult();

        return $result;
    }

    public function countFor(AreaOfInterest $area): int
    {
        return (int) $this->createQueryBuilder('z')
            ->select('COUNT(z.id)')
            ->where('z.area = :area')
            ->setParameter('area', $area)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function findOneForName(AreaOfInterest $area, string $name): ?Zone
    {
        return $this->findOneBy(['area' => $area, 'name' => $name]);
    }

    /**
     * The sibling zone whose interior the given geometry would share, or null
     * when the geometry fits. $ignore excludes the zone being re-drawn from its
     * own check — otherwise every edit would collide with itself.
     */
    public function findStInteriorConflict(AreaOfInterest $area, string $geoJson, ?Zone $ignore = null): ?Zone
    {
        $areaId = $area->getId();
        if (null === $areaId) {
            return null;
        }

        $parameters = ['area' => $areaId, 'geom' => $geoJson, 'pattern' => self::INTERIORS_INTERSECT];
        // The && bounding-box test is the index-using prefilter; ST_Relate then decides.
        $sql = 'SELECT z.id FROM zone z WHERE z.area_id = :area'
            .' AND z.geom && ST_GeomFromGeoJSON(:geom)'
            .' AND ST_Relate(z.geom, ST_GeomFromGeoJSON(:geom), :pattern)';
        $ignoreId = $ignore?->getId();
        if (null !== $ignoreId) {
            $sql .= ' AND z.id <> :ignore';
            $parameters['ignore'] = $ignoreId;
        }
        $sql .= ' ORDER BY z.name ASC, z.id ASC LIMIT 1';

        $id = $this->getEntityManager()->getConnection()->fetchOne($sql, $parameters);

        return is_numeric($id) ? $this->find((int) $id) : null;
    }

    /**
     * The zone covering the point, or null where the area is unzoned. ST_Covers
     * includes the boundary, so a point on an edge two zones share matches BOTH;
     * the ordering below settles it deterministically — lowest name, then lowest
     * id — so repeated calls agree.
     */
    public function findStCovering(AreaOfInterest $area, float $lon, float $lat): ?Zone
    {
        $areaId = $area->getId();
        if (null === $areaId) {
            return null;
        }

        $id = $this->getEntityManager()->getConnection()->fetchOne(
            'SELECT z.id FROM zone z WHERE z.area_id = :area'
            .' AND ST_Covers(z.geom, ST_SetSRID(ST_MakePoint(:lon, :lat), 4326))'
            .' ORDER BY z.name ASC, z.id ASC LIMIT 1',
            ['area' => $areaId, 'lon' => $lon, 'lat' => $lat],
        );

        return is_numeric($id) ? $this->find((int) $id) : null;
    }

    /**
     * DOES THE AREA'S OWN BOUNDARY COVER THIS CANDIDATE ZONE? A zone SUBDIVIDES
     * its area, so a polygon with any part of it outside the boundary is not a
     * subdivision of anything — it is a file imported onto the wrong area, which
     * is exactly what happens when an installation has several.
     *
     * ST_Covers, not ST_Contains: a zone that reaches the area's own edge — the
     * outermost zone of any real scheme does — shares boundary with it, and
     * ST_Contains calls that false.
     *
     * AN AREA WITH NO BOUNDARY COVERS NOTHING AND REFUSES NOTHING. There is no
     * edge to be outside of, so the question does not arise and the answer is
     * true; the check belongs to the boundary, not to the zone.
     */
    public function stAreaCovers(AreaOfInterest $area, string $geoJson): bool
    {
        $areaId = $area->getId();
        if (null === $areaId || !$area->hasBoundary()) {
            return true;
        }

        $covers = $this->getEntityManager()->getConnection()->fetchOne(
            'SELECT ST_Covers(a.geom, ST_GeomFromGeoJSON(:geom))::int FROM area_of_interest a WHERE a.id = :area',
            ['area' => $areaId, 'geom' => $geoJson],
        );

        return is_numeric($covers) && 1 === (int) $covers;
    }

    /**
     * THE GROUND A CANDIDATE RING COVERS, on the spheroid, before it is stored.
     * The preview prints a size beside every feature, and a size computed from
     * degrees in PHP is wrong by a factor that grows with latitude — so the
     * database answers, exactly as it does for a zone that already exists.
     */
    public function stGeometryKm2(string $geoJson): float
    {
        $km2 = $this->getEntityManager()->getConnection()->fetchOne(
            'SELECT ST_Area(ST_GeomFromGeoJSON(:geom)::geography) / 1000000.0',
            ['geom' => $geoJson],
        );

        return is_numeric($km2) ? (float) $km2 : 0.0;
    }

    /**
     * HOW MUCH GROUND A CANDIDATE RING WOULD TAKE FROM A ZONE THAT IS ALREADY
     * THERE. "Overlaps Crater" is a refusal; "overlaps Crater by 41 km²" is a
     * refusal somebody can act on, because it says whether the file is wrong or
     * the stored zone is.
     */
    public function stOverlapKm2(Zone $zone, string $geoJson): float
    {
        $id = $zone->getId();
        if (null === $id) {
            return 0.0;
        }

        $km2 = $this->getEntityManager()->getConnection()->fetchOne(
            'SELECT ST_Area(ST_Intersection(z.geom, ST_GeomFromGeoJSON(:geom))::geography) / 1000000.0'
            .' FROM zone z WHERE z.id = :id',
            ['id' => $id, 'geom' => $geoJson],
        );

        return is_numeric($km2) ? (float) $km2 : 0.0;
    }

    /**
     * The same interior test between two geometries that are not stored yet —
     * what an import needs to compare the features of one file against each
     * other before writing any of them.
     */
    public function stInteriorsIntersect(string $firstGeoJson, string $secondGeoJson): bool
    {
        // Cast in SQL: the driver hands booleans back as 't'/'f' or 1/0
        // depending on build, an int is unambiguous.
        $intersects = $this->getEntityManager()->getConnection()->fetchOne(
            'SELECT ST_Relate(ST_GeomFromGeoJSON(:first), ST_GeomFromGeoJSON(:second), :pattern)::int',
            ['first' => $firstGeoJson, 'second' => $secondGeoJson, 'pattern' => self::INTERIORS_INTERSECT],
        );

        return is_numeric($intersects) && 1 === (int) $intersects;
    }
}
