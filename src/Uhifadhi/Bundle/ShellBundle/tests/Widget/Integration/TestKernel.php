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

namespace Uhifadhi\Bundle\ShellBundle\Tests\Widget\Integration;

use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Bundle\SecurityBundle\SecurityBundle;
use Symfony\Bundle\TwigBundle\TwigBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\UX\Icons\UXIconsBundle;
use Uhifadhi\Bundle\ShellBundle\ShellBundle;
use Uhifadhi\Bundle\ShellBundle\Tests\Widget\Integration\Fixtures\HostUser;
use Uhifadhi\Bundle\ShellBundle\Tests\Widget\Integration\Fixtures\SightingsSurface;
use Uhifadhi\Bundle\ShellBundle\Widget\Registry\WidgetSurfaceInterface;
use Uhifadhi\Contracts\Entity\UserInterface;

/**
 * The smallest installation this bundle can live in: framework + doctrine +
 * security, talking to a REAL database (UHIFADHI_TEST_DATABASE_URL, see
 * phpunit.dist.xml).
 *
 * IT PLAYS THE INSTALLATION'S END OF THE USER RESOLUTION, AND THAT IS THE
 * POINT. The shell points every stored layout at
 * `Uhifadhi\Contracts\Entity\UserInterface` and cannot build a schema
 * until an installation resolves it. In a running installation TeamBundle
 * states the resolution — but Team depends on the shell, not the other way
 * round, so this suite cannot boot Team without inverting the core's own
 * dependency rule. It answers the interface itself, with {@see HostUser}, which
 * is exactly what an installation whose people are its own entity does.
 *
 * The `resolve_target_entities` block below is verbatim what the shell's
 * `docs/widget/user-contract.md` tells such an installation to write. The two
 * are kept in step deliberately: documentation nothing exercises is
 * documentation that rots.
 */
final class TestKernel extends Kernel
{
    use MicroKernelTrait;

    public function registerBundles(): iterable
    {
        yield new FrameworkBundle();
        yield new DoctrineBundle();
        yield new SecurityBundle();
        // Twig and the icon set, because the shell's own templates compile
        // against both. Nothing here renders a page.
        yield new TwigBundle();
        yield new UXIconsBundle();
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
            // Every write to a layout carries a token, so the manager the
            // endpoint reads has to exist exactly as it does in an installation.
            'session' => ['storage_factory_id' => 'session.storage.factory.mock_file'],
            'csrf_protection' => ['enabled' => true],
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
                // The skeleton's own choice, mirrored so this bundle's SQL is
                // exercised against the column names it will actually meet —
                // which the partial unique indexes name in their WHERE clauses.
                'naming_strategy' => 'doctrine.orm.naming_strategy.underscore',
                // THE ONE LINE AN INSTALLATION WRITES. Without it nothing here
                // can build a schema.
                'resolve_target_entities' => [
                    UserInterface::class => HostUser::class,
                ],
                'mappings' => [
                    'TestHost' => [
                        'type' => 'attribute',
                        'dir' => __DIR__.'/Fixtures',
                        'prefix' => 'Uhifadhi\\Bundle\\ShellBundle\\Tests\\Widget\\Integration\\Fixtures',
                        'is_bundle' => false,
                    ],
                ],
            ],
        ]);

        // A module standing in for every module that declares a dashboard. It
        // is registered WITHOUT autoconfiguration and tags by hand, exactly as
        // a module shipped as a reusable bundle must.
        $container->services()
            ->set(SightingsSurface::class)
            ->tag(WidgetSurfaceInterface::TAG);

        // Public aliases so a test can hold the bundle's private services, which
        // are private exactly as a reusable bundle's should be.
        foreach ([
            \Uhifadhi\Bundle\ShellBundle\Widget\Registry\WidgetSurfaceRegistry::class => 'shell.widget.surfaces',
            \Uhifadhi\Bundle\ShellBundle\Widget\Service\WidgetService::class => 'shell.widget.service',
            \Uhifadhi\Bundle\ShellBundle\Widget\Service\WidgetEndpoint::class => 'shell.widget.endpoint',
            \Uhifadhi\Bundle\ShellBundle\Widget\Service\WidgetPruneService::class => 'shell.widget.pruner',
            \Uhifadhi\Bundle\ShellBundle\Widget\Repository\WidgetPreferenceRepository::class => \Uhifadhi\Bundle\ShellBundle\Widget\Repository\WidgetPreferenceRepository::class,
            \Uhifadhi\Bundle\ShellBundle\Widget\Repository\WidgetCustomPresetRepository::class => \Uhifadhi\Bundle\ShellBundle\Widget\Repository\WidgetCustomPresetRepository::class,
        ] as $class => $serviceId) {
            $container->services()->alias('test_public.'.$class, $serviceId)->public();
        }
    }

    public function getCacheDir(): string
    {
        return sys_get_temp_dir().'/uhifadhi-core-tests/cache/'.$this->getEnvironment().'/'.static::class;
    }

    public function getLogDir(): string
    {
        return sys_get_temp_dir().'/uhifadhi-core-tests/log';
    }
}
