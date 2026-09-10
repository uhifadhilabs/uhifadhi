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

namespace Uhifadhi\Bundle\RegistryBundle\Version;

use Composer\InstalledVersions;
use Doctrine\Migrations\Configuration\Configuration;
use Doctrine\Migrations\Version\Comparator;
use Doctrine\Migrations\Version\Version;

/**
 * THE RULE: a package's versions run after the versions of every package it
 * requires. The timestamp orders versions that belong to the SAME package, and
 * decides nothing between two packages. Versions an installation keeps itself
 * run last. Only packages that ship migrations are ordered at all.
 *
 * A version's identity in doctrine/migrations is its full class name — the
 * repository builds one as `new Version($migrationClassName)` — and the shipped
 * comparator is a `strcmp` over that name:
 *
 * > `return strcmp((string) $a, (string) $b);`
 *
 * @see vendor/doctrine/migrations/src/FilesystemMigrationsRepository.php
 * @see vendor/doctrine/migrations/src/Version/AlphabeticalComparator.php
 * @see vendor/doctrine/migrations/src/Version/SortedMigrationPlanCalculator.php
 *      — `uasort(…, $this->sorter->compare(…))`, the one place order is decided.
 *
 * With a namespace per package that sorts by NAMESPACE, so a package whose name
 * happens to sort earlier creates its tables before the tables they reference.
 * A timestamp sorts by DATE, which agrees with the dependencies only while one
 * team writes every package in sequence: two packages released independently
 * carry whatever dates their own authors typed.
 *
 * The pattern this follows is the one the doctrine/migrations 3.0 maintainer
 * documents for exactly this situation — a `Comparator` that topologically
 * sorts the packages and falls back to the default order inside one of them,
 * whose ideal graph source he names as "the package relations defined in the
 * composer.json file":
 * @see https://www.goetas.com/blog/multi-namespace-migrations-with-doctrinemigrations-30/
 *
 * WHERE THE GRAPH COMES FROM. Composer writes the installed set twice.
 * `vendor/composer/installed.php` carries `name`, `version`, `install_path` and
 * `replaced`/`provided` but no `require`; `vendor/composer/installed.json`
 * carries `require` and `replace` per package but does NOT carry the ROOT
 * package — and the root package is the installation itself, the one whose
 * requirements are the reason its own versions run last. So the graph is read
 * from each installed package's OWN `composer.json`, at the install path
 * `InstalledVersions` reports, which is uniform across the root and the rest.
 * @see vendor/composer/InstalledVersions.php — `getAllRawData()`, whose `root`
 *      entry and `versions` entries both carry `install_path`; a name reachable
 *      only through a `replace` carries none, because there is no directory.
 * @see https://getcomposer.org/doc/07-runtime.md#installed-versions
 *
 * HOW A VERSION FINDS ITS PACKAGE. Its class name names its namespace; the
 * migrations configuration maps that namespace to a directory; the package is
 * the installed one whose install path contains that directory, longest match
 * first, because the root package's path contains every vendor path. A
 * directory no installed package contains belongs to the installation.
 *
 * Registered through the seam the migrations bundle documents for it,
 * `doctrine_migrations.services`:
 *
 * > services:
 * >     'Doctrine\Migrations\Version\Comparator': ~
 * @see https://symfony.com/bundles/DoctrineMigrationsBundle/current/index.html#configuration
 * @see vendor/doctrine/doctrine-migrations-bundle/src/DependencyInjection/DoctrineMigrationsExtension.php
 *      — each `services` entry becomes `DependencyFactory::setDefinition()`, so
 *      the comparator is an ordinary container service and may take arguments.
 */
final class DependencyOrderComparator implements Comparator
{
    private const string TIMESTAMP = '/Version(?<stamp>\d{14})$/';

    /** Sorts after every real timestamp: 14 digits can never reach this. */
    private const string UNDATED = '99999999999999';

    /** What a requirement on the platform itself, an extension or a library is called. */
    private const string PLATFORM = '/^(php|hhvm|composer|composer-.+|ext-.+|lib-.+)$/i';

    /**
     * Package name => how many packages deep it sits, and the name itself as
     * the tie-break between two that sit equally deep. Built once.
     *
     * @var array<string, int>|null
     */
    private ?array $depths = null;

    /**
     * Version class name => the package it belongs to. `compare()` is called
     * once per pair, so the same name arrives many times over.
     *
     * @var array<string, string>
     */
    private array $owners = [];

