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
