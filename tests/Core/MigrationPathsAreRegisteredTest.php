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
use PHPUnit\Framework\Attributes\CoversNothing;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Uhifadhi\Core\Tests\Application\Kernel;

/**
 * AN INSTALLATION CONFIGURES NOTHING.
 *
 * Each bundle that owns tables names its own `migrations/` directory under
 * `doctrine_migrations.migrations_paths` from its `prependExtension()`, which is
 * the documented shape for a bundle-shipped namespace:
 *
 * > migrations_paths:
 * >     'SomeBundle\Migrations': '@SomeBundle/Migrations'
 *
 * @see https://symfony.com/bundles/DoctrineMigrationsBundle/current/index.html
 * @see vendor/doctrine/doctrine-migrations-bundle/src/DependencyInjection/DoctrineMigrationsExtension.php
 *      — `addMigrationsDirectory` is called once per namespace/path pair.
 *
 * The bundles that own no tables register nothing, and this specification is
 * where that stays true: the set is asserted EXACTLY, so a sixth path cannot
 * appear without a decision.
 */
#[CoversNothing]
final class MigrationPathsAreRegisteredTest extends KernelTestCase
{
    protected static function getKernelClass(): string
    {
        return Kernel::class;
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

    public function testEveryBundleThatOwnsTablesRegistersItsOwnMigrationsDirectory(): void
    {
        self::bootKernel();

        /** @var DependencyFactory $factory */
        $factory = static::getContainer()->get('doctrine.migrations.dependency_factory');

        $directories = $factory->getConfiguration()->getMigrationDirectories();
        ksort($directories);

        self::assertSame(
            [
                'Uhifadhi\\Bundle\\AreaBundle\\Migrations',
                'Uhifadhi\\Bundle\\RegistryBundle\\Migrations',
                'Uhifadhi\\Bundle\\ShellBundle\\Migrations',
                'Uhifadhi\\Bundle\\TeamBundle\\Migrations',
            ],
            array_keys($directories),
            'exactly the four core bundles that own tables ship migrations; Atlas owns none and registers none',
        );

        foreach ($directories as $namespace => $path) {
            self::assertDirectoryExists(
                $path,
                \sprintf('%s is registered but has no directory; the migration finder throws on a path that is not there', $namespace),
            );
        }
    }

    public function testEachRegisteredPathIsTheBundlesOwnMigrationsDirectory(): void
    {
        self::bootKernel();

        /** @var DependencyFactory $factory */
        $factory = static::getContainer()->get('doctrine.migrations.dependency_factory');

        $root = \dirname(__DIR__, 2).'/src/Uhifadhi/Bundle';

        foreach ($factory->getConfiguration()->getMigrationDirectories() as $namespace => $path) {
            $bundle = explode('\\', $namespace)[2] ?? '';

            self::assertSame(
                realpath($root.'/'.$bundle.'/migrations'),
                realpath($path),
                \sprintf('%s must point at the directory that ships inside the bundle it belongs to', $namespace),
            );
        }
    }

    public function testTheMigrationClassesAreAutoloadableUnderTheirBundlesPsr4Root(): void
    {
        // A version is found by `require_once` at runtime, so the finder alone
        // would not need an autoloader. Static analysis, the require-checker and
        // anything in an installation that names a version class by hand DO —
        // and a psr-4 prefix that does not resolve is only ever discovered by
        // one of them failing on a machine with a case-sensitive filesystem.
        //
        // @see vendor/doctrine/migrations/src/Finder/Finder.php — `requireOnce()`
        $root = \dirname(__DIR__, 2);

        /** @var array{autoload: array{'psr-4': array<string, string>}} $manifest */
        $manifest = json_decode((string) file_get_contents($root.'/composer.json'), true, 512, \JSON_THROW_ON_ERROR);

        foreach (['Area', 'Registry', 'Shell', 'Team'] as $bundle) {
            $namespace = \sprintf('Uhifadhi\\Bundle\\%sBundle\\Migrations\\', $bundle);

            self::assertArrayHasKey(
                $namespace,
                $manifest['autoload']['psr-4'],
                \sprintf('the root manifest must map %s, or an installation cannot resolve the class name', $namespace),
            );

            self::assertSame(
                realpath($root.'/src/Uhifadhi/Bundle/'.$bundle.'Bundle/migrations'),
                realpath($root.'/'.$manifest['autoload']['psr-4'][$namespace]),
                \sprintf('%s must map to the directory the bundle actually ships', $namespace),
            );

            /** @var array{autoload: array{'psr-4': array<string, string>}} $own */
            $own = json_decode(
                (string) file_get_contents($root.'/src/Uhifadhi/Bundle/'.$bundle.'Bundle/composer.json'),
                true,
                512,
                \JSON_THROW_ON_ERROR,
            );

            self::assertArrayHasKey(
                $namespace,
                $own['autoload']['psr-4'],
                \sprintf('%s must survive the split: the bundle\'s own manifest maps it too', $namespace),
            );
        }
    }
}
