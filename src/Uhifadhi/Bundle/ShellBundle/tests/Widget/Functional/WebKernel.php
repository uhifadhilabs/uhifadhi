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

namespace Uhifadhi\Bundle\ShellBundle\Tests\Widget\Functional;

use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Bundle\SecurityBundle\SecurityBundle;
use Symfony\Bundle\TwigBundle\TwigBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\UX\Icons\UXIconsBundle;
use Symfony\UX\StimulusBundle\StimulusBundle;
use Uhifadhi\Bundle\ShellBundle\ShellBundle;
use Uhifadhi\Bundle\ShellBundle\Tests\Integration\CheckoutTempDirTrait;
use Uhifadhi\Bundle\ShellBundle\Tests\Widget\Functional\Fixtures\AreaSightingsController;
use Uhifadhi\Bundle\ShellBundle\Tests\Widget\Integration\Fixtures\HostUser;
use Uhifadhi\Bundle\ShellBundle\Tests\Widget\Integration\Fixtures\SightingsSurface;
use Uhifadhi\Bundle\ShellBundle\Widget\Registry\WidgetSurfaceInterface;
use Uhifadhi\Bundle\ShellBundle\Widget\Service\WidgetEndpoint;
use Uhifadhi\Bundle\ShellBundle\Widget\Service\WidgetService;
use Uhifadhi\Contracts\Entity\UserInterface;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

/**
 * AN INSTALLATION WITH A LIBRARY PAGE IN IT — the widget suite's kernel plus the
 * three things a page needs: twig to render in, a firewall to be signed into,
 * and a module whose dashboard the library is about.
 *
 * WHY A SECOND KERNEL RATHER THAN A ROUTE ON THE FIRST. What is exercised here
 * is the round trip a person makes — render, click, write, render again —
 * through the REAL endpoint, the REAL CSRF manager and the REAL templates. The
 * integration kernel answers a different question (what is stored) and has no
 * page in it; keeping them apart keeps each one the smallest installation its
 * own question needs.
 */
final class WebKernel extends Kernel
{
    /*
     * MicroKernelTrait, not a hand-rolled Kernel: it is what tags the `kernel`
     * service as a route loader and points framework.router at
     * `kernel::loadRoutes`. Without it configureRoutes() is never called and
     * every route below silently does not exist.
     */
    use CheckoutTempDirTrait;
    use MicroKernelTrait;

