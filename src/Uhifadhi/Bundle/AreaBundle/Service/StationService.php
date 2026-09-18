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
use Uhifadhi\Bundle\AreaBundle\Entity\AreaOfInterest;
use Uhifadhi\Bundle\AreaBundle\Entity\Station;
use Uhifadhi\Bundle\AreaBundle\Repository\StationRepository;

/**
 * THE ONLY SUPPORTED WAY A STATION GETS A POINT, and therefore a zone.
 *
 * A STATION'S ZONE IS NEVER TYPED. It is derived from the point by PostGIS and
 * cached on the row; every write that can move the answer comes through here
 * and re-derives it, so the cache cannot drift from the map.
 *
 * FOUR THINGS MOVE THE ANSWER, and only one of them is the station:
 *
 *   1. the station's own point moves       — {@see moveTo()}
 *   2. an import adds zones                — the importer calls {@see rederiveFor()}
 *   3. a zone's ring is replaced           — the same
 *   4. a zone is removed, or the set is    — the same
 *
 * The last three are the area's business rather than the station's, which is
 * why they are one public method the zone surfaces call rather than an event
 * this service listens for: a listener would make the order of two writes
 * decide the answer, and a caller that forgot would leave a stale zone nobody
 * could see was stale.
 *
 * ONE STATEMENT FOR THE WHOLE AREA. An import moves every station's answer at
 * once, so the recompute is a single UPDATE rather than a query per station —
 * see {@see StationRepository::updateDerivedZones()}, which also carries the
 * tie-break.
 */
final readonly class StationService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private StationRepository $stations,
    ) {
    }

    /**
     * A STATION, AT A POINT. Longitude then latitude, in that order, because
     * GeoJSON and PostGIS both put them that way round and a product that
     * reversed them once would reverse them everywhere.
     */
    public function add(
        AreaOfInterest $area,
        string $name,
        float $lon,
        float $lat,
        ?string $code = null,
    ): Station {
        $station = new Station()
            ->setArea($area)
            ->setName(trim($name))
            ->setCode(null === $code || '' === trim($code) ? null : trim($code))
            ->setPoint(self::pointAt($lon, $lat));

        $this->entityManager->persist($station);
        $this->entityManager->flush();

        $this->rederiveFor($area);
        $this->entityManager->refresh($station);

        return $station;
    }

    /** The post moved. Its ground may have changed hands, so the answer is re-asked. */
    public function moveTo(Station $station, float $lon, float $lat): Station
    {
        $area = $station->getArea();
        if (null === $area) {
            throw new \LogicException('A station always belongs to an area.');
        }

        $station->setPoint(self::pointAt($lon, $lat));
        $this->entityManager->flush();

        $this->rederiveFor($area);
        $this->entityManager->refresh($station);

        return $station;
    }

    /**
     * EVERY STATION IN THE AREA, RE-ASKED. Called by whatever moved the zones
     * under them — an import, a replaced ring, a removal, a cleared set.
     *
     * @return int how many stations changed zone, which is what a log line says
     */
    public function rederiveFor(AreaOfInterest $area): int
    {
        $moved = $this->stations->updateDerivedZones($area);

        /*
         * THE STATEMENT WENT ROUND THE UNIT OF WORK, so anything already loaded
         * still holds the old zone. Clearing only the station rows would be
         * cheaper and is not available; a caller that holds one refreshes it,
         * which is what the two writers above do.
         */
        return $moved;
    }

    /** A point as the column's GeoJSON, longitude first. */
    private static function pointAt(float $lon, float $lat): string
    {
        return (string) json_encode(
            ['type' => 'Point', 'coordinates' => [$lon, $lat]],
            \JSON_THROW_ON_ERROR,
        );
    }
}
