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

namespace Uhifadhi\Bundle\TeamBundle\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Twig\Environment;
use Uhifadhi\Bundle\RegistryBundle\Service\ModuleCatalogue;
use Uhifadhi\Bundle\TeamBundle\Entity\Department;
use Uhifadhi\Bundle\TeamBundle\Enum\PermissionEnum;
use Uhifadhi\Bundle\TeamBundle\Model\DepartmentQuery;
use Uhifadhi\Bundle\TeamBundle\Repository\DepartmentRepository;
use Uhifadhi\Bundle\TeamBundle\Repository\PositionRepository;
use Uhifadhi\Bundle\TeamBundle\Repository\UserRepository;
use Uhifadhi\Bundle\TeamBundle\Service\DepartmentPalette;
use Uhifadhi\Bundle\TeamBundle\Service\DepartmentPerformance;
use Uhifadhi\Contracts\Entity\AreaInterface;

/**
 * ONE AREA'S DEPARTMENTS — the tab that reads them, and the section that
 * changes them.
 *
 * WHY THESE ROUTES ARE TEAM'S. A department already knows which area it
 * belongs to: `Department::$area` is the published {@see AreaInterface},
 * resolved by the installation. So the bundle that owns departments can
 * answer "the ones that read this area" without the area bundle learning
 * what a department is, and the area's tab strip picks the route up the way
 * it picks up any other — by name, tolerantly, so an installation without
 * this bundle simply has a shorter strip. No new seam buys anything here.
 *
 * THE TAB READS AND THE SECTION WRITES, which is the same split every area
 * surface makes. The tab's cards are the organisation register's cards —
 * the same partial — because two registers of one thing are two registers
 * that drift.
 *
 * THE AREA'S OWN COME FIRST, then the org-wide ones it inherits. On the
 * organisation's register the order is the other way about, and both are
 * read outwards from where the reader is standing: there, from the
 * organisation into its areas; here, from this place into what it shares.
 */