    public function registerBundles(): iterable
    {
        yield new FrameworkBundle();
        yield new TwigBundle();
        yield new SecurityBundle();
        yield new DoctrineBundle();
        // The library draws its glyphs with ux_icon() and asks for a confirm
        // dialog with stimulus_controller(); both are hard requirements of the
        // shell, so an installation with the shell has them.
        yield new UXIconsBundle();
        yield new StimulusBundle();
        yield new ShellBundle();
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
            'session' => ['storage_factory_id' => 'session.storage.factory.mock_file'],
            // Every write to a layout carries a token, so the manager the
            // endpoint reads has to be the one an installation has.
            'csrf_protection' => ['enabled' => true],
        ]);

        // STRICT: a template that reads a variable the controller did not pass
        // fails here rather than rendering a blank cell in an installation.
        $container->extension('twig', [
            'strict_variables' => true,
            'paths' => [__DIR__.'/Fixtures/templates' => 'Fixture'],
        ]);

        /*
         * THE ICONS ARE LOCAL. `shell:` is a set the shell registers and is
         * answered only from the shell's own directory; the empty directory here
         * is the application's, which every application has. On-demand fetching
         * is off, so this suite proves the page renders rather than that the
         * machine has internet.
         */
        $container->extension('ux_icons', [
            'icon_dir' => __DIR__.'/icons',
            'iconify' => ['on_demand' => false],
        ]);

        $container->extension('security', [
            'providers' => [
                'host_user_provider' => [
                    'entity' => ['class' => HostUser::class, 'property' => 'email'],
                ],
            ],
            'firewalls' => [
                'main' => ['lazy' => true, 'provider' => 'host_user_provider'],
            ],
        ]);

        $container->extension('doctrine', [
            'dbal' => ['url' => '%env(UHIFADHI_TEST_DATABASE_URL)%'],
            'orm' => [
                'naming_strategy' => 'doctrine.orm.naming_strategy.underscore',
                // THE ONE LINE AN INSTALLATION WRITES; without it nothing here
                // can build a schema. See docs/widget/user-contract.md.
                'resolve_target_entities' => [
                    UserInterface::class => HostUser::class,
                ],
                'mappings' => [
                    'TestHost' => [
                        'type' => 'attribute',
                        'dir' => \dirname(__DIR__).'/Integration/Fixtures',
                        'prefix' => 'Uhifadhi\\Bundle\\ShellBundle\\Tests\\Widget\\Integration\\Fixtures',
                        'is_bundle' => false,
                    ],
                ],
            ],
        ]);

        $services = $container->services();

        // The module, registered WITHOUT autoconfiguration and tagged by hand,
        // exactly as a module shipped as a reusable bundle must be.
        $services->set(SightingsSurface::class)
            ->tag(WidgetSurfaceInterface::TAG);

        // And its screens, wired explicitly and public, the way core's own
        // controllers are — no base class, no service subscriber.
        $services->set(AreaSightingsController::class)
            ->args([
                service('twig'),
                service('router'),
                service('shell.widget.service'),
                service('shell.widget.endpoint'),
            ])
            ->public();

        foreach ([
            WidgetService::class => 'shell.widget.service',
            WidgetEndpoint::class => 'shell.widget.endpoint',
        ] as $class => $serviceId) {
            $services->alias('test_public.'.$class, $serviceId)->public();
        }
    }

    protected function configureRoutes(RoutingConfigurator $routes): void
    {
        $area = ['uuid' => Requirement::UUID];
        $prefix = '/areas/{uuid}/modules/sightings';

        $routes->add('sightings_dashboard', $prefix)
            ->controller([AreaSightingsController::class, 'dashboard'])
            ->requirements($area)
            ->methods(['GET']);

        $routes->add('sightings_widgets', $prefix.'/widgets')
            ->controller([AreaSightingsController::class, 'library'])
            ->requirements($area)
            ->methods(['GET']);

        $routes->add('sightings_widgets_save', $prefix.'/widgets/save')
            ->controller([AreaSightingsController::class, 'save'])
            ->requirements($area)
            ->methods(['POST']);

        $routes->add('sightings_widgets_reset', $prefix.'/widgets/reset')
            ->controller([AreaSightingsController::class, 'reset'])
            ->requirements($area)
            ->methods(['POST']);

        $routes->add('sightings_widgets_preset_copy', $prefix.'/widgets/preset/{presetId}/copy')
            ->controller([AreaSightingsController::class, 'copyPreset'])
            ->requirements($area + ['presetId' => '[a-z0-9_-]+'])
            ->methods(['POST']);

        $routes->add('sightings_widgets_preset', $prefix.'/widgets/preset/{presetId}')
            ->controller([AreaSightingsController::class, 'applyPreset'])
            ->requirements($area + ['presetId' => '[a-z0-9_-]+'])
            ->methods(['POST']);

        $routes->add('sightings_widgets_presets', $prefix.'/widgets/presets')
            ->controller([AreaSightingsController::class, 'save'])
            ->requirements($area)
            ->methods(['POST']);

        $routes->add('sightings_widgets_preset_apply', $prefix.'/widgets/presets/{presetUuid}/apply')
            ->controller([AreaSightingsController::class, 'applyCustomPreset'])
            ->requirements($area + ['presetUuid' => Requirement::UUID])
            ->methods(['POST']);

        $routes->add('sightings_widgets_preset_rename', $prefix.'/widgets/presets/{presetUuid}/rename')
            ->controller([AreaSightingsController::class, 'renameCustomPreset'])
            ->requirements($area + ['presetUuid' => Requirement::UUID])
            ->methods(['POST']);

        $routes->add('sightings_widgets_preset_delete', $prefix.'/widgets/presets/{presetUuid}/delete')
            ->controller([AreaSightingsController::class, 'deleteCustomPreset'])
            ->requirements($area + ['presetUuid' => Requirement::UUID])
            ->methods(['POST']);
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
