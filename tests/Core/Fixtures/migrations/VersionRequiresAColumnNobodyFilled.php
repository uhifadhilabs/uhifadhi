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
 * BREAKS RULE ONE, deliberately: a required column on a table that already has
 * rows, with nothing said about what those rows should contain.
 *
 * This is never registered as a migrations path. It exists so the lint can be
 * shown failing something, which is the only way a lint that passes everything
 * shipped is worth having.
 */
final class VersionRequiresAColumnNobodyFilled extends AbstractMigration
{
    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE team_user ADD staff_number VARCHAR(32) NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE team_user DROP staff_number');
    }
}
