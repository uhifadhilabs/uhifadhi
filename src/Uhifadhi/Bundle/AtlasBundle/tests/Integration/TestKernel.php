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

namespace Uhifadhi\Bundle\AtlasBundle\Tests\Integration;

use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Bundle\TwigBundle\TwigBundle;
use Symfony\Component\AssetMapper\AssetMapperInterface;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\UX\Map\UXMapBundle;
use Symfony\UX\StimulusBundle\StimulusBundle;
use Uhifadhi\Bundle\AtlasBundle\AtlasBundle;
use Uhifadhi\Bundle\AtlasBundle\Map\MapBuilderInterface;
use Uhifadhi\Bundle\AtlasBundle\Tests\Integration\Fixtures\CollectedModules;

use function Symfony\Component\DependencyInjection\Loader\Configurator\tagged_iterator;

/**
 * Smallest possible host app for integration tests: framework (with AssetMapper
 * on), twig, and the atlas. No doctrine and no database — this bundle owns no
 * entities.
 *
 * AssetMapper is enabled on purpose rather than for completeness: the whole
 * asset contract rests on a bundle being able to register its own directory under a
 * namespace and a host then naming logical paths inside it. If that ever stops
 * working, every map in the product goes blank, and this kernel is where it
 * gets noticed.
 *
 * The satellite provider is left UNCONFIGURED here, so the tests run against the
 * defaults a host gets for free — which is the case worth guarding.
 */
final class TestKernel extends Kernel
{
    use CheckoutTempDirTrait;
    use MicroKernelTrait;

    public function registerBundles(): iterable
    {
        yield new FrameworkBundle();
        yield new TwigBundle();
        yield new StimulusBundle();
        yield new UXMapBundle();
        yield new AtlasBundle();
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
            'assets' => null,
            'asset_mapper' => ['paths' => [__DIR__.'/Fixtures/assets']],
        ]);

        $container->extension('twig', [
            'default_path' => __DIR__.'/Fixtures/templates',
        ]);

        /*
         * The Leaflet renderer, named the way a host names it. The plate is
         * built on UX Map and its bridge, so a test that renders one has to go
         * through the real renderer or it proves nothing about the markup a
         * browser is served.
         */
        $container->extension('ux_map', ['renderer' => 'leaflet://default']);

        // Stands in for the HOST's module catalogue: the host collects every
        // service tagged "uhifadhi.module" and seeds its catalogue from them.
        // Tagged services are private, so this collector is what makes the
        // bundle's contribution observable from a test.
        $container->services()
            ->set(CollectedModules::class)
            ->args([tagged_iterator('uhifadhi.module')])
            ->public();

        // Both contracts the bundle exists to provide, made reachable from a test.
        $container->services()->alias('test.asset_mapper', AssetMapperInterface::class)->public();
        $container->services()->alias('test.twig', 'twig')->public();
        $container->services()->alias('test.atlas.map_builder', MapBuilderInterface::class)->public();
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
