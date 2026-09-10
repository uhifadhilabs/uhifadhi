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

    private const string PROJECT = '/srv/installation';

    private const string INSTALLED = '/srv/installation/vendor/';

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
     * THE SHAPE AN INSTALLATION ACTUALLY HAS, read off a real one: a root
     * package with no `name` of its own — Composer calls it `__root__` — whose
     * install path contains every vendor path, and whose manifest `replace`s
     * the polyfills the Symfony skeleton ships that block for.
     *
     * Everything under it requires a polyfill somewhere. If the installation is
     * allowed to answer for one, every package in the graph depends on the
     * installation, and the installation depends on every package: nothing can
     * be ordered at all.
     */
    public function testAnInstallationThatReplacesAPolyfillIsStillADependencyOfNothing(): void
    {
        self::assertSame(
            [
                'Uhifadhi\\Bundle\\AreaBundle\\Migrations\\Version20260101000000',
                'Uhifadhi\\Bundle\\AreaBundle\\Migrations\\Version20260101000100',
                'Uhifadhi\\Bundle\\RegistryBundle\\Migrations\\Version20260101000200',
                'Uhifadhi\\Bundle\\TeamBundle\\Migrations\\Version20260101000300',
                'Uhifadhi\\Bundle\\ShellBundle\\Migrations\\Version20260101000400',
                'Uhifadhi\\Patrol\\Migrations\\Version20260910044923',
                'Uhifadhi\\Incident\\Migrations\\Version20260910045214',
                'DoctrineMigrations\\Version20260909014925',
            ],
            $this->sortedOnAnInstallation([
                'DoctrineMigrations\\Version20260909014925',
                'Uhifadhi\\Incident\\Migrations\\Version20260910045214',
                'Uhifadhi\\Bundle\\ShellBundle\\Migrations\\Version20260101000400',
                'Uhifadhi\\Patrol\\Migrations\\Version20260910044923',
                'Uhifadhi\\Bundle\\AreaBundle\\Migrations\\Version20260101000000',
                'Uhifadhi\\Bundle\\TeamBundle\\Migrations\\Version20260101000300',
                'Uhifadhi\\Bundle\\RegistryBundle\\Migrations\\Version20260101000200',
                'Uhifadhi\\Bundle\\AreaBundle\\Migrations\\Version20260101000100',
            ]),
        );
    }

    /**
     * Two modules that require the same core and not each other: the graph is
     * silent between them, so the date decides — and the alphabet does not.
     * `uhifadhi/incident-module` sorts before `uhifadhi/patrol-module`, while
     * the version patrol ships is the older of the two.
     */
    public function testTwoModulesThatDoNotRequireEachOtherAreOrderedByDate(): void
    {
        $plan = $this->sortedOnAnInstallation([
            'Uhifadhi\\Incident\\Migrations\\Version20260910045214',
            'Uhifadhi\\Patrol\\Migrations\\Version20260910044923',
        ]);

        self::assertSame(
            [
                'Uhifadhi\\Patrol\\Migrations\\Version20260910044923',
                'Uhifadhi\\Incident\\Migrations\\Version20260910045214',
            ],
            $plan,
        );
    }

    /**
     * @param list<string> $names
     *
     * @return list<string>
     */
    private function sortedOnAnInstallation(array $names): array
    {
        $comparator = new DependencyOrderComparator(
            $this->installationConfiguration(),
            $this->installationPackages(),
        );

        $versions = array_map(static fn (string $name): Version => new Version($name), $names);
        usort($versions, $comparator->compare(...));

        return array_map(strval(...), $versions);
    }

    /**
     * The namespaces such an installation configures for its default
     * connection. A module whose history runs on a SECOND connection registers
     * its namespace in that connection's configuration and not in this one, so
     * it is a package with migrations that this configuration never names.
     */
    private function installationConfiguration(): Configuration
    {
        $configuration = new Configuration();
        $core = self::INSTALLED.'uhifadhi/uhifadhi/src/Uhifadhi/Bundle/';

        foreach ([
            'DoctrineMigrations' => self::PROJECT.'/migrations',
            'Uhifadhi\\Bundle\\AreaBundle\\Migrations' => $core.'AreaBundle/migrations',
            'Uhifadhi\\Bundle\\RegistryBundle\\Migrations' => $core.'RegistryBundle/migrations',
            'Uhifadhi\\Bundle\\TeamBundle\\Migrations' => $core.'TeamBundle/migrations',
            'Uhifadhi\\Bundle\\ShellBundle\\Migrations' => $core.'ShellBundle/migrations',
            'Uhifadhi\\Patrol\\Migrations' => self::INSTALLED.'uhifadhi/patrol-module/migrations',
            'Uhifadhi\\Incident\\Migrations' => self::INSTALLED.'uhifadhi/incident-module/migrations',
        ] as $namespace => $directory) {
            $configuration->addMigrationsDirectory($namespace, $directory);
        }

        return $configuration;
    }

    /**
     * @return list<array{name: string, path: string, require: list<string>, replace: list<string>, root: bool}>
     */
    private function installationPackages(): array
    {
        return [
            $this->root('__root__', self::PROJECT, [
                'symfony/framework-bundle',
                'uhifadhi/uhifadhi',
                'uhifadhi/patrol-module',
                'uhifadhi/incident-module',
                'uhifadhi/storage-module',
                'uhifadhi/telemetry-module',
                'uhifadhi/devkit-module',
            ], ['symfony/polyfill-ctype', 'symfony/polyfill-php80']),

            // Every package in a Symfony installation reaches a polyfill.
            $this->package('symfony/framework-bundle', self::INSTALLED.'symfony/framework-bundle', ['symfony/polyfill-ctype', 'symfony/polyfill-php80']),

            $this->package('uhifadhi/uhifadhi', self::INSTALLED.'uhifadhi/uhifadhi', ['symfony/framework-bundle'], [
                'uhifadhi/area-bundle',
                'uhifadhi/atlas-bundle',
                'uhifadhi/contracts',
                'uhifadhi/registry-bundle',
                'uhifadhi/shell-bundle',
                'uhifadhi/team-bundle',
            ]),

            // Ships no migrations, and stands between patrol and the core.
            $this->package('uhifadhi/storage-module', self::INSTALLED.'uhifadhi/storage-module', ['uhifadhi/uhifadhi']),

            $this->package('uhifadhi/patrol-module', self::INSTALLED.'uhifadhi/patrol-module', ['uhifadhi/storage-module', 'uhifadhi/uhifadhi']),
            $this->package('uhifadhi/incident-module', self::INSTALLED.'uhifadhi/incident-module', ['uhifadhi/uhifadhi']),

            // Migrations of its own, on a connection this configuration is not for.
            $this->package('uhifadhi/telemetry-module', self::INSTALLED.'uhifadhi/telemetry-module', ['uhifadhi/uhifadhi']),

            $this->package('uhifadhi/devkit-module', self::INSTALLED.'uhifadhi/devkit-module', ['uhifadhi/uhifadhi']),
        ];
    }

    /**
     * @param list<string> $require
     * @param list<string> $replace
     *
     * @return array{name: string, path: string, require: list<string>, replace: list<string>, root: bool}
     */
    private function root(string $name, string $path, array $require, array $replace = []): array
    {
        return ['name' => $name, 'path' => $path, 'require' => $require, 'replace' => $replace, 'root' => true];
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
     * @return list<array{name: string, path: string, require: list<string>, replace: list<string>, root: bool}>
     */
    private function packages(): array
    {
        return [
            $this->root('acme/installation', self::INSTALLATION, ['acme/core', 'acme/second-module', 'acme/replacing-module']),
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
     * @return array{name: string, path: string, require: list<string>, replace: list<string>, root: bool}
     */
    private function package(string $name, string $path, array $require, array $replace = []): array
    {
        return ['name' => $name, 'path' => $path, 'require' => $require, 'replace' => $replace, 'root' => false];
    }
}
