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
use Uhifadhi\Bundle\AreaBundle\Controller\StationConfigureController;
use Uhifadhi\Bundle\AreaBundle\Controller\StationEditController;
use Uhifadhi\Bundle\AreaBundle\Controller\StationRecordController;
use Uhifadhi\Bundle\AreaBundle\Controller\StationsController;
use Uhifadhi\Bundle\AreaBundle\Controller\ZoneConfigureController;
use Uhifadhi\Bundle\AreaBundle\Controller\ZoneController;
use Uhifadhi\Bundle\AreaBundle\Controller\ZoneEditController;
use Uhifadhi\Bundle\AreaBundle\Controller\ZoneImportController;
use Uhifadhi\Bundle\AreaBundle\Controller\ZoneRecordController;
use Uhifadhi\Bundle\AreaBundle\Repository\AreaOfInterestRepository;
use Uhifadhi\Bundle\AreaBundle\Repository\PostingRepository;
use Uhifadhi\Bundle\AreaBundle\Repository\StationEventRepository;
use Uhifadhi\Bundle\AreaBundle\Repository\StationRepository;
use Uhifadhi\Bundle\AreaBundle\Repository\ZoneEventRepository;
use Uhifadhi\Bundle\AreaBundle\Repository\ZoneRepository;
use Uhifadhi\Bundle\AreaBundle\Service\StationNoticeStore;
use Uhifadhi\Bundle\AreaBundle\Service\ZoneImportDraftStore;
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
            service(StationRepository::class),
            service('area.register'),
            service('area.overview'),
            service('area.map_payload'),
            service('area.map'),
            service('router'),
            service('area.preset_library'),
            // The surface's own catalogue — the area's cells and one
            // contributor per module switched on here.
            service('area.overview_catalogue'),
            service('area.composition'),
            service('registry.catalogue'),
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

    /*
     * THE ZONES TAB — how the area is divided, read: the set's figures, the
     * whole ground on one plate, and the stations and people those zones
     * account for. The picker is the sidebar, not a column of the page.
     */
    $services->set('area.controller.zone', ZoneController::class)
        ->args([
            service('twig'),
            service('area.zone_set'),
            service('area.station_register'),
            service(StationRepository::class),
            service(PostingRepository::class),
            service('area.posting_board'),
            service('area.register'),
            service('area.zone_plate'),
            service('area.zone_figures'),
            service('area.zone_list'),
            service('registry.area_modules'),
            service('registry.entry_routes'),
        ])
        ->tag('controller.service_arguments');
    $services->alias(ZoneController::class, 'area.controller.zone')->public();

    /*
     * ONE ZONE, READ — the same surface one step in. It reads only; the ring,
     * the name and the removal are the configure section's.
     */
    $services->set('area.controller.zone_record', ZoneRecordController::class)
        ->args([
            service('twig'),
            service('area.zone_set'),
            service(StationRepository::class),
            service(PostingRepository::class),
            service('area.posting_board'),
            service(ZoneEventRepository::class),
            service('area.zone_plate'),
            service('area.zone_figures'),
            service('registry.area_modules'),
            service('registry.entry_routes'),
        ])
        ->tag('controller.service_arguments');
    $services->alias(ZoneRecordController::class, 'area.controller.zone_record')->public();

    /*
     * THE ZONES SECTION OF THE AREA'S CONFIGURE PAGE — a screen with an address
     * of its own, because a section the shell renders is a template with no
     * request of its own and this one posts files, previews them and writes.
     *
     * THE TOKEN MANAGER IS ASKED FOR BY EVERY ONE OF THE THREE: the page mints
     * the tokens, the two writers check them. A screen that minted a token
     * nobody checked would be furniture.
     */
    /*
     * WHAT THE PERSON IS LOOKING AT BETWEEN THE UPLOAD AND THE CONFIRM. It
     * lives with the screens rather than the model because it is the request's:
     * a console importer has no session and needs none.
     */
    $services->set('area.zone_import_draft', ZoneImportDraftStore::class)
        ->args([service('request_stack')]);
    $services->alias(ZoneImportDraftStore::class, 'area.zone_import_draft');

    /*
     * ONE POST, READ. It only reads: the record is edited in the area's
     * configure page, so this controller has no write route at all.
     */
    $services->set('area.controller.station_record', StationRecordController::class)
        ->args([
            service('twig'),
            service(StationRepository::class),
            service(PostingRepository::class),
            service(StationEventRepository::class),
            service('area.posting_board'),
            service('area.zone_set'),
            service('area.zone_plate'),
            service('area.station_figures'),
            service('registry.area_modules'),
        ])
        ->tag('controller.service_arguments');
    $services->alias(StationRecordController::class, 'area.controller.station_record')->public();

    /*
     * EVERY STATION IN ONE AREA — the tab. It reads; the section beside it is
     * where a post is added, moved and staffed.
     */
    $services->set('area.controller.stations', StationsController::class)
        ->args([
            service('twig'),
            service('area.station_register'),
            service(StationRepository::class),
            service(PostingRepository::class),
            service('area.posting_board'),
            service('area.zone_set'),
            service(ZoneRepository::class),
            service('area.register'),
            service('area.zone_plate'),
            service('area.station_figures'),
            service('registry.area_modules'),
            service('registry.entry_routes'),
        ])
        ->tag('controller.service_arguments');
    $services->alias(StationsController::class, 'area.controller.stations')->public();

    /*
     * THE STATIONS SECTION — a screen for the reason Zones is one: it writes,
     * and it answers its writes with redirects, so it has an address of its own.
     */
    $services->set('area.controller.station_configure', StationConfigureController::class)
        ->args([
            service('twig'),
            service('area.station_register'),
            service(StationRepository::class),
            service(PostingRepository::class),
            service(StationEventRepository::class),
            service('area.posting_board'),
            service('area.person_directory'),
            service('area.stations'),
            service('area.zone_set'),
            service('area.zone_plate'),
            service('area.station_notices'),
            service('security.csrf.token_manager'),
        ])
        ->tag('controller.service_arguments');
    $services->alias(StationConfigureController::class, 'area.controller.station_configure')->public();

    $services->set('area.controller.station_edit', StationEditController::class)
        ->args([
            service('area.stations'),
            service('area.postings'),
            service('area.person_directory'),
            service('area.station_notices'),
            service('security.csrf.token_manager'),
            service('router'),
            service('security.token_storage')->nullOnInvalid(),
        ])
        ->tag('controller.service_arguments');
    $services->alias(StationEditController::class, 'area.controller.station_edit')->public();

    /*
     * WHAT A WRITE LEFT THE READER TO READ. Beside the screens rather than
     * with the model, exactly like the zone draft store: it is the request's,
     * and a console caller has no session and needs none.
     */
    $services->set('area.station_notices', StationNoticeStore::class)
        ->args([service('request_stack')]);
    $services->alias(StationNoticeStore::class, 'area.station_notices');

    $services->set('area.controller.zone_configure', ZoneConfigureController::class)
        ->args([
            service('twig'),
            service('area.zone_set'),
            service('area.zone_plate'),
            service(ZoneEventRepository::class),
            service('area.zone_import_draft'),
            service('area.zone_export'),
            service(ZoneRepository::class),
            service('area.zone_stations'),
            service('area.zone_figures'),
            service('registry.area_modules'),
            service('security.csrf.token_manager'),
        ])
        ->tag('controller.service_arguments');
    $services->alias(ZoneConfigureController::class, 'area.controller.zone_configure')->public();

    $services->set('area.controller.zone_import', ZoneImportController::class)
        ->args([
            service('area.zone_import'),
            service('area.zone_import_draft'),
            service('security.csrf.token_manager'),
            service('router'),
            service('security.token_storage')->nullOnInvalid(),
        ])
        ->tag('controller.service_arguments');
    $services->alias(ZoneImportController::class, 'area.controller.zone_import')->public();

    $services->set('area.controller.zone_edit', ZoneEditController::class)
        ->args([
            service('area.zones'),
            service('area.zone_import'),
            service('area.zone_import_draft'),
            service('security.csrf.token_manager'),
            service('router'),
            service('security.token_storage')->nullOnInvalid(),
        ])
        ->tag('controller.service_arguments');
    $services->alias(ZoneEditController::class, 'area.controller.zone_edit')->public();

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
                tagged_iterator('uhifadhi.area_sections'),
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
                // And for the fifth: the area's zones, hued by the same walk
                // over the same ordered set the plate and the key read.
                service('area.zone_set'),
                // And for a rung this bundle may not name: whatever another
                // bundle hangs under one of the area's screens.
                tagged_iterator('uhifadhi.area_nav_children'),
            ])
            ->tag('shell.nav_section');
        $services->alias(AreaNavigation::class, 'area.navigation');
    }
};
