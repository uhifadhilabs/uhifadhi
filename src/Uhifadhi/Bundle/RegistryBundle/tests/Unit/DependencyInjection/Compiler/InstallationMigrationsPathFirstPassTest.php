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

namespace Uhifadhi\Bundle\RegistryBundle\Tests\Unit\DependencyInjection\Compiler;

use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Uhifadhi\Bundle\RegistryBundle\DependencyInjection\Compiler\InstallationMigrationsPathFirstPass;

/**
 * The pass, on its own: what it does to the calls the migrations extension
 * leaves on `doctrine.migrations.configuration`.
 *
 * The integration end — a booted application whose flagless `migrations:diff`
 * writes where it should — lives in the core's own suite; this states the rule
 * the pass applies, including the cases an installation reaches and the core's
 * throwaway application does not: no directory of its own, and no migrations
 * bundle at all.
 */
final class InstallationMigrationsPathFirstPassTest extends TestCase
{
    public function testTheDirectoryNoBundleShipsIsMovedInFrontOfTheOnesTheyDo(): void
    {
        $container = $this->container([
            'Package\\Migrations' => '/srv/app/vendor/vendor/package/migrations',
            'Other\\Migrations' => '/srv/app/vendor/vendor/other/migrations',
            'DoctrineMigrations' => '/srv/app/migrations',
        ]);

        new InstallationMigrationsPathFirstPass()->process($container);

        self::assertSame(
            [
                ['DoctrineMigrations', '/srv/app/migrations'],
                ['Package\\Migrations', '/srv/app/vendor/vendor/package/migrations'],
                ['Other\\Migrations', '/srv/app/vendor/vendor/other/migrations'],
            ],
            $this->registeredDirectories($container),
        );
    }

    public function testEverythingElseOnTheDefinitionKeepsItsPlace(): void
    {
        $container = $this->container([
            'Package\\Migrations' => '/srv/app/vendor/vendor/package/migrations',
            'DoctrineMigrations' => '/srv/app/migrations',
        ]);

        $definition = $container->getDefinition('doctrine.migrations.configuration');
        $definition->addMethodCall('setAllOrNothing', [true]);

        new InstallationMigrationsPathFirstPass()->process($container);

        $methods = [];

        /** @var array{0: string, 1: array<mixed>} $call */
        foreach ($definition->getMethodCalls() as $call) {
            $methods[] = $call[0];
        }

        self::assertSame(
            ['addMigrationsDirectory', 'addMigrationsDirectory', 'setAllOrNothing'],
            $methods,
            'the directory calls are reordered among themselves; no other call moves',
        );
    }

    public function testAnInstallationWithNoDirectoryOfItsOwnIsLeftExactlyAsItWas(): void
    {
        $container = $this->container([
            'Package\\Migrations' => '/srv/app/vendor/vendor/package/migrations',
            'Other\\Migrations' => '/srv/app/vendor/vendor/other/migrations',
        ]);

        new InstallationMigrationsPathFirstPass()->process($container);

        self::assertSame(
            [
                ['Package\\Migrations', '/srv/app/vendor/vendor/package/migrations'],
                ['Other\\Migrations', '/srv/app/vendor/vendor/other/migrations'],
            ],
            $this->registeredDirectories($container),
        );
    }

    public function testAnApplicationWithoutTheMigrationsBundleIsNotTouched(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.bundles_metadata', []);

        new InstallationMigrationsPathFirstPass()->process($container);

        self::assertFalse($container->hasDefinition('doctrine.migrations.configuration'));
    }

    /**
     * @param array<string, string> $paths namespace => directory, in the order the extension registered them
     */
    private function container(array $paths): ContainerBuilder
    {
        $container = new ContainerBuilder();

        // What a kernel publishes about the bundles it has: the two packages
        // above ship one directory each, and the application ships none.
        $container->setParameter('kernel.bundles_metadata', [
            'PackageBundle' => ['path' => '/srv/app/vendor/vendor/package'],
            'OtherBundle' => ['path' => '/srv/app/vendor/vendor/other'],
        ]);

        $definition = new Definition();

        foreach ($paths as $namespace => $path) {
            $definition->addMethodCall('addMigrationsDirectory', [$namespace, $path]);
        }

        $container->setDefinition('doctrine.migrations.configuration', $definition);

        return $container;
    }

    /**
     * @return list<array{string, string}>
     */
    private function registeredDirectories(ContainerBuilder $container): array
    {
        $directories = [];

        /** @var array{0: string, 1: array{string, string}} $call */
        foreach ($container->getDefinition('doctrine.migrations.configuration')->getMethodCalls() as $call) {
            if ('addMigrationsDirectory' === $call[0]) {
                $directories[] = $call[1];
            }
        }

        return $directories;
    }
}
