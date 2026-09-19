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
use Uhifadhi\Bundle\AreaBundle\Entity\AreaOfInterest;
use Uhifadhi\Bundle\AreaBundle\Entity\Station;
use Uhifadhi\Bundle\AreaBundle\Entity\Zone;
use UtafitiLabs\PostGISBundle\Repository\SpatialEntityRepository;

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
    /**
     * EIGHT WORDS FOR A BEARING, because "moved 340 m on a bearing of 271°" is
     * a sentence for an instrument and "moved 340 m west" is one for a person
     * deciding whether to go and look.
     */
    private const array COMPASS = ['north', 'north-east', 'east', 'south-east', 'south', 'south-west', 'west', 'north-west'];

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
     * EVERY STATION ON THE INSTALLATION, in the order a cross-area board reads
     * them: by the area's name, then by the station's own code.
     *
     * THE AREA AND THE ZONE COME WITH IT. A board prints both on every row, and
     * fetched lazily that is two queries a station — the shape that turns a
     * twelve-post installation into twenty-five queries and a four-hundred-post
     * one into a timeout.
     *
     * @return list<Station>
     */
    public function findAllOrdered(): array
    {
        /** @var list<Station> $stations */
        $stations = $this->createQueryBuilder('s')
            ->addSelect('a', 'z')
            ->join('s.area', 'a')
            ->leftJoin('s.zone', 'z')
            ->orderBy('a.name', 'ASC')
            ->addOrderBy('s.code', 'ASC')
            ->addOrderBy('s.name', 'ASC')
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

    /**
     * HOW MANY POSTS STAND IN EACH ZONE, in one statement.
     *
     * UNZONED POSTS ARE NOT IN THE ANSWER. A station on ground no zone covers
     * is a legal and common state, and it belongs to no key here; the zones
     * surface counts zones, and the area's own count is what says how many
     * posts exist altogether.
     *
     * @return array<string, int> zone uuid to the number of posts in it
     */
    public function countPerZone(AreaOfInterest $area): array
    {
        /** @var list<array{zone: mixed, total: mixed}> $rows */
        $rows = $this->createQueryBuilder('s')
            ->select('z.uuid AS zone, COUNT(s.id) AS total')
            ->join('s.zone', 'z')
            ->where('s.area = :area')
            ->setParameter('area', $area)
            ->groupBy('z.uuid')
            ->getQuery()
            ->getResult();

        $counts = [];
        foreach ($rows as $row) {
            $zone = $row['zone'];
            $total = $row['total'];
            // The uuid comes back as the platform's own type, which prints itself.
            if ((\is_string($zone) || $zone instanceof \Stringable) && is_numeric($total)) {
                $counts[(string) $zone] = (int) $total;
            }
        }

        return $counts;
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
     * HOW FAR A POINT MOVED AND WHICH WAY, on the spheroid — the two facts a
     * log line about a move is worth reading for.
     *
     * THE DATABASE ANSWERS BOTH. A distance from degrees computed in PHP is
     * wrong by a factor that grows with latitude, and a bearing from them is
     * wrong twice over; `ST_Distance` on geography and `ST_Azimuth` are right
     * everywhere and cost one round trip on a write that already made several.
     *
     * @return array{0: int, 1: string} metres, rounded, and the compass word
     */
    public function stDisplacement(string $fromGeoJson, string $toGeoJson): array
    {
        if ('' === $fromGeoJson || $fromGeoJson === $toGeoJson) {
            return [0, 'north'];
        }

        $row = $this->getEntityManager()->getConnection()->fetchAssociative(
            'SELECT ST_Distance(a::geography, b::geography) AS metres,'
            .' degrees(ST_Azimuth(a, b)) AS bearing'
            .' FROM (SELECT ST_GeomFromGeoJSON(:from) AS a, ST_GeomFromGeoJSON(:to) AS b) p',
            ['from' => $fromGeoJson, 'to' => $toGeoJson],
        );

        if (false === $row || !is_numeric($row['metres'] ?? null)) {
            return [0, 'north'];
        }

        $metres = (int) round((float) $row['metres']);
        $bearing = is_numeric($row['bearing'] ?? null) ? (float) $row['bearing'] : 0.0;

        // THE BEARING IS DEGREES AND DEGREES ARE NOT WHOLE: the modulus has
        // to be the floating-point one, or the language rounds the angle to a
        // whole number on its way into the operator and says so.
        return [$metres, self::COMPASS[(int) round(fmod($bearing, 360.0) / 45.0) % 8]];
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
     * IT ANSWERS WHICH ONES MOVED, not how many. A station whose zone changed
     * gets a line in its own log, and a count could not say whose.
     *
     * @return list<int> the ids of the stations whose zone changed
     */
    public function updateDerivedZones(AreaOfInterest $area): array
    {
        $areaId = $area->getId();
        if (null === $areaId) {
            return [];
        }

        $rows = $this->getEntityManager()->getConnection()->fetchFirstColumn(
            'UPDATE station s SET zone_id = ('
            .'   SELECT z.id FROM zone z'
            .'   WHERE z.area_id = s.area_id AND ST_Covers(z.geom, s.point)'
            .'   ORDER BY z.name ASC, z.id ASC LIMIT 1'
            .' ) WHERE s.area_id = :area'
            .' AND s.zone_id IS DISTINCT FROM ('
            .'   SELECT z.id FROM zone z'
            .'   WHERE z.area_id = s.area_id AND ST_Covers(z.geom, s.point)'
            .'   ORDER BY z.name ASC, z.id ASC LIMIT 1'
            .' ) RETURNING s.id',
            ['area' => $areaId],
        );

        $ids = [];
        foreach ($rows as $row) {
            if (is_numeric($row)) {
                $ids[] = (int) $row;
            }
        }

        return $ids;
    }
}
