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

namespace Uhifadhi\Bundle\AtlasBundle\Tests\Integration\Module;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Uhifadhi\Bundle\AtlasBundle\Tests\Integration\Fixtures\CollectedModules;
use Uhifadhi\Bundle\AtlasBundle\Tests\Integration\TestKernel;

/**
 * MAP IS INFRASTRUCTURE, NOT A CATALOGUE MODULE.
 *
 * The two tiers: an INFRASTRUCTURE module is machinery every map-bearing screen
 * imports — installed means on, never a per-area choice — and a CAPABILITY module
 * (patrol, incident) is the per-area grid an admin switches on. Map is the former.
 * A deployment that installs this bundle has already decided; nothing in the
 * product draws a tile without it, so there is no honest per-area toggle to offer.
 *
 * Concretely that means this bundle contributes NO "uhifadhi.module" provider:
 * nothing for the contract to collect, nothing for the catalogue to list, no per-area
 * ledger row and no route to gate. All of its rendering machinery stays — Leaflet,
 * the basemap contract, the boundary and chrome assets, the Twig extension — only its
 * per-AreaBundle identity is gone. This test is what keeps it gone.
 */
final class AtlasIsNotACatalogueModuleTest extends KernelTestCase
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

    public function testTheAtlasContributesNoModuleToTheCatalogue(): void
    {
        self::bootKernel();

        /** @var CollectedModules $catalogue */
        $catalogue = self::getContainer()->get(CollectedModules::class);

        self::assertSame(
            [],
            $catalogue->bySlug(),
            'the map bundle still tags a "uhifadhi.module" provider — it must be infrastructure, not a catalogue module',
        );
    }

    public function testTheMapBundleRegistersNoModuleProviderService(): void
    {
        self::bootKernel();

        self::assertFalse(
            self::getContainer()->has('map.module_provider'),
            'the map bundle still registers a per-area module provider service',
        );
    }

    protected function tearDown(): void
    {
        parent::tearDown();

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
