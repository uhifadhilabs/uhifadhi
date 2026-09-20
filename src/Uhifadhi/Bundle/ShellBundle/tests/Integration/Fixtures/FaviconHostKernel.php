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

namespace Uhifadhi\Bundle\ShellBundle\Tests\Integration\Fixtures;

use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;
use Uhifadhi\Bundle\ShellBundle\ShellBundle;
use Uhifadhi\Bundle\ShellBundle\Tests\Integration\TestKernel;

/**
 * AN APPLICATION THAT HAS ACCEPTED THE ICON'S ADDRESS — the one line a real
 * installation writes in its own `config/routes/shell.yaml`:
 *
 *     shell_favicon:
 *         resource: '@ShellBundle/config/routes/favicon.php'
 *
 * Written out by hand, resource constant and all, for the reason the welcome
 * page's fixture writes its own: this is exactly what an installation has to
 * type.
 */
final class FaviconHostKernel extends TestKernel
{
    /**
     * Protected rather than private — the trait's own signature — only because
     * MicroKernelTrait reaches this by reflection and a private method that
     * nothing in the file calls reads to a static analyser as dead code.
     */
    protected function configureRoutes(RoutingConfigurator $routes): void
    {
        $routes->import(ShellBundle::FAVICON_ROUTES);
    }
}
