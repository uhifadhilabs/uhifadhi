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

namespace Uhifadhi\Core\Tests\Core;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpKernel\CacheWarmer\CacheWarmerAggregate;
use Uhifadhi\Bundle\AtlasBundle\AtlasBundle;
use Uhifadhi\Bundle\RegistryBundle\RegistryBundle;
use Uhifadhi\Bundle\ShellBundle\ShellBundle;
use Uhifadhi\Core\Tests\Application\Kernel;

/**
 * THE CORE AS ONE INSTALLATION.
 *
 * Every bundle here has a suite that boots it in the smallest kernel it can
 * live in, which is the right shape for a specification ABOUT that bundle and
 * the wrong shape for the only question this file asks: do they compile
 * together, in one container, with one Twig, one router and one database?
 *
 * They are released together, so that is not an integration test in the
 * optional sense — it is the product. A bundle that compiles alone and
 * conflicts with its siblings has passed its own suite and broken the package.
 */
final class CoreBootTest extends KernelTestCase
{
    protected static function getKernelClass(): string
    {
        return Kernel::class;
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        // The framework's debug exception handler is registered while a kernel
        // boots and never popped, which PHPUnit reports as a risky test.
        while (true) {
            $previous = set_exception_handler(static fn () => null);
            restore_exception_handler();
            if (null === $previous) {
                break;
            }
            restore_exception_handler();
        }
    }

    public function testEveryCoreBundleCompilesInOneContainer(): void
    {
        $kernel = self::bootKernel();

        foreach ([RegistryBundle::class, ShellBundle::class, AtlasBundle::class] as $bundle) {
            self::assertArrayHasKey(
                substr($bundle, (int) strrpos($bundle, '\\') + 1),
                $kernel->getBundles(),
                $bundle.' must be installed.',
            );
        }
    }

    /**
     * EACH BUNDLE'S CONFIG IS KEYED BY ITS OWN WORD, and the three cannot
     * collide because they are three words. An installation writes
     * config/packages/registry.yaml, shell.yaml and atlas.yaml, one per bundle,
     * exactly as it would if each were its own package.
     */
    public function testEachBundleKeepsItsOwnConfigurationRoot(): void
    {
        $kernel = self::bootKernel();

        $aliases = [];
        foreach (['RegistryBundle', 'ShellBundle', 'AtlasBundle'] as $name) {
            $aliases[$name] = $kernel->getBundle($name)->getContainerExtension()?->getAlias();
        }

        self::assertSame(
            ['RegistryBundle' => 'registry', 'ShellBundle' => 'shell', 'AtlasBundle' => 'atlas'],
            $aliases,
        );
    }

    /**
     * A DEPLOY IS `cache:clear`, and this is why: warming the cache reconciles
     * the registry with whatever module providers the installation carries. The
     * warmer runs here against a database with no registry tables in it — the
     * state a fresh installation is in before its first migration — and the
     * whole point is that it does not throw.
     */
    public function testWarmingTheCacheReconcilesTheRegistryAndSurvivesAFreshInstall(): void
    {
        $kernel = self::bootKernel();

        $warmer = self::getContainer()->get('cache_warmer');
        \assert($warmer instanceof CacheWarmerAggregate);

        $warmer->enableOptionalWarmers();
        $warmer->warmUp($kernel->getCacheDir(), $kernel->getBuildDir());

        $this->expectNotToPerformAssertions();
    }
}
