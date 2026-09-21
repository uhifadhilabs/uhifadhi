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

namespace Uhifadhi\Bundle\TeamBundle\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * A POSITION IS RETIRED, NEVER DELETED.
 *
 * EXPAND ONLY, and nothing to backfill: every position that exists the
 * moment this runs is in use, so the stamp is null for all of them and the
 * column is nullable for the life of the table. Null is not "unknown" here —
 * it is the positive fact that the position is open.
 *
 * THE HOLDING'S OWN DATE comes with it, because a position's holders are
 * read with their dates and there was nowhere to store one. Null there IS
 * "unknown": a holding written before the day was recorded has no day, and
 * stamping the upgrade's date on it would invent one.
 */
final class Version20260921003000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'A position carries the day it was retired, and a holding the day it started.';
    }

    public function up(Schema $schema): void
    {
        // NO `COMMENT ON COLUMN ... (DC2Type:datetime_immutable)`, deliberately.
        // DBAL 4 removed type comments altogether — there is no `DC2Type` left
        // in the library — so `doctrine:migrations:diff` on this platform emits
        // the bare ALTER and would generate a migration REMOVING any such
        // comment a hand-written version added. Every other migration in this
        // package is written the same way, and `tests/Core/MigrationsCoverSchemaTest`
        // is what holds the two together.
        $this->addSql('ALTER TABLE team_position ADD retired_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('ALTER TABLE team_user ADD position_since TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE team_position DROP retired_at');
        $this->addSql('ALTER TABLE team_user DROP position_since');
    }
}
