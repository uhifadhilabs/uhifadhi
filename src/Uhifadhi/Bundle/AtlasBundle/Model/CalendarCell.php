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

use Uhifadhi\Contracts\Atlas\CalendarPill;

/**
 * ONE SQUARE OF THE MONTH GRID, ready to draw.
 *
 * WHAT THE FEED SAID, BOUNDED. `pills` is what fits; `hidden` is how
 * many did not, which the cell prints as "+N more". The feed handed
 * over everything and this is where the layout's limit was applied —
 * the module cannot know how many fit, and must not be asked to guess.
 *
 * `inMonth` IS THE GRID'S BUSINESS. A month starts mid-week, so the
 * first and last rows carry days of the months either side; they are
 * drawn faint and they are still real dates.
 */
final readonly class CalendarCell
{
    /** @param list<CalendarPill> $pills as many as the cell holds, in the feed's order */
    public function __construct(
        /** `2026-09-19`. */
        public string $localDate,
        /** The number in the corner — 1 to 31. */
        public int $dayOfMonth,
        public bool $inMonth,
        public bool $isToday,
        public array $pills = [],
        /** How many the cell could not hold. */
        public int $hidden = 0,
        /** Everything the day really had, which may exceed what was handed over. */
        public int $total = 0,
        /** Where "+N more" goes, and the day's own page. */
        public ?string $url = null,
    ) {
    }

    public function isEmpty(): bool
    {
        return [] === $this->pills && 0 === $this->total;
    }
}
