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
use Uhifadhi\Bundle\AreaBundle\Model\StationRegister;
use Uhifadhi\Bundle\AreaBundle\Repository\PostingRepository;
use Uhifadhi\Bundle\AreaBundle\Repository\StationRepository;
use Uhifadhi\Bundle\AreaBundle\Repository\ZoneRepository;
use Uhifadhi\Bundle\AreaBundle\Service\AreaPlateService;
use Uhifadhi\Bundle\AreaBundle\Service\AreaRegister;
use Uhifadhi\Bundle\AreaBundle\Service\PostingBoardService;
use Uhifadhi\Bundle\AreaBundle\Service\StationFigureService;
use Uhifadhi\Bundle\AreaBundle\Service\StationRegisterService;
use Uhifadhi\Bundle\AreaBundle\Service\ZoneSetService;
use Uhifadhi\Bundle\AtlasBundle\Calendar\Periods;
use Uhifadhi\Bundle\RegistryBundle\Service\AreaModuleService;
use Uhifadhi\Bundle\RegistryBundle\Service\ModuleEntryRouteResolver;
use Uhifadhi\Contracts\Kpi\StationRef;

/**
 * EVERY STATION IN ONE AREA — where each one stands and who is posted to it.
 *
 * A TAB, NOT A CONFIGURE SCREEN. It reads: adding, moving and staffing a post
 * is the Stations section's job, and the one control that changes anything
 * here is the Configure action the frame draws itself.
 *
 * ONE FLAT TABLE, FOUND BY FILTER. There is no zone-grouped twin: grouping by
 * zone makes the zone the only way in, when the way an operator arrives is a
 * name or a code. The zone is a filter here, and a column.
 *
 * FIVE FIGURES OR NONE. Three of them are the area's own — the posts, the
 * people and the leads — and two come from whatever modules publish about a
 * station. A module that publishes nothing leaves its card stating that, in
 * the house's own words, rather than leaving a row of three where the design
 * has five.
 */
final readonly class StationsController
{
    public const string ROUTE = 'area_stations';

    /** Which page of the people list is shown — the table has its own. */
    public const string PEOPLE_PAGE = 'ppage';

    /** How many cards of the five the modules may fill. */
    private const int MODULE_CARDS = 2;

    public function __construct(
        private Environment $twig,
        private StationRegisterService $register,
        private StationRepository $stations,
        private PostingRepository $postings,
        private PostingBoardService $board,
        private ZoneSetService $set,
        private ZoneRepository $zones,
        private AreaRegister $areas,
        private AreaPlateService $plates,
        private StationFigureService $figures,
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

    #[Route('/areas/{uuid}/stations', name: self::ROUTE, requirements: ['uuid' => Requirement::UUID], methods: ['GET'])]
    #[IsGranted('area.view')]
    public function index(
        Request $request,
        #[MapEntity(mapping: ['uuid' => 'uuid'])] AreaOfInterest $area,
    ): Response {
        $query = StationConfigureController::queryFrom($request);
        $register = $this->register->register($area, $query);
        $view = $this->set->view($area);

        $cats = [];
        foreach ($view->rows as $zone) {
            $cats[$zone->name] = $zone->cat;
        }

        $posts = [];
        $leaders = 0;
        $staffed = 0;
        $refs = [];
        foreach ($this->stations->findByArea($area) as $post) {
            $posted = $this->postings->countStandingByStation($post);
            $zone = $post->getZone()?->getName();

            $posts[] = [
                'uuid' => (string) $post->getUuidString(),
                'name' => (string) $post->getName(),
                'point' => $post->getPoint(),
                'posted' => $posted,
                'here' => false,
                'zone' => $zone,
                'cat' => null === $zone ? null : ($cats[$zone] ?? null),
            ];
            $refs[] = new StationRef((string) $post->getUuidString(), (string) $area->getUuidString(), (string) $post->getName());

            if ($posted > 0) {
                ++$staffed;
            }

            if (null !== $this->postings->findLeaderAt($post)) {
                ++$leaders;
            }
        }

        // THE PEOPLE LIST IS THE WHOLE AREA'S, bounded by a page like the
        // table above it: a card that grew with the payroll would own the page.
        $people = $this->board->board($this->postings->findStandingByArea($area), new PostingQuery())['rows'];
        $pages = max(1, (int) ceil(\count($people) / StationRegister::PER_PAGE));
        $page = max(1, min($request->query->getInt(self::PEOPLE_PAGE, 1), $pages));

        $names = [];
        foreach ($posts as $post) {
            $names[$post['uuid']] = $post['name'];
        }

        return new Response($this->twig->render('@Area/station/index.html.twig', [
            'area' => $area,
            'areaKm2' => $this->areas->areaKm2($area),
            'zoneCount' => $this->zones->countFor($area),
            'register' => $register,
            'query' => $query,
            'stationCount' => \count($posts),
            'staffed' => $staffed,
            'leaders' => $leaders,
            'peopleTotal' => \count($people),
            'people' => \array_slice($people, ($page - 1) * StationRegister::PER_PAGE, StationRegister::PER_PAGE),
            'peoplePage' => $page,
            'peoplePages' => $pages,
            'stationNames' => $names,
            'modules' => $this->moduleCards($area, $refs),
            'moduleCards' => self::MODULE_CARDS,
            'map' => $this->plates->stationsPlate($area, $view->rows, $posts),
        ]));
    }

    /**
     * WHAT THE MODULES PUBLISH ABOUT THIS AREA'S POSTS, added up — one card
     * per module, in module order, capped at the two the design has room for.
     *
     * NO MODULE IS NAMED HERE. A module that reports by station tags a
     * provider; this reads the tag through the seam and prints whatever comes
     * back, so the two cards are roster's and patrols' in one installation
     * and somebody else's in another.
     *
     * @param list<StationRef> $refs
     *
     * @return list<array{label: string, value: float, module: string, caption: string, route: string|null}>
     */
    private function moduleCards(AreaOfInterest $area, array $refs): array
    {
        if ([] === $refs) {
            return [];
        }

        $figures = $this->figures->collect(
            $refs,
            $this->periods->month(),
            fn (string $slug): bool => $this->areaModules->isActive($area, $slug),
        );

        /** @var array<string, array{label: string, value: float, module: string, caption: string, route: string|null}> $cards */
        $cards = [];
        foreach ($refs as $ref) {
            foreach ($figures->dockFor($ref->stationUuid) as $figure) {
                if (null === $figure->value) {
                    continue;
                }

                $cards[$figure->moduleSlug] ??= [
                    'label' => $figure->label,
                    'value' => 0.0,
                    'module' => $figure->moduleName,
                    'caption' => $figures->period->label,
                    // WHERE THE MODULE ITSELF READS THIS, resolved live from
                    // the provider: a module that is not installed takes its
                    // route with it and the link simply is not offered.
                    'route' => $this->entryRoutes->entryRouteFor($figure->moduleSlug),
                ];
                $cards[$figure->moduleSlug]['value'] += $figure->value;
            }
        }

        return \array_slice(array_values($cards), 0, self::MODULE_CARDS);
    }
}
