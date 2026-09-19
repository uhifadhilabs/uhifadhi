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
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Uid\Uuid;
use Twig\Environment;
use Uhifadhi\Bundle\AreaBundle\Entity\AreaOfInterest;
use Uhifadhi\Bundle\AreaBundle\Model\ZonePalette;
use Uhifadhi\Bundle\AreaBundle\Repository\ZoneEventRepository;
use Uhifadhi\Bundle\AreaBundle\Service\AreaPlateService;
use Uhifadhi\Bundle\AreaBundle\Service\ZoneExportService;
use Uhifadhi\Bundle\AreaBundle\Service\ZoneImportDraftStore;
use Uhifadhi\Bundle\AreaBundle\Service\ZoneImportService;
use Uhifadhi\Bundle\AreaBundle\Service\ZoneSetService;

/**
 * THE ZONES SECTION OF AN AREA'S CONFIGURE PAGE — where the set is changed, and
 * the only place it is changed.
 *
 * A SECTION WITH AN ADDRESS OF ITS OWN. The shell draws most configure sections
 * itself from a template a surface names, but this one posts files, previews
 * them and writes — so it is declared as a SCREEN
 * ({@see \Uhifadhi\Contracts\Shell\ConfigurationSection::screen()}) and answers
 * at its own route. The frame is unchanged either way: the shell puts the
 * section strip where a data page's tabs go and lights the Configure action,
 * because {@see \Uhifadhi\Bundle\ShellBundle\Frame\Service\ModuleFrameService}
 * recognises a section's own route.
 *
 * THE STATE OF THE IMPORT CARD IS THE DATA'S, not a switch. An area with no
 * zones gets the first-import card; an area with zones gets the resting one; a
 * file previewed a moment ago puts its verdicts there instead; a file that could
 * not be read puts its refusal there. One card, and the area decides which of it
 * is drawn.
 *
 * READING IS GATED ON `area.view`, WRITING ON `area.edit` — the same split the
 * Zones tab makes, for the same reason: how an area is divided is a lens and
 * explains nothing to somebody who may not look through it, while changing the
 * division is an edit of the area itself.
 */
final readonly class ZoneConfigureController
{
    /** Where the strip's Zones entry points, and where every write comes back to. */
    public const string ROUTE = 'area_zones_configure';

    /**
     * THE TWO THINGS THE ADDRESS CARRIES. Which card is open is a place, so it
     * is a query a link can be shared and a write can come back to; taking an
     * uploaded file back off the card is the other.
     *
     * A DESTRUCTIVE ACTION IS NOT ONE OF THEM. Removing a zone and removing the
     * set both ask through the platform's shared confirm modal, which is the
     * house rule and is also what keeps every destructive question in the
     * product phrased and dismissed the same way.
     */
    public const string OPEN_QUERY = 'open';
    public const string DISCARD_QUERY = 'discard';

    public function __construct(
        private Environment $twig,
        private ZoneSetService $set,
        private AreaPlateService $plates,
        private ZoneEventRepository $events,
        private ZoneImportDraftStore $draft,
        private ZoneExportService $export,
        private CsrfTokenManagerInterface $csrf,
    ) {
    }

    #[Route('/areas/{uuid}/zones/settings', name: self::ROUTE, requirements: ['uuid' => Requirement::UUID], methods: ['GET'])]
    #[IsGranted('area.view')]
    public function configure(
        Request $request,
        #[MapEntity(mapping: ['uuid' => 'uuid'])] AreaOfInterest $area,
    ): Response {
        if ($request->query->has(self::DISCARD_QUERY)) {
            // TAKING THE FILE OFF THE CARD drops the plan and nothing else: the
            // document was never stored, so there is nothing else to drop.
            $this->draft->dropPlan($area);
        }

        $view = $this->set->view($area);
        $plan = $this->draft->plan($area);

        // THE HUES ARE DECIDED ONCE AND SPENT THREE TIMES — the swatch on a
        // preview row, the ring on the plate and the row in the key — so the
        // page cannot show one feature in two colours.
        $hues = [];
        foreach ($plan?->arriving() ?? [] as $position => $feature) {
            $hues[$feature->name] = ZonePalette::hueFor($position);
        }

        return new Response($this->twig->render('@Area/zone/configure.html.twig', [
            'area' => $area,
            'set' => $view,
            'plan' => $plan,
            'refusal' => $this->draft->takeRefusal($area),
            'outcome' => $this->draft->takeOutcome($area),
            'openZone' => self::uuidQuery($request, self::OPEN_QUERY),
            'exportName' => $this->export->fileName($area),
            'events' => $this->events->findByArea($area),
            'map' => null === $plan
                ? $this->plates->plate($area, $view->rows)
                : $this->plates->previewPlate($area, $plan->arriving()),
            'hues' => $hues,
            'nameProperties' => ZoneImportService::NAME_PROPERTIES,
            'importToken' => $this->csrf->getToken(ZoneImportController::TOKEN)->getValue(),
            'renameToken' => $this->csrf->getToken(ZoneEditController::RENAME_TOKEN)->getValue(),
            'ringToken' => $this->csrf->getToken(ZoneEditController::RING_TOKEN)->getValue(),
            'removeToken' => $this->csrf->getToken(ZoneEditController::REMOVE_TOKEN)->getValue(),
            'clearToken' => $this->csrf->getToken(ZoneEditController::CLEAR_TOKEN)->getValue(),
        ]));
    }

    /**
     * A UUID FROM THE QUERY, OR NULL. The page states are addressed by the
     * zone's own public identifier, so anything that is not one is not a state
     * this page has — and answering with "no card is open" is the right answer
     * to a link somebody mangled.
     */
    private static function uuidQuery(Request $request, string $key): ?string
    {
        $value = $request->query->get($key);

        return \is_string($value) && Uuid::isValid($value) ? $value : null;
    }

    /**
     * THE LIVE SET, AS THE FILE IT CAME IN AS — always available, and not only
     * when something is about to be deleted. No superseded geometry is kept
     * anywhere, so this download is the only copy of a zone set that will ever
     * exist.
     */
    #[Route('/areas/{uuid}/zones/export.geojson', name: 'area_zones_export', requirements: ['uuid' => Requirement::UUID], methods: ['GET'])]
    #[IsGranted('area.view')]
    public function export(
        #[MapEntity(mapping: ['uuid' => 'uuid'])] AreaOfInterest $area,
    ): Response {
        $response = new Response($this->export->featureCollection($area), Response::HTTP_OK, [
            // RFC 7946's own media type, so a desktop GIS opens it without being told what it is.
            'Content-Type' => 'application/geo+json',
        ]);
        $response->headers->set('Content-Disposition', $response->headers->makeDisposition(
            ResponseHeaderBag::DISPOSITION_ATTACHMENT,
            $this->export->fileName($area),
        ));

        return $response;
    }
}
