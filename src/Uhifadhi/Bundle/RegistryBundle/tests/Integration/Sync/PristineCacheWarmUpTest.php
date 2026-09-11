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

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\DataProvider;
use Psr\Container\ContainerInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Uhifadhi\Bundle\RegistryBundle\Tests\Integration\Fixtures\DeployedHostKernel;
use Uhifadhi\Bundle\RegistryBundle\Tests\Integration\Fixtures\HostKernel;
use Uhifadhi\Bundle\RegistryBundle\Tests\Integration\InstallationTestCase;

/**
 * A DEPLOY, AS THE OPERATOR RUNS IT: the two cache commands, without debug, on
 * a cache directory that holds nothing.
 *
 * That is the state a production image is built in, and it is the one state the
 * rest of this suite cannot reach: every other specification boots a debug
 * kernel, where doctrine-bundle registers no metadata cache warmer at all, so
 * the rule that warmer enforces is invisible to them.
 *
 * The rule is that NOBODY MAY LOAD ORM METADATA BEFORE IT DOES. It refuses to
 * build its PHP-array metadata cache from a factory somebody already filled,
 * and the refusal is fatal to the command:
 *
 *   "DoctrineMetadataCacheWarmer must load metadata first, check priority of
 *    your warmers."
 *
 * @see vendor/doctrine/doctrine-bundle/src/CacheWarmer/DoctrineMetadataCacheWarmer.php
 *
 * The kernel warms the cache ONCE WHILE IT COMPILES THE CONTAINER, and that
 * pass runs the non-optional warmers only — Doctrine's is optional, so it is
 * not in it. A non-optional warmer that reads the database therefore loads
 * metadata in a pass Doctrine's warmer is absent from, and poisons the pass the
 * command runs next, in the same process.
 * @see vendor/symfony/http-kernel/Kernel.php — `initializeContainer()` warms the cache after a rebuild, enabling the optional warmers only when the cache and build directories differ
 */
final class PristineCacheWarmUpTest extends InstallationTestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function deployCommands(): iterable
    {
        yield 'cache:warmup' => ['cache:warmup'];
        yield 'cache:clear' => ['cache:clear'];
    }

    #[DataProvider('deployCommands')]
    public function testADeployCommandSucceedsOnAPristineCacheAndReconcilesTheRegistry(string $command): void
    {
        // A migrated installation carrying an area, and no module on it yet:
        // the catalogue is what the deploy has to fill.
        $this->install([]);
        $this->area('Deployed area');

        $kernel = $this->deployed(['sightings' => []]);
        $output = new BufferedOutput();

        $application = new Application($kernel);
        $application->setAutoExit(false);
        $status = $application->run(new ArrayInput(['command' => $command]), $output);

        self::assertSame(0, $status, $output->fetch());

        $connection = $this->connection($kernel);
        self::assertSame(
            ['sightings'],
            $connection->fetchFirstColumn('SELECT slug FROM module ORDER BY slug'),
            'the deploy reconciled the catalogue with the installed providers',
        );
        self::assertCount(
            1,
            $connection->fetchFirstColumn('SELECT id FROM area_module'),
            'and gave the area its row',
        );

        $kernel->shutdown();
    }

    /**
     * Doctrine's own warmer did its work rather than being skipped — the file it
     * writes is the evidence, and an installation's runtime reads metadata from
     * it instead of parsing attributes on every request.
     */
    public function testWarmingUpAPristineCacheBuildsTheMetadataCache(): void
    {
        $this->install([]);

        $kernel = $this->deployed(['sightings' => []]);

        $application = new Application($kernel);
        $application->setAutoExit(false);
        $application->run(new ArrayInput(['command' => 'cache:warmup']), new BufferedOutput());

        self::assertFileExists($kernel->getBuildDir().'/doctrine/orm/default_metadata.php');

        $kernel->shutdown();
    }

    /**
     * @param array<string, array<string, mixed>> $modules
     */
    private function deployed(array $modules): DeployedHostKernel
    {
        self::ensureKernelShutdown();
        HostKernel::$modules = $modules;

        $kernel = new DeployedHostKernel('prod', false);
        self::remove($kernel->getCacheDir());
        $kernel->boot();

        return $kernel;
    }

    private function connection(DeployedHostKernel $kernel): Connection
    {
        // `framework.test` publishes a locator over the private services, which
        // is how a specification reaches an entity manager in a kernel it is
        // driving by hand.
        $container = $kernel->getContainer()->get('test.service_container');
        \assert($container instanceof ContainerInterface);

        $connection = $container->get('doctrine.dbal.default_connection');
        \assert($connection instanceof Connection);

        return $connection;
    }

    private static function remove(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }

        $entries = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($entries as $entry) {
            \assert($entry instanceof \SplFileInfo);
            $entry->isDir() ? rmdir($entry->getPathname()) : unlink($entry->getPathname());
        }

        rmdir($directory);
    }
}
