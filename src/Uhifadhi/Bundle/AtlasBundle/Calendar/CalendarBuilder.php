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

namespace Uhifadhi\Bundle\AtlasBundle\Calendar;

use Uhifadhi\Bundle\AtlasBundle\Model\AtlasCalendar;
use Uhifadhi\Bundle\AtlasBundle\Model\CalendarCell;
use Uhifadhi\Contracts\Atlas\CalendarMonth;
use Uhifadhi\Contracts\Atlas\YearMonth;

/**
 * TURNS A MONTH A MODULE PUBLISHED INTO THE GRID THE ATLAS DRAWS.
 *
 * THE LAYOUT IS THE PLATFORM'S AND NOT THE MODULE'S. Which weekday the
 * first falls on, how many days spill in from the months either side,
 * which cell is today, and — the rule the whole component exists for —
 * HOW MANY PILLS A CELL HOLDS. A module hands over everything it has
 * for a day; the cell is one fixed height whatever it holds, so the
 * surplus becomes "+N more" here, where the height is known.
 *
 * WHOLE WEEKS, ALWAYS. Five rows or six, never a ragged last line: a
 * grid that ended mid-row would put the 30th under a different column
 * width from the 2nd.
 *
 * TODAY IS THE VIEWER'S TODAY and is handed in rather than read off the
 * clock, because every instant in this product is read in the viewer's
 * zone and a builder that called `now()` would ring the wrong square
 * for half the world.
 */
final readonly class CalendarBuilder
{
    /**
     * HOW MANY PILLS FIT, at the design's 96px cell.
     *
     * Measured against the drawn month rather than chosen: three marks
     * and a "+N more" is what the cell holds without the overflow
     * hiding a line halfway. A caller that changes the cell height
     * changes this with it, which is why it is an argument and not a
     * constant somewhere private.
     */
    public const int PILLS_PER_CELL = 3;

    /** Monday, stated once — see {@see AtlasCalendar::WEEKDAYS}. */
    private const int WEEK_STARTS_ON = 1;

    public function build(
        CalendarMonth $published,
        ?\DateTimeImmutable $today = null,
        int $pillsPerCell = self::PILLS_PER_CELL,
        ?string $previousUrl = null,
        ?string $nextUrl = null,
    ): AtlasCalendar {
        $month = $published->month;
        $todayDate = $today?->format('Y-m-d');

        $cursor = self::firstCellOf($month);
        $cells = [];

        // WHOLE WEEKS UNTIL THE MONTH IS COVERED. The condition is the
        // month's last day rather than a cell count, so a 28-day February
        // beginning on a Monday is four rows and not a padded six.
        do {
            for ($i = 0; $i < 7; ++$i) {
                $date = $cursor->format('Y-m-d');
                $day = $published->day($date);
                $pills = null === $day ? [] : $day->pills;
                $shown = \array_slice($pills, 0, max(0, $pillsPerCell));

                $cells[] = new CalendarCell(
                    localDate: $date,
                    dayOfMonth: (int) $cursor->format('j'),
                    inMonth: (int) $cursor->format('n') === $month->month && (int) $cursor->format('Y') === $month->year,
                    isToday: $date === $todayDate,
                    pills: $shown,
                    // WHAT DID NOT FIT, counted against everything the DAY
                    // had — which may be more than the feed handed over, so
                    // a day of forty reads "+37 more" and not "+0".
                    hidden: max(0, ($day?->total() ?? 0) - \count($shown)),
                    total: $day?->total() ?? 0,
                    url: $day?->url,
                );

                $cursor = $cursor->modify('+1 day');
            }
        } while ($cursor <= $month->lastDay());

        return new AtlasCalendar($month, $cells, $previousUrl, $nextUrl);
    }

    /** The Monday on or before the first of the month. */
    private static function firstCellOf(YearMonth $month): \DateTimeImmutable
    {
        $first = $month->firstDay();
        $weekday = (int) $first->format('N');

        return $first->modify(\sprintf('-%d days', ($weekday - self::WEEK_STARTS_ON + 7) % 7));
    }
}
