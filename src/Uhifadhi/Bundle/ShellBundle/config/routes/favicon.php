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

use Uhifadhi\Bundle\ShellBundle\Controller\FaviconController;

/*
 * `/favicon.ico` — shipped as a resource, loaded by nobody here, exactly like
 * the welcome page's, the configure page's and the settings section's. An
 * application asks for it in one line, in a file it owns:
 *
 *     # config/routes/shell.yaml (your application)
 *     shell_favicon:
 *         resource: '@ShellBundle/config/routes/favicon.php'
 *
 * A RESOURCE OF ITS OWN, because it is a decision of its own: an installation
 * that serves its own icon from the web server, or from a CDN in front of it,
 * wants this address left alone — and deleting one import is how it says so.
 *
 * WHY IT IS WORTH AN ADDRESS AT ALL. Every page declares `<link rel="icon">`
 * and every browser still asks for `/favicon.ico`: before the first page, on
 * a redirect, on an error page, and whenever it has nothing cached. Nothing
 * answered, so the request became the one exception a staging installation
 * had ever logged.
 */
return static function (RoutingConfigurator $routes): void {
    $routes->add(FaviconController::ROUTE, '/favicon.ico')
        ->controller('shell.controller.favicon')
        ->methods(['GET']);
};
