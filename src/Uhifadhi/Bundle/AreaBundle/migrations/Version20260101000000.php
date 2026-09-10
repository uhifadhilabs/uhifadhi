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
 * THE FIRST THING THAT RUNS IN AN INSTALLATION.
 *
 * A gazetted boundary is a `geometry(MULTIPOLYGON,4326)` column and a zone's
 * outline is another, and the type does not exist until PostGIS does — so the
 * extension is its own version, dated before every other, in the bundle that
 * owns the first table to need it.
 *
 * `IF NOT EXISTS` because a managed database usually arrives with PostGIS
 * already enabled by whoever provisioned it, and an installation that is handed
 * one should not be told to undo that.
 *
 * `CREATE EXTENSION` is not a right every database grants: PostGIS is not a
 * trusted extension, so it wants a superuser. A hosted database that withholds
 * that has the extension enabled for it by the provider — after which this runs
 * and does nothing.
 */
final class Version20260101000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'PostGIS, before the first geometry column asks for it';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE EXTENSION IF NOT EXISTS postgis');
    }

    public function down(Schema $schema): void
    {
        // Deliberately without CASCADE: if anything still has a geometry column
        // — a module's table, an installation's own — this refuses rather than
        // silently taking it with it.
        $this->addSql('DROP EXTENSION IF EXISTS postgis');
    }
}
