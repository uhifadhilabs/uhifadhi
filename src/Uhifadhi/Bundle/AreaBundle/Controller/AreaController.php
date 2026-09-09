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
use Uhifadhi\Bundle\AreaBundle\Service\AreaMapPayload;
use Uhifadhi\Bundle\AreaBundle\Service\AreaOverview;
use Uhifadhi\Bundle\AreaBundle\Service\AreaRegister;

/**
 * THE AREA SCREENS — the register, one area's overview, and its settings.
 *
 * A PLAIN CLASS, extending nothing, with its collaborators handed to it: the
 * reusable-bundle rule, because a bundle installed by other projects must not
 * reach into a container through a base class. See config/services.php.
 *
 * GATED ON THE PLATFORM'S OWN PERMISSION STRINGS — `area.view`, `area.edit` —
 * and on nothing else. The strings are in the catalogue TeamBundle ships,
 * and team's voter answers them at runtime; this bundle names them and does not
 * depend on team, which is what lets an installation swap the answering module
 * without touching a screen.
 *
 * ADDRESSED BY UUID. Every route takes `{uuid}` with the uuid requirement, so
 * `/areas/2` is a 404 rather than a sequential key anybody can walk.
 */
final readonly class AreaController
{
    public function __construct(
        private Environment $twig,
        private ZoneRepository $zones,
        private AreaRegister $register,
        private AreaOverview $overview,
        private AreaMapPayload $mapPayload,
    ) {
    }

    /**
     * THE REGISTER — every area this installation manages.
     *
     * Not gated on `area.create`: reading which areas exist is for anybody who
     * may see an area at all, and the create affordance inside the page is what
     * carries the stricter permission.
     */
    #[Route('/areas', name: 'area_index', methods: ['GET'])]
    #[IsGranted('area.view')]
    public function index(): Response
    {
        // Handed to the register once, so every card on the wall is measured
        // against the same clock: two areas' "6 min ago" then mean the same thing.
        $rows = $this->register->rows(new \DateTimeImmutable());

        return new Response($this->twig->render('@Area/area/index.html.twig', [
            'rows' => $rows,
            'counts' => $this->register->counts($rows),
        ]));
    }

    /**
     * ONE AREA'S OVERVIEW. The widgets on it are not written here — every
     * operational one arrives from a module installed in this area, through the
     * contribution contracts in src/Overview. What this action owns is the area's identity and
     * the honest-absent state for everything nobody contributed.
     */
    #[Route('/areas/{uuid}', name: 'area_show', requirements: ['uuid' => Requirement::UUID], methods: ['GET'])]
    #[IsGranted('area.view')]
    public function show(
        #[MapEntity(mapping: ['uuid' => 'uuid'])] AreaOfInterest $area,
    ): Response {
        // Handed in once rather than read per widget, so every card on the page
        // is measured at the SAME moment: two counts taken a second apart is how
        // a strip comes to disagree with the list under it.
        $now = new \DateTimeImmutable();
        $mapLayers = $this->overview->mapLayersFor($area, $now);

        return new Response($this->twig->render('@Area/area/overview.html.twig', [
            'area' => $area,
            'areaKm2' => $this->register->areaKm2($area),
            'zoneCount' => $this->zones->countFor($area),
            'mapPayload' => $this->mapPayload->forArea($area),
            'mapLayers' => $mapLayers,
            'mapLayerGroups' => AreaOverview::groupByContributor($mapLayers),
            'nowTiles' => $this->overview->nowTilesFor($area, $now),
            'attention' => $this->overview->attentionFor($area, $now),
            'installedSlugs' => $this->overview->installedSlugs($area),
        ]));
    }

    #[Route('/areas/{uuid}/settings', name: 'area_settings', requirements: ['uuid' => Requirement::UUID], methods: ['GET'])]
    #[IsGranted('area.edit')]
    public function settings(
        #[MapEntity(mapping: ['uuid' => 'uuid'])] AreaOfInterest $area,
    ): Response {
        return new Response($this->twig->render('@Area/area/settings.html.twig', [
            'area' => $area,
            'areaKm2' => $this->register->areaKm2($area),
            'zoneCount' => $this->zones->countFor($area),
        ]));
    }
}
