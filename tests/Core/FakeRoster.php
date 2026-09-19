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

namespace Uhifadhi\Core\Tests\Core;

use Uhifadhi\Contracts\Roster\Watch;
use Uhifadhi\Contracts\Roster\WatchProviderInterface;

/**
 * THE ROSTER MODULE THIS PLATFORM HAS NOT WRITTEN YET, played by a fixture
 * and tagged by hand exactly as a real module would tag its own.
 *
 * IT KEEPS ONE WATCH, ON ONE DAY. That is enough to say two things the
 * contract turns on and nothing else: a watch the area does not own reaches
 * the handset through the seam, and **a day with no watch is a rest day** —
 * a window this roster has nothing in answers an empty list, which is the
 * answer and not a gap.
 *
 * IT ANSWERS FOR WHOEVER ASKS, deliberately. Which person is rostered is the
 * roster module's business and there is none here to have an opinion; what
 * these specifications are about is the area's side of the seam.
 */
final readonly class FakeRoster implements WatchProviderInterface
{
    /** The one day this stand-in roster has anybody on. */
    public const string THE_DAY = '2026-09-19';

    public function watchesFor(string $areaUuid, string $personUuid, string $from, string $to): array
    {
        if ($from > self::THE_DAY || $to < self::THE_DAY) {
            return [];
        }

        return [
            new Watch(
                self::THE_DAY,
                new \DateTimeImmutable(self::THE_DAY.'T06:00:00+03:00'),
                new \DateTimeImmutable(self::THE_DAY.'T18:00:00+03:00'),
                null,
                'Day watch',
            ),
        ];
    }
}
