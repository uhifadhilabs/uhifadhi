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
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Twig\Environment;
use Uhifadhi\Bundle\AreaBundle\Entity\AreaOfInterest;
use Uhifadhi\Bundle\AreaBundle\Model\PostingQuery;
use Uhifadhi\Bundle\AreaBundle\Model\PostingRow;
use Uhifadhi\Bundle\AreaBundle\Model\ZoneFigureCard;
use Uhifadhi\Bundle\AreaBundle\Model\ZoneListQuery;
use Uhifadhi\Bundle\AreaBundle\Repository\PostingRepository;
use Uhifadhi\Bundle\AreaBundle\Repository\StationRepository;
use Uhifadhi\Bundle\AreaBundle\Service\AreaPlateService;
use Uhifadhi\Bundle\AreaBundle\Service\AreaRegister;
use Uhifadhi\Bundle\AreaBundle\Service\PostingBoardService;
use Uhifadhi\Bundle\AreaBundle\Service\StationRegisterService;
use Uhifadhi\Bundle\AreaBundle\Service\ZoneFigureService;
use Uhifadhi\Bundle\AreaBundle\Service\ZoneListService;
use Uhifadhi\Bundle\AreaBundle\Service\ZoneSetService;
use Uhifadhi\Bundle\AtlasBundle\Calendar\Periods;
use Uhifadhi\Bundle\RegistryBundle\Service\AreaModuleService;
use Uhifadhi\Bundle\RegistryBundle\Service\ModuleEntryRouteResolver;
use Uhifadhi\Contracts\Kpi\DepartmentKpi;
use Uhifadhi\Contracts\Kpi\ZoneRef;

/**
 * HOW AN AREA IS DIVIDED, READ — the whole set on one page.
 *
 * PICK ALL ZONES OR ONE. This is the all-zones state: the area's band, the
 * area's figures, the whole ground on one plate, and under it the stations
 * those zones account for and the people working out of them. Picking one is
 * {@see ZoneRecordController}, the same surface one step in, and the picker
 * is the sidebar rather than a column of this page — which is what lets the
 * reading column keep the band's full width.
 *
 * A LENS, NOT A FENCE. Reading how an area is divided is for anybody who may
 * see the area; every write lives on the configure section, and the one
 * control here that changes anything is the Configure action the frame draws.
 *
 * AN AREA WITH NO ZONES IS THE NORMAL STATE, and the page says what a zone is
 * rather than rendering an empty table — a table with no rows reads as a page
 * that failed to load.
 *
 * FIVE FIGURES OR NONE. Three are the area's own and two are whatever modules
 * publish about a zone; a module that publishes nothing leaves its card
 * saying so, because a row of three where the design has five is a different
 * design.
 */
