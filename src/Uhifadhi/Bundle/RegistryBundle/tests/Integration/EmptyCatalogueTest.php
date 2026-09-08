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

use Uhifadhi\Bundle\RegistryBundle\RegistryBundle;
use Uhifadhi\Bundle\RegistryBundle\Tests\Integration\Fixtures\CollectedModules;

/**
 * THE FIRST INSTALLATION STEP: skeleton + registry, and no modules at all.
 *
 * This is what a real installation looks like the moment after
 * `composer require uhifadhi/uhifadhi` and before the first module — and it
 * has to be a boring, working, EMPTY installation: the container compiles, the
 * registry exists, and it carries nothing. A runtime that only works once somebody
 * installs a module is a runtime with a hidden dependency on modules, and the
 * whole point of a registry is that the modules attach to it rather than the
 * other way round.
 *
 * Zero is a real number of modules. It is also the first one every installation
 * has.
 */
final class EmptyCatalogueTest extends RegistryKernelTestCase
{
    public function testAnInstallationWithNoModulesBootsAndCarriesNothing(): void
    {
        self::bootKernel();

        /** @var CollectedModules $collected */
        $collected = self::getContainer()->get(CollectedModules::class);

        self::assertSame([], $collected->all(), 'no modules installed, so nothing is registered');
        self::assertSame([], $collected->bySlug());
    }

    /**
     * THE REGISTRY IS NOT A MODULE. It contributes no tile, no category and no
     * catalogue row of its own — it is the thing rows live in. A runtime that
     * registered itself would appear in every area's module grid as a
     * capability nobody can use.
     *
     * This is also what makes the assertion above meaningful: "empty" means
     * empty, not "empty apart from us".
     */
    public function testTheRegistryRegistersNoModuleOfItsOwn(): void
    {
        $kernel = self::bootKernel();

        self::assertArrayHasKey('RegistryBundle', $kernel->getBundles(), 'the registry is installed');

        /** @var CollectedModules $collected */
        $collected = self::getContainer()->get(CollectedModules::class);
        self::assertCount(0, $collected->all(), 'and it registered no module while it was at it');
    }

    /**
     * The tag string is published as a constant on the bundle, because the
     * registry is the end that collects it and a module bundle types the other end
     * by hand. Renaming it is a breaking change for every installed module, so
     * the value is pinned here rather than left to a refactor.
     */
    public function testTheModuleTagNameIsPartOfThePublishedContract(): void
    {
        self::assertSame('uhifadhi.module', RegistryBundle::MODULE_TAG);
    }
}
