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
 * AN APPLICATION THAT HAS ACCEPTED THE SETTINGS SECTION: {@see TestKernel} —
 * a fresh installation with no contracts, no areas, no user and no database —
 * plus the one line a real installation writes in its own
 * `config/routes/shell.yaml`:
 *
 *     shell_settings:
 *         resource: '@ShellBundle/config/routes/settings.php'
 *
 * The import is written out by hand, resource constant and all, for the same
 * reason the welcome page's fixture writes its own: this is exactly what an
 * application has to type, so if it needed anything else, this suite would be
 * the first to know.
 *
 * AND NOTHING ELSE IS INSTALLED, deliberately. Every fact the section draws
 * arrives through a contract, so a kernel with no contributor at all is the
 * one that proves the screens state their own absence instead of failing.
 */
final class SettingsHostKernel extends TestKernel
{
    /**
     * Protected rather than private — the trait's own signature — only because
     * MicroKernelTrait reaches this by reflection and a private method that
     * nothing in the file calls reads to a static analyser as dead code.
     */
    protected function configureRoutes(RoutingConfigurator $routes): void
    {
        $routes->import(ShellBundle::SETTINGS_ROUTES);
    }
}
