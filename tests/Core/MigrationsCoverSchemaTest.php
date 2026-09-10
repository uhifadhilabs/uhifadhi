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

use PHPUnit\Framework\Attributes\CoversNothing;

/**
 * THE DRIFT LOCK: what the migrations build IS what the entities describe.
 *
 * An installer's proof that a release is whole is `doctrine:migrations:diff`
 * saying it has nothing to write, so that is the command this asks — not a
 * private comparison of two schemas that could disagree with it.
 *
 * @see vendor/doctrine/migrations/src/Tools/Console/Command/DiffCommand.php
 *      — a diff with nothing to generate is `NoChangesDetected`, reported as
 *      "No changes detected in your mapping information." and exit status 0.
 *
 * An entity gains a column and nobody writes the migration: this test fails.
 */
#[CoversNothing]
final class MigrationsCoverSchemaTest extends MigrationsTestCase
{
    public function testAnEmptyDatabaseMigratedLeavesNothingForDiffToWrite(): void
    {
        $this->console('doctrine:migrations:migrate', ['--no-interaction' => true, 'version' => 'latest']);

        $before = $this->shippedVersionFiles();

        $diff = $this->console('doctrine:migrations:diff', [
            '--no-interaction' => true,
            '--namespace' => 'Uhifadhi\\Bundle\\AreaBundle\\Migrations',
        ]);

        // A diff that found work WROTE it. Take the file back out before
        // asserting, so a drift failure leaves the repository as it was and the
        // SQL it would have written is in the message instead.
        $written = array_diff($this->shippedVersionFiles(), $before);
        $drift = '';
        foreach ($written as $file) {
            $drift .= (string) file_get_contents($file);
            unlink($file);
        }

        self::assertStringContainsString(
            'No changes detected in your mapping information.',
            $diff,
            'the shipped migrations and the shipped entities have drifted apart; diff wanted to write:'.\PHP_EOL.$drift,
        );
    }

    /**
     * @return list<string>
     */
    private function shippedVersionFiles(): array
    {
        $files = glob(\dirname(__DIR__, 2).'/src/Uhifadhi/Bundle/*/migrations/*.php') ?: [];
        sort($files);

        return array_values($files);
    }

    public function testTheMigrationsBuildEveryTableTheCoreOwnsAndNoOther(): void
    {
        $this->console('doctrine:migrations:migrate', ['--no-interaction' => true, 'version' => 'latest']);

        self::assertSame(
            [
                'area_module',
                'area_of_interest',
                'doctrine_migration_versions',
                'module',
                'team_api_token',
                'team_department',
                'team_department_scope_change',
                'team_position',
                'team_user',
                'widget_custom_preset',
                'widget_preference',
                'zone',
            ],
            $this->tableNames(),
        );
    }

    public function testMigrationZeroPutsPostgisThereBeforeAGeometryColumnNeedsIt(): void
    {
        $plan = $this->plannedVersions();

        self::assertNotSame([], $plan);
        self::assertSame(
            'Uhifadhi\\Bundle\\AreaBundle\\Migrations\\Version20260101000000',
            $plan[0],
            'the extension has to exist before geometry(MULTIPOLYGON,4326) can be declared',
        );

        $this->console('doctrine:migrations:migrate', ['--no-interaction' => true, 'version' => 'latest']);

        self::assertSame(
            1,
            (int) $this->connection->fetchOne("SELECT count(*) FROM pg_extension WHERE extname = 'postgis'"),
        );
    }

    /**
     * The order the versions run in is not the order their timestamps suggest
     * unless somebody makes it so: a version's identity is its FULL CLASS NAME,
     * and the shipped comparator is a `strcmp` over that name.
     *
     * @see vendor/doctrine/migrations/src/FilesystemMigrationsRepository.php
     *      — `new Version($migrationClassName)`
     * @see vendor/doctrine/migrations/src/Version/AlphabeticalComparator.php
     *
     * Left alone, `Uhifadhi\Bundle\ShellBundle\…` sorts before
     * `Uhifadhi\Bundle\TeamBundle\…`, and `widget_preference.user_id` references
     * a `team_user` that does not exist yet. This states the order the foreign
     * keys actually demand.
     */
    public function testTheVersionsAreOrderedByTheirTimestampAndNotByTheirNamespace(): void
    {
        self::assertSame(
            [
                'Uhifadhi\\Bundle\\AreaBundle\\Migrations\\Version20260101000000',
                'Uhifadhi\\Bundle\\AreaBundle\\Migrations\\Version20260101000100',
                'Uhifadhi\\Bundle\\RegistryBundle\\Migrations\\Version20260101000200',
                'Uhifadhi\\Bundle\\TeamBundle\\Migrations\\Version20260101000300',
                'Uhifadhi\\Bundle\\ShellBundle\\Migrations\\Version20260101000400',
            ],
            $this->plannedVersions(),
        );
    }
}