final readonly class ZoneController
{
    public const string ROUTE = 'area_zones';

    /** The people card searches on a key of its own; the stations card owns `q`. */
    public const string PERSON_QUERY = 'person';
    public const string PEOPLE_PAGE = 'ppage';

    /** What the two cards draw before they page — the design's own numbers. */
    public const int STATIONS_PER_PAGE = 5;
    public const int PEOPLE_PER_PAGE = 6;

    /** How many of the five cards the modules may fill. */
    private const int MODULE_CARDS = 2;

    public function __construct(
        private Environment $twig,
        private ZoneSetService $set,
        private StationRegisterService $register,
        private StationRepository $stations,
        private PostingRepository $postings,
        private PostingBoardService $board,
        private AreaRegister $areas,
        private AreaPlateService $plates,
        private ZoneFigureService $figures,
        private ZoneListService $zoneList,
        private AreaModuleService $areaModules,
        private ModuleEntryRouteResolver $entryRoutes,
        /**
         * WHAT PERIOD IT IS NOW, from the one source that decides it. This
         * was `FigurePeriod::month(new \DateTimeImmutable())` written here
         * and in ten other places, each asking the wall clock: correct on
         * the 14th, wrong on the 1st, and unpinnable by a test.
         */
        private Periods $periods,
    ) {
    }

    #[Route('/areas/{uuid}/zones', name: self::ROUTE, requirements: ['uuid' => Requirement::UUID], methods: ['GET'])]
    #[IsGranted('zones.read')]
    public function index(
        Request $request,
        #[MapEntity(mapping: ['uuid' => 'uuid'])] AreaOfInterest $area,
    ): Response {
        $view = $this->set->view($area);
        $zoneQuery = ZoneListQuery::from($request);
        $query = StationConfigureController::queryFrom($request);
        $register = $this->register->register($area, $query, self::STATIONS_PER_PAGE);

        $posts = [];
        $staffed = 0;
        foreach ($this->stations->findByArea($area) as $post) {
            $posted = $this->postings->countStandingByStation($post);
            $posts[] = [
                'uuid' => (string) $post->getUuidString(),
                'name' => (string) $post->getName(),
                'point' => $post->getPoint(),
                'posted' => $posted,
                'here' => false,
            ];

            if ($posted > 0) {
                ++$staffed;
            }
        }

        $people = $this->peopleOf($area, $this->postings->findStandingByArea($area), $request);

        return new Response($this->twig->render('@Area/zone/index.html.twig', [
            'area' => $area,
            'areaKm2' => $this->areas->areaKm2($area),
            'set' => $view,
            // THE ZONES THEMSELVES, AS A TABLE — owner-ruled: the tab lists
            // every zone the way it lists every station.
            'zones' => $this->zoneList->register($area, $zoneQuery),
            'zoneQuery' => $zoneQuery,
            'register' => $register,
            'query' => $query,
            'stationCount' => \count($posts),
            'staffed' => $staffed,
            ...$people,
            'modules' => $this->moduleCards($area, $view->rows),
            'moduleCards' => self::MODULE_CARDS,
            'map' => $this->plates->picker($area, $view->rows, $posts),
        ]));
    }

    /**
     * THE PEOPLE CARD: everybody working out of a post in the selection,
     * searched by name and bounded by a page.
     *
     * ITS SEARCH IS NOT THE STATIONS CARD'S. Two cards on one page, each with
     * a search, need two keys or one of them silently filters the other.
     *
     * @param list<\Uhifadhi\Bundle\AreaBundle\Entity\Posting> $postings
     *
     * @return array{people: list<PostingRow>, peopleTotal: int, peopleMatching: int, peoplePage: int, peoplePages: int, peopleSearch: string, stationNames: array<string, string>}
     */
    private function peopleOf(AreaOfInterest $area, array $postings, Request $request, int $perPage = self::PEOPLE_PER_PAGE): array
    {
        $search = trim($request->query->getString(self::PERSON_QUERY));
        $all = $this->board->board($postings, new PostingQuery())['rows'];

        $matching = '' === $search
            ? $all
            : array_values(array_filter(
                $all,
                static fn (PostingRow $row): bool => str_contains(mb_strtolower($row->name), mb_strtolower($search)),
            ));

        $pages = max(1, (int) ceil(\count($matching) / $perPage));
        $page = max(1, min($request->query->getInt(self::PEOPLE_PAGE, 1), $pages));

        $names = [];
        foreach ($this->stations->findByArea($area) as $station) {
            $names[(string) $station->getUuidString()] = (string) $station->getName();
        }

        return [
            'people' => \array_slice($matching, ($page - 1) * $perPage, $perPage),
            'peopleTotal' => \count($all),
            'peopleMatching' => \count($matching),
            'peoplePage' => $page,
            'peoplePages' => $pages,
            'peopleSearch' => $search,
            'stationNames' => $names,
        ];
    }

    /**
     * WHAT THE MODULES SAY ABOUT THIS AREA'S ZONES, one card per module, in
     * module order and capped at the two the design has room for.
     *
     * NO MODULE IS NAMED HERE. A module that reports by zone tags a provider;
     * this reads the tag through the seam and prints whatever comes back.
     *
     * @param list<\Uhifadhi\Bundle\AreaBundle\Model\ZoneRow> $rows
     *
     * @return list<ZoneFigureCard>
     */
    private function moduleCards(AreaOfInterest $area, array $rows): array
    {
        if ([] === $rows) {
            return [];
        }

        $figures = $this->figures->collect(
            array_map(
                static fn ($row): ZoneRef => new ZoneRef($row->uuid, (string) $area->getUuidString(), $row->name),
                $rows,
            ),
            $this->periods->month(),
            fn (string $slug): bool => $this->areaModules->isActive($area, $slug),
        );

        /** @var array<string, list<DepartmentKpi>> $byModule */
        $byModule = [];
        foreach ($rows as $row) {
            foreach ($figures->forZone($row->uuid) as $figure) {
                $byModule[$figure->moduleSlug][] = $figure;
            }
        }

        $cards = [];
        foreach ($byModule as $slug => $readings) {
            $card = ZoneFigureCard::of($readings, $figures->period->label, $this->entryRoutes->entryRouteFor($slug));
            if (null !== $card) {
                $cards[] = $card;
            }
        }

        return \array_slice($cards, 0, self::MODULE_CARDS);
    }
}
