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
 * ONE DAY OF A MONTH, AND WHAT A MODULE PUTS ON IT.
 *
 * THE COUNT IS THE DAY'S, NOT THE CELL'S. A cell shows as many pills as
 * fit and says "+N more"; the count in its corner is how many there
 * REALLY were, so a full day still reads as full. A feed that computed
 * it from the pills it is handing over would be right by accident — a
 * feed may legitimately publish three pills for a day that had forty.
 *
 * A DAY WITH NOTHING ON IT IS NOT SENT, and a feed that sent one would
 * be saying "nought" where the honest answer is "nothing happened".
 * {@see CalendarMonth} keys only the days it has something for.
 *
 * THE URL IS THE DAY'S OWN — where "+N more" goes, and where the cell
 * goes when it is clicked. The module owns it, as it owns a pill's.
 */
final readonly class CalendarDay
{
    /** @param list<CalendarPill> $pills in the order the module wants them read */
    public function __construct(
        /** `2026-09-19`. */
        public string $localDate,
        public array $pills = [],
        /** How many there really were; null means "as many as there are pills". */
        public ?int $count = null,
        public ?string $url = null,
    ) {
        if (1 !== preg_match('/^\d{4}-\d{2}-\d{2}$/', $localDate)) {
            throw new \InvalidArgumentException(\sprintf('A calendar day is written 2026-09-19; "%s" is not.', $localDate));
        }
    }

    public function total(): int
    {
        return $this->count ?? \count($this->pills);
    }
}
