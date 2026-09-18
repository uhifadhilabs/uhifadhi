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

use Uhifadhi\Bundle\AreaBundle\Repository\AreaOfInterestRepository;
use Uhifadhi\Bundle\AreaBundle\Repository\StationRepository;
use Uhifadhi\Bundle\AreaBundle\Repository\ZoneEventRepository;
use Uhifadhi\Bundle\AreaBundle\Repository\ZoneRepository;
use Uhifadhi\Bundle\AreaBundle\Service\AreaComposition;
use Uhifadhi\Bundle\AreaBundle\Service\AreaCreator;
use Uhifadhi\Bundle\AreaBundle\Service\AreaIdentity;
use Uhifadhi\Bundle\AreaBundle\Service\AreaMapPayload;
use Uhifadhi\Bundle\AreaBundle\Service\AreaMapService;
use Uhifadhi\Bundle\AreaBundle\Service\AreaOverview;
use Uhifadhi\Bundle\AreaBundle\Service\AreaPresetLibrary;
use Uhifadhi\Bundle\AreaBundle\Service\AreaRegister;
use Uhifadhi\Bundle\AreaBundle\Service\AreaThumbnailer;
use Uhifadhi\Bundle\AreaBundle\Service\BoundaryImport;
use Uhifadhi\Bundle\AreaBundle\Service\StationService;
use Uhifadhi\Bundle\AreaBundle\Service\ZoneEventService;
use Uhifadhi\Bundle\AreaBundle\Service\ZoneExportService;
use Uhifadhi\Bundle\AreaBundle\Service\ZoneFigureService;
use Uhifadhi\Bundle\AreaBundle\Service\ZoneImportService;
use Uhifadhi\Bundle\AreaBundle\Service\ZoneOverlapService;
use Uhifadhi\Bundle\AreaBundle\Service\ZonePlateService;
use Uhifadhi\Bundle\AreaBundle\Service\ZoneService;
use Uhifadhi\Bundle\AreaBundle\Service\ZoneSetService;
use Uhifadhi\Bundle\AreaBundle\Widget\AreaIndexWidgets;
use Uhifadhi\Bundle\AtlasBundle\Map\MapBuilderInterface;
use Uhifadhi\Bundle\RegistryBundle\Repository\AreaModuleRepository;
use Uhifadhi\Bundle\ShellBundle\Widget\Registry\WidgetSurfaceInterface;
use Uhifadhi\Contracts\Kpi\ZoneFigureProviderInterface;

/*
 * The bundle's static service wiring.
 *
 * PHP (not YAML) on purpose: a reusable bundle must not force symfony/yaml onto
 * an installation, and FQCN references stay refactor-safe and phpstan-checked.
 * Imported by AreaBundle::loadExtension().
 *
 * Everything below is defined EXPLICITLY — no autowire(), no autoconfigure() —
 * because this bundle is installed by other projects via Composer, which is what
 * Symfony calls a reusable bundle:
 *
 *   "Services should not use autowiring or autoconfiguration. Instead, all
 *    services should be defined explicitly."
 *   "If the bundle defines services, they must be prefixed with the bundle alias."
 *   — https://symfony.com/doc/current/bundles/best_practices.html
 */
