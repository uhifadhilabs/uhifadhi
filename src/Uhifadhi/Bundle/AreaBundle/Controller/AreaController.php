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
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Twig\Environment;
use Uhifadhi\Bundle\AreaBundle\Entity\AreaOfInterest;
use Uhifadhi\Bundle\AreaBundle\Repository\ZoneRepository;
use Uhifadhi\Bundle\AreaBundle\Service\AreaMapPayload;
use Uhifadhi\Bundle\AreaBundle\Service\AreaMapService;
use Uhifadhi\Bundle\AreaBundle\Service\AreaOverview;
use Uhifadhi\Bundle\AreaBundle\Service\AreaPresetLibrary;
use Uhifadhi\Bundle\AreaBundle\Service\AreaRegister;
use Uhifadhi\Bundle\AreaBundle\Widget\AreaIndexWidgets;
use Uhifadhi\Bundle\ShellBundle\Frame\Controller\ConfigureController;
use Uhifadhi\Bundle\ShellBundle\Widget\Service\WidgetService;
use Uhifadhi\Contracts\Entity\UserInterface as ModuleUserInterface;
use Uhifadhi\Contracts\Shell\ConfigurationSection;

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
        private AreaMapService $areaMap,
        private UrlGeneratorInterface $urls,
        private AreaPresetLibrary $library,
        private WidgetService $widgets,
        private TokenStorageInterface $tokens,
    ) {
    }

    /**
     * THE REGISTER — every area this installation manages.
     *
     * Not gated on `area.create`: reading which areas exist is for anybody who
     * may see an area at all, and the create affordance inside the page is what
     * carries the stricter permission.
     *
     * IT IS A WIDGET SURFACE, and its five layouts are alternatives rather than
     * additions, so the page draws the ONE the person adopted in the library —
     * read back through the widget framework from the same stored row the library
     * wrote. A layout adopted on one screen and ignored on the other is the whole
     * defect this resolve() call exists to close.
     */
    #[Route('/areas', name: 'area_index', methods: ['GET'])]
    #[IsGranted('area.view')]
    public function index(): Response
    {
        $catalog = new AreaIndexWidgets()->catalog();

        return new Response($this->twig->render('@Area/area/index.html.twig', [
            'view' => self::adopted($this->widgets->resolve($catalog, $this->signedIn())),
            // Handed to the register once, so every figure on the landing is
            // measured against the same clock: two areas' "6 min ago" then mean
            // the same thing.
            ...$this->library->landing(new \DateTimeImmutable()),
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
            'map' => $this->areaMap->overview($this->mapPayload->forArea($area), $mapLayers),
            'nowTiles' => $this->overview->nowTilesFor($area, $now),
            'attention' => $this->overview->attentionFor($area, $now),
            'installedSlugs' => $this->overview->installedSlugs($area),
        ]));
    }

    /**
     * THE OLD SETTINGS SCREEN'S ADDRESS, PERMANENTLY MOVED.
     *
     * What an area is set up with is configuration, and all of it now lives on
     * the one configure page behind the one Configure action — the record itself
     * in that page's Area settings section, which is where this points. The address stays
     * answered rather than deleted because it is in bookmarks, in a redirect a
     * form posts through, and in whatever an installation typed into its own
     * links — and 301 is what tells all three where it went for good.
     *
     * THE PERMISSION IS UNCHANGED. `area.edit` was the gate on the screen and it
     * is the gate on the redirect, so a viewer who could not read it before
     * still cannot be bounced into it.
     */
    #[Route('/areas/{uuid}/settings', name: 'area_settings', requirements: ['uuid' => Requirement::UUID], methods: ['GET'])]
    #[IsGranted('area.edit')]
    public function settings(
        #[MapEntity(mapping: ['uuid' => 'uuid'])] AreaOfInterest $area,
    ): Response {
        return new RedirectResponse(
            $this->urls->generate(ConfigureController::AREA_ROUTE, [
                'uuid' => $area->getUuidString(),
                'section' => ConfigurationSection::SETTINGS,
            ]),
            Response::HTTP_MOVED_PERMANENTLY,
        );
    }

    /**
     * WHICH OF THE FIVE LAYOUTS IS ON, read off a resolved layout. They are
     * alternatives, so the first one switched on is the answer; a stored row that
     * somehow has none on falls back to the layout this surface ships, because a
     * register that drew nothing would read as a page that failed to load.
     *
     * @param list<array{id: string, label: string, group: string, on: bool, cols: int, spans: list<int>}> $resolved
     */
    private static function adopted(array $resolved): string
    {
        foreach ($resolved as $widget) {
            if ($widget['on']) {
                return $widget['id'];
            }
        }

        return AreaIndexWidgets::DEFAULT_PRESET;
    }

    /**
     * The signed-in person as the CONTRACT sees them, which is what the widget
     * framework keeps a layout against — it never type-hints an installation's
     * account class, and this call site is not where that would start.
     *
     * Null is a real answer rather than a guard: the framework hands an anonymous
     * request the catalogue's own layout, which is exactly right for a register
     * nobody is signed in to.
     */
    private function signedIn(): ?ModuleUserInterface
    {
        $user = $this->tokens->getToken()?->getUser();

        return $user instanceof ModuleUserInterface ? $user : null;
    }
}
