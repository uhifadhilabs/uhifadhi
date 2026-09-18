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
use Uhifadhi\Bundle\AreaBundle\Entity\Station;
use Uhifadhi\Bundle\AreaBundle\Entity\Zone;

/**
 * Station lookups, and the one spatial question a station asks: which of its
 * area's zones is its point in?
 *
 * THE DERIVATION IS ONE STATEMENT FOR THE WHOLE AREA. An import or a cleared
 * set moves every station's answer at once, and re-deriving them one at a time
 * would be a query per station on a write that already touched every zone. So
 * the recompute is a single UPDATE the database resolves in one pass, and the
 * tie-break rides inside it.
 *
 * @extends SpatialEntityRepository<Station>
 */
class StationRepository extends SpatialEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Station::class);
    }

    /**
     * Every station of one area, by name. An area with none — the ordinary
     * state before anybody records one — is an empty list, not an error.
     *
     * @return list<Station>
     */
    public function findByArea(AreaOfInterest $area): array
    {
        /** @var list<Station> $stations */
        $stations = $this->createQueryBuilder('s')
            ->where('s.area = :area')
            ->setParameter('area', $area)
            ->orderBy('s.name', 'ASC')
            ->getQuery()
            ->getResult();

        return $stations;
    }

    /**
     * @return list<Station>
     */
    public function findByZone(Zone $zone): array
    {
        /** @var list<Station> $stations */
        $stations = $this->createQueryBuilder('s')
            ->where('s.zone = :zone')
            ->setParameter('zone', $zone)
            ->orderBy('s.name', 'ASC')
            ->getQuery()
            ->getResult();

        return $stations;
    }

    public function countByArea(AreaOfInterest $area): int
    {
        return (int) $this->createQueryBuilder('s')
            ->select('COUNT(s.id)')
            ->where('s.area = :area')
            ->setParameter('area', $area)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * EVERY STATION OF ONE AREA, RE-ASKED THE QUESTION — in one statement.
     *
     * THE TIE-BREAK IS THE ZONE RULE'S OWN. `ST_Covers` includes the boundary,
     * so a point on an edge two zones share — or inside a sliver the area's
     * tolerance accepted — is covered by both; the answer is the zone whose
     * name sorts first, then the lowest id, exactly as
     * {@see ZoneRepository::findStCovering()} answers the same question live.
     * One ordering in two places would be two chances to disagree, so it is
     * written the same way in both and tested from both ends.
     *
     * A STATION IN NO ZONE IS SET TO NULL, not left as it was: the correlated
     * subquery answers null where nothing covers the point, and that is the
     * honest new value after a set is cleared.
     *
     * @return int the number of stations whose zone changed
     */
    public function updateDerivedZones(AreaOfInterest $area): int
    {
        $areaId = $area->getId();
        if (null === $areaId) {
            return 0;
        }

        return (int) $this->getEntityManager()->getConnection()->executeStatement(
            'UPDATE station s SET zone_id = ('
            .'   SELECT z.id FROM zone z'
            .'   WHERE z.area_id = s.area_id AND ST_Covers(z.geom, s.point)'
            .'   ORDER BY z.name ASC, z.id ASC LIMIT 1'
            .' ) WHERE s.area_id = :area'
            .' AND s.zone_id IS DISTINCT FROM ('
            .'   SELECT z.id FROM zone z'
            .'   WHERE z.area_id = s.area_id AND ST_Covers(z.geom, s.point)'
            .'   ORDER BY z.name ASC, z.id ASC LIMIT 1'
            .' )',
            ['area' => $areaId],
        );
    }
}