return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    /*
     * The repository keeps its FQCN id — the one place the bundle-alias prefix
     * cannot be used: ServiceRepositoryCompilerPass keys its locator by SERVICE
     * ID over findTaggedServiceIds(), while ContainerRepositoryFactory looks a
     * repository up by CLASS NAME; tagged-id lookup never sees aliases.
     *
     * @see vendor/doctrine/doctrine-bundle/src/DependencyInjection/Compiler/ServiceRepositoryCompilerPass.php
     */
    $services->set(AreaOfInterestRepository::class)
        ->args([service('doctrine')])
        ->tag('doctrine.repository_service');

    $services->set(ZoneRepository::class)
        ->args([service('doctrine')])
        ->tag('doctrine.repository_service');

    $services->set(ZoneEventRepository::class)
        ->args([service('doctrine')])
        ->tag('doctrine.repository_service');

    $services->set(StationRepository::class)
        ->args([service('doctrine')])
        ->tag('doctrine.repository_service');

    /*
     * THE ONLY SUPPORTED WAY A ZONE GETS A GEOMETRY. The invariant sibling zones
     * are held to — never sharing interior — is not expressible as a column
     * constraint, so it lives here; anything that writes a zone around this
     * service writes an overlap nobody will notice until a point falls in two
     * zones at once.
     */
    /*
     * WHEN SHARED GROUND IS A SLIVER. A stateless rule with no collaborators,
     * so the same sentence decides for an import, for a redrawn ring and for
     * anything that writes a zone later.
     */
    $services->set('area.zone_overlaps', ZoneOverlapService::class);
    $services->alias(ZoneOverlapService::class, 'area.zone_overlaps');

    /*
     * THE ONLY SUPPORTED WAY A STATION GETS A POINT, and therefore a zone: the
     * zone is derived from the point and cached, and every write that can move
     * the answer comes through here. Beside the entity rather than with the
     * screens, because a fixture loader and a console importer place stations
     * too and neither has twig.
     */
    $services->set('area.stations', StationService::class)
        ->args([
            service('doctrine.orm.entity_manager'),
            service(StationRepository::class),
        ]);
    $services->alias(StationService::class, 'area.stations');

    /*
     * WHAT THE MODULES SAY ABOUT A ZONE — every tagged provider, asked once for
     * the whole set, and only where the area runs that module.
     *
     * THE TAG IS READ HERE AND NO MODULE IS NAMED. A module that reports by
     * zone tags a provider in its own extension; nothing in the core has to
     * learn its name for its figures to appear on every zone surface at once.
     */
    $services->set('area.zone_figures', ZoneFigureService::class)
        ->args([tagged_iterator(ZoneFigureProviderInterface::TAG)]);
    $services->alias(ZoneFigureService::class, 'area.zone_figures');

    $services->set('area.zones', ZoneService::class)
        ->args([
            service('doctrine.orm.entity_manager'),
            service(ZoneRepository::class),
            service('area.zone_overlaps'),
            service('area.stations'),
        ]);
    $services->alias(ZoneService::class, 'area.zones');

    /*
     * A WHOLE ZONING SCHEME, FROM ONE FILE. Beside the model rather than with
     * the screens, exactly like the boundary import: an installer, a fixture
     * loader or an API with no twig in it must be able to import a scheme too.
     * It writes through the zone service above, so a scheme is held to the same
     * invariant a hand-drawn zone is.
     */
    $services->set('area.zone_import', ZoneImportService::class)
        ->args([
            service('doctrine.orm.entity_manager'),
            service('area.zones'),
            service(ZoneRepository::class),
            service('area.zone_overlaps'),
        ]);
    $services->alias(ZoneImportService::class, 'area.zone_import');

    /*
     * THE SET, BACK OUT. Beside the importer for the reason the importer is
     * beside the entity: a console command that dumps an area's zones has no
     * twig either, and the export is the only copy of a set that will ever
     * exist — nothing keeps superseded geometry.
     */
    $services->set('area.zone_export', ZoneExportService::class)
        ->args([service(ZoneRepository::class)]);
    $services->alias(ZoneExportService::class, 'area.zone_export');

    /*
     * THE ZONE LOG, WRITTEN IN ONE VOCABULARY. Every screen and command that
     * changes a set hands its facts here and the sentence is composed once, so
     * two callers cannot log the same event in two different words.
     */
    $services->set('area.zone_events', ZoneEventService::class)
        ->args([service('doctrine.orm.entity_manager')]);
    $services->alias(ZoneEventService::class, 'area.zone_events');

    /*
     * WHAT THE CONFIGURE PAGE READS ABOUT A SET — the rows, the totals and the
     * plate, from one walk over one list, so the colour on a card, the colour
     * in the key and the colour of the ring are the same colour.
     */
    $services->set('area.zone_set', ZoneSetService::class)
        ->args([
            service('doctrine.orm.entity_manager'),
            service(ZoneRepository::class),
        ]);
    $services->alias(ZoneSetService::class, 'area.zone_set');

    /*
     * THE PLATE, WHICH NEEDS THE ATLAS — registered beside the set rather than
     * with the screens, because a console command that renders a map payload
     * is a thing an installation may want and a twig engine is not what it
     * would need for it.
     */
    $services->set('area.zone_plate', ZonePlateService::class)
        ->args([
            service(ZoneRepository::class),
            service(MapBuilderInterface::class),
        ]);
    $services->alias(ZonePlateService::class, 'area.zone_plate');

    /*
     * What the register knows about each area — the measurements PostGIS makes
     * and the live count the registry keeps, read once here rather than assembled in
     * a template.
     */
    $services->set('area.register', AreaRegister::class)
        ->args([
            service(AreaOfInterestRepository::class),
            service(AreaModuleRepository::class),
            service(AreaOverview::class),
            service(AreaThumbnailer::class),
        ]);
    $services->alias(AreaRegister::class, 'area.register');

    /*
     * THE OVERVIEW'S CONTRIBUTED CONTENT. The tag strings are written out rather
     * than read from the interfaces' own constants, because they are the platform's
     * published contract and a module bundle writes them the same way — see
     * tests/Unit/Overview/ContributionContractTest.php, which keeps the two
     * spellings equal.
     */
    /*
     * THE OPERATIONAL MAP'S BASE CONTENT — the area's own boundary and zones, as
     * the GeoJSON the browser plate draws. Kept apart from the overview's
     * contributed content above because it is the area's, not a module's:
     * the boundary is always drawn and the zones are the area's own polygons.
     */
    $services->set('area.map_payload', AreaMapPayload::class)
        ->args([service(ZoneRepository::class)]);
    $services->alias(AreaMapPayload::class, 'area.map_payload');

    /*
     * THE AREA'S PLATES. What the area states about its two maps — the overview's
     * and the register's — handed to the atlas to draw. The area writes no
     * JavaScript: this builds the map, and render_map() puts it on the page.
     */
    $services->set('area.map', AreaMapService::class)
        ->args([service(MapBuilderInterface::class)]);
    $services->alias(AreaMapService::class, 'area.map');

    /*
     * WHAT THE FIVE AREAS-INDEX LAYOUTS READ — every fact any of them draws,
     * gathered once at one clock, for the landing and for the library alike.
     * Reads the same overview contributions the register does, so it names no module's
     * content.
     */
    $services->set('area.preset_library', AreaPresetLibrary::class)
        ->args([
            service(AreaOverview::class),
            service(ZoneRepository::class),
            service(AreaRegister::class),
            service(AreaMapService::class),
            service('router'),
        ]);
    $services->alias(AreaPresetLibrary::class, 'area.preset_library');

    /*
     * THE /areas SURFACE'S CATALOGUE — the five layouts the landing ships, as the
     * widget framework's own declaration, so the adopted one is a stored
     * preference row the register and its library both read.
     *
     * TAGGED BY HAND: a reusable bundle wires its services explicitly, and a
     * surface that forgot the tag has a working dashboard and an unreachable
     * registry entry.
     */
    $services->set('area.widget_surface', AreaIndexWidgets::class)
        ->tag(WidgetSurfaceInterface::TAG);

    $services->set('area.overview', AreaOverview::class)
        ->args([
            tagged_iterator('uhifadhi.overview.now_tile'),
            tagged_iterator('uhifadhi.overview.attention'),
            tagged_iterator('uhifadhi.map.layer'),
            tagged_iterator('uhifadhi.overview.pulse'),
            service(AreaModuleRepository::class),
        ]);
    $services->alias(AreaOverview::class, 'area.overview');

    /*
     * THE REGISTER CARD'S FACE — the area's boundary, simplified in the database
     * and projected into the card's viewBox. The satellite raster the design's
     * card face also asks for is deferred; see AreaThumbnailer's docblock.
     */
    $services->set('area.thumbnailer', AreaThumbnailer::class)
        ->args([service(AreaOfInterestRepository::class)]);
    $services->alias(AreaThumbnailer::class, 'area.thumbnailer');

    /*
     * AN AREA, CREATED FROM ITS IDENTITY. Beside the entity rather than with the
     * screens for the same reason as the import: a console importer or a fixture
     * loader with no twig must be able to make an area too.
     */
    $services->set('area.creator', AreaCreator::class)
        ->args([service('doctrine.orm.entity_manager')]);
    $services->alias(AreaCreator::class, 'area.creator');

    /*
     * AN AREA'S IDENTITY, EDITED IN PLACE. The sibling of the creator, and beside
     * the entity for the same reason: editing an area's name or gazetted facts is
     * a model concern a console command or a fixture loader with no twig would
     * want too. The boundary is not its business — that is BoundaryImport's.
     */
    $services->set('area.identity', AreaIdentity::class)
        ->args([service('doctrine.orm.entity_manager')]);
    $services->alias(AreaIdentity::class, 'area.identity');

    /*
     * THE ONLY SUPPORTED WAY AN AREA GETS A BOUNDARY FROM OUTSIDE — added or
     * replaced onto an area that already exists. Registered beside the entity
     * rather than with the screens, because it is not a screen's: a console
     * importer or a fixture loader in an installation with no twig must be able
     * to ask for it too.
     */
    $services->set('area.boundary_import', BoundaryImport::class)
        ->args([service('doctrine.orm.entity_manager')]);
    $services->alias(BoundaryImport::class, 'area.boundary_import');

    /*
     * AN AREA'S COMPOSITION, READ FROM THE REGISTRY'S OWN SERVICES. The three ids
     * asked for here are PRIVATE in the registry and that is fine — private means
     * "not fetchable from the container at runtime", never "not injectable". The
     * registry publishes no aliases deliberately, so a consumer names the ids;
     * they are its published surface either way.
     *
     * REGISTERED HERE RATHER THAN WITH THE SCREENS because the reading is not a
     * screen's: an installation that carries the model and mounts no page reads
     * its own composition too, and has no twig.
     */
    $services->set('area.composition', AreaComposition::class)
        ->args([
            service('registry.catalogue'),
            service('registry.area_modules'),
            service('registry.entry_routes'),
            service('router'),
        ]);
    $services->alias(AreaComposition::class, 'area.composition');
};
