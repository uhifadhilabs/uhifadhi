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
use Uhifadhi\Bundle\AreaBundle\Entity\Zone;
use Uhifadhi\Bundle\AreaBundle\Exception\ZoneNameException;
use Uhifadhi\Bundle\AreaBundle\Exception\ZoneOverlapException;
use Uhifadhi\Bundle\AreaBundle\Repository\StationRepository;
use Uhifadhi\Bundle\AreaBundle\Repository\ZoneRepository;

/**
 * The write side of the spatial lens, and THE ONLY SUPPORTED WAY a zone gets a
 * geometry.
 *
 * THE INVARIANT: sibling zones of one area never share more than a SLIVER of
 * interior. Adjacency is legal — two zones may share an edge — and so are
 * gaps: an area is often only partly zoned. Sharing is found with the DE-9IM
 * pattern `T********` in {@see ZoneRepository::findStInteriorConflicts()} and
 * then MEASURED, because two rings digitised by hand share metres of edge that
 * were meant to touch; {@see ZoneOverlapService} says which side of the area's
 * own tolerance that falls, and a real overlap names the zone and the size.
 *
 * EVERY WRITE HERE LEAVES A LINE IN THE AREA'S ZONE LOG, and it is written
 * HERE rather than by whoever called. A screen, a console importer and a
 * fixture loader all rename and remove zones; a line written in a controller
 * is a history that is complete on one path and empty on the others, which
 * looks right on the page and is wrong in the record. The ACTOR is still the
 * caller's to supply, because only the caller knows whether there is one.
 *
 * EVERY WRITE HERE ALSO RE-DERIVES THE AREA'S STATIONS. A station's zone is its
 * point's answer, cached; moving the zones under it changes that answer
 * without anybody touching the station, so the four writes below each end by
 * re-asking. It is done here rather than in a listener because a listener
 * would make the order of two writes decide the answer, and a caller that
 * forgot would leave a stale zone nothing on the page could show was stale.
 *
 * A ZONE MAY LIE OUTSIDE THE AREA BOUNDARY. That was once refused here and is
 * not any more: a gazetted edge and an operational subdivision are drawn by
 * different people from different sources, and the surfaces that import zones
 * state how far a ring reaches past the line instead of turning it away.
 *
 * The read side is {@see self::zoneOf()}: point-in-zone by ST_Covers, null when
 * the point falls in no zone. On an edge two zones share ST_Covers is true for
 * both, so the tie is broken by name (then id) — the answer is stable, and it is
 * documented rather than left to whatever order the planner returns.
 */