    /**
     * @param list<array{name: string, path: string, require: list<string>, replace: list<string>}> $packages
     */
    public function __construct(
        private readonly Configuration $configuration,
        private readonly array $packages,
    ) {
    }

    /**
     * The installed set, as Composer reports it in the running process.
     */
    public static function fromComposer(Configuration $configuration): self
    {
        $packages = [];
        $seen = [];

        foreach (InstalledVersions::getAllRawData() as $set) {
            // The root package leads: an installation is one of the packages
            // whose requirements decide the order, and it is the only one the
            // versions list never carries.
            $entries = [$set['root']['name'] => ['install_path' => $set['root']['install_path']]] + $set['versions'];

            foreach ($entries as $name => $entry) {
                // No path is no install: the name is one an installed package
                // answers to through `replace`, not a directory anybody has. A
                // package can also appear in more than one raw-data set when
                // several autoloaders are in play, and it is still one package.
                if (isset($seen[$name]) || !isset($entry['install_path'])) {
                    continue;
                }

                $seen[$name] = true;
                $packages[] = self::manifestAt($name, $entry['install_path']);
            }
        }

        return new self($configuration, $packages);
    }

    public function compare(Version $a, Version $b): int
    {
        $depths = $this->depths ??= $this->depths();

        $packageOfA = $this->packageOf($a);
        $packageOfB = $this->packageOf($b);

        return [
            $depths[$packageOfA] ?? \PHP_INT_MAX,
            $packageOfA,
            $this->timestamp($a),
            (string) $a,
        ] <=> [
            $depths[$packageOfB] ?? \PHP_INT_MAX,
            $packageOfB,
            $this->timestamp($b),
            (string) $b,
        ];
    }

    /**
     * One package's manifest, read where Composer put it. A package whose
     * manifest cannot be read contributes a node with no edges rather than
     * stopping an installation from migrating: an unreadable manifest is a
     * package that requires nothing anybody here can see.
     *
     * @return array{name: string, path: string, require: list<string>, replace: list<string>}
     */
    private static function manifestAt(string $name, string $path): array
    {
        $manifest = rtrim($path, \DIRECTORY_SEPARATOR).'/composer.json';
        $json = is_file($manifest) ? json_decode((string) file_get_contents($manifest), true) : null;

        return [
            'name' => $name,
            'path' => $path,
            'require' => \is_array($json) ? self::names($json['require'] ?? null) : [],
            'replace' => \is_array($json) ? self::names($json['replace'] ?? null) : [],
        ];
    }

    /**
     * The package names in one manifest section, without the platform packages
     * no installation has a directory for.
     *
     * @return list<string>
     */
    private static function names(mixed $section): array
    {
        if (!\is_array($section)) {
            return [];
        }

        $names = [];
        foreach (array_keys($section) as $name) {
            if (\is_string($name) && 1 !== preg_match(self::PLATFORM, $name)) {
                $names[] = strtolower($name);
            }
        }

        return $names;
    }

    /**
     * HOW DEEP EACH PACKAGE THAT SHIPS MIGRATIONS SITS: one more than the
     * deepest such package it reaches, so it is always strictly deeper than
     * everything it depends on and the depth alone is a runnable order.
     *
     * ONLY PACKAGES THAT SHIP MIGRATIONS ARE ORDERED. A cycle elsewhere in an
     * installation's graph is nothing to do with the order tables are created
     * in, and refusing to migrate over one would refuse over a shape real
     * installations have — `league/flysystem` and `league/flysystem-local`
     * require each other.
     *
     * @return array<string, int>
     */
    private function depths(): array
    {
        $requires = $this->reachable($this->shipping(), $this->requires());

        $depths = [];
        $pending = array_keys($requires);

        while ([] !== $pending) {
            $settled = false;

            foreach ($pending as $position => $name) {
                $deepest = -1;
                foreach ($requires[$name] as $dependency) {
                    if (!isset($depths[$dependency])) {
                        continue 2;
                    }

                    $deepest = max($deepest, $depths[$dependency]);
                }

                $depths[$name] = $deepest + 1;
                unset($pending[$position]);
                $settled = true;
            }

            if (!$settled) {
                sort($pending);

                throw new \LogicException(\sprintf('The migrations cannot be ordered: these installed packages ship migrations and require each other in a cycle: %s.', implode(', ', $pending)));
            }
        }

        return $depths;
    }

