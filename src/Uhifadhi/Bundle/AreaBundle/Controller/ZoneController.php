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

namespace Uhifadhi\Bundle\AreaBundle\Controller;

use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Twig\Environment;
use Uhifadhi\Bundle\AreaBundle\Entity\AreaOfInterest;
use Uhifadhi\Bundle\AreaBundle\Repository\ZoneRepository;

/**
 * THE ZONES OF ONE AREA — the spatial lens, listed.
 *
 * READING IS NOT GATED BEYOND THE AREA ITSELF, and that is a ruling rather than
 * an oversight: a zone is a lens, and a lens nobody may look through explains
 * nothing. Anybody who may see the area may see how it is divided. The WRITES —
 * importing a scheme, renaming, redrawing, deleting — are gated on `area.edit`
 * where they are offered, which is why the page takes the permission as a flag
 * rather than refusing the whole screen.
 *
 * AN AREA WITH NO ZONES IS THE NORMAL STATE. The page says so in its own words
 * instead of rendering an empty table, because a table with no rows reads as a
 * page that failed to load.
 */
final readonly class ZoneController
{
    public function __construct(
        private Environment $twig,
        private ZoneRepository $zones,
    ) {
    }

    #[Route('/areas/{uuid}/zones', name: 'area_zones', requirements: ['uuid' => Requirement::UUID], methods: ['GET'])]
    #[IsGranted('area.view')]
    public function index(
        #[MapEntity(mapping: ['uuid' => 'uuid'])] AreaOfInterest $area,
    ): Response {
        $zones = $this->zones->zonesFor($area);

        // Measured on the spheroid, one zone at a time, because a zone's size is
        // the database's answer and not a number PHP can get right from degrees.
        $km2 = [];
        foreach ($zones as $zone) {
            $id = $zone->getId();
            if (null !== $id) {
                $km2[$id] = (int) round($this->zones->stAreaKm2(['id' => $id]));
            }
        }

        return new Response($this->twig->render('@Area/zone/index.html.twig', [
            'area' => $area,
            'zones' => $zones,
            'zoneKm2' => $km2,
        ]));
    }
}
