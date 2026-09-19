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
        private StationEventService $events,
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
        ?string $actor = null,
    ): Station {
        $station = new Station()
            ->setArea($area)
            ->setName(trim($name))
            ->setCode(null === $code || '' === trim($code) ? null : trim($code))
            ->setPoint(self::pointAt($lon, $lat));

        $this->entityManager->persist($station);
        $this->entityManager->flush();

        $this->events->recorded($station, $actor);
        $this->rederiveFor($area, 'the station was recorded');
        $this->entityManager->refresh($station);

        return $station;
    }

    /**
     * The post moved. Its ground may have changed hands, so the answer is
     * re-asked — and the log says how far it went and which way, because "the
     * point changed" is a fact nobody can check and "340 m west" is one
     * somebody can walk to.
     */
    public function moveTo(Station $station, float $lon, float $lat, ?string $actor = null): Station
    {
        $area = $station->getArea();
        if (null === $area) {
            throw new \LogicException('A station always belongs to an area.');
        }

        $to = self::pointAt($lon, $lat);
        [$metres, $heading] = $this->stations->stDisplacement((string) $station->getPoint(), $to);

        $station->setPoint($to);
        $this->entityManager->flush();

        if ($metres > 0) {
            $this->events->pointMoved($station, $metres, $heading, $actor);
        }

        $this->rederiveFor($area, 'the station moved');
        $this->entityManager->refresh($station);

        return $station;
    }

    /** A rename touches nothing else: the post is where it was. */
    public function rename(Station $station, string $name, ?string $actor = null): Station
    {
        $was = (string) $station->getName();
        $name = trim($name);
        if ('' === $name || $was === $name) {
            return $station;
        }

        $station->setName($name);
        $this->entityManager->flush();
        $this->events->renamed($station, $was, $name, $actor);

        return $station;
    }

    /** Closed, not deleted: every line, every posting and every patrol stays. */
    public function deactivate(Station $station, ?string $actor = null): Station
    {
        if ($station->isActive()) {
            $station->setActive(false);
            $this->entityManager->flush();
            $this->events->deactivated($station, $actor);
        }

        return $station;
    }

    public function reactivate(Station $station, ?string $actor = null): Station
    {
        if (!$station->isActive()) {
            $station->setActive(true);
            $this->entityManager->flush();
            $this->events->reactivated($station, $actor);
        }

        return $station;
    }

    /**
     * EVERY STATION IN THE AREA, RE-ASKED. Called by whatever moved the zones
     * under them — an import, a replaced ring, a removal, a cleared set.
     *
     * EACH ONE THAT MOVED GETS A LINE IN ITS OWN LOG, and `$because` names
     * what did it: an import, a replaced ring, a removal. A station whose zone
     * did NOT change gets none — the alternative is a line on every post in
     * the area every time anybody edits a zone.
     *
     * `$alsoNotify` NAMES THE STATIONS THE DATABASE ALREADY ANSWERED FOR. When
     * a zone is deleted the foreign key sets `zone_id` to null before this
     * runs, so the recompute finds nothing to change and would log nothing —
     * for the one event a reader most wants to see. The caller that deleted
     * the zone knows who stood in it, and says so here.
     *
     * @param list<int> $alsoNotify station ids to log even when the statement changed nothing
     *
     * @return int how many stations changed zone
     */
    public function rederiveFor(AreaOfInterest $area, string $because = 'the zones changed', array $alsoNotify = []): int
    {
        $moved = $this->stations->updateDerivedZones($area);

        foreach (array_unique([...$moved, ...$alsoNotify]) as $id) {
            $station = $this->stations->find($id);
            if (null === $station) {
                continue;
            }

            // The statement went round the unit of work, so the loaded row
            // still holds the zone it had before it.
            $this->entityManager->refresh($station);
            $this->events->zoneDerived($station, $station->getZone()?->getName(), $because);
        }

        return \count($moved);
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