class ZoneService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ZoneRepository $zones,
        private readonly ZoneOverlapService $overlaps,
        private readonly StationService $stations,
        private readonly StationRepository $stationRows,
        private readonly ZoneEventService $events,
    ) {
    }

    /**
     * @param string $geomJson a MultiPolygon GeoJSON string in WGS84
     *
     * @throws ZoneOverlapException when the geometry would share interior with a sibling
     */
    public function create(AreaOfInterest $area, string $name, string $geomJson): Zone
    {
        $this->assertFits($area, $name, $geomJson);

        $zone = new Zone()->setArea($area)->setName($name)->setGeom($geomJson);
        $this->em->persist($zone);
        $this->em->flush();
        $this->stations->rederiveFor($area);

        return $zone;
    }

    /**
     * Re-draws an existing zone. The zone's own current footprint is excluded
     * from the check — otherwise every edit would collide with itself.
     *
     * @throws ZoneOverlapException
     */
    public function replaceGeometry(Zone $zone, string $geomJson, ?string $actor = null): Zone
    {
        $area = $zone->getArea();
        if (null === $area) {
            throw new \LogicException('A zone always belongs to an area.');
        }
        $this->assertFits($area, $zone->getName() ?? '', $geomJson, $zone);

        $zone->setGeom($geomJson);
        $this->em->flush();
        $this->events->ringReplaced($area, (string) $zone->getName(), $actor);
        $this->stations->rederiveFor($area, 'a zone was redrawn');

        return $zone;
    }

    /**
     * A ZONE IS RENAMED, AND NOTHING ELSE HAPPENS. The ground did not move, so
     * no geometry is touched, no record changes zone and no history of the ring
     * is involved — which is why this is the one zone edit that needs no file
     * and no map.
     *
     * A ZONE MAY BE RENAMED TO WHAT IT IS ALREADY CALLED. Saving a form without
     * changing the field is not a clash with itself, and refusing it would be a
     * puzzle rather than a safeguard.
     *
     * @throws ZoneNameException when the name is blank, or already used in this area
     */
    public function rename(Zone $zone, string $name, ?string $actor = null): Zone
    {
        $name = trim($name);
        if ('' === $name) {
            throw ZoneNameException::empty();
        }

        $area = $zone->getArea();
        if (null === $area) {
            throw new \LogicException('A zone always belongs to an area.');
        }

        $was = (string) $zone->getName();
        $holder = $this->zones->findOneForName($area, $name);
        if (null !== $holder && $holder->getId() !== $zone->getId()) {
            throw ZoneNameException::alreadyUsed($name, $area->getName() ?? '');
        }

        if ($holder?->getId() === $zone->getId() && $was === $name) {
            return $zone;
        }

        $zone->setName($name);
        $this->em->flush();
        $this->events->renamed($area, $was, $name, $actor);

        return $zone;
    }

    /**
     * ONE ZONE, GONE. Its ground becomes unzoned, which is a legal state: an
     * area is often only partly zoned, and every consumer already treats "in no
     * zone" as a first-class answer. Nothing that referred to the ground is
     * deleted with it.
     */
    public function remove(Zone $zone, ?string $actor = null): void
    {
        $area = $zone->getArea();

        /*
         * WHO STOOD IN IT, READ BEFORE IT GOES. The foreign key sets their
         * `zone_id` to null as the row is deleted, so by the time the
         * recompute runs there is nothing left for it to notice — and the one
         * line a reader most wants, "this post is now in no zone", would never
         * be written. So the caller that knows says so.
         */
        $name = (string) $zone->getName();
        $standing = $this->stationRows->findByZone($zone);
        $stood = self::idsOf($standing);

        /*
         * THE LINK IS CUT IN MEMORY AS WELL AS IN THE DATABASE. The foreign
         * key sets `zone_id` to null as the row goes, but a station already
         * loaded still points at the removed object, and the next flush —
         * the log line's — would try to persist a zone that no longer exists.
         */
        foreach ($standing as $station) {
            $station->setZone(null);
        }

        $this->em->remove($zone);
        $this->em->flush();

        if (null !== $area) {
            $this->events->removed($area, $name, $actor);
            $this->stations->rederiveFor($area, 'a zone was removed', $stood);
        }
    }

    /**
     * THE WHOLE SET, IN ONE ACT, answering how many it removed — so the sentence
     * the person confirmed and the sentence they are told afterwards are about
     * the same number. A loop of single deletions would leave an area half
     * zoned if it stopped halfway, which is the one state nobody asked for.
     */
    public function removeAll(AreaOfInterest $area, ?string $actor = null): int
    {
        $zones = $this->zones->zonesFor($area);

        // The same reasoning as removing one: the keys are cleared by the
        // database as the rows go, so the stations that stood in them are read
        // before the delete and named to the recompute.
        $standing = $this->stationRows->findByArea($area);
        $stood = self::idsOf($standing);

        $this->em->wrapInTransaction(function () use ($zones, $standing): void {
            // The same reason as removing one: a station already loaded would
            // otherwise still point at a zone the flush has just deleted.
            foreach ($standing as $station) {
                $station->setZone(null);
            }

            foreach ($zones as $zone) {
                $this->em->remove($zone);
            }
            $this->em->flush();
        });

        if ([] !== $zones) {
            $this->events->cleared($area, \count($zones), $actor);
        }

        $this->stations->rederiveFor($area, 'every zone was removed', $stood);

        return \count($zones);
    }

    /**
     * The ids of stations that are about to lose their zone pointer to a
     * foreign key, so the recompute can be told whom to write a line for.
     *
     * @param list<Station> $stations
     *
     * @return list<int>
     */
    private static function idsOf(array $stations): array
    {
        $ids = [];
        foreach ($stations as $station) {
            $id = $station->getId();
            if (null !== $id) {
                $ids[] = $id;
            }
        }

        return $ids;
    }

    /**
     * @throws ZoneOverlapException
     */
    public function assertFits(AreaOfInterest $area, string $name, string $geomJson, ?Zone $ignore = null): void
    {
        $km2 = $this->zones->stGeometryKm2($geomJson);
        $tolerance = $this->overlaps->toleranceOf($area);

        foreach ($this->zones->findStInteriorConflicts($area, $geomJson, $ignore) as $conflict) {
            $shared = $this->zones->stOverlapKm2($conflict, $geomJson);
            $theirs = $this->zones->stGeometryKm2((string) $conflict->getGeom());

            if (!$this->overlaps->isSliver($shared, $theirs, $km2, $tolerance)) {
                throw ZoneOverlapException::between($name, $conflict, (int) round($shared));
            }
        }
    }

    /**
     * The zone containing the point, or null when the point lies in no zone — an
     * unzoned area, or a gap between zones, is a first-class answer and not an
     * error.
     *
     * A point exactly on an edge two zones share is covered by both; the one
     * whose name sorts first wins (ties broken by id), so repeated calls always
     * answer the same zone.
     */
    public function zoneOf(AreaOfInterest $area, float $lon, float $lat): ?Zone
    {
        return $this->zones->findStCovering($area, $lon, $lat);
    }
}
