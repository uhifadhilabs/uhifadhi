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

use Doctrine\DBAL\Connection;
use Doctrine\Migrations\DependencyFactory;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Uhifadhi\Core\Tests\Application\Kernel;

/**
 * The base the migration specifications share: an EMPTY database, and the
 * console an installer types into.
 *
 * A migration is only ever proven by running it, so these tests do what an
 * installer does — `doctrine:migrations:migrate` through the console, against a
 * database with nothing in it. Symfony's documented way to drive a command in
 * process is a `FrameworkBundle\Console\Application` built on a booted kernel.
 *
 * @see https://symfony.com/doc/current/console.html#testing-commands
 * @see vendor/symfony/framework-bundle/Console/Application.php
 */
abstract class MigrationsTestCase extends KernelTestCase
{
    protected Connection $connection;

    protected static function getKernelClass(): string
    {
        return Kernel::class;
    }

    protected function setUp(): void
    {
        self::bootKernel();

        /** @var Connection $connection */
        $connection = static::getContainer()->get('doctrine.dbal.default_connection');
        $this->connection = $connection;

        $this->emptyTheDatabase();
    }

    protected function tearDown(): void
    {
        $this->connection->close();
        parent::tearDown();

        // KernelTestCase leaves the handlers a booted kernel installed on the
        // stack; PHPUnit fails a test that ends with more of them than it began
        // with. The same loop every DB-backed base in this repository runs.
        while (true) {
            $previous = set_exception_handler(static fn () => null);
            restore_exception_handler();
            if (null === $previous) {
                break;
            }
            restore_exception_handler();
        }
    }

    /**
     * Nothing at all: no tables, no `doctrine_migration_versions`, and no
     * postgis extension either — the state migration zero is written for.
     */
    protected function emptyTheDatabase(): void
    {
        $this->connection->executeStatement('DROP SCHEMA IF EXISTS public CASCADE');
        $this->connection->executeStatement('CREATE SCHEMA public');
    }

    /**
     * A SECOND `migrate` IN ONE PROCESS NEEDS A SECOND KERNEL.
     *
     * The repository hands out one instance per version and the executor freezes
     * it once it has run, so asking the same instance for its SQL again throws
     * `FrozenMigration`. A real installation never meets this — each `migrate`
     * is its own process — and a test that runs up, down and up again has to
     * arrange the same thing for itself.
     *
     * @see vendor/doctrine/migrations/src/AbstractMigration.php — `freeze()`
     * @see vendor/doctrine/migrations/src/Version/DbalExecutor.php
     */
    protected function asAFreshProcess(): void
    {
        $this->connection->close();

        self::ensureKernelShutdown();
        self::bootKernel();

        /** @var Connection $connection */
        $connection = static::getContainer()->get('doctrine.dbal.default_connection');
        $this->connection = $connection;
    }

    /**
     * @param array<string, bool|string> $arguments
     */
    protected function console(string $command, array $arguments = []): string
    {
        $kernel = self::$kernel;
        self::assertNotNull($kernel);

        $application = new Application($kernel);
        $application->setAutoExit(false);
        $application->setCatchExceptions(false);

        $output = new BufferedOutput();
        $status = $application->run(new ArrayInput(['command' => $command] + $arguments), $output);

        $text = $output->fetch();

        self::assertSame(0, $status, \sprintf('`%s` failed:%s%s', $command, \PHP_EOL, $text));

        return $text;
    }

    /**
     * The migrations Doctrine actually knows about, in the order it will run
     * them — read off the same dependency factory the console commands use.
     *
     * @return list<string>
     */
    protected function plannedVersions(): array
    {
        /** @var DependencyFactory $factory */
        $factory = static::getContainer()->get('doctrine.migrations.dependency_factory');

        $versions = [];
        foreach ($factory->getMigrationPlanCalculator()->getMigrations()->getItems() as $migration) {
            $versions[] = (string) $migration->getVersion();
        }

        return $versions;
    }

    /**
     * @return list<string>
     */
    protected function tableNames(): array
    {
        /** @var list<string> $names */
        $names = $this->connection->fetchFirstColumn(
            "SELECT tablename FROM pg_tables WHERE schemaname = 'public' ORDER BY tablename"
        );

        return $names;
    }
}
