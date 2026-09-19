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
 * ONE PERSON'S DAY, AS READ — the claim they made, and what the
 * positions say about it.
 *
 * NOTHING HERE IS STORED. Every field is derived at the moment of
 * asking from rows that record only what was said and what was
 * measured; a page, a roster module and a handset asking the same
 * question get the same answer, and a catchment corrected next month
 * changes all three.
 *
 * THE WORDS ARE THE AREA'S. `statusLabel` is what the organisation
 * calls this status — "On escort", "Court appearance" — and
 * {@see DayState} is what the platform reasons about. A surface prints
 * the label and branches on the state.
 */
final readonly class PersonDay
{
    public function __construct(
        public string $personUuid,
        public string $personName,
        /** `2026-09-19` — the ranger's own day. */
        public string $localDate,
        public DayState $state,
        /** The area's own word for the status claimed, where there is one. */
        public ?string $statusKey = null,
        public ?string $statusLabel = null,
        public ?string $stationUuid = null,
        public ?string $stationName = null,
        /** Why an at-post day is unverified. Null unless it is. */
        public ?UnverifiedReason $unverifiedReason = null,
        /** How far the best position was from the post, where both exist. */
        public ?float $distanceM = null,
        public ?\DateTimeImmutable $occurredAt = null,
        public ?\DateTimeImmutable $endedAt = null,
        /**
         * THE WATCH ENDED AND NOBODY CHECKED OUT. A fact, not a failure:
         * the phone stops pinging and the server closes the watch at the
         * rostered end. Derived on read, like everything else here — a
         * state that needed a nightly job to be true would be wrong every
         * night the job did not run.
         */
        public bool $notCheckedOut = false,
        /** When the last ping arrived, where any did. */
        public ?\DateTimeImmutable $lastPingAt = null,
        public int $pings = 0,
        /** What the ranger handed to the next watch. */
        public ?string $handoverNote = null,
        /** The reason they gave, where the status takes one. */
        public ?string $note = null,
    ) {
    }
}
