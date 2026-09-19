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

namespace Uhifadhi\Contracts\Atlas;

/**
 * WHAT ONE MODULE HAS TO SAY ABOUT ONE MONTH.
 *
 * KEYED BY DAY, NOT ORDERED. The atlas lays the grid out itself — which
 * weekday the first falls on, how many days the month has, what spills
 * into the weeks either side — and a list would make the renderer match
 * days to cells by position, which survives exactly until a feed skips
 * a day it has nothing for.
 *
 * ONLY THE DAYS IT HAS SOMETHING FOR. A quiet day is simply absent, and
 * the cell the atlas draws for it is empty rather than a nought.
 *
 * THE MONTH IS RESTATED so that an answer can be checked against the
 * question: a feed handed September must not answer with August, and a
 * renderer that trusted the caller would draw the right grid over the
 * wrong data.
 */
final readonly class CalendarMonth
{
    /** @param array<string, CalendarDay> $days keyed `2026-09-19` */
    public function __construct(
        public YearMonth $month,
        public array $days = [],
    ) {
    }

    /** A feed with nothing for this month — an honest answer, and a common one. */
    public static function none(YearMonth $month): self
    {
        return new self($month);
    }

    public function day(string $localDate): ?CalendarDay
    {
        return $this->days[$localDate] ?? null;
    }

    public function isEmpty(): bool
    {
        return [] === $this->days;
    }

    /** Everything on the month, for a caller that wants a total rather than a grid. */
    public function total(): int
    {
        $total = 0;
        foreach ($this->days as $day) {
            $total += $day->total();
        }

        return $total;
    }
}
