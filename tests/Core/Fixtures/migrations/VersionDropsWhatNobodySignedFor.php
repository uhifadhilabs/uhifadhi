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
 * BREAKS RULE TWO, deliberately: a column and a table go away, and the file
 * names no release that stopped reading them.
 *
 * This is never registered as a migrations path.
 */
final class VersionDropsWhatNobodySignedFor extends AbstractMigration
{
    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE widget_preference DROP active_kind');
        $this->addSql('DROP TABLE widget_custom_preset');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE widget_preference ADD active_kind VARCHAR(8) DEFAULT NULL');
    }
}
