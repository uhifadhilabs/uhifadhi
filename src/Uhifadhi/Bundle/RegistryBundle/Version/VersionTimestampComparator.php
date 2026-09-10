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

use Doctrine\Migrations\Version\Comparator;
use Doctrine\Migrations\Version\Version;

/**
 * The order versions run in is the order they were WRITTEN in, across every
 * namespace an installation has.
 *
 * A version's identity in doctrine/migrations is its **full class name** — the
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
 * With one namespace that is the same thing as sorting by date, because the
 * class name ends in one. With a namespace per package it is not: it sorts by
 * PACKAGE first, and a table's foreign key does not care what its owner is
 * called. A package whose name happens to sort earlier gets its tables created
 * before the tables they reference, and the migration fails on a relation that
 * does not exist yet. The same accident reaches an installation, whose recipe
 * names its own namespace `DoctrineMigrations`: `D` precedes almost everything,
 * so its first version would be planned before every version it depends on.
 *
 * So the trailing `YmdHis` decides, and the class name only breaks a tie. A
 * name carrying no timestamp sorts after every name that does: it has opted out
 * of being placed, and placing it last is the only answer that cannot reorder a
 * dated version behind its own foreign key.
 *
 * Registered through the seam the migrations bundle documents for exactly this,
 * `doctrine_migrations.services`:
 *
 * > services:
 * >     'Doctrine\Migrations\Version\Comparator': ~
 * @see https://symfony.com/bundles/DoctrineMigrationsBundle/current/index.html
 * @see vendor/doctrine/doctrine-migrations-bundle/src/DependencyInjection/DoctrineMigrationsExtension.php
 *      — each `services` entry becomes `DependencyFactory::setDefinition()`.
 */
final class VersionTimestampComparator implements Comparator
{
    private const TIMESTAMP = '/Version(?<stamp>\d{14})$/';

    public function compare(Version $a, Version $b): int
    {
        return [$this->timestamp($a), (string) $a] <=> [$this->timestamp($b), (string) $b];
    }

    private function timestamp(Version $version): string
    {
        if (1 === preg_match(self::TIMESTAMP, (string) $version, $match)) {
            return $match['stamp'];
        }

        // Sorts after every real timestamp: 14 digits can never reach this.
        return '99999999999999';
    }
}
