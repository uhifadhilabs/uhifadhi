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

namespace Uhifadhi\Bundle\RegistryBundle\Tests\Integration;

use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel;
use Uhifadhi\Bundle\RegistryBundle\RegistryBundle;
use Uhifadhi\Bundle\RegistryBundle\Tests\Integration\Fixtures\CollectedCacheWarmers;
use Uhifadhi\Bundle\RegistryBundle\Tests\Integration\Fixtures\CollectedModules;

use function Symfony\Component\DependencyInjection\Loader\Configurator\tagged_iterator;

/**
 * THE SKELETON, PLUS THE REGISTRY, AND NOTHING ELSE: framework + doctrine + this
 * bundle. That is not a convenience for testing — it is the installation this
 * bundle exists to make possible, and the kernel here is as close as a test can
 * get to a fresh project with the registry on it and nothing more.
 *
 * It opens no database connection. That is what the bundle's own boot has to
 * survive — a host that has not migrated yet, or has not resolved the area
 * interface, still boots. The specifications that need the catalogue tables
 * boot {@see Fixtures\HostKernel}
 * instead, which adds a stand-in host on top of this one.
 */
class TestKernel extends Kernel
{
    use CheckoutTempDirTrait;
    use MicroKernelTrait;

    public function registerBundles(): iterable
    {
        yield new FrameworkBundle();
        yield new DoctrineBundle();
        yield new RegistryBundle();
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
        ]);

        $container->extension('doctrine', [
            'dbal' => ['url' => '%env(UHIFADHI_TEST_DATABASE_URL)%'],
        ]);

        // The collecting end of the registry, made observable. Tagged services are
        // private, so without this fixture a test cannot see what reached the
        // registry at all. In a real installation the registry's own catalogue is what
        // receives this iterator.
        $services = $container->services();

        $services->set(CollectedModules::class)
            ->args([tagged_iterator(RegistryBundle::MODULE_TAG)])
            ->public();

        // The framework's own warmer list, made observable for the same reason:
        // a tag nothing can see is a tag nobody can assert.
        $services->set(CollectedCacheWarmers::class)
            ->args([tagged_iterator('kernel.cache_warmer')])
            ->public();
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
