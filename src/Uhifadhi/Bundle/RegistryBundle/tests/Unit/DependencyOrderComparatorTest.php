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

namespace Uhifadhi\Bundle\RegistryBundle\Tests\Unit;

use Doctrine\Migrations\Configuration\Configuration;
use Doctrine\Migrations\Version\Version;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Uhifadhi\Bundle\RegistryBundle\Version\DependencyOrderComparator;

/**
 * The rule, stated as an installation meets it: a package's versions run after
 * the versions of every package it requires, and the timestamp only orders
 * versions that belong to the same package.
 *
 * The graph here is a fixture rather than this repository's own Composer data,
 * because the shapes worth locking — a module older than the core, a module
 * requiring another module, a name reached through `replace`, a cycle — are
 * shapes no single checkout has all of at once.
 */
#[CoversClass(DependencyOrderComparator::class)]
final class DependencyOrderComparatorTest extends TestCase
{
    private const string INSTALLATION = '/fixture';

    private const string VENDOR = '/fixture/vendor/';

    public function testAModulesVersionRunsAfterTheCoresEvenWhenItsTimestampIsOlder(): void
    {
        self::assertSame(
            [
                'Acme\\Core\\Shell\\Migrations\\Version20260101000400',
                'Acme\\First\\Migrations\\Version20200101000000',
            ],
            $this->sorted([
                'Acme\\First\\Migrations\\Version20200101000000',
                'Acme\\Core\\Shell\\Migrations\\Version20260101000400',
            ]),
        );
    }

    public function testAModuleThatRequiresAnotherModuleRunsAfterIt(): void
    {
        self::assertSame(
            [
                'Acme\\Core\\Area\\Migrations\\Version20260101000100',
                'Acme\\First\\Migrations\\Version20200101000000',
                'Acme\\Second\\Migrations\\Version20100101000000',
            ],
            $this->sorted([
                'Acme\\Second\\Migrations\\Version20100101000000',
                'Acme\\First\\Migrations\\Version20200101000000',
                'Acme\\Core\\Area\\Migrations\\Version20260101000100',
            ]),
        );
    }

    /**
     * The name a module writes in its manifest may be one nobody installed: the
     * core is one package that answers to a name per bundle, and a module that
     * requires the registry alone must still be placed behind the whole core.
     */
    public function testANameReachedThroughReplaceCountsAsTheReplacingPackage(): void
    {
        self::assertSame(
            [
                'Acme\\Core\\Shell\\Migrations\\Version20260101000400',
                'Acme\\Replacing\\Migrations\\Version20200101000000',
            ],
            $this->sorted([
                'Acme\\Replacing\\Migrations\\Version20200101000000',
                'Acme\\Core\\Shell\\Migrations\\Version20260101000400',
            ]),
        );
    }

    /**
     * Two namespaces, one package: the core ships a directory per bundle and one
     * install path, so nothing about the namespaces decides and the date does.
     */
    public function testTheTimestampOrdersVersionsInsideOnePackage(): void
    {
        self::assertSame(
            [
                'Acme\\Core\\Area\\Migrations\\Version20260101000100',
                'Acme\\Core\\Shell\\Migrations\\Version20260101000400',
            ],
            $this->sorted([
                'Acme\\Core\\Shell\\Migrations\\Version20260101000400',
                'Acme\\Core\\Area\\Migrations\\Version20260101000100',
            ]),
        );
    }

    public function testTwoVersionsSharingATimestampFallBackToTheirName(): void
    {
        self::assertSame(
            [
                'Acme\\Core\\Area\\Migrations\\Version20260101000100',
                'Acme\\Core\\Shell\\Migrations\\Version20260101000100',
            ],
            $this->sorted([
                'Acme\\Core\\Shell\\Migrations\\Version20260101000100',
                'Acme\\Core\\Area\\Migrations\\Version20260101000100',
            ]),
        );
    }

    /**
     * A name carrying no date has opted out of being placed; after everything
     * dated in its own package is the only answer that cannot reorder a dated
     * one behind its own foreign key.
     */
    public function testANameThatCarriesNoTimestampSortsLastInsideItsPackage(): void
    {
        self::assertSame(
            [
                'Acme\\Core\\Area\\Migrations\\Version20260101000100',
                'Acme\\Core\\Area\\Migrations\\VersionHandWritten',
                'Acme\\First\\Migrations\\Version20200101000000',
            ],
            $this->sorted([
                'Acme\\Core\\Area\\Migrations\\VersionHandWritten',
                'Acme\\First\\Migrations\\Version20200101000000',
                'Acme\\Core\\Area\\Migrations\\Version20260101000100',
            ]),
        );
    }

    public function testTheInstallationsOwnVersionsRunLastWhateverTheirDate(): void
    {
        self::assertSame(
            [
                'Acme\\Core\\Area\\Migrations\\Version20260101000100',
                'Acme\\Second\\Migrations\\Version20100101000000',
                'DoctrineMigrations\\Version20100101000000',
            ],
            $this->sorted([
                'DoctrineMigrations\\Version20100101000000',
                'Acme\\Second\\Migrations\\Version20100101000000',
                'Acme\\Core\\Area\\Migrations\\Version20260101000100',
            ]),
        );
    }

    /**
     * A directory inside no installed package at all is the installation's too:
     * an application is free to keep its versions anywhere, and wherever it
     * keeps them they run after every package's.
     */
    public function testADirectoryNoInstalledPackageOwnsRunsLastAsWell(): void
    {
        self::assertSame(
            [
                'Acme\\Core\\Area\\Migrations\\Version20260101000100',
                'Elsewhere\\Migrations\\Version20100101000000',
            ],
            $this->sorted([
                'Elsewhere\\Migrations\\Version20100101000000',
                'Acme\\Core\\Area\\Migrations\\Version20260101000100',
            ]),
        );
    }

