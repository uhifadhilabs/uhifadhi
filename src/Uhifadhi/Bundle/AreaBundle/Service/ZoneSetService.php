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
use Uhifadhi\Bundle\AreaBundle\Entity\ZoneImport;
use Uhifadhi\Bundle\AreaBundle\Model\ZonePalette;
use Uhifadhi\Bundle\AreaBundle\Model\ZoneRow;
use Uhifadhi\Bundle\AreaBundle\Model\ZoneSetView;
use Uhifadhi\Bundle\AreaBundle\Repository\ZoneRepository;

/**
 * WHAT THE CONFIGURE PAGE READS ABOUT A ZONE SET — the rows and the totals.
 *
 * ONE WALK OVER ONE LIST decides each zone's hue from its position in the set,
 * and {@see ZonePlateService} draws from these same rows rather than deciding
 * again — so a zone cannot be teal on its card and pink on the plate.
 *
 * NO MAP HERE, DELIBERATELY. An installation that carries the area model and
 * mounts no page still reads its own zone set — a console report, an API — and
 * a reader that needed a map builder to count square kilometres could be asked
 * in neither. The atlas is the plate's dependency, not the set's.
 */
final readonly class ZoneSetService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ZoneRepository $zones,
    ) {
    }

    public function view(AreaOfInterest $area): ZoneSetView
    {
        $rows = [];
        $zoned = 0;
        foreach ($this->zones->zonesFor($area) as $position => $zone) {
            $id = $zone->getId();
            $km2 = null === $id ? 0 : (int) round($this->zones->stAreaKm2(['id' => $id]));
            $zoned += $km2;

            $rows[] = new ZoneRow(
                (string) $zone->getUuidString(),
                (string) $zone->getName(),
                ZonePalette::hueFor($position),
                $km2,
                null,
            );
        }

        /*
         * THE DENOMINATOR IS THE UNION, because a zone may lie outside the
         * gazetted line and "zoned of boundary" would then read over a hundred
         * percent. The register's own area figure is the BOUNDARY's and stays
         * that — it is what the settings record and the identity band mean by
         * the size of an area — so this asks a different question of the
         * database rather than reinterpreting that answer.
         */
        $ground = $this->zones->stGroundKm2($area);
        $groundKm2 = null === $ground || $ground <= 0.0 ? null : (int) round($ground);

        // THE SHARE IS RESOLVED ONCE THE TOTAL IS KNOWN, not guessed per row: a
        // page with no ground to measure against states sizes and withholds
        // percentages rather than printing a ratio of nothing.
        if (null !== $groundKm2 && $groundKm2 > 0) {
            $rows = array_map(
                static fn (ZoneRow $row): ZoneRow => new ZoneRow(
                    $row->uuid,
                    $row->name,
                    $row->hue,
                    $row->km2,
                    round($row->km2 / $groundKm2 * 100, 1),
                ),
                $rows,
            );
        }

        return new ZoneSetView($rows, $zoned, $groundKm2, $this->lastImport($area));
    }

    /** The provenance of the most recent import into this area, or null. */
    private function lastImport(AreaOfInterest $area): ?ZoneImport
    {
        return $this->entityManager->getRepository(ZoneImport::class)
            ->findOneBy(['area' => $area], ['importedAt' => 'DESC', 'id' => 'DESC']);
    }
}
