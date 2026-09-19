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

namespace Uhifadhi\Contracts\Area;

/**
 * ONE PERSON'S DAY, AS READ — every watch they worked, and what the
 * positions say about each.
 *
 * A DAY IS A LIST, NOT A FROM-TO. Ruled 2026-09-21: a day holds ANY
 * number of watches. Somebody checks in at dawn, checks out at noon and
 * checks in again at four, and all three facts are the same day — a
 * timesheet. A shape with one `occurredAt` and one `endedAt` could only
 * hold the first of them, and the second check-in would either be lost
 * or arrive as a second "day" for the same person on the same date.
 *
 * WHAT IS THE DAY'S AND WHAT IS A WATCH'S. Only what is true of the
 * whole day is here: who, which day, how it reads, and the totals. The
 * status, the post, the times and the proof belong to ONE stretch of
 * duty and live on {@see PersonWatch}.
 *
 * NOTHING HERE IS STORED. Every field is derived at the moment of
 * asking from rows that record only what was said and what was
 * measured; a page, a roster module and a handset asking the same
 * question get the same answer, and a catchment corrected next month
 * changes all three.
 */
final readonly class PersonDay
{
    /** @param list<PersonWatch> $watches in the order they were claimed */
    public function __construct(
        public string $personUuid,
        public string $personName,
        /** `2026-09-19` — the ranger's own day. */
        public string $localDate,
        /**
         * HOW THE DAY READS, which is the LAST watch's reading: what
         * somebody is doing now — or finished the day doing — is what a
         * board is asking when it colours a name. The watches are there
         * for anybody who needs more than that.
         */
        public DayState $state,
        public array $watches = [],
        /** When the day's FIRST claim was made. */
        public ?\DateTimeImmutable $occurredAt = null,
        /** When the last ping of the whole day arrived, where any did. */
        public ?\DateTimeImmutable $lastPingAt = null,
        /** Every ping of every watch. */
        public int $pings = 0,
    ) {
    }

    /**
     * THE DAY'S TOTAL, in minutes, summed over the watches that closed.
     *
     * AN OPEN WATCH ADDS NOTHING YET and is not nought: it has no length
     * until somebody checks out, and guessing one would make a total
     * that changes when nobody did anything. {@see hasOpenWatch()} is
     * how a surface says "and still working".
     */
    public function minutesOnDuty(): int
    {
        $total = 0;
        foreach ($this->watches as $watch) {
            $total += $watch->minutes() ?? 0;
        }

        return $total;
    }

    public function hasOpenWatch(): bool
    {
        foreach ($this->watches as $watch) {
            if ($watch->isOpen()) {
                return true;
            }
        }

        return false;
    }

    /** The last watch of the day — the one a board reads the name's colour from. */
    public function lastWatch(): ?PersonWatch
    {
        return [] === $this->watches ? null : $this->watches[array_key_last($this->watches)];
    }
}
