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

/*
 * THE WELCOME PAGE'S ADDRESS — shipped as a resource, loaded by nobody here.
 *
 * Nothing in this bundle imports this file. It is reachable because an
 * application asks for it, in one line, in a file the application owns:
 *
 *     # config/routes/shell.yaml (your application)
 *     shell:
 *         resource: '@ShellBundle/config/routes/welcome.php'
 *
 * That is the shell's boundary in one sentence: the shell claims no URL an
 * application has not asked it to claim. Delete the import and
 * `/` is the application's again, with nothing left behind — no compiled route,
 * no listener, no fallback.
 *
 * PHP, NOT YAML, for the same reason config/services.php is: a reusable bundle
 * must not force symfony/yaml onto the hosts that install it. This loads with
 * symfony/routing, which the bundle requires because it ships this file.
 *
 * THE ROUTE NAME IS `welcome`, and it is part of what an application is told:
 * `debug:router` is where somebody looks to see what is answering `/` before
 * they replace it. Nothing in the shell generates it or depends on it existing.
 */
return static function (RoutingConfigurator $routes): void {
    $routes->add('welcome', '/')
        ->controller('shell.controller.welcome');
};
