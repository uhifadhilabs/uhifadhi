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
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Uhifadhi\Bundle\RegistryBundle\EventListener\RegistrySyncListener;
use Uhifadhi\Bundle\RegistryBundle\Service\AreaModuleService;
use Uhifadhi\Bundle\RegistryBundle\Tests\Integration\Fixtures\CollectedCacheWarmers;
use Uhifadhi\Bundle\RegistryBundle\Tests\Integration\InstallationTestCase;

/**
 * THE MECHANISM: A LISTENER ON THE MOMENTS A DEPLOY IS MADE OF, NOT A COMMAND
 * AND NOT A CACHE WARMER.
 *
 * Reconciling the registry with the installed module providers is a
 * once-per-deploy job, and the core ships no console command for it — devkit
 * owns commands. So it hangs off the two moments that bracket every deploy: a
 * console command has finished (`cache:clear` is the one an operator runs), or a
 * request has arrived. Whichever comes first does it, once per build, and the
 * stamp file in the cache directory is what "once per build" is made of.
 *
 * IT IS NOT A CACHE WARMER, AND CANNOT BE ONE: a warmer that reads a database
 * breaks the cache commands on a pristine prod cache. The kernel's own warm-up
 * pass, run while it compiles the container, skips the optional warmers —
 * doctrine-bundle's metadata warmer among them — and that warmer then refuses to
 * build its cache from a metadata factory somebody already filled.
 *
 * @see PristineCacheWarmUpTest the deploy that pins it
 *
 * The half that is easy to get wrong is the FRESH INSTALL. `cache:clear` runs
 * before the first migration, so the reconciliation meets a database with no
 * registry tables in it — and it must neither break the command it is attached
 * to nor remember that nothing was done as if something had been.
 */
final class RegistrySyncListenerTest extends InstallationTestCase
{
    private function listener(): RegistrySyncListener
    {
        $listener = $this->service('registry.sync_listener');
        \assert($listener instanceof RegistrySyncListener);

        return $listener;
    }

    /**
     * It is wired to both halves of a deploy as far as the framework is
     * concerned, which is what makes `cache:clear` the whole of the operator's
     * instructions.
     *
     * `console.terminate` and not `console.command`: the command a deploy runs is
     * `cache:warmup`, and reconciling before it executes would load ORM metadata
     * into the very pass that must not find any.
     */
    public function testTheListenerIsWiredToBothHalvesOfADeploy(): void
    {
        $this->install([]);

        $dispatcher = self::getContainer()->get('event_dispatcher');
        \assert($dispatcher instanceof EventDispatcherInterface);

        $listener = $this->listener();

        self::assertContains(
            [$listener, 'onConsoleTerminate'],
            $dispatcher->getListeners('console.terminate'),
        );
        self::assertContains(
            [$listener, 'onKernelRequest'],
            $dispatcher->getListeners('kernel.request'),
        );

        // Ahead of the parked-module gate, which reads the catalogue at 8.
        self::assertSame(
            16,
            $dispatcher->getListenerPriority('kernel.request', [$listener, 'onKernelRequest']),
        );
    }

    /**
     * AND IT IS NOT A CACHE WARMER. A warmer that reads the database breaks the
     * cache commands on a pristine prod cache, so the registry contributes none:
     * this asserts the tag list the framework's aggregate receives carries
     * nothing of the registry's.
     */
    public function testTheRegistryContributesNoCacheWarmer(): void
    {
        $this->install([]);

        $collected = self::getContainer()->get(CollectedCacheWarmers::class);
        \assert($collected instanceof CollectedCacheWarmers);

        foreach ($collected->classNames() as $warmer) {
            self::assertStringNotContainsString('Uhifadhi\\', $warmer);
        }
    }

    public function testItReconcilesTheRegistry(): void
    {
        $this->install(['sightings']);
        $area = $this->area();

        // A new build: the area was configured after the last deploy, and this
        // is the deploy that gives it its rows.
        $this->reconcile();

        $this->em()->clear();
        $service = $this->service('registry.area_modules');
        \assert($service instanceof AreaModuleService);

        self::assertContains('sightings', array_map(
            static fn (object $areaModule): ?string => $areaModule->getModule()?->getSlug(),
            $service->allFor($area),
        ));
    }

    /**
     * ONCE PER BUILD, AND THE STAMP IS WHAT SAYS SO. Without it this would be a
     * reconciliation on every request forever, to answer a question a deploy
     * asks once. The proof is a row removed by hand that a second call does not
     * put back.
     */
    public function testAReconciledBuildIsNotReconciledAgain(): void
    {
        $this->install(['sightings']);

        self::assertFileExists($this->listener()->stampFile);

        $this->em()->getConnection()->executeStatement('DELETE FROM area_module');
        $this->em()->getConnection()->executeStatement('DELETE FROM module');

        $this->listener()->reconcileOnce();

        self::assertSame(
            [],
            $this->em()->getConnection()->fetchFirstColumn('SELECT slug FROM module'),
            'this build was reconciled already; the listener asked nothing',
        );
    }

    /**
     * THE FRESH-INSTALL GUARD. No tables yet — `cache:clear` still has to work,
     * and the reconciliation says so rather than exploding.
     *
     * AND IT MUST NOT BE REMEMBERED AS DONE: the operator's next step is the
     * first migration, and the request after it is the one that fills the
     * catalogue. A stamp left behind here would leave an installation with an
     * empty catalogue until its second deploy.
     */
    public function testReconcilingBeforeTheFirstMigrationIsNotRememberedAsDone(): void
    {
        $this->install(['sightings']);

        $metadata = $this->em()->getMetadataFactory()->getAllMetadata();
        $tool = new SchemaTool($this->em());
        $tool->dropSchema($metadata);

        @unlink($this->listener()->stampFile);
        $this->listener()->reconcileOnce();

        self::assertFileDoesNotExist($this->listener()->stampFile);
        self::assertTrue($this->sync()->skipped, 'no registry tables, nothing to reconcile');

        // …and the migration's turn comes: the tables appear, and the next
        // request reconciles.
        $tool->createSchema($metadata);
        $this->listener()->reconcileOnce();

        self::assertSame(
            ['sightings'],
            $this->em()->getConnection()->fetchFirstColumn('SELECT slug FROM module'),
        );
    }
}
