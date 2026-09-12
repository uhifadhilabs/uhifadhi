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

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Uhifadhi\Bundle\AreaBundle\Controller\AreaController;
use Uhifadhi\Bundle\AreaBundle\Controller\AreaCreateController;
use Uhifadhi\Bundle\AreaBundle\Controller\AreaEditController;
use Uhifadhi\Bundle\AreaBundle\Controller\AreaModulesController;
use Uhifadhi\Bundle\AreaBundle\Controller\AreaWidgetsController;
use Uhifadhi\Bundle\AreaBundle\Controller\ZoneController;
use Uhifadhi\Bundle\AreaBundle\Repository\AreaOfInterestRepository;
use Uhifadhi\Bundle\AreaBundle\Repository\ZoneRepository;
use Uhifadhi\Bundle\AreaBundle\Shell\AreaConfigurationSections;
use Uhifadhi\Bundle\AreaBundle\Shell\AreaNavigation;
use Uhifadhi\Bundle\AreaBundle\Shell\AreaShellSource;
use Uhifadhi\Bundle\ShellBundle\Contract\AreaShellSourceInterface;
use Uhifadhi\Bundle\ShellBundle\Contract\NavigationSourceInterface;
use Uhifadhi\Bundle\ShellBundle\Model\ModuleGroup;
use Uhifadhi\Contracts\Shell\ConfigurationSectionsInterface;

/*
 * THE SCREENS, AND THE CONTRACTS THAT FRAME THEM — imported ONLY where an
 * installation can actually render a page.
 *
 * SEPARATE FROM services.php BECAUSE THE ENTITY MUST OUTLIVE THE SCREENS. This
 * module is two things at once: a model an installation persists, and a set of
 * pages. The first has no opinion about twig, and an installation that wants
 * only the area entity — a console-only importer, a test kernel, an API — must
 * still boot. Registering a controller that depends on `twig` in such an
 * installation fails at COMPILE time with "has a dependency on a non-existent
 * service", which is a long way from the paragraph anybody would think to read.
 *
 * So AreaBundle::loadExtension() imports this file only when the
 * application has both twig to render in and security to be gated by. See there
 * for the guard itself.
 */
