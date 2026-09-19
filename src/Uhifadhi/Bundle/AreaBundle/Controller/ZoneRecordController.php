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
use Uhifadhi\Bundle\RegistryBundle\Service\AreaModuleService;
use Uhifadhi\Bundle\RegistryBundle\Service\ModuleEntryRouteResolver;
use Uhifadhi\Contracts\Kpi\DepartmentKpi;
use Uhifadhi\Contracts\Kpi\FigurePeriod;
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
    ) {
    }

    #[Route(
        '/areas/{uuid}/zones/{zone}',
        name: self::ROUTE,
        requirements: ['uuid' => Requirement::UUID, 'zone' => Requirement::UUID],
        methods: ['GET'],
    )]
    #[IsGranted('area.view')]
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
                $row?->hue,
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
            FigurePeriod::month(new \DateTimeImmutable()),
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
            'events' => $this->events->findMentioning($area, (string) $zone->getName()),
            'lastImport' => $view->lastImport,
            'map' => $this->plates->picker($area, $view->rows, $posts),
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
     * WHAT ELSE THE MODULES PUBLISH ABOUT THIS GROUND — every figure but the
     * covered share, which the band states first and on its own.
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

            $facts[] = ['figure' => $figure, 'route' => $this->entryRoutes->entryRouteFor($figure->moduleSlug)];
        }

        return $facts;
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
