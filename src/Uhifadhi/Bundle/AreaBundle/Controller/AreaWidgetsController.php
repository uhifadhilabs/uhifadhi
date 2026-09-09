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

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Twig\Environment;
use Uhifadhi\Bundle\AreaBundle\Model\AreaRow;
use Uhifadhi\Bundle\AreaBundle\Service\AreaPresetLibrary;
use Uhifadhi\Bundle\AreaBundle\Service\AreaRegister;

/**
 * THE AREAS-INDEX WIDGET LIBRARY — the "Widget library" button on the register
 * opens here, onto the five whole-page layouts the areas landing ships.
 *
 * A PLAIN CLASS, extending nothing, its collaborators handed in — the
 * reusable-bundle rule, because a bundle installed by other projects must not
 * reach a container through a base class. See config/screens.php.
 *
 * ADOPT-ONLY, AND SELF-CONTAINED. The five layouts are rendered whole and inline,
 * from the same real rows the register reads; previewing one swaps the inline
 * layout on this page and adopting it makes it the landing. The preview and the
 * adoption are the page's own client-side concern in this slice — the server
 * hands down the five layouts and marks the shipped default, and never links out
 * to the design scratchboard the layouts were graduated from.
 *
 * GATED ON `area.view` — reading which layouts the landing can wear is for anyone
 * who may see the register at all.
 */
final readonly class AreaWidgetsController
{
    public function __construct(
        private Environment $twig,
        private AreaRegister $register,
        private AreaPresetLibrary $library,
        private UrlGeneratorInterface $urls,
    ) {
    }

    /**
     * Mounted with a priority so a future module page under `/areas/{slug}` can
     * never shadow it; `/areas/{uuid}` cannot, since "widgets" is not a UUID.
     */
    #[Route('/areas/widgets', name: 'area_widgets', methods: ['GET'], priority: 1)]
    #[IsGranted('area.view')]
    public function index(): Response
    {
        // One clock for every layout, exactly as the register measures its wall:
        // the map dock, the register table and the attention board all read the
        // same rows at the same instant, so no two disagree.
        $now = new \DateTimeImmutable();
        $rows = $this->register->rows($now);
        $presetRows = $this->library->enrich($rows, $now);
        $flagship = AreaPresetLibrary::flagship($presetRows);

        return new Response($this->twig->render('@Area/area/widgets.html.twig', [
            'presets' => $this->library->presets(),
            'defaultKey' => $this->library->defaultKey(),
            'rows' => $rows,
            'counts' => $this->register->counts($rows),
            'presetRows' => $presetRows,
            'needsAttention' => AreaPresetLibrary::needsAttention($presetRows),
            'runningSteady' => AreaPresetLibrary::runningSteady($presetRows),
            'awaitingSetup' => AreaPresetLibrary::awaitingSetup($presetRows),
            'flagship' => $flagship,
            'flagshipRest' => AreaPresetLibrary::rest($presetRows, $flagship),
            'statColumns' => $this->statColumns($rows),
            'mapAreas' => $this->mapAreas($rows),
        ]));
    }

    /**
     * THE REGISTER TABLE'S OPERATIONAL COLUMN HEADERS — the labels the now-tile
     * contributions handed back, read from the first live area (they are uniform across
     * areas, one module contributing the same tiles to each). Empty when nothing
     * is live, so the table draws no column for a figure no module contributed —
     * the same absent-not-zero discipline the wall keeps, in a table.
     *
     * @param list<AreaRow> $rows
     *
     * @return list<string>
     */
    private function statColumns(array $rows): array
    {
        foreach ($rows as $row) {
            if ($row->isLive()) {
                return array_map(static fn ($stat): string => $stat->label, $row->stats);
            }
        }

        return [];
    }

    /**
     * The map-of-the-network payload — each area as a point the browser plate
     * draws: its name, whether it is live, the link to its overview, and its
     * boundary as GeoJSON (or null, for a boundary-less area that has no place on
     * the map but still rides the dock beside it). The geometry travels as text
     * exactly as the column holds it; it is never parsed in PHP.
     *
     * @param list<AreaRow> $rows
     *
     * @return list<array{name: string, live: bool, href: string, boundary: string|null}>
     */
    private function mapAreas(array $rows): array
    {
        $areas = [];
        foreach ($rows as $row) {
            $areas[] = [
                'name' => $row->area->getName() ?? '',
                'live' => $row->isLive(),
                'href' => $this->urls->generate('area_show', ['uuid' => $row->area->getUuidString()]),
                'boundary' => $row->area->getGeom(),
            ];
        }

        return $areas;
    }
}
