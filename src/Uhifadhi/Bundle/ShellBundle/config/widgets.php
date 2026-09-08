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

use Uhifadhi\Bundle\ShellBundle\Widget\Registry\WidgetSurfaceInterface;
use Uhifadhi\Bundle\ShellBundle\Widget\Registry\WidgetSurfaceRegistry;
use Uhifadhi\Bundle\ShellBundle\Widget\Repository\WidgetCustomPresetRepository;
use Uhifadhi\Bundle\ShellBundle\Widget\Repository\WidgetPreferenceRepository;
use Uhifadhi\Bundle\ShellBundle\Widget\Service\WidgetEndpoint;
use Uhifadhi\Bundle\ShellBundle\Widget\Service\WidgetPruneService;
use Uhifadhi\Bundle\ShellBundle\Widget\Service\WidgetService;

/*
 * The bundle's static service wiring.
 *
 * PHP (not YAML) on purpose: a reusable bundle must not force symfony/yaml onto
 * an installation, and FQCN references stay refactor-safe and phpstan-checked.
 * Imported by ShellBundle::loadExtension().
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
 * THE IDS ARE THE PUBLISHED SURFACE. A module's own services take these by
 * reference:
 *
 *   widget.surfaces   which dashboards this installation has
 *   widget.service    resolve a person's layout, and write one
 *   widget.endpoint   the six writes a library page posts to
 *
 * They are aliased to their FQCNs as well, so a module's service definition can
 * name the class it type-hints instead of remembering a string.
 */
return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    /*
     * Repositories keep FQCN ids — the one place the bundle-alias prefix cannot
     * be used: ServiceRepositoryCompilerPass keys its locator by SERVICE ID over
     * findTaggedServiceIds(), while ContainerRepositoryFactory looks a repository
     * up by CLASS NAME; tagged-id lookup never sees aliases.
     */
    $services->set(WidgetPreferenceRepository::class)
        ->args([service('doctrine')])
        ->tag('doctrine.repository_service');

    $services->set(WidgetCustomPresetRepository::class)
        ->args([service('doctrine')])
        ->tag('doctrine.repository_service');

    /*
     * WHICH DASHBOARDS EXIST, read live from the container in registration
     * order — which is what makes removing a module remove its surface on the
     * next request rather than the next deploy.
     */
    $services->set('shell.widget.surfaces', WidgetSurfaceRegistry::class)
        ->args([tagged_iterator(WidgetSurfaceInterface::TAG)]);
    $services->alias(WidgetSurfaceRegistry::class, 'shell.widget.surfaces');

    /*
     * THE FRAMEWORK ITSELF: a person's layout of a surface, resolved from the
     * surface's catalogue and their stored row, and every write that changes it.
     */
    $services->set('shell.widget.service', WidgetService::class)
        ->args([
            service(WidgetPreferenceRepository::class),
            service(WidgetCustomPresetRepository::class),
            service('doctrine.orm.entity_manager'),
        ]);
    $services->alias(WidgetService::class, 'shell.widget.service');

    /*
     * The write endpoints a library page posts to. Registered unconditionally:
     * this bundle requires symfony/security-core and symfony/security-csrf
     * outright, because a dashboard layout belongs to a person and every write
     * that changes one carries a token.
     */
    $services->set('shell.widget.endpoint', WidgetEndpoint::class)
        ->args([
            service('shell.widget.service'),
            service('security.token_storage'),
            service('security.csrf.token_manager'),
        ]);
    $services->alias(WidgetEndpoint::class, 'shell.widget.endpoint');

    /*
     * The operator's broom. Never automatic, never wired to a kernel event:
     * removing somebody's saved dashboards is a decision, and a decision
     * belongs to a person answering a prompt. The core ships no command to ask
     * with — devkit owns commands — so this is the service that prompt drives.
     */
    $services->set('shell.widget.pruner', WidgetPruneService::class)
        ->args([
            service('doctrine.orm.entity_manager'),
            service('shell.widget.surfaces'),
            service(WidgetPreferenceRepository::class),
            service(WidgetCustomPresetRepository::class),
        ]);

    $services->alias(WidgetPruneService::class, 'shell.widget.pruner');
};
