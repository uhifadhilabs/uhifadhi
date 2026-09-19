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
 * HOW OFTEN A HANDSET ON DUTY REPORTS ITS POSITION — API-CONTRACT.md §13D.
 *
 * ONE NULLABLE COLUMN, AND NULL IS NOT ZERO. Null means the area has not
 * set an interval and reads as the product's default; writing today's
 * number into every row would freeze it there and make changing it a
 * migration rather than a setting.
 *
 * EXPAND ONLY: nothing existing is altered and nothing is backfilled.
 */
final class Version20260921000200 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'area_of_interest.ping_interval_minutes';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE area_of_interest ADD ping_interval_minutes INT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE area_of_interest DROP ping_interval_minutes');
    }
}
