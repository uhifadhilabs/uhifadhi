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

namespace Uhifadhi\Bundle\RegistryBundle\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * WHEN AN AREA TOOK A MODULE ON.
 *
 * The area's own page states what each module contributes there and since
 * when; nothing recorded when a module was switched on, so there was no
 * "since" to state. EXPAND ONLY: the column is nullable and nothing is
 * backfilled, because a date nobody recorded cannot be recovered and a made-up
 * one would be read as a fact. Rows that predate this say so on the page.
 */
final class Version20260919000100 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'area_module.installed_at';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE area_module ADD installed_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE area_module DROP installed_at');
    }
}
