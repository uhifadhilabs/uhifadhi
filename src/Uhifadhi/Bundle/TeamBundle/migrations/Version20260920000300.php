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
 * SINCE WHEN A POST HAS STOOD EMPTY.
 *
 * EXPAND ONLY, AND DELIBERATELY NOT BACKFILLED. The day a position fell
 * vacant cannot be recovered — nothing recorded when its last holder
 * stopped — and dating every empty post at the upgrade would make an
 * eighteen-month vacancy look like a fresh one. They stay null, which
 * every surface reads as "unknown", and the next vacancy is dated from
 * the day it happens.
 */
final class Version20260920000300 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'team_position.vacant_since';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE team_position ADD vacant_since TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE team_position DROP vacant_since');
    }
}
