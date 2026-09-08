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
 * AN APPLICATION THAT HAS GIVEN ITS CONSENT: {@see TestKernel} — a fresh
 * installation with no contracts, no areas, no user and no database — plus the one
 * line a real installation writes in its own `config/routes/shell.yaml`:
 *
 *     shell:
 *         resource: '@ShellBundle/config/routes/welcome.php'
 *
 * That line is the entire mechanism, and the difference between this kernel and
 * its parent is the entire boundary. The shell ships the route as a RESOURCE and
 * never loads it: an application that imports it gets `/`, an application that
 * deletes the import gets its `/` back, and neither has to know what is behind
 * the address.
 *
 * The import is written out by hand, resource string and all, for the same
 * reason the fixture contract services write their tags by hand — this is exactly
 * what an application has to type, so if it needed anything the recipe does not
 * ship, this suite would be the first to know.
 */
final class ImportingHostKernel extends TestKernel
{
    /**
     * Protected rather than private — the trait's own signature — only because
     * MicroKernelTrait reaches this by reflection and a private method that
     * nothing in the file calls reads to a static analyser as dead code.
     */
    protected function configureRoutes(RoutingConfigurator $routes): void
    {
        $routes->import(ShellBundle::ROUTES);
    }
}
