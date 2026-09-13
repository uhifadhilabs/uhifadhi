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
 * THE LENS: which modules a department leads with.
 *
 * A new table and nothing else — no column is added to a populated one, so there
 * is nothing to backfill and no NOT NULL to tighten later. An installation
 * upgrading gains an empty table, and every department carries on leading with
 * nothing until somebody attaches a module.
 *
 * Dated after `team_department` (Version20260101000300), which it references, and
 * inside this package's own timestamps; the order against other packages is their
 * Composer graph, resolved by the registry's comparator.
 *
 * BOTH FOREIGN KEYS CASCADE. An attachment is a statement about a pair of rows
 * and means nothing once either half is gone: a department that is deleted takes
 * its attachments with it, and so does a module row.
 */
final class Version20260101000310 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'team_department_module — the modules a department leads with';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE team_department_module (
              department_id INT NOT NULL,
              module_id INT NOT NULL,
              PRIMARY KEY (department_id, module_id)
            )
        SQL);
        $this->addSql('CREATE INDEX IDX_54E05784AE80F5DF ON team_department_module (department_id)');
        $this->addSql('CREATE INDEX IDX_54E05784AFC2B591 ON team_department_module (module_id)');
        $this->addSql(<<<'SQL'
            ALTER TABLE team_department_module
            ADD CONSTRAINT FK_54E05784AE80F5DF FOREIGN KEY (department_id) REFERENCES team_department (id)
            ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE team_department_module
            ADD CONSTRAINT FK_54E05784AFC2B591 FOREIGN KEY (module_id) REFERENCES module (id)
            ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE team_department_module DROP CONSTRAINT FK_54E05784AE80F5DF');
        $this->addSql('ALTER TABLE team_department_module DROP CONSTRAINT FK_54E05784AFC2B591');
        $this->addSql('DROP TABLE team_department_module');
    }
}