    public function testPackagesShippingMigrationsThatRequireEachOtherAreRefusedByName(): void
    {
        $comparator = new DependencyOrderComparator(
            $this->configuration(),
            [
                $this->package('acme/first-module', self::VENDOR.'acme/first-module', ['acme/second-module']),
                $this->package('acme/second-module', self::VENDOR.'acme/second-module', ['acme/first-module']),
            ],
        );

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('acme/first-module, acme/second-module');

        $comparator->compare(
            new Version('Acme\\First\\Migrations\\Version20200101000000'),
            new Version('Acme\\Second\\Migrations\\Version20100101000000'),
        );
    }

    /**
     * A cycle between packages that ship no migrations is not this comparator's
     * business, and refusing to migrate over one would refuse over a shape real
     * installations have: `league/flysystem` and `league/flysystem-local`
     * require each other, and either can be somewhere under a module.
     */
    public function testACycleBetweenPackagesThatShipNoMigrationsIsNotRefused(): void
    {
        $comparator = new DependencyOrderComparator(
            $this->configuration(),
            [
                ...$this->packages(),
                $this->package('league/flysystem', self::VENDOR.'league/flysystem', ['league/flysystem-local']),
                $this->package('league/flysystem-local', self::VENDOR.'league/flysystem-local', ['league/flysystem']),
            ],
        );

        $versions = array_map(
            static fn (string $name): Version => new Version($name),
            [
                'Acme\\First\\Migrations\\Version20200101000000',
                'Acme\\Core\\Area\\Migrations\\Version20260101000100',
            ],
        );

        usort($versions, $comparator->compare(...));

        self::assertSame(
            [
                'Acme\\Core\\Area\\Migrations\\Version20260101000100',
                'Acme\\First\\Migrations\\Version20200101000000',
            ],
            array_map(strval(...), $versions),
        );
    }

    /**
     * The dependency between two packages may run through packages that ship no
     * migrations at all, and it still places them.
     */
    public function testAPackageIsPlacedBehindOneItReachesThroughAPackageWithNoMigrations(): void
    {
        $comparator = new DependencyOrderComparator(
            $this->configuration(),
            [
                $this->package('acme/core', self::VENDOR.'acme/core', []),
                $this->package('acme/plumbing', self::VENDOR.'acme/plumbing', ['acme/core']),
                $this->package('acme/first-module', self::VENDOR.'acme/first-module', ['acme/plumbing']),
            ],
        );

        $versions = array_map(
            static fn (string $name): Version => new Version($name),
            [
                'Acme\\First\\Migrations\\Version20200101000000',
                'Acme\\Core\\Shell\\Migrations\\Version20260101000400',
            ],
        );

        usort($versions, $comparator->compare(...));

        self::assertSame(
            [
                'Acme\\Core\\Shell\\Migrations\\Version20260101000400',
                'Acme\\First\\Migrations\\Version20200101000000',
            ],
            array_map(strval(...), $versions),
        );
    }

    /**
     * @param list<string> $names
     *
     * @return list<string>
     */
    private function sorted(array $names): array
    {
        $comparator = new DependencyOrderComparator($this->configuration(), $this->packages());

        $versions = array_map(static fn (string $name): Version => new Version($name), $names);
        usort($versions, $comparator->compare(...));

        return array_map(strval(...), $versions);
    }

    /**
     * The namespaces an installation of the fixture graph would have: two
     * directories inside the one core package, one per module, the
     * installation's own, and one belonging to nobody.
     */
    private function configuration(): Configuration
    {
        $configuration = new Configuration();

        foreach ([
            'DoctrineMigrations' => self::INSTALLATION.'/migrations',
            'Acme\\Core\\Area\\Migrations' => self::VENDOR.'acme/core/src/Area/migrations',
            'Acme\\Core\\Shell\\Migrations' => self::VENDOR.'acme/core/src/Shell/migrations',
            'Acme\\First\\Migrations' => self::VENDOR.'acme/first-module/migrations',
            'Acme\\Second\\Migrations' => self::VENDOR.'acme/second-module/migrations',
            'Acme\\Replacing\\Migrations' => self::VENDOR.'acme/replacing-module/migrations',
            'Elsewhere\\Migrations' => '/somewhere/else/migrations',
        ] as $namespace => $directory) {
            $configuration->addMigrationsDirectory($namespace, $directory);
        }

        return $configuration;
    }

    /**
     * @return list<array{name: string, path: string, require: list<string>, replace: list<string>}>
     */
    private function packages(): array
    {
        return [
            $this->package('acme/installation', self::INSTALLATION, ['acme/core', 'acme/second-module', 'acme/replacing-module']),
            $this->package('acme/contracts', self::VENDOR.'acme/contracts', []),
            $this->package('acme/core', self::VENDOR.'acme/core', ['acme/contracts'], ['acme/core-registry']),
            $this->package('acme/first-module', self::VENDOR.'acme/first-module', ['acme/core']),
            $this->package('acme/second-module', self::VENDOR.'acme/second-module', ['acme/first-module']),
            $this->package('acme/replacing-module', self::VENDOR.'acme/replacing-module', ['acme/core-registry']),
        ];
    }

    /**
     * @param list<string> $require
     * @param list<string> $replace
     *
     * @return array{name: string, path: string, require: list<string>, replace: list<string>}
     */
    private function package(string $name, string $path, array $require, array $replace = []): array
    {
        return ['name' => $name, 'path' => $path, 'require' => $require, 'replace' => $replace];
    }
}
