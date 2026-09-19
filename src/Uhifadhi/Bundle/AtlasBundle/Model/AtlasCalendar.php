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

namespace Uhifadhi\Bundle\AtlasBundle\Model;

use Uhifadhi\Contracts\Atlas\YearMonth;

/**
 * ONE MONTH, LAID OUT.
 *
 * THE PLATE'S AND THE CHART'S SIBLING, AND THE SAME BARGAIN. A module
 * says what happened on which day; the atlas owns the grid, the day
 * head, the cell, its fixed height, the day number, the "+N more" and
 * the stepper — so every month in the product reads the same way and a
 * module cannot invent a fifth one.
 *
 * A FIXED BOX, MEASURED IN ROWS. A month is five or six weeks and the
 * cells are one height, so a busy week never makes the month taller
 * than a quiet one. That is the whole reason this is a component
 * rather than a loop each module writes.
 *
 * THE WEEK STARTS ON MONDAY, stated once here rather than decided per
 * caller: two surfaces disagreeing about which column Sunday is in is
 * worse than either choice.
 */
final readonly class AtlasCalendar
{
    /** The day heads, in the order the grid draws them. */
    public const array WEEKDAYS = ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'];

    /** @param list<CalendarCell> $cells whole weeks, Monday first, 35 or 42 of them */
    public function __construct(
        public YearMonth $month,
        public array $cells,
        /** Where the stepper's arrows go, or null where the month does not step. */
        public ?string $previousUrl = null,
        public ?string $nextUrl = null,
    ) {
    }

    /** Whether any cell of this month carries anything at all. */
    public function isEmpty(): bool
    {
        foreach ($this->cells as $cell) {
            if ($cell->inMonth && !$cell->isEmpty()) {
                return false;
            }
        }

        return true;
    }
}
