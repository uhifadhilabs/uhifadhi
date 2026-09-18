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

namespace Uhifadhi\Bundle\AreaBundle\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * HOW MUCH SHARED GROUND BETWEEN TWO ZONES AN AREA CALLS A SLIVER.
 *
 * NULLABLE, AND NOTHING IS BACKFILLED. Null means "not set" and reads as the
 * platform's default, so every area that exists keeps behaving exactly as it
 * did and an installation that never opens the field never has to. Writing
 * today's default into every row instead would freeze this release's number
 * into the data and make changing it a migration.
 *
 * NO EXPAND-BACKFILL-CONTRACT, because there is nothing to contract to: this
 * column is not on its way to becoming required. A per-area setting that has
 * to be answered before an area can exist is a question nobody has when they
 * are creating one.
 */
final class Version20260101000130 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'area_of_interest.zone_overlap_tolerance_pct: what this area calls a sliver';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE area_of_interest ADD zone_overlap_tolerance_pct DOUBLE PRECISION DEFAULT NULL');
    }

    /** @destructive drops the per-area setting; areas fall back to the platform default. */
    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE area_of_interest DROP zone_overlap_tolerance_pct');
    }
}
