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

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Uhifadhi\Bundle\AreaBundle\Entity\AreaOfInterest;
use Uhifadhi\Bundle\AreaBundle\Entity\CheckIn;
use Uhifadhi\Bundle\AreaBundle\Entity\PersonPosition;

/**
 * @extends ServiceEntityRepository<PersonPosition>
 */
class PersonPositionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PersonPosition::class);
    }

    /**
     * WHICH OF THESE REFERENCES THE AREA ALREADY HOLDS — one query, so a
     * batch of two hundred pings is one round trip rather than two
     * hundred.
     *
     * @param list<string> $clientRefs
     *
     * @return list<string>
     */
    public function knownRefs(AreaOfInterest $area, array $clientRefs): array
    {
        if ([] === $clientRefs) {
            return [];
        }

        /** @var list<array{clientRef: string}> $rows */
        $rows = $this->createQueryBuilder('p')
            ->select('p.clientRef AS clientRef')
            ->andWhere('p.area = :area')
            ->andWhere('p.clientRef IN (:refs)')
            ->setParameter('area', $area)
            ->setParameter('refs', $clientRefs)
            ->getQuery()
            ->getArrayResult();

        return array_map(static fn (array $row): string => $row['clientRef'], $rows);
    }

    /**
     * THE PINGS OF ONE WATCH, oldest first — what a day's reading is
     * derived from.
     *
     * @return list<PersonPosition>
     */
    public function forCheckIn(CheckIn $checkIn): array
    {
        /** @var list<PersonPosition> $rows */
        $rows = $this->createQueryBuilder('p')
            ->andWhere('p.checkIn = :checkin')
            ->setParameter('checkin', $checkIn)
            ->orderBy('p.recordedAt', 'ASC')
            ->getQuery()
            ->getResult();

        return $rows;
    }

    /**
     * HOW CLOSE THE WATCH CAME TO THE POST — the nearest of a watch's
     * pings, in metres, and when that one arrived.
     *
     * THE DATABASE ANSWERS IT. A distance computed in PHP from degrees
     * is wrong by a factor that grows with latitude, and a ring two
     * hundred metres across is exactly the scale where that decides an
     * answer. `ST_Distance` on geography is right everywhere.
     *
     * The CHECK-IN'S OWN POSITION IS INCLUDED, because a ranger who
     * tapped at the gate and whose phone then lost signal did report a
     * position — and it is the one that bears the claim out.
     *
     * @return array{metres: float, at: \DateTimeImmutable}|null null where the watch reported nothing
     */
    public function nearestTo(CheckIn $checkIn, string $stationPoint): ?array
    {
        $row = $this->getEntityManager()->getConnection()->fetchAssociative(
            <<<'SQL'
                SELECT ST_Distance(p.position::geography, s.point::geography) AS metres, p.recorded_at AS at
                FROM (
                    SELECT position, recorded_at FROM duty_position WHERE checkin_id = :checkin
                    UNION ALL
                    SELECT position, COALESCE(position_at, occurred_at) FROM duty_checkin
                    WHERE id = :checkin AND position IS NOT NULL
                ) p, (SELECT ST_GeomFromGeoJSON(:station) AS point) s
                ORDER BY metres ASC
                LIMIT 1
                SQL,
            ['checkin' => $checkIn->getId(), 'station' => $stationPoint],
        );

        if (false === $row || !is_numeric($row['metres'] ?? null)) {
            return null;
        }

        /** @var array{metres: numeric-string|float, at: string} $row */
        return [
            'metres' => (float) $row['metres'],
            'at' => new \DateTimeImmutable($row['at']),
        ];
    }

    /**
     * THE LAST FIX A WATCH SENT — what a live map draws, and the only
     * ping of a watch that answers "where is this person now".
     *
     * THE CHECK-IN'S OWN POSITION COUNTS AS ONE. A ranger who tapped at
     * the gate and whose phone then lost signal did report a position,
     * and it is the last thing anybody knows about where they are; the
     * day read already treats it as a fix and so must this, or somebody
     * who checked in two minutes ago would be missing from the map.
     *
     * @return array{lat: float, lon: float, at: \DateTimeImmutable, accuracy: float|null, battery: int|null}|null
     */
    public function latestFor(CheckIn $checkIn): ?array
    {
        $row = $this->getEntityManager()->getConnection()->fetchAssociative(
            <<<'SQL'
                SELECT ST_Y(p.position::geometry) AS lat, ST_X(p.position::geometry) AS lon,
                       p.recorded_at AS at, p.accuracy_m AS accuracy, p.battery_pct AS battery
                FROM (
                    SELECT position, recorded_at, accuracy_m, battery_pct
                    FROM duty_position WHERE checkin_id = :checkin
                    UNION ALL
                    SELECT position, COALESCE(position_at, occurred_at), accuracy_m, NULL
                    FROM duty_checkin WHERE id = :checkin AND position IS NOT NULL
                ) p
                ORDER BY p.recorded_at DESC
                LIMIT 1
                SQL,
            ['checkin' => $checkIn->getId()],
        );

        if (false === $row || !is_numeric($row['lat'] ?? null) || !is_numeric($row['lon'] ?? null)) {
            return null;
        }

        /** @var array{lat: numeric-string|float, lon: numeric-string|float, at: string, accuracy: numeric-string|float|null, battery: int|numeric-string|null} $row */
        return [
            'lat' => (float) $row['lat'],
            'lon' => (float) $row['lon'],
            'at' => new \DateTimeImmutable($row['at']),
            'accuracy' => null === $row['accuracy'] ? null : (float) $row['accuracy'],
            'battery' => null === $row['battery'] ? null : (int) $row['battery'],
        ];
    }

    /**
     * HOW FAR ONE FIX FELL FROM A POST, in metres.
     *
     * THE DATABASE ANSWERS IT, for the reason {@see nearestTo()} states:
     * a distance computed in PHP from degrees is wrong by a factor that
     * grows with latitude, and a ring two hundred metres across is
     * exactly the scale where that decides an answer.
     */
    public function metresBetween(float $lat, float $lon, string $stationPoint): ?float
    {
        $row = $this->getEntityManager()->getConnection()->fetchAssociative(
            <<<'SQL'
                SELECT ST_Distance(
                    ST_SetSRID(ST_MakePoint(:lon, :lat), 4326)::geography,
                    ST_GeomFromGeoJSON(:station)::geography
                ) AS metres
                SQL,
            ['lat' => $lat, 'lon' => $lon, 'station' => $stationPoint],
        );

        return false !== $row && is_numeric($row['metres'] ?? null) ? (float) $row['metres'] : null;
    }

    /**
     * WHAT A WATCH REPORTED: how many pings, and when the last one
     * arrived.
     *
     * @return array{pings: int, last: \DateTimeImmutable|null}
     */
    public function tallyFor(CheckIn $checkIn): array
    {
        $row = $this->getEntityManager()->getConnection()->fetchAssociative(
            'SELECT COUNT(*) AS pings, MAX(recorded_at) AS last FROM duty_position WHERE checkin_id = :checkin',
            ['checkin' => $checkIn->getId()],
        );

        if (false === $row) {
            return ['pings' => 0, 'last' => null];
        }

        /** @var array{pings: numeric-string|int, last: string|null} $row */
        return [
            'pings' => (int) $row['pings'],
            'last' => null === $row['last'] ? null : new \DateTimeImmutable($row['last']),
        ];
    }
}
