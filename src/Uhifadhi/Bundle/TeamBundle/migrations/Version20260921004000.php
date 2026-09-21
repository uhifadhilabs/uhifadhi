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
 * AND THE MODULES' OWN VALUES, BACKFILLED TOO.
 *
 * {@see Version20260921004000} — WHY THIS IS THE CORE'S JOB. The first
 * backfill translated the values the CORE declared and left everything else
 * where it was, on the reasoning that a module's values are the module's. That
 * reasoning is wrong about one thing: `team_position.grants` is the core's
 * table. A module cannot write a migration against a column it does not own,
 * so if the core does not carry the modules' values across, nobody does — and
 * an installation that upgrades goes dark on Patrols, Incidents and Roster all
 * at once, with nothing on any screen saying why, until somebody re-ticks
 * every position by hand.
 *
 * SO IT IS GENERIC, AND IT HAS TO BE. This migration cannot name a module: the
 * core does not know which are installed, and one written for the three we
 * happen to run today would silently skip the fourth. It reads the values that
 * are actually THERE, in the rows, and translates them by SHAPE:
 *
 *   ·  every old value `<slug>.<verb>` is granted unchanged as the pair
 *      `<slug>.<verb>` — a module wrote its values in the pair's own shape
 *      before there were pairs, which is the whole reason this is possible;
 *   ·  and `<slug>.read` is granted to every position that held ANY
 *      `<slug>.*` value, because READING is a declared power now and was not
 *      one before. Somebody who could record a patrol could obviously see the
 *      patrols; under the new model that is a pair they were never given, and
 *      without this line the screen they recorded from yesterday refuses them.
 *
 * THE CORE'S OWN OLD VALUES ARE EXCLUDED BY NAME, because they are the ones
 * that did NOT translate by shape — `area.view` became `areas.read` and
 * `team.manage` fanned out to eight — and {@see Version20260921002000} has
 * already done them. Left in, the shape rule would grant the pair `area.view`,
 * which no concern declares, and `area.read`, which is not the concern's name.
 *
 * IT ONLY ADDS. Nothing is removed from `grants`, `permissions` is not touched,
 * and a value that yields a pair the position already holds yields nothing —
 * so re-running this against a database it has already run against writes the
 * same set it found. That is what lets an operator re-run a migration they are
 * not sure completed.
 *
 * A PAIR NOTHING DECLARES IS STILL HONEST. A module that was uninstalled
 * between the two releases leaves values here whose concern no longer exists;
 * they are carried across as ORPHANED GRANTS, which the position screens draw
 * muted and revocable rather than dropping — that is the ruled treatment, and
 * a backfill that quietly discarded them would be deciding something an
 * administrator should be shown.
 */
final class Version20260921004000 extends AbstractMigration
{
    /**
     * The core's own old flat values — everything {@see Version20260921002000}
     * has already translated, and the only values whose new pair is not their
     * own spelling.
     *
     * @var list<string>
     */
    private const array THE_CORES_OWN = [
        'area.view',
        'area.create',
        'area.edit',
        'area.delete',
        'module.view',
        'module.create',
        'duty.checkin',
        'team.manage',
    ];

    public function getDescription(): string
    {
        return 'team_position.grants, backfilled from the modules’ flat permission values';
    }

    public function up(Schema $schema): void
    {
        $excluded = implode(', ', array_map(
            static fn (string $value): string => "'".$value."'",
            self::THE_CORES_OWN,
        ));

        /*
         * ONE STATEMENT, AND IT IS A SET UNION. The position's grants, plus
         * every module value it holds, plus that value's `<slug>.read` — read
         * back distinct and ordered, so two installations with the same rows
         * store byte-identical json and a later diff has nothing to report.
         *
         * THE SLUG IS EVERYTHING BEFORE THE LAST DOT, which is the ruled
         * reading of a pair: the verb is the last segment, so a concern key
         * may carry hyphens AND dots ("observation-kinds.configure" is one
         * concern and one verb).
         *
         * A VALUE WITH NO DOT IS NOT A PAIR and is left alone rather than
         * guessed at — it would yield a verb-less pair nothing could ever
         * match, and inventing one is worse than carrying nothing.
         */
        $this->addSql(<<<SQL
            UPDATE team_position p
            SET grants = (
                SELECT coalesce(to_json(array_agg(pair ORDER BY pair)), '[]'::json)
                FROM (
                    SELECT jsonb_array_elements_text(coalesce(p.grants, '[]')::jsonb) AS pair
                    UNION
                    SELECT v AS pair
                    FROM jsonb_array_elements_text(coalesce(p.permissions, '[]')::jsonb) AS v
                    WHERE v NOT IN ({$excluded}) AND v ~ '^.+\\.[^.]+$'
                    UNION
                    SELECT regexp_replace(v, '\\.[^.]+$', '') || '.read' AS pair
                    FROM jsonb_array_elements_text(coalesce(p.permissions, '[]')::jsonb) AS v
                    WHERE v NOT IN ({$excluded}) AND v ~ '^.+\\.[^.]+$'
                ) pairs
            )
            WHERE EXISTS (
                SELECT 1
                FROM jsonb_array_elements_text(coalesce(p.permissions, '[]')::jsonb) AS v
                WHERE v NOT IN ({$excluded}) AND v ~ '^.+\\.[^.]+$'
            )
            SQL);
    }

    /**
     * NOTHING TO UNDO, AND SAYING SO IS THE HONEST ANSWER.
     *
     * This version adds pairs to a column that already existed and holds pairs
     * from a backfill before it; there is no statement that could take back
     * exactly the ones added here without also taking back a grant somebody has
     * made since. The column's own inverse is where it was created —
     * {@see Version20260921002000::down()} drops it — and that is the rollback
     * an operator rehearsing an upgrade actually runs.
     */
    public function down(Schema $schema): void
    {
        $this->skipIf(true, 'Backfills add pairs; there is nothing to take back that is provably this version’s.');
    }
}
