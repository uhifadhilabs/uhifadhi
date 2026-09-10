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

namespace Uhifadhi\Core\Tests\Core\Fixtures\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * KEEPS BOTH RULES, deliberately, and does the two awkward things a real
 * release does: it makes an existing table carry a new required column by
 * writing the value before requiring it, and it signs the drop that follows.
 *
 * @destructive 1.4 — team_user.ranger_code stopped being read in 1.3
 *
 * This is never registered as a migrations path.
 */
final class VersionExpandsBackfillsAndContracts extends AbstractMigration
{
    public function up(Schema $schema): void
    {
        // Expand.
        $this->addSql('ALTER TABLE team_user ADD staff_number VARCHAR(32) DEFAULT NULL');
        $this->addSql('CREATE TABLE team_shift (id INT NOT NULL, label VARCHAR(64) NOT NULL, PRIMARY KEY(id))');

        // Backfill.
        $this->addSql("UPDATE team_user SET staff_number = 'unassigned' WHERE staff_number IS NULL");

        // Contract.
        $this->addSql('ALTER TABLE team_user ALTER staff_number SET NOT NULL');
        $this->addSql('ALTER TABLE team_user DROP ranger_code');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE team_user ADD ranger_code VARCHAR(32) DEFAULT NULL');
        $this->addSql('ALTER TABLE team_user DROP staff_number');
        $this->addSql('DROP TABLE team_shift');
    }
}