return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    /*
     * THE SCREENS. Plain classes with their collaborators handed to them — a
     * reusable bundle's controllers extend nothing, so they are ordinary
     * services and carry the tag that lets the router pass route arguments.
     */
    $services->set('area.controller.area', AreaController::class)
        ->args([
            service('twig'),
            service(ZoneRepository::class),
            service('area.register'),
            service('area.overview'),
            service('area.map_payload'),
            service('area.map'),
            service('router'),
            service('area.preset_library'),
            service('shell.widget.service'),
            service('security.token_storage'),
        ])
        ->tag('controller.service_arguments');
    $services->alias(AreaController::class, 'area.controller.area')->public();

    /*
     * THE AREAS-INDEX WIDGET LIBRARY. Reads what the five layouts draw from the
     * same service the landing does, and takes its one write — adopt a layout —
     * through the shell's widget endpoint, so the landing reads back exactly what
     * was adopted here.
     */
    $services->set('area.controller.widgets', AreaWidgetsController::class)
        ->args([
            service('twig'),
            service('area.preset_library'),
            service('shell.widget.service'),
            service('shell.widget.endpoint'),
            service('router'),
            service('security.token_storage'),
        ])
        ->tag('controller.service_arguments');
    $services->alias(AreaWidgetsController::class, 'area.controller.widgets')->public();

    $services->set('area.controller.create', AreaCreateController::class)
        ->args([
            service('twig'),
            service('area.creator'),
            service('area.boundary_import'),
            service('security.csrf.token_manager'),
            service('router'),
        ])
        ->tag('controller.service_arguments');
    $services->alias(AreaCreateController::class, 'area.controller.create')->public();

    /*
     * THE EDIT SCREEN — an area's identity edited in place, and its boundary
     * replaced through the import pipeline with a guard in front of it. Its two
     * writes lean on the model services (identity, boundary import) and the
     * read collaborators the map preview and the record rows need.
     */
    $services->set('area.controller.edit', AreaEditController::class)
        ->args([
            service('twig'),
            service('area.identity'),
            service('area.boundary_import'),
            service('area.map_payload'),
            service('area.map'),
            service('area.register'),
            service(ZoneRepository::class),
            service('security.csrf.token_manager'),
            service('router'),
        ])
        ->tag('controller.service_arguments');
    $services->alias(AreaEditController::class, 'area.controller.edit')->public();

    $services->set('area.controller.zone', ZoneController::class)
        ->args([
            service('twig'),
            service(ZoneRepository::class),
        ])
        ->tag('controller.service_arguments');
    $services->alias(ZoneController::class, 'area.controller.zone')->public();

    /*
     * THE MODULE SCREENS NEED THE SHELL'S PICTURE, so they are registered only
     * where the shell is installed — the grid renders through
     * `@Shell/_module_grid.html.twig` and the service that feeds it
     * builds the shell's own ModuleGroup. Without the shell the route is simply
     * not mounted, which is the case AreaShellSource has always tolerated: no
     * route, no Modules tab, and the patrol module's crumb goes back to plain
     * text. That is a shorter strip, never a broken page.
     */
    if (class_exists(ModuleGroup::class)) {
        $services->set('area.controller.modules', AreaModulesController::class)
            ->args([
                service('twig'),
                service('area.composition'),
                service('registry.area_modules'),
                service('security.csrf.token_manager'),
                service('router'),
            ])
            ->tag('controller.service_arguments');
        $services->alias(AreaModulesController::class, 'area.controller.modules')->public();
    }

    /*
     * THE SHELL CONTRACTS, AND THEY ARE GUARDED. The shell is a `suggest`, not a
     * `require`: these screens render in its frame where an installation has one
     * and render unframed where it does not, so this file has to be readable in
     * an installation that has no shell at all.
     *
     * THE TAG AND ALIAS STRINGS ARE WRITTEN OUT rather than read from the
     * shell's own constants, for the same reason: reading
     * ShellBundle::NAV_TAG would load a class that need not be there.
     */
    if (interface_exists(AreaShellSourceInterface::class)) {
        $services->set('area.shell_source', AreaShellSource::class)
            ->args([
                service('request_stack'),
                service('router'),
                service(AreaOfInterestRepository::class),
                service('security.authorization_checker'),
            ]);
        $services->alias(AreaShellSource::class, 'area.shell_source');

        /*
         * AN ALIAS, NOT A TAG — two things claiming to know where you are is
         * exactly the disagreement the shell's contract exists to prevent. An
         * installation whose areas are its own model overrides this by aliasing
         * the same id to its own class.
         */
        $services->alias('shell.area_shell_source', 'area.shell_source');
    }

    /*
     * WHAT IS ON THE AREA'S CONFIGURE PAGE. The area declares its sections
     * through exactly the contract a module declares its own through — one
     * frame, one page, one button — so the rule has no exception on the day it
     * ships.
     *
     * GUARDED like the two above: the shell is a `suggest`, and an installation
     * without one has the area model and no configure page.
     */
    if (interface_exists(ConfigurationSectionsInterface::class)) {
        $services->set('area.configuration_sections', AreaConfigurationSections::class)
            ->args([
                service('request_stack'),
                service(AreaOfInterestRepository::class),
                service('area.register'),
                service(ZoneRepository::class),
            ])
            ->tag('uhifadhi.configuration_sections');
        $services->alias(AreaConfigurationSections::class, 'area.configuration_sections');
    }

    if (interface_exists(NavigationSourceInterface::class)) {
        $services->set('area.navigation', AreaNavigation::class)
            ->args([
                service('router'),
                service('security.token_storage'),
                service('security.authorization_checker'),
                service('request_stack'),
                service(AreaOfInterestRepository::class),
                service('area.shell_source'),
                service('area.composition'),
                // The module frame, for the tree's fourth level: a module's own
                // data places, read from the SAME declaration the strip under
                // its head is drawn from.
                service('shell.frame'),
            ])
            ->tag('shell.nav_section');
        $services->alias(AreaNavigation::class, 'area.navigation');
    }
};
