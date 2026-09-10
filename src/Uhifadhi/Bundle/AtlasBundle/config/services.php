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

use Uhifadhi\Bundle\AtlasBundle\Map\MapBuilder;
use Uhifadhi\Bundle\AtlasBundle\Map\MapBuilderInterface;

/*
 * The bundle's static service wiring.
 *
 * PHP (not YAML) on purpose: a reusable bundle must not force symfony/yaml onto
 * hosts, and FQCN references stay refactor-safe and phpstan-checked. Imported by
 * AtlasBundle::loadExtension(), which keeps the config-DRIVEN
 * definitions (the satellite source and the Twig contract).
 *
 * Everything is defined EXPLICITLY — no autowire(), no autoconfigure(), and ids
 * prefixed with the bundle alias — because this bundle is installed by other
 * projects via Composer, which is what Symfony calls a reusable bundle:
 *
 *   "Services should not use autowiring or autoconfiguration. Instead, all
 *    services should be defined explicitly."
 *   "If the bundle defines services, they must be prefixed with the bundle alias."
 *   "services not meant to be used by the application directly, should be
 *    defined as private. For public services, aliases should be created from the
 *    interface/class to the service id."
 *   — https://symfony.com/doc/current/bundles/best_practices.html
 */
return static function (ContainerConfigurator $container): void {
    $container->services()
        /*
         * The one way a module gets a map. Config-free by design: which imagery
         * a plate draws is settled in the browser, off the attribute on the
         * <body>, rather than baked into the map on the server — so the builder
         * is the same object whatever a deployment configured.
         */
        ->set('atlas.map_builder', MapBuilder::class)

        /*
         * The name a module names it by. The service stays private; this alias
         * is what a consumer's own explicit wiring references.
         */
        ->alias(MapBuilderInterface::class, 'atlas.map_builder')
    ;
};
