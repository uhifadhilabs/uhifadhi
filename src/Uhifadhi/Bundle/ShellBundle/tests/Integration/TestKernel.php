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

namespace Uhifadhi\Bundle\ShellBundle\Tests\Integration;

use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Bundle\TwigBundle\TwigBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\UX\Icons\UXIconsBundle;
use Symfony\UX\StimulusBundle\StimulusBundle;
use Uhifadhi\Bundle\ShellBundle\ShellBundle;

/**
 * THE SKELETON, PLUS THE SHELL, AND NOTHING ELSE: framework + twig + ux-icons +
 * this bundle. Note what is missing and stays missing — doctrine. The shell is
 * the one bundle in the platform that can be booted without a database, and that is
 * not a testing convenience: it is the boundary. A layout that cannot render
 * without a connection has stopped being a layout.
 *
 * Note also what is missing and is NOT the boundary: a contract. The shell does
 * not require one (see docs/boundaries.md) — module rows and module
 * cards reach it as data, through the contracts, from whoever composed them. This
 * kernel proves it by booting a shell with no contract runtime under it at all.
 */
class TestKernel extends Kernel
{
    use CheckoutTempDirTrait;
    use MicroKernelTrait;

    public function registerBundles(): iterable
    {
        yield new FrameworkBundle();
        yield new TwigBundle();
        yield new UXIconsBundle();
        yield new StimulusBundle();
        yield new ShellBundle();
    }

    /**
     * THE STAND-IN HOST'S PROJECT DIRECTORY — a Flex-installed application's
     * asset side and nothing else: an importmap with the `app` entrypoint and
     * the file it points at. The shell's document renders that importmap, so
     * the suite needs an application that has one; pointing the kernel at a
     * fixture directory is how it gets one without this repository growing an
     * importmap of its own, which a shipped bundle has no business carrying.
     */
    public function getProjectDir(): string
    {
        return __DIR__.'/Fixtures/app';
    }

    protected function configureContainer(ContainerConfigurator $container): void
    {
        $container->extension('framework', [
            'secret' => 'test',
            'test' => true,
            'router' => ['utf8' => true],
            'http_method_override' => false,
            'handle_all_throwables' => true,
            'php_errors' => ['log' => true],
            // The asset side of a Flex-installed application: the stand-in
            // host's own assets/, plus — prepended by the bundle itself, not
            // named here — the shell's controllers under
            // @uhifadhi/shell-bundle. That prepend is the thing under test.
            'asset_mapper' => [
                'paths' => [$this->getProjectDir().'/assets' => ''],
            ],
        ]);

        // A SILENT LOGGER. This suite now handles real requests, and Symfony's
        // default debug logger writes every kernel event to stderr — pages of
        // it per test, drowning the one line that matters when something fails.
        $container->services()->set('logger', NullLogger::class);

        // strict_variables ON, in the bundle's own test host, because the shell
        // is a set of templates other people fill: a page that hands the frame
        // an undefined variable must fail here rather than render a hole.
        $container->extension('twig', [
            'strict_variables' => true,
        ]);

        // NO NETWORK, for the reason there is no database: this suite renders,
        // and a render that reaches the internet is a render that fails in a
        // tunnel. Icons the shell ships resolve from its own `shell:` set; an
        // icon a FIXTURE names (a host's own set, which this kernel does not
        // have) resolves to nothing rather than to an HTTP request, because
        // whether a host's icons are installed is not this bundle's contract.
        $container->extension('ux_icons', [
            'iconify' => ['enabled' => false],
            'ignore_not_found' => true,
        ]);
    }

    public function getCacheDir(): string
    {
        return $this->checkoutTempDir('cache/'.$this->getEnvironment().'/'.static::class);
    }

    public function getLogDir(): string
    {
        return $this->checkoutTempDir('log');
    }
}