final readonly class AreaDepartmentController
{
    public const string TAB = 'area_departments';
    public const string SECTION = 'area_departments_configure';

    public function __construct(
        private Environment $twig,
        private DepartmentRepository $departments,
        private PositionRepository $positions,
        private UserRepository $users,
        private EntityManagerInterface $entityManager,
        private DepartmentPerformance $performance,
        private CsrfTokenManagerInterface $csrf,
        private RouterInterface $router,
        private ModuleCatalogue $catalogue,
        private DepartmentPalette $palette,
    ) {
    }

    #[Route('/areas/{uuid}/departments', name: self::TAB, requirements: ['uuid' => Requirement::UUID], methods: ['GET'])]
    #[IsGranted(PermissionEnum::AreaView->value)]
    public function tab(Request $request, string $uuid): Response
    {
        $area = $this->areaByUuid($uuid);
        $query = DepartmentQuery::from($request);

        $own = $query->matching($this->departments->findForArea($area));
        $inherited = $query->matching($this->departments->findOrgLevelOrdered());
        $reading = $this->reading([...$own, ...$inherited]);

        return new Response($this->twig->render('@Team/departments/area_tab.html.twig', [
            'area' => $area,
            'query' => $query,
            'groups' => [
                [
                    'key' => 'own',
                    'label' => \sprintf('%s’s own', $area->getName()),
                    'note' => 'Area-level · this area only',
                    'departments' => $own,
                ],
                [
                    'key' => 'org',
                    'label' => 'Org-wide · read this area too',
                    'note' => 'Belong to the organisation · every area reads them',
                    'departments' => $inherited,
                ],
            ],
            'homeHref' => $this->tolerant('area_index'),
            'areaHref' => $this->tolerant('area_show', ['uuid' => $area->getUuidString()]),
            /* Which card is open is a place, so it is a query a link carries. */
            'openDepartment' => '' === trim($request->query->getString('open')) ? null : trim($request->query->getString('open')),
            'ownCount' => \count($this->departments->findForArea($area)),
            'orgCount' => \count($this->departments->findOrgLevelOrdered()),
            ...$reading,
            // THE BAND'S SECOND FACT IS ABOUT THIS AREA'S OWN DEPARTMENTS.
            // An org-wide department's positions belong to the
            // organisation, and counting them here would make every area
            // report the same number as its own.
            ...self::staffing($own, $reading['owned'], $reading['holders']),
        ]));
    }

    /**
     * THE DEPARTMENTS SECTION OF THE AREA'S CONFIGURE PAGE — the only place
     * this area's departments are added and edited.
     *
     * READING IS GATED ON `area.view`, as every area surface is; each WRITE
     * is the register's own route and carries the register's own gate, so
     * nothing is permitted here that is not permitted there.
     */
    #[Route('/areas/{uuid}/departments/settings', name: self::SECTION, requirements: ['uuid' => Requirement::UUID], methods: ['GET'])]
    #[IsGranted(PermissionEnum::AreaView->value)]
    public function configure(string $uuid): Response
    {
        $area = $this->areaByUuid($uuid);
        $own = $this->departments->findForArea($area);
        $inherited = $this->departments->findOrgLevelOrdered();

        return new Response($this->twig->render('@Team/departments/area_configure.html.twig', [
            'area' => $area,
            'own' => $own,
            'inherited' => $inherited,
            'attachable' => $this->catalogue->all(),
            'homeHref' => $this->tolerant('area_index'),
            'areaHref' => $this->tolerant('area_show', ['uuid' => $area->getUuidString()]),
            'csrfToken' => $this->csrf->getToken(DepartmentController::CSRF_ID)->getValue(),
            ...$this->reading([...$own, ...$inherited]),
        ]));
    }

    /**
     * EVERYTHING THE CARDS READ, for one page — the positions filed under
     * each department, who holds them and what the modules publish.
     *
     * @param list<Department> $departments
     *
     * @return array{owned: array<string, list<\Uhifadhi\Bundle\TeamBundle\Entity\Position>>, headcount: array<string, int>, holders: array<string, int>, figures: array<string, list<\Uhifadhi\Contracts\Kpi\DepartmentKpi>>, marks: array<string, string>}
     */
    private function reading(array $departments): array
    {
        $owned = [];
        foreach ($this->positions->findAllOrdered() as $position) {
            $department = $position->getDepartment();
            if (null !== $department) {
                $owned[$department->getUuidString() ?? ''][] = $position;
            }
        }

        $holders = [];
        foreach ($this->positions->findAllOrdered() as $position) {
            $holders[$position->getUuidString() ?? ''] = $this->users->countActiveHoldingAnyPosition([$position]);
        }

        $headcount = [];
        $figures = [];
        $marks = [];
        foreach ($departments as $department) {
            $key = $department->getUuidString() ?? '';
            $mine = $owned[$key] ?? [];
            $headcount[$key] = $this->users->countActiveHoldingAnyPosition($mine);
            $figures[$key] = $this->performance->kpisFor($department);
            $marks[$key] = self::mark((string) $department->getName());
        }

        return [
            'owned' => $owned,
            'headcount' => $headcount,
            'holders' => $holders,
            'figures' => $figures,
            'marks' => $marks,
            // A DEPARTMENT NAMES A CATEGORY AND NEVER A COLOUR, and it is the
            // same category on every surface that marks one.
            'cats' => $this->palette->indexes(),
        ];
    }

    /**
     * HOW MANY POSITIONS THESE DEPARTMENTS OWN, AND HOW MANY ARE FILLED.
     *
     * BOTH HALVES COUNT POSITIONS, which is the only way "M of N filled" can
     * be read. Summing each department's HEADCOUNT against a count of
     * positions compared two different things and printed "6 of 5 filled" —
     * a person may hold positions in two departments, and a position may be
     * held by several people. A position is filled when somebody holds it.
     *
     * @param list<Department>                                                 $departments
     * @param array<string, int>                                               $holders     position uuid to how many hold it
     * @param array<string, list<\Uhifadhi\Bundle\TeamBundle\Entity\Position>> $owned
     *
     * @return array{positionCount: int, filled: int}
     */
    private static function staffing(array $departments, array $owned, array $holders): array
    {
        $positions = 0;
        $filled = 0;
        foreach ($departments as $department) {
            foreach ($owned[$department->getUuidString() ?? ''] ?? [] as $position) {
                ++$positions;
                if (($holders[$position->getUuidString() ?? ''] ?? 0) > 0) {
                    ++$filled;
                }
            }
        }

        return ['positionCount' => $positions, 'filled' => $filled];
    }

    /**
     * A LINK ONLY IF THE INSTALLATION HAS THAT ROUTE. Areas come from
     * another bundle, which an installation may not have mounted; a crumb
     * is not worth a 500, so it degrades to plain text.
     *
     * @param array<string, string|null> $parameters
     */
    private function tolerant(string $name, array $parameters = []): ?string
    {
        if (null === $this->router->getRouteCollection()->get($name)) {
            return null;
        }

        return $this->router->generate($name, array_filter($parameters, static fn (?string $v): bool => null !== $v));
    }

    /** The two letters a card wears, from the name nobody typed them for. */
    private static function mark(string $name): string
    {
        $words = array_values(array_filter(preg_split('/\s+/', trim($name)) ?: []));
        if ([] === $words) {
            return '—';
        }

        return mb_strtoupper(1 === \count($words)
            ? mb_substr($words[0], 0, 2)
            : mb_substr($words[0], 0, 1).mb_substr((string) end($words), 0, 1));
    }

    /**
     * THE AREA, THROUGH THE PUBLISHED CONTRACT. This bundle never names the
     * class that holds areas: it reads the association it already declares
     * on {@see Department::$area}, which is whatever the installation
     * resolved the contract to.
     */
    private function areaByUuid(string $uuid): AreaInterface
    {
        $class = $this->entityManager->getClassMetadata(Department::class)->getAssociationTargetClass('area');

        /** @var AreaInterface|null $area */
        $area = $this->entityManager->getRepository($class)->findOneBy(['uuid' => $uuid]);

        if (null === $area) {
            throw new NotFoundHttpException('No area of this installation has that identifier.');
        }

        return $area;
    }
}
