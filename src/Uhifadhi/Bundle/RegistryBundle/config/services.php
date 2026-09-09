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

use Psr\Container\ContainerInterface;
use Uhifadhi\Bundle\RegistryBundle\CacheWarmer\RegistrySyncWarmer;
use Uhifadhi\Bundle\RegistryBundle\EventListener\ParkedModuleListener;
use Uhifadhi\Bundle\RegistryBundle\RegistryBundle;
use Uhifadhi\Bundle\RegistryBundle\Repository\AreaModuleRepository;
use Uhifadhi\Bundle\RegistryBundle\Repository\ModuleRepository;
use Uhifadhi\Bundle\RegistryBundle\Service\AreaModuleLedger;
use Uhifadhi\Bundle\RegistryBundle\Service\AreaModuleService;
use Uhifadhi\Bundle\RegistryBundle\Service\ModuleCatalogue;
use Uhifadhi\Bundle\RegistryBundle\Service\ModuleEntryRouteResolver;
use Uhifadhi\Bundle\RegistryBundle\Service\ModulePermissionCatalogue;
use Uhifadhi\Bundle\RegistryBundle\Service\ModuleRouteGate;
use Uhifadhi\Bundle\RegistryBundle\Service\ProviderCatalogueMapper;
use Uhifadhi\Bundle\RegistryBundle\Service\RegistrySyncService;

/*
 * The bundle's static service wiring.
 *
 * PHP (not YAML) on purpose: a reusable bundle must not force symfony/yaml onto
 * hosts, and FQCN references stay refactor-safe and phpstan-checked. Imported by
 * RegistryBundle::loadExtension(), which keeps only the config-DRIVEN
 * definitions.
 *
 * Everything below is defined EXPLICITLY — no autowire(), no autoconfigure(),
 * and ids prefixed with the bundle alias — because this bundle is installed by
 * other projects via Composer, which is what Symfony calls a reusable bundle:
 *
 *   "Services should not use autowiring or autoconfiguration. Instead, all
 *    services should be defined explicitly."
 *   "If the bundle defines services, they must be prefixed with the bundle alias."
 *   — https://symfony.com/doc/current/bundles/best_practices.html
 *
 * The ids are the published surface. They are private, as a reusable bundle's
 * should be; a host that wants one aliases it, and the specification suite does
 * exactly that.
 *
 *   registry.catalogue             what modules this deployment has
 *   registry.provider_mapper       provider -> catalogue row (category coercion)
 *   registry.area_modules          per-area install state: install, uninstall, order
 *   registry.area_module_ledger    what an area has and what it does not
 *   registry.entry_routes          where a module's tile links
 *   registry.module_route_gate     is this request for a module the area parked?
 *   registry.parked_module_listener  the gate, applied to every incoming request
 *   registry.permissions           the permissions installed modules declare
 *   registry.sync                  the create-only reconciliation itself
 *   registry.sync_warmer           the deploy hook that runs it
 */
return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    /*
     * THE COLLECTING END OF THE REGISTRY, four times over. Each of these reads the
     * providers live, from the container, in registration order — which is what
     * makes uninstalling a bundle take its module, its route and its declared
     * permissions with it on the next request rather than on the next deploy.
     */
    $providers = tagged_iterator(RegistryBundle::MODULE_TAG);

    /*
     * Repositories keep FQCN ids — the one place the bundle-alias prefix cannot
     * be used: ServiceRepositoryCompilerPass keys its locator by SERVICE ID over
     * findTaggedServiceIds(), while ContainerRepositoryFactory looks a repository
     * up by CLASS NAME; tagged-id lookup never sees aliases.
     *
     * @see vendor/doctrine/doctrine-bundle/src/DependencyInjection/Compiler/ServiceRepositoryCompilerPass.php
     */
    $services->set(ModuleRepository::class)
        ->args([service('doctrine')])
        ->tag('doctrine.repository_service');

    $services->set(AreaModuleRepository::class)
        ->args([service('doctrine')])
        ->tag('doctrine.repository_service');

    $services->set('registry.provider_mapper', ProviderCatalogueMapper::class)
        ->args([param('registry.default_category')]);

    $services->set('registry.catalogue', ModuleCatalogue::class)
        ->args([service(ModuleRepository::class), $providers]);

    $services->set('registry.area_modules', AreaModuleService::class)
        ->args([
            service('doctrine.orm.entity_manager'),
            service(AreaModuleRepository::class),
            service('registry.catalogue'),
        ]);

    $services->set('registry.area_module_ledger', AreaModuleLedger::class)
        ->args([service('registry.catalogue'), service(AreaModuleRepository::class)]);

    $services->set('registry.entry_routes', ModuleEntryRouteResolver::class)
        ->args([$providers]);

    /*
     * THE ROUTE GATE, and the listener that is its only caller. Parking a
     * module for an area closes that module's routes there — the registry owns the
     * ledger, so the registry is where the question is answered, once, for every
     * module at the same time.
     *
     * Priority 8 puts the listener after Symfony's RouterListener (32), whose
     * work — the route's defaults, on the request — is what the gate reads, and
     * well before any controller runs.
     */
    $services->set('registry.module_route_gate', ModuleRouteGate::class)
        ->args([service(AreaModuleRepository::class), service('registry.catalogue')]);

    $services->set('registry.parked_module_listener', ParkedModuleListener::class)
        ->args([service('registry.module_route_gate')])
        ->tag('kernel.event_listener', ['event' => 'kernel.request', 'priority' => 8]);

    $services->set('registry.permissions', ModulePermissionCatalogue::class)
        ->args([$providers]);

    /*
     * THE DEPLOY HOOK. The core ships no console command; reconciling the
     * catalogue with the installed providers is a cache warmer, which Symfony
     * runs on cache:warmup, on cache:clear, and on the first request if neither
     * has — the full set of moments a deploy can be said to have happened.
     * @see https://symfony.com/doc/current/reference/dic_tags.html#kernel-cache-warmer
     *
     * Both tags are written out by hand: nothing here is autoconfigured, so
     * neither the warmer tag nor the service-subscriber tag that gives the
     * warmer its lazy locator is applied for us.
     */
    $services->set('registry.sync', RegistrySyncService::class)
        ->args([
            service('doctrine.orm.entity_manager'),
            service(ModuleRepository::class),
            service(AreaModuleRepository::class),
            service('registry.provider_mapper'),
            $providers,
        ]);

    $services->alias(RegistrySyncService::class, 'registry.sync');

    $services->set('registry.sync_warmer', RegistrySyncWarmer::class)
        // ResolveServiceSubscribersPass swaps a Psr ContainerInterface reference
        // for the subscriber's own locator; any other id would inject the real
        // container. @see vendor/symfony/dependency-injection/Compiler/ResolveServiceSubscribersPass.php
        ->args([service(ContainerInterface::class)])
        ->tag('container.service_subscriber')
        ->tag('kernel.cache_warmer');
};
