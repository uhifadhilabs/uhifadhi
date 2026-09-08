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

use Uhifadhi\Bundle\ShellBundle\Contract\LayoutContract;
use Uhifadhi\Bundle\ShellBundle\Controller\WelcomeController;
use Uhifadhi\Bundle\ShellBundle\Service\AreaShell;
use Uhifadhi\Bundle\ShellBundle\Service\Installation;
use Uhifadhi\Bundle\ShellBundle\Service\Navigation;
use Uhifadhi\Bundle\ShellBundle\Service\Theme;
use Uhifadhi\Bundle\ShellBundle\Service\UserBadgeReader;
use Uhifadhi\Bundle\ShellBundle\ShellBundle;
use Uhifadhi\Bundle\ShellBundle\Twig\ShellExtension;
use Uhifadhi\Bundle\ShellBundle\Twig\ShellRuntime;

/*
 * The bundle's static service wiring.
 *
 * PHP (not YAML) on purpose: a reusable bundle must not force symfony/yaml onto
 * hosts, and FQCN references stay refactor-safe and phpstan-checked. Imported by
 * ShellBundle::loadExtension(), which keeps only the config-DRIVEN
 * definitions.
 *
 * Everything defined here is defined EXPLICITLY — no autowire(), no autoconfigure(),
 * and ids prefixed with the bundle alias — because this bundle is installed by other
 * projects via Composer, which is what Symfony calls a reusable bundle:
 *
 *   "Services should not use autowiring or autoconfiguration. Instead, all
 *    services should be defined explicitly."
 *   "If the bundle defines services, they must be prefixed with the bundle alias."
 *   — https://symfony.com/doc/current/bundles/best_practices.html
 *
 * A NOTE ON TWIG, because this bundle is mostly Twig and the split is not
 * decoration: an EXTENSION is constructed as soon as the `twig` service is
 * built, and the image build does exactly that (asset-map:compile fires the
 * asset-compile event and UX Icons warms its cache off it). An extension
 * holding anything that reads a request or a repository therefore breaks the
 * BUILD, not a page. So the shell ships a thin extension that declares
 * functions and a RUNTIME that is constructed lazily, on the first call — i.e.
 * only when a template is actually rendered. The host learned this the hard
 * way with its sidebar; the shell inherits the lesson, not the bug.
 */
return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    // The frozen manifest, as a service, so a host or a module can ask
    // the container which contract version it is mounting rather than reading
    // a constant off a class it had to guess the name of.
    $services->set('shell.contract', LayoutContract::class);

    /*
     * THE NAV CONTRACT'S COLLECTOR. A tagged iterator, and nothing else: whatever
     * carries the tag contributes, whether that is the host folding areas and
     * permissions into rows or a module adding one platform-wide row.
     *
     * The iterator is lazy and is walked on every render, which is what makes
     * the contract's same-day promise true — switch a contributor off and its rows
     * are gone on the next request, not after a deploy.
     */
    $services->set('shell.navigation', Navigation::class)
        ->args([tagged_iterator(ShellBundle::NAV_TAG)]);

    /*
     * THE AREA CONTRACT'S READER. One source, aliased by the host to the id below —
     * an alias rather than a tagged collection because two things claiming to
     * know where the viewer is, is exactly the disagreement this bundle exists
     * to prevent.
     *
     * nullOnInvalid() is the ring gate written into the container: a freshly
     * installation declares no such source, and it must get pages with
     * no tab strip rather than a container that will not compile.
     */
    $services->set('shell.area_shell', AreaShell::class)
        ->args([service('shell.area_shell_source')->nullOnInvalid()]);

    /*
     * THE TOP BAR'S VIEWER-CARD READER. One optional source, aliased by the
     * host to the id below — an alias rather than a tagged collection for the
     * area contract's reason: two things claiming to know who is signed in is the
     * disagreement this bundle exists to prevent.
     *
     * nullOnInvalid() is the ring gate written into the container: a fresh
     * installation declares no such source, and it must get a top bar with no
     * card rather than a container that will not compile.
     */
    $services->set('shell.user_badge', UserBadgeReader::class)
        ->args([service('shell.user_badge_source')->nullOnInvalid()]);

    // What a visitor who has never chosen a theme gets. Everything else about
    // the theme is the browser's, resolved before the first paint.
    $services->set('shell.theme', Theme::class)
        ->args(['%shell.default_theme%']);

    // WHAT THIS INSTALLATION IS MADE OF, read from composer's runtime API. No
    // arguments: the answer is a property of the vendor directory, not of any
    // configuration, and a welcome screen that reported a list somebody typed
    // would be wrong the first time anybody installed a module.
    $services->set('shell.installation', Installation::class);

    /*
     * THE WELCOME PAGE, as a controller service.
     *
     * The `controller.service_arguments` tag is what lets the route address it
     * by this id: the tag registers the service with the controller resolver's
     * locator, which is how a controller stays a normal, explicitly wired
     * service instead of a public one fished out of the container by class name.
     *
     * REACHABLE ONLY IF THE APPLICATION SAYS SO. Registering this service does
     * not put it at an address — config/routes/welcome.php does that, and
     * nothing here loads that file (see ShellBundle::ROUTES).
     */
    $services->set('shell.controller.welcome', WelcomeController::class)
        ->args([
            service('twig'),
            service('shell.installation'),
        ])
        ->tag('controller.service_arguments');

    $services->set('shell.twig.extension', ShellExtension::class)
        ->tag('twig.extension');

    $services->set('shell.twig.runtime', ShellRuntime::class)
        ->args([
            service('shell.navigation'),
            service('shell.area_shell'),
            service('shell.user_badge'),
            service('shell.theme'),
            service('router'),
            '%shell.brand_name%',
            '%shell.home_route%',
        ])
        ->tag('twig.runtime');
};
