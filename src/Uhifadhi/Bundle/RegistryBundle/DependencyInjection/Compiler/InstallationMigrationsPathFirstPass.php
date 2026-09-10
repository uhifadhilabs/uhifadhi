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

namespace Uhifadhi\Bundle\RegistryBundle\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * THE INSTALLATION'S OWN MIGRATIONS DIRECTORY GOES FIRST.
 *
 * `doctrine:migrations:diff` and `doctrine:migrations:generate` write into the
 * namespace named by `--namespace`; with the flag absent they take the FIRST
 * configured one, silently when there is nothing to ask and by a question whose
 * default is the same entry when there is:
 *
 * > $dirs = $configuration->getMigrationDirectories();
 * > if ($namespace === null && count($dirs) === 1) {
 * >     $namespace = key($dirs);
 * > } elseif ($namespace === null && count($dirs) > 1) {
 * >     $question = new ChoiceQuestion('Please choose a namespace (defaults to the first one)', array_keys($dirs), 0);
 *
 * (`DoctrineCommand::getNamespace()`)
 *
 * And the first configured one is a PACKAGE'S. Every bundle that owns tables
 * names its directory from `prependExtension()`, prepended config is merged
 * ahead of the application's own (`prependExtensionConfig()` unshifts), and the
 * extension turns the merged result into calls in key order:
 *
 * > foreach ($config['migrations_paths'] as $ns => $path) {
 * >     $configurationDefinition->addMethodCall('addMigrationsDirectory', [$ns, $path]);
 *
 * So an installation that runs the flagless `diff` its own entities need has
 * the version written under `vendor/`, where the next `composer update` deletes
 * it and the row in `doctrine_migration_versions` outlives the file.
 *
 * This pass reorders those calls: the directories no installed bundle ships
 * come first, in the order they were registered, and the packages' follow in
 * theirs. Nothing is added, removed or rewritten — the same namespaces point at
 * the same paths, and only which of them a flagless command falls back to
 * changes. Ownership is read off the bundle paths the kernel publishes, which is
 * the same parameter the migrations bundle resolves `@Bundle/…` against, in
 * `getBundlePath()`.
 *
 * @see https://symfony.com/bundles/DoctrineMigrationsBundle/current/index.html
 * @see vendor/doctrine/migrations/src/Tools/Console/Command/DoctrineCommand.php
 * @see vendor/doctrine/doctrine-migrations-bundle/src/DependencyInjection/DoctrineMigrationsExtension.php
 * @see vendor/symfony/dependency-injection/ContainerBuilder.php
 */
final class InstallationMigrationsPathFirstPass implements CompilerPassInterface
{
    private const string CONFIGURATION_ID = 'doctrine.migrations.configuration';

    private const string METHOD = 'addMigrationsDirectory';

    public function process(ContainerBuilder $container): void
    {
        // An application may install the core without the migrations bundle; it
        // has no history to run and nothing here to order.
        if (!$container->hasDefinition(self::CONFIGURATION_ID)) {
            return;
        }

        $definition = $container->getDefinition(self::CONFIGURATION_ID);

        /** @var list<array{0: string, 1: array<mixed>}|array{0: string, 1: array<mixed>, 2: bool}> $calls */
        $calls = $definition->getMethodCalls();

        /** @var list<int> $slots the positions the directory calls occupy, which the reordering keeps */
        $slots = [];

        /** @var list<array{0: string, 1: array<mixed>}|array{0: string, 1: array<mixed>, 2: bool}> $installation */
        $installation = [];

        /** @var list<array{0: string, 1: array<mixed>}|array{0: string, 1: array<mixed>, 2: bool}> $packages */
        $packages = [];

        foreach ($calls as $index => $call) {
            if (self::METHOD !== $call[0]) {
                continue;
            }

            $slots[] = $index;

            $path = $call[1][1] ?? null;

            if (\is_string($path) && $this->isShippedByABundle($path, $container)) {
                $packages[] = $call;
            } else {
                $installation[] = $call;
            }
        }

        $ordered = [...$installation, ...$packages];

        foreach ($slots as $position => $index) {
            $calls[$index] = $ordered[$position];
        }

        $definition->setMethodCalls($calls);
    }

    private function isShippedByABundle(string $path, ContainerBuilder $container): bool
    {
        /** @var array<string, array{path: string}> $metadata */
        $metadata = $container->getParameter('kernel.bundles_metadata');

        $directory = $this->normalise($path);

        foreach ($metadata as $bundle) {
            if (str_starts_with($directory, $this->normalise($bundle['path']))) {
                return true;
            }
        }

        return false;
    }

    /**
     * A path the two ends can be compared on: resolved where it exists, and
     * closed with a separator so `…/AreaBundle` never matches `…/AreaBundleX`.
     */
    private function normalise(string $path): string
    {
        return rtrim(realpath($path) ?: $path, \DIRECTORY_SEPARATOR).\DIRECTORY_SEPARATOR;
    }
}
