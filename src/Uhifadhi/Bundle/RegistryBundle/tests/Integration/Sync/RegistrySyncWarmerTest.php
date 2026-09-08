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

namespace Uhifadhi\Bundle\RegistryBundle\Tests\Integration\Sync;

use Doctrine\ORM\Tools\SchemaTool;
use Uhifadhi\Bundle\RegistryBundle\CacheWarmer\RegistrySyncWarmer;
use Uhifadhi\Bundle\RegistryBundle\Service\AreaModuleService;
use Uhifadhi\Bundle\RegistryBundle\Tests\Integration\Fixtures\CollectedCacheWarmers;
use Uhifadhi\Bundle\RegistryBundle\Tests\Integration\InstallationTestCase;

/**
 * THE MECHANISM: A CACHE WARMER, NOT A COMMAND.
 *
 * Reconciling the registry with the installed module providers is a
 * once-per-deploy job, and Symfony has exactly one such hook. Cache warmers run
 * on `cache:warmup`, on `cache:clear`, and — if neither has run yet — on the
 * first request, which is the full set of moments a deploy can be said to have
 * happened. The core therefore ships no console command for it; devkit owns
 * commands.
 *
 * The half that is easy to get wrong is the FRESH INSTALL. `cache:clear` runs
 * before the first migration, so the warmer meets a database with no registry
 * tables in it — and a warmer that throws there breaks `cache:clear` itself,
 * which is the one command an operator has left when everything else is broken.
 */
final class RegistrySyncWarmerTest extends InstallationTestCase
{
    private function warmer(): RegistrySyncWarmer
    {
        $warmer = $this->service('registry.sync_warmer');
        \assert($warmer instanceof RegistrySyncWarmer);

        return $warmer;
    }

    /**
     * NOT OPTIONAL. The documented rule is "a warmer should return true if the
     * cache can be generated incrementally and on-demand" — this one cannot: an
     * installation whose registry was never reconciled has an empty catalogue
     * and every module page 404s. `--no-optional-warmers` must not skip it.
     *
     * @see https://symfony.com/doc/current/reference/dic_tags.html#kernel-cache-warmer
     */
    public function testTheSyncIsNotAnOptionalWarmer(): void
    {
        $this->install([]);

        self::assertFalse($this->warmer()->isOptional());
    }

    /**
     * It is a warmer as far as the framework is concerned, which is what makes
     * `cache:clear` on a deploy the whole of the operator's instructions.
     */
    public function testTheWarmerIsRegisteredWithTheFramework(): void
    {
        $this->install([]);

        $collected = self::getContainer()->get(CollectedCacheWarmers::class);
        \assert($collected instanceof CollectedCacheWarmers);

        self::assertContains(RegistrySyncWarmer::class, $collected->classNames());
    }

    /**
     * A CACHE WARMER PRELOADS NOTHING HERE. `warmUp()` returns the files and
     * classes to preload; this one writes rows, so the honest answer is the
     * empty array.
     */
    public function testTheWarmerReconcilesTheRegistryAndPreloadsNothing(): void
    {
        $this->install(['sightings']);
        $area = $this->area();

        self::assertSame([], $this->warmer()->warmUp(sys_get_temp_dir()));

        $this->em()->clear();
        $service = $this->service('registry.area_modules');
        \assert($service instanceof AreaModuleService);

        self::assertContains('sightings', array_map(
            static fn (object $areaModule): ?string => $areaModule->getModule()?->getSlug(),
            $service->allFor($area),
        ));
    }

    /**
     * THE FRESH-INSTALL GUARD. No tables yet — `cache:clear` still has to work,
     * and the sync says so rather than exploding. A warmer swallows what it
     * cannot do rather than fataling — the outer catch here is that guard.
     *
     * @see vendor/symfony/framework-bundle/CacheWarmer/ValidatorCacheWarmer.php
     */
    public function testWarmingUpBeforeTheFirstMigrationDoesNotBreakCacheClear(): void
    {
        $this->install(['sightings']);

        $metadata = $this->em()->getMetadataFactory()->getAllMetadata();
        new SchemaTool($this->em())->dropSchema($metadata);

        self::assertSame([], $this->warmer()->warmUp(sys_get_temp_dir()));
        self::assertTrue($this->sync()->skipped, 'no registry tables, nothing to reconcile');
    }
}
