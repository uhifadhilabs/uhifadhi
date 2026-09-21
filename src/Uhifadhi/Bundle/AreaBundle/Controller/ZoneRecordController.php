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
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Twig\Environment;
use Uhifadhi\Bundle\AreaBundle\Entity\AreaOfInterest;
use Uhifadhi\Bundle\AreaBundle\Entity\Zone;
use Uhifadhi\Bundle\AreaBundle\Model\PostingQuery;
use Uhifadhi\Bundle\AreaBundle\Model\PostingRow;
use Uhifadhi\Bundle\AreaBundle\Model\StationQuery;
use Uhifadhi\Bundle\AreaBundle\Model\StationRow;
use Uhifadhi\Bundle\AreaBundle\Model\ZoneFigureSet;
use Uhifadhi\Bundle\AreaBundle\Repository\PostingRepository;
use Uhifadhi\Bundle\AreaBundle\Repository\StationRepository;
use Uhifadhi\Bundle\AreaBundle\Repository\ZoneEventRepository;
use Uhifadhi\Bundle\AreaBundle\Service\AreaPlateService;
use Uhifadhi\Bundle\AreaBundle\Service\PostingBoardService;
use Uhifadhi\Bundle\AreaBundle\Service\ZoneFigureService;
use Uhifadhi\Bundle\AreaBundle\Service\ZoneSetService;
use Uhifadhi\Bundle\AtlasBundle\Calendar\Periods;
use Uhifadhi\Bundle\RegistryBundle\Service\AreaModuleService;
use Uhifadhi\Bundle\RegistryBundle\Service\ModuleEntryRouteResolver;
use Uhifadhi\Contracts\Kpi\DepartmentKpi;
use Uhifadhi\Contracts\Kpi\ZoneFigureProviderInterface;
use Uhifadhi\Contracts\Kpi\ZoneRef;

/**
 * ONE ZONE, READ.
 *
 * A RECORD, NOT A TAB. A picked zone wears the station page's treatment: its
 * bare name as the h1, its context demoted to the quiet subline, the crumb
 * one step longer, its own identity band — and no tab strip, because a zone
 * is a thing inside the area rather than one of the ways of looking at the
 * area. The frame draws no Configure here for the same reason.
 *
 * ITS FIGURES ARE IN ITS BAND. A record states them there the way the station
 * page does — extent, covered, stations, people, and what the modules
 * publish — and carries no row of cards; the cards belong to the set.
 *
 * IT ONLY READS. The ring, the name and the removal are the configure
 * section's, and nothing here writes.
 */
