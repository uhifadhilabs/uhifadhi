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
use Doctrine\DBAL\Schema\Schema;
use Psr\Log\NullLogger;

/**
 * The two rules a shipped migration is read against, applied to the SQL a
 * version actually plans rather than to the text of its file.
 *
 * A migration is asked for its statements the only honest way there is: it is
 * constructed and `up()` is called, and `getSql()` returns what `addSql()`
 * collected. Nothing is executed.
 *
 * @see vendor/doctrine/migrations/src/AbstractMigration.php — `getSql(): Query[]`
 *
 * **Rule one — expand, backfill, contract, in that order and in one version.**
 * A column added `NOT NULL` to a table an EARLIER version created has to say
 * what the rows already there are supposed to contain: either the statement
 * carries a `DEFAULT`, or the same version writes the value first with an
 * `UPDATE`. A table this version creates is exempt: it has no rows yet.
 *
 * **Rule two — a destructive statement is a decision, and it is signed.**
 * Dropping a table or a column loses data that no `down()` brings back, so it
 * may only ride a release LATER than the one that stopped using the column, and
 * the file has to say which release that was:
 *
 *     @ destructive 1.4 — widget_preference.legacy_layout stopped being read in 1.3
 *
 * (written without the space). No marker, no drop.
 */
final class MigrationLinter
{
    private const NOT_NULL_ADDED = '/^ALTER\s+TABLE\s+(?<table>\S+)\s+ADD\s+(?<column>\S+)\s+(?<rest>.*\bNOT\s+NULL\b.*)$/i';

    private const NOT_NULL_SET = '/^ALTER\s+TABLE\s+(?<table>\S+)\s+ALTER\s+(?:COLUMN\s+)?(?<column>\S+)\s+SET\s+NOT\s+NULL\b/i';

    private const CREATES_TABLE = '/^CREATE\s+TABLE\s+(?:IF\s+NOT\s+EXISTS\s+)?(?<table>\S+)/i';

    private const DROPS_TABLE = '/^DROP\s+TABLE\b/i';

    private const DROPS_COLUMN = '/^ALTER\s+TABLE\s+\S+\s+DROP\s+(?:COLUMN\s+)?(?!CONSTRAINT\b|PRIMARY\b|FOREIGN\b|INDEX\b)\S+/i';

    private const BACKFILLS = '/^UPDATE\s+(?<table>\S+)\s+SET\s+(?<column>\S+)\s*=/i';

    private const DESTRUCTIVE_MARKER = '/@destructive\s+\S+/';

    /**
     * @param class-string<\Doctrine\Migrations\AbstractMigration> $version
     *
     * @return list<string> one sentence per broken rule; empty means the version passes
     */
    public function violations(string $version, string $file, Connection $connection): array
    {
        $migration = new $version($connection, new NullLogger());
        $migration->up(new Schema());

        $statements = [];
        foreach ($migration->getSql() as $query) {
            $statements[] = trim(preg_replace('/\s+/', ' ', $query->getStatement()) ?? '');
        }

        $source = (string) file_get_contents($file);
        $signed = 1 === preg_match(self::DESTRUCTIVE_MARKER, $source);
        $short = substr(strrchr($version, '\\') ?: $version, 1);

        $created = [];
        $backfilled = [];
        $violations = [];

        foreach ($statements as $statement) {
            if (preg_match(self::CREATES_TABLE, $statement, $match)) {
                $created[strtolower($match['table'])] = true;
                continue;
            }

            if (preg_match(self::BACKFILLS, $statement, $match)) {
                $backfilled[strtolower($match['table']).'.'.strtolower($match['column'])] = true;
                continue;
            }

            $violations = [
                ...$violations,
                ...$this->readNotNull($statement, $short, $created, $backfilled),
                ...$this->readDrop($statement, $short, $signed),
            ];
        }

        return $violations;
    }

    /**
     * @param array<string, true> $created
     * @param array<string, true> $backfilled
     *
     * @return list<string>
     */
    private function readNotNull(string $statement, string $short, array $created, array $backfilled): array
    {
        $carriesDefault = false;

        if (preg_match(self::NOT_NULL_ADDED, $statement, $match)) {
            $carriesDefault = 1 === preg_match('/\bDEFAULT\b/i', $match['rest']);
        } elseif (!preg_match(self::NOT_NULL_SET, $statement, $match)) {
            return [];
        }

        $table = strtolower($match['table']);
        $column = strtolower($match['column']);

        if (isset($created[$table]) || $carriesDefault || isset($backfilled[$table.'.'.$column])) {
            return [];
        }

        return [\sprintf(
            '%s requires %s.%s of rows it did not write: an existing table gets a NOT NULL column only with a DEFAULT, or after an UPDATE in this same version. Statement: %s',
            $short,
            $table,
            $column,
            $statement,
        )];
    }

    /**
     * @return list<string>
     */
    private function readDrop(string $statement, string $short, bool $signed): array
    {
        if (!preg_match(self::DROPS_TABLE, $statement) && !preg_match(self::DROPS_COLUMN, $statement)) {
            return [];
        }

        if ($signed) {
            return [];
        }

        return [\sprintf(
            '%s drops something and carries no @destructive marker: a drop rides a release later than the code that stopped reading it, and the file names that release. Statement: %s',
            $short,
            $statement,
        )];
    }
}
