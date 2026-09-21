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
 * A GRANT BECOMES A (CONCERN, VERB) PAIR.
 *
 * EXPAND ONLY. The pairs arrive in a column of their own and the old flat
 * values stay exactly where they are, because the gates that read them move
 * over one release at a time: dropping the old column in the release that
 * stopped writing it would take every grant an organization has made with
 * it. `team_position.permissions` goes in the release after the last gate
 * moves, under the two-release rule.
 *
 * WHAT EACH OLD VALUE BECOMES. The mapping is the ruling's, and the reasoning
 * is worth keeping beside it because two of the rows are judgements rather
 * than translations:
 *
 *   area.view     -> areas.read
 *   area.create   -> areas.configure
 *   area.edit     -> areas.configure
 *   area.delete   -> areas.configure
 *   module.view   -> modules.read
 *   module.create -> modules.configure
 *   duty.checkin  -> duty.record
 *   team.manage   -> directory.read, directory.manage, personal-details.read,
 *                    personal-details.manage, positions.read,
 *                    positions.configure, departments.read,
 *                    departments.configure
 *
 * THE THREE AREA VALUES COLLAPSE INTO ONE PAIR, and that is a real loss of
 * distinction rather than a tidy-up. Identity, boundary and settings are
 * configuration, so `area.edit` is plainly `areas.configure`; creating an
 * area and deleting one are neither field facts nor settings, and the ruling
 * gives no verb that fits them better. Anybody holding one of the three held
 * `areas.configure`'s worth of power in practice, so nobody gains anything
 * here - but an installation that deliberately gave somebody `area.view` and
 * `area.create` while withholding `area.edit` will find that distinction
 * gone, and should re-read those positions.
 *
 * TEAM.MANAGE FANS OUT TO EIGHT PAIRS, because it was one value gating the
 * whole of team administration and the ruling breaks that apart. The backfill
 * is deliberately GENEROUS: everybody who could administer the team keeps
 * being able to, and an organization that wants the finer grain now has the
 * rows to take away. The alternative - guessing which half of team
 * administration each holder was meant to have - would lock somebody out of
 * a page they used yesterday, and a missing permission discovered by the
 * person who needed it is only cheap when they can see why.
 *
 * IT IS IDEMPOTENT AND ORDER-FREE. Each statement adds one pair to the rows
 * that hold one old value, and a position holding several old values
 * accumulates the union without duplicates.
 */
final class Version20260921002000 extends AbstractMigration
{
    /**
     * The ruling's mapping, as old value => the pairs it becomes.
     *
     * @return array<string, list<string>>
     */
    private const array MAPPING = [
        'area.view' => ['areas.read'],
        'area.create' => ['areas.configure'],
        'area.edit' => ['areas.configure'],
        'area.delete' => ['areas.configure'],
        'module.view' => ['modules.read'],
        'module.create' => ['modules.configure'],
        'duty.checkin' => ['duty.record'],
        'team.manage' => [
            'directory.read',
            'directory.manage',
            'personal-details.read',
            'personal-details.manage',
            'positions.read',
            'positions.configure',
            'departments.read',
            'departments.configure',
        ],
    ];

    public function getDescription(): string
    {
        return 'team_position.grants, backfilled from the flat permission values';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE team_position ADD grants JSON DEFAULT '[]' NOT NULL");

        foreach (self::MAPPING as $old => $pairs) {
            foreach ($pairs as $pair) {
                // jsonb_build_array is the only way to append a string to a
                // json column without parsing it in PHP; the containment test
                // keeps the statement idempotent and stops a position that
                // holds two old values mapping to one pair from storing it
                // twice.
                $this->addSql(<<<SQL
                    UPDATE team_position
                    SET grants = (grants::jsonb || jsonb_build_array('{$pair}'))::json
                    WHERE permissions::jsonb @> jsonb_build_array('{$old}')
                      AND NOT grants::jsonb @> jsonb_build_array('{$pair}')
                    SQL);
            }
        }
    }

    /**
     * @destructive 1.0 - team_position.grants is dropped here, and it is the
     *              column this very version created: nothing read it before
     *              this release, so a rollback loses only what the backfill
     *              computed from `permissions`, which is still there.
     */
    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE team_position DROP grants');
    }
}
