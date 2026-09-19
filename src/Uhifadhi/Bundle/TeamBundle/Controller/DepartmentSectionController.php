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

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Twig\Environment;
use Uhifadhi\Bundle\RegistryBundle\Entity\Module;
use Uhifadhi\Bundle\RegistryBundle\Service\ModuleCatalogue;
use Uhifadhi\Bundle\TeamBundle\Entity\Department;
use Uhifadhi\Bundle\TeamBundle\Enum\PermissionEnum;
use Uhifadhi\Bundle\TeamBundle\Repository\DepartmentRepository;
use Uhifadhi\Bundle\TeamBundle\Service\DepartmentSectionOverview;

/**
 * THE DEPARTMENTS SECTION'S TWO READING SCREENS — its Overview and its Modules
 * matrix.
 *
 * NEITHER OF THEM WRITES ANYTHING. The register creates and scopes a
 * department and the department's own card attaches a module; these two read
 * what those wrote. That is why the matrix has no checkbox in it and the
 * overview has no control at all: a figure you can change from the page that
 * reports it is a page nobody can trust as a report.
 *
 * THEY ARE SEPARATE FROM THE REGISTER'S CONTROLLER on purpose. The register is
 * the section's write surface — create, rename, scope, deactivate, attach — and
 * it is already long; a reading screen that shares its constructor would drag
 * eight write collaborators into a page that needs three.
 */
final readonly class DepartmentSectionController
{
    /** The section's first tab, and what its `Configure` returns you to. */
    public const string OVERVIEW = 'team_departments_overview';

    /** Which department reads which module — one row a department, one column a module. */
    public const string MODULES = 'team_departments_modules';

    public function __construct(
        private Environment $twig,
        private DepartmentSectionOverview $overview,
        private DepartmentRepository $departments,
        private ModuleCatalogue $catalogue,
    ) {
    }

    /**
     * SCOPED, STAFFED AND ATTACHED — the section read from the organisation
     * inwards. Every figure here exists on the register, in Team or on
     * Performance already; this page owns none of them.
     */
    #[Route('/departments/overview', name: self::OVERVIEW, defaults: DepartmentController::SURFACE, methods: ['GET'])]
    #[IsGranted(PermissionEnum::TeamManage->value)]
    public function overview(): Response
    {
        return new Response($this->twig->render('@Team/departments/overview.html.twig', $this->overview->read()));
    }

    /**
     * THE ATTACHMENT MATRIX — nine rows by however many modules are installed.
     *
     * ABSENCE IS DRAWN, NOT LEFT BLANK. A cell a reader cannot tell apart from
     * a rendering failure is not an answer, so a department that does not read
     * a module gets a dashed ring rather than an empty box; and the row and
     * column totals are here so the two readings of the same grid — what a
     * department reads, who reads a module — are both one glance.
     */
    #[Route('/departments/modules', name: self::MODULES, defaults: DepartmentController::SURFACE, methods: ['GET'])]
    #[IsGranted(PermissionEnum::TeamManage->value)]
    public function modules(): Response
    {
        $modules = $this->catalogue->all();
        $departments = $this->departments->findAllActiveOrdered();

        $rows = [];
        $perModule = array_fill_keys(array_map(self::slugOf(...), $modules), 0);

        foreach ($departments as $department) {
            $cells = [];
            $attached = 0;
            foreach ($modules as $module) {
                $reads = $department->hasModule($module);
                $cells[] = ['module' => $module, 'reads' => $reads];
                if ($reads) {
                    ++$attached;
                    ++$perModule[self::slugOf($module)];
                }
            }

            $rows[] = [
                'department' => $department,
                'scope' => $department->isOrgLevel() ? 'org-wide' : (string) $department->getArea()?->getName(),
                'cells' => $cells,
                'attached' => $attached,
            ];
        }

        return new Response($this->twig->render('@Team/departments/modules.html.twig', [
            'modules' => $modules,
            'rows' => $rows,
            'perModule' => $perModule,
            'attachments' => array_sum($perModule),
            'unread' => array_values(array_filter($modules, static fn (Module $module): bool => 0 === $perModule[self::slugOf($module)])),
            'readsNothing' => array_values(array_filter(
                $departments,
                static fn (Department $department): bool => 0 === $department->getModules()->count(),
            )),
        ]));
    }

    /**
     * A MODULE'S SLUG, AND NEVER AN EMPTY KEY. The entity's accessor is
     * nullable because Doctrine hydrates before it assigns; a catalogue entry
     * with no slug is not a module anybody installed, and keying a column by
     * "" would silently merge two of them into one.
     */
    private static function slugOf(Module $module): string
    {
        return $module->getSlug() ?? '';
    }
}