final readonly class ZoneRecordController
{
    public const string ROUTE = 'area_zone_show';

    /** The people card searches on a key of its own; the stations card owns `q`. */
    public const string PERSON_QUERY = 'person';
    public const string PEOPLE_PAGE = 'ppage';

    /** A record's column is narrower than the tab's, and its cards page sooner. */
    public const int STATIONS_PER_PAGE = 5;
    public const int PEOPLE_PER_PAGE = 4;

    public function __construct(
        private Environment $twig,
        private ZoneSetService $set,
        private StationRepository $stations,
        private PostingRepository $postings,
        private PostingBoardService $board,
        private ZoneEventRepository $events,
        private AreaPlateService $plates,
        private ZoneFigureService $figures,
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

    #[Route(
        '/areas/{uuid}/zones/{zone}',
        name: self::ROUTE,
        requirements: ['uuid' => Requirement::UUID, 'zone' => Requirement::UUID],
        methods: ['GET'],
    )]
    #[IsGranted('zones.read')]
    public function show(
        Request $request,
        #[MapEntity(mapping: ['uuid' => 'uuid'])] AreaOfInterest $area,
        #[MapEntity(mapping: ['zone' => 'uuid'])] Zone $zone,
    ): Response {
        $this->denyUnlessTheZoneIsThisAreas($area, $zone);

        $view = $this->set->view($area);
        $row = null;
        foreach ($view->rows as $candidate) {
            if ($candidate->uuid === (string) $zone->getUuidString()) {
                $row = $candidate;
            }
        }

        // THE POSTS ON THIS GROUND, searched by name or code and nothing else:
        // every other filter of the register would answer the same on every
        // row of one zone.
        $search = trim($request->query->getString(StationQuery::SEARCH));
        $stations = [];
        $posts = [];
        foreach ($this->stations->findByZone($zone) as $station) {
            $posted = $this->postings->countStandingByStation($station);
            $stations[] = StationRow::of(
                $station,
                $posted,
                null !== $this->postings->findLeaderAt($station),
                $row?->cat,
            );
            $posts[] = [
                'uuid' => (string) $station->getUuidString(),
                'name' => (string) $station->getName(),
                'point' => $station->getPoint(),
                'posted' => $posted,
                'here' => false,
            ];
        }

        $matching = array_values(array_filter(
            $stations,
            static fn (StationRow $station): bool => $station->matches($search),
        ));

        $pages = max(1, (int) ceil(\count($matching) / self::STATIONS_PER_PAGE));
        $page = max(1, min($request->query->getInt(StationQuery::PAGE, 1), $pages));

        $people = $this->peopleOf($zone, $request);

        /*
         * ONE QUESTION OF THE SEAM PER REQUEST, however many facts the band
         * reads out of the answer.
         */
        $figures = $this->figures->collect(
            [new ZoneRef((string) $zone->getUuidString(), (string) $area->getUuidString(), null === $row ? (string) $zone->getName() : $row->name)],
            $this->periods->month(),
            fn (string $slug): bool => $this->areaModules->isActive($area, $slug),
        );

        return new Response($this->twig->render('@Area/zone/record.html.twig', [
            'area' => $area,
            'zone' => $zone,
            'row' => $row,
            'query' => new StationQuery(search: $search, page: $page),
            'stations' => \array_slice($matching, ($page - 1) * self::STATIONS_PER_PAGE, self::STATIONS_PER_PAGE),
            'stationCount' => \count($stations),
            'stationsMatching' => \count($matching),
            'stationsPage' => $page,
            'stationsPages' => $pages,
            'perPage' => self::STATIONS_PER_PAGE,
            ...$people,
            'covered' => $figures->covered((string) $zone->getUuidString()),
            'figures' => $this->moduleFacts($figures, (string) $zone->getUuidString()),
            // THE BAND'S UNIT IS THE PERIOD — one short word under each
            // module's figure, where the figure's own caption is a
            // sentence and a sentence is what made the band four rows.
            'period' => $figures->period,
            /*
             * ONE DOOR A MODULE, and every module that published
             * anything about this ground gets one — including the one
             * whose only figure is the covered share, which the band
             * states on its own. The header used to repeat a module's
             * name once per figure.
             */
            'moduleDoors' => $this->moduleDoors($figures, (string) $zone->getUuidString()),
            'events' => $this->events->findMentioning($area, (string) $zone->getName()),
            'lastImport' => $view->lastImport,
            /*
             * THE PLATE IS ABOUT THIS ZONE. It draws the whole area, the
             * other zones and every post, because a zone is read against
             * its neighbours — but opening on all of that put the zone at a
             * quarter of the plate's width, which is a page about the park.
             */
            'map' => $this->plates->focusOn(
                $this->plates->picker($area, $view->rows, $posts),
                $zone->getGeom(),
            ),
        ]));
    }

    /**
     * THE PEOPLE WORKING OUT OF THIS GROUND — everybody at a post inside the
     * zone, searched by name and bounded by a page.
     *
     * @return array{people: list<PostingRow>, peopleTotal: int, peopleMatching: int, peoplePage: int, peoplePages: int, peopleSearch: string, stationNames: array<string, string>}
     */
    private function peopleOf(Zone $zone, Request $request): array
    {
        $search = trim($request->query->getString(self::PERSON_QUERY));
        $all = $this->board->board($this->postings->findStandingByZone($zone), new PostingQuery())['rows'];

        $matching = '' === $search
            ? $all
            : array_values(array_filter(
                $all,
                static fn (PostingRow $person): bool => str_contains(mb_strtolower($person->name), mb_strtolower($search)),
            ));

        $pages = max(1, (int) ceil(\count($matching) / self::PEOPLE_PER_PAGE));
        $page = max(1, min($request->query->getInt(self::PEOPLE_PAGE, 1), $pages));

        $names = [];
        foreach ($this->stations->findByZone($zone) as $station) {
            $names[(string) $station->getUuidString()] = (string) $station->getName();
        }

        return [
            'people' => \array_slice($matching, ($page - 1) * self::PEOPLE_PER_PAGE, self::PEOPLE_PER_PAGE),
            'peopleTotal' => \count($all),
            'peopleMatching' => \count($matching),
            'peoplePage' => $page,
            'peoplePages' => $pages,
            'peopleSearch' => $search,
            'stationNames' => $names,
        ];
    }

    /**
     * ONE FACT A MODULE — the band is a LINE, not a list.
     *
     * THE DEFECT THIS FIXES: every figure every module published about
     * the ground went into the band, so an area running two modules
     * drew twelve facts wrapping onto four rows, each captioned with a
     * sentence ("every track that entered the zone"), and the header
     * repeated "See incidents" once per figure. A band a reader has to
     * scan four rows of is not an identity band.
     *
     * THE HEADLINE IS THE FIRST FIGURE THE MODULE PUBLISHED, and that
     * is a decision worth stating. The alternative was a second
     * well-known key beside {@see ZoneFigureProviderInterface::COVERED}
     * — but a key every module must adopt fails SILENTLY for the ones
     * that have not, and the symptom is an empty band. Order needs no
     * contract change, no module edit, and it is the rule the
     * performance matrix already reads a topic's headline by: the
     * publisher decided which of its figures leads.
     *
     * THE REST ARE NOT LOST. They are the zone figure cards on the
     * all-zones page, which is where the design puts them.
     *
     * @return list<array{figure: DepartmentKpi, route: string|null}>
     */
    private function moduleFacts(ZoneFigureSet $figures, string $zoneUuid): array
    {
        $facts = [];
        foreach ($figures->forZone($zoneUuid) as $figure) {
            if (ZoneFigureProviderInterface::COVERED === $figure->key) {
                continue;
            }

            // FIRST WINS, and a module is in the band once.
            if (\array_key_exists($figure->moduleSlug, $facts)) {
                continue;
            }

            $facts[$figure->moduleSlug] = [
                'figure' => $figure,
                'route' => $this->entryRoutes->entryRouteFor($figure->moduleSlug),
            ];
        }

        return array_values($facts);
    }

    /**
     * ONE WAY IN A MODULE — by distinct module, not by figure.
     *
     * @return list<array{name: string, route: string}>
     */
    private function moduleDoors(ZoneFigureSet $figures, string $zoneUuid): array
    {
        $doors = [];
        foreach ($figures->forZone($zoneUuid) as $figure) {
            $route = $this->entryRoutes->entryRouteFor($figure->moduleSlug);
            if (null === $route || \array_key_exists($figure->moduleSlug, $doors)) {
                continue;
            }

            $doors[$figure->moduleSlug] = ['name' => $figure->moduleName, 'route' => $route];
        }

        return array_values($doors);
    }

    /**
     * A ZONE IS ADDRESSED INSIDE ITS AREA. A uuid from one area on another's
     * address is not a zone that happens to be elsewhere; it is a request for
     * something this page is not about.
     */
    private function denyUnlessTheZoneIsThisAreas(AreaOfInterest $area, Zone $zone): void
    {
        if ($zone->getArea()?->getId() !== $area->getId()) {
            throw new AccessDeniedException('That zone does not belong to this area.');
        }
    }
}
