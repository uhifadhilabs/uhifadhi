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

namespace Uhifadhi\Bundle\TeamBundle\Service;

/**
 * THE FIVE FIGURES THE TEAM SECTION REMEMBERS, and the words they are
 * remembered under.
 *
 * THE KEY IS THE FIGURE'S PUBLISHED NAME, under the same naming rule the
 * department history uses: the scope, a dot, the figure. They are named on a
 * class rather than typed at each call site because a page that spelled one
 * differently would look for a history that is there and find nothing — the
 * one failure a history has that nobody notices.
 */
final readonly class TeamFigures
{
    /** Everybody with an account, active or not — the register's own count. */
    public const string PEOPLE = 'team.people';

    /** Every position, in a department or loose. */
    public const string POSITIONS = 'team.positions';

    /** The positions somebody actually holds. */
    public const string FILLED = 'team.filled';

    /** Standing postings, across every area — the ground's figure, read here. */
    public const string POSTINGS = 'team.postings';

    /** How many people may administer the team, by tier or by grant. */
    public const string ADMINISTRATORS = 'team.administrators';

    /**
     * The five, in the order the overview draws them.
     *
     * @return list<string>
     */
    public static function all(): array
    {
        return [self::PEOPLE, self::POSITIONS, self::FILLED, self::POSTINGS, self::ADMINISTRATORS];
    }
}
