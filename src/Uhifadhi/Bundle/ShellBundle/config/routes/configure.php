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

namespace Symfony\Component\Routing\Loader\Configurator;

use Symfony\Component\Routing\Requirement\Requirement;
use Uhifadhi\Bundle\ShellBundle\Frame\Controller\ConfigureController;

/*
 * THE CONFIGURE PAGE'S TWO ADDRESSES — shipped as a resource, loaded by nobody
 * here, exactly like the welcome page's. An application asks for them in one
 * line, in a file it owns:
 *
 *     # config/routes/shell.yaml (your application)
 *     shell_configure:
 *         resource: '@ShellBundle/config/routes/configure.php'
 *
 * An installation that never imports it has no configure page and every module
 * in it keeps whatever it had; nothing here claims a URL the application did
 * not ask it to claim.
 *
 * THE SECTION IS A TRAILING OPTIONAL PARAMETER, which is what makes the ruled
 * order visible in the URL: the surface's LAST section — Settings — is the bare
 * address the `Configure` action opens, and every other section the shell
 * renders hangs one segment below it. So `/areas/{uuid}/configure` is the area's
 * settings and `/areas/{uuid}/configure/widgets` is its widget library, and the
 * router generates the bare form on its own whenever no section is named.
 *
 * NOTHING HERE NEEDS A PRIORITY. The module address is three segments under
 * `/modules/`, and the module grid's own customize family is two, so neither can
 * shadow the other however an application orders its imports.
 *
 * THE MODULE ADDRESS WEARS THE PLATFORM'S MODULE PATH SHAPE on purpose:
 * `/areas/{uuid}/modules/{slug}/…` is the shape a parked module is closed by, so
 * a module switched off for an area has no configure page either, with nothing
 * written here to arrange it.
 */
return static function (RoutingConfigurator $routes): void {
    $routes->add(ConfigureController::AREA_ROUTE, '/areas/{uuid}/configure/{section}')
        ->controller(['shell.controller.configure', 'area'])
        ->requirements(['uuid' => Requirement::UUID, 'section' => '[a-z][a-z0-9-]*'])
        ->defaults(['section' => null])
        ->methods(['GET']);

    $routes->add(ConfigureController::MODULE_ROUTE, '/areas/{uuid}/modules/{slug}/configure/{section}')
        ->controller(['shell.controller.configure', 'module'])
        ->requirements(['uuid' => Requirement::UUID, 'slug' => '[a-z]+', 'section' => '[a-z][a-z0-9-]*'])
        ->defaults(['section' => null])
        ->methods(['GET']);
};
