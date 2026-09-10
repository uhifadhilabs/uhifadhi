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

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Uhifadhi\Bundle\AtlasBundle\AtlasBundle;

/**
 * The smoke test: registering the bundle in a real kernel compiles a real
 * container. Everything else in this repo rides on that.
 */
final class BundleBootTest extends KernelTestCase
{
    /**
     * The kernel is named in code rather than through `KERNEL_CLASS`: the core is
     * one repository with one phpunit config and several bundles, so an env var
     * can only ever name one of their kernels.
     */
    protected static function getKernelClass(): string
    {
        return TestKernel::class;
    }

    public function testTheBundleBootsInAHostKernel(): void
    {
        $kernel = self::bootKernel();

        self::assertArrayHasKey('AtlasBundle', $kernel->getBundles());
        self::assertInstanceOf(
            AtlasBundle::class,
            $kernel->getBundle('AtlasBundle'),
        );
    }

    /**
     * Config lives under "atlas:", not the class-derived "atlas_bundle:" —
     * the alias is part of the host contract.
     */
    public function testItsConfigurationIsKeyedByTheMapAlias(): void
    {
        $kernel = self::bootKernel();

        self::assertSame('atlas', $kernel->getBundle('AtlasBundle')
            ->getContainerExtension()?->getAlias());
    }

    /**
     * ONE LEAFLET ON THE PAGE, and it is the one the UX Map bridge imports from
     * the host's importmap. A second copy served out of this bundle would be a
     * second Leaflet namespace: layers built against one and added to a map
     * built by the other, which fails in ways nobody can read.
     */
    public function testThisBundleShipsNoLeafletOfItsOwn(): void
    {
        self::assertDirectoryDoesNotExist(\dirname(__DIR__, 2).'/public/leaflet');
    }

    /**
     * The platform's one map stylesheet — the chrome that chrome.js builds, the
     * imagery frame and the zone label — is a constant for the same reason, and
     * ships out of the same public/ dir. A consumer links it by this path.
     */
    public function testTheStylesheetPointsAtAFileThisBundleReallyShips(): void
    {
        $public = \dirname(__DIR__, 2).'/public';

        self::assertFileExists($public.'/map.css');
        self::assertSame('bundles/atlas/map.css', AtlasBundle::STYLESHEET);
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        // The framework's debug error handler is registered during the test and
        // never popped; PHPUnit flags that as risky. Pop whatever is left.
        while (true) {
            $previous = set_exception_handler(static fn () => null);
            restore_exception_handler();
            if (null === $previous) {
                break;
            }
            restore_exception_handler();
        }
    }
}
