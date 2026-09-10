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

use Doctrine\Migrations\DependencyFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use Uhifadhi\Bundle\RegistryBundle\DependencyInjection\Compiler\InstallationMigrationsPathFirstPass;
use Uhifadhi\Core\Tests\Application\Kernel;

/**
 * A GENERATED VERSION MUST NEVER LAND IN A PACKAGE DIRECTORY.
 *
 * `doctrine:migrations:diff` and `doctrine:migrations:generate` take the
 * namespace they write into from the flag, and without the flag they take the
 * FIRST configured one:
 *
 * > $dirs = $configuration->getMigrationDirectories();
 * > if ($namespace === null && count($dirs) === 1) {
 * >     $namespace = key($dirs);
 * > } elseif ($namespace === null && count($dirs) > 1) {
 * >     $question = new ChoiceQuestion('Please choose a namespace (defaults to the first one)', array_keys($dirs), 0);
 *
 * (`DoctrineCommand::getNamespace()`, which `DiffCommand` and `GenerateCommand`
 * both call.)
 *
 * Left alone that first entry is a bundle's, because every bundle prepends its
 * own path and an application's own config is merged in last:
 *
 * > foreach ($config['migrations_paths'] as $ns => $path) {
 * >     $configurationDefinition->addMethodCall('addMigrationsDirectory', [$ns, $path]);
 *
 * A version written under `vendor/` is deleted by the next `composer update`
 * while its row in `doctrine_migration_versions` stays, which is an
 * installation's history gone and a ledger that lies about it.
 *
 * @see https://symfony.com/bundles/DoctrineMigrationsBundle/current/index.html
 * @see vendor/doctrine/migrations/src/Tools/Console/Command/DoctrineCommand.php
 * @see vendor/doctrine/migrations/src/Tools/Console/Command/DiffCommand.php
 * @see vendor/doctrine/migrations/src/Tools/Console/Command/GenerateCommand.php
 * @see vendor/doctrine/doctrine-migrations-bundle/src/DependencyInjection/DoctrineMigrationsExtension.php
 */
#[CoversClass(InstallationMigrationsPathFirstPass::class)]
final class InstallationOwnsTheFirstMigrationsPathTest extends MigrationsTestCase
{
    public function testTheInstallationsOwnNamespaceIsTheFirstOneConfigured(): void
    {
        $directories = $this->migrationDirectories();

        self::assertSame(
            'DoctrineMigrations',
            array_key_first($directories),
            'the namespace an installation maps in its own config/packages must be the one a flagless diff falls back to',
        );
    }

    public function testTheNamespacesAPackageShipsKeepTheirOrderBehindIt(): void
    {
        self::assertSame(
            [
                'DoctrineMigrations',
                'Uhifadhi\\Bundle\\AreaBundle\\Migrations',
                'Uhifadhi\\Bundle\\TeamBundle\\Migrations',
                'Uhifadhi\\Bundle\\ShellBundle\\Migrations',
                'Uhifadhi\\Bundle\\RegistryBundle\\Migrations',
            ],
            array_keys($this->migrationDirectories()),
            'only the installation moves; the packages stay in the order they were registered in',
        );
    }

    /**
     * The whole point, run rather than reasoned about.
     *
     * An empty database is the biggest difference there is between what the
     * entities describe and what is there, so `diff` always has a version to
     * write here — no flag given, exactly as an installation runs it for its
     * own entities.
     */
    public function testADiffWithNoNamespaceWritesIntoTheInstallationsDirectory(): void
    {
        $packageVersionsBefore = $this->packageVersionFiles();

        $this->console('doctrine:migrations:diff', ['--no-interaction' => true]);

        $written = $this->installationVersionFiles();
        $strays = array_values(array_diff($this->packageVersionFiles(), $packageVersionsBefore));

        // Before the assertions, because a run that FAILS is the run that put a
        // file in a package directory, and it must not leave it in the checkout.
        foreach ([...$written, ...$strays] as $file) {
            unlink($file);
        }

        self::assertSame(
            [],
            $strays,
            'nothing may appear under src/Uhifadhi/Bundle: those directories are vendor/ on an installation',
        );

        self::assertCount(
            1,
            $written,
            'a flagless diff against an empty database writes exactly one version, and it writes it here',
        );
    }

    /**
     * @return array<string, string>
     */
    private function migrationDirectories(): array
    {
        /** @var DependencyFactory $factory */
        $factory = static::getContainer()->get('doctrine.migrations.dependency_factory');

        return $factory->getConfiguration()->getMigrationDirectories();
    }

    /**
     * @return list<string>
     */
    private function installationVersionFiles(): array
    {
        $kernel = self::$kernel;
        self::assertInstanceOf(Kernel::class, $kernel);

        $files = glob($kernel->installationMigrationsDir().'/*.php') ?: [];
        sort($files);

        return $files;
    }

    /**
     * @return list<string>
     */
    private function packageVersionFiles(): array
    {
        $files = glob(\dirname(__DIR__, 2).'/src/Uhifadhi/Bundle/*/migrations/*.php') ?: [];
        sort($files);

        return $files;
    }
}