    /**
     * The packages that own a registered migrations directory. They are the
     * only ones there is anything to order.
     *
     * @return list<string>
     */
    private function shipping(): array
    {
        $shipping = [];

        foreach ($this->configuration->getMigrationDirectories() as $directory) {
            $owner = $this->ownerOf($this->normalise($directory));

            if ('' !== $owner) {
                $shipping[$owner] = true;
            }
        }

        return array_keys($shipping);
    }

    /**
     * Each of those packages, pointed at the others it reaches — through
     * however many packages that ship nothing lie between them.
     *
     * @param list<string>                $shipping
     * @param array<string, list<string>> $requires
     *
     * @return array<string, list<string>>
     */
    private function reachable(array $shipping, array $requires): array
    {
        $ships = array_fill_keys($shipping, true);
        $graph = [];

        foreach ($shipping as $package) {
            $found = [];
            $walked = [$package => true];
            $frontier = $requires[$package] ?? [];

            while ([] !== $frontier) {
                $next = array_pop($frontier);

                if (isset($walked[$next])) {
                    continue;
                }

                $walked[$next] = true;

                if (isset($ships[$next])) {
                    $found[$next] = true;

                    // A package that ships migrations is placed by its own
                    // requirements; walking past it would only rediscover them.
                    continue;
                }

                $frontier = [...$frontier, ...$requires[$next] ?? []];
            }

            $graph[$package] = array_keys($found);
        }

        return $graph;
    }

    /**
     * Every installed package, pointed at the installed packages it requires.
     * A requirement is followed through `replace` — the core is one package
     * that answers to a name per bundle — and a requirement nothing installed
     * answers to, or that resolves back to the package itself, is not an edge.
     *
     * @return array<string, list<string>>
     */
    private function requires(): array
    {
        $answersTo = [];
        foreach ($this->packages as $package) {
            $answersTo[$package['name']] = $package['name'];
            foreach ($package['replace'] as $replaced) {
                $answersTo[$replaced] = $package['name'];
            }
        }

        $requires = [];
        foreach ($this->packages as $package) {
            $edges = [];
            foreach ($package['require'] as $required) {
                $installed = $answersTo[$required] ?? null;
                if (null !== $installed && $installed !== $package['name']) {
                    $edges[$installed] = true;
                }
            }

            $requires[$package['name']] = array_keys($edges);
        }

        return $requires;
    }

    /**
     * The package a version belongs to, or the empty name — the installation's
     * — for a directory no installed package contains.
     */
    private function packageOf(Version $version): string
    {
        $class = (string) $version;

        if (!isset($this->owners[$class])) {
            $directory = $this->directoryOf($class);
            $this->owners[$class] = null === $directory ? '' : $this->ownerOf($directory);
        }

        return $this->owners[$class];
    }

    /**
     * The installed package whose install path contains a directory, longest
     * match first because the root package's path contains every vendor path,
     * or the empty name for a directory no installed package contains.
     */
    private function ownerOf(string $directory): string
    {
        $owner = '';
        $longest = 0;

        foreach ($this->packages as $package) {
            $path = $this->normalise($package['path']);

            if (\strlen($path) > $longest && str_starts_with($directory, $path)) {
                $owner = $package['name'];
                $longest = \strlen($path);
            }
        }

        return $owner;
    }

    /**
     * The directory the version's namespace is registered under — the longest
     * registered namespace the class name sits inside, so a package that maps
     * both `Acme\Migrations` and `Acme\Migrations\Extra` is read precisely.
     */
    private function directoryOf(string $class): ?string
    {
        $directory = null;
        $longest = 0;

        foreach ($this->configuration->getMigrationDirectories() as $namespace => $candidate) {
            $prefix = rtrim($namespace, '\\').'\\';

            if (\strlen($prefix) > $longest && str_starts_with($class, $prefix)) {
                $directory = $this->normalise($candidate);
                $longest = \strlen($prefix);
            }
        }

        return $directory;
    }

    /**
     * A path the two ends can be compared on: resolved where it exists, and
     * closed with a separator so `…/area-module` never matches `…/area-module-x`.
     */
    private function normalise(string $path): string
    {
        return rtrim(realpath($path) ?: $path, \DIRECTORY_SEPARATOR).\DIRECTORY_SEPARATOR;
    }

    private function timestamp(Version $version): string
    {
        if (1 === preg_match(self::TIMESTAMP, (string) $version, $match)) {
            return $match['stamp'];
        }

        return self::UNDATED;
    }
}
