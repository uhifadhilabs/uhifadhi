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
 * ONE WATCH: a check-in, and the check-out that closed it.
 *
 * A DAY HOLDS AS MANY OF THESE AS SOMEBODY WORKED. Ruled 2026-09-21:
 * a ranger may check in at dawn, check out at noon, and check in again
 * at four — a timesheet, not a single from-to. Everything that belongs
 * to ONE stretch of duty lives here; only what is true of the whole day
 * stays on {@see PersonDay}.
 *
 * NOTHING HERE IS STORED. Every field is derived at the moment of
 * asking from rows that record only what was said and what was
 * measured, so a catchment corrected next month re-reads every watch
 * that used it.
 *
 * THE WORDS ARE THE AREA'S. `statusLabel` is what the organization
 * calls this status — "On escort", "Court appearance" — and
 * {@see DayState} is what the platform reasons about. A surface prints
 * the label and branches on the state.
 */
final readonly class PersonWatch
{
    public function __construct(
        /** The client's own reference for the claim, so a caller can amend it. */
        public string $clientRef,
        public DayState $state,
        /** The area's own word for the status claimed, where there is one. */
        public ?string $statusKey = null,
        public ?string $statusLabel = null,
        public ?string $stationUuid = null,
        public ?string $stationName = null,
        /** Why an at-post watch is unverified. Null unless it is. */
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
        /** When the last ping of THIS watch arrived, where any did. */
        public ?\DateTimeImmutable $lastPingAt = null,
        public int $pings = 0,
        /** What the ranger handed to the next watch. */
        public ?string $handoverNote = null,
        /** The reason they gave, where the status takes one. */
        public ?string $note = null,
    ) {
    }

    /**
     * HOW LONG THIS WATCH RAN, in minutes — or null while it is still
     * open, which is not nought. A watch nobody has closed has no length
     * yet, and counting it as zero would make a day's total shrink as
     * somebody worked.
     */
    public function minutes(): ?int
    {
        if (null === $this->occurredAt || null === $this->endedAt) {
            return null;
        }

        return (int) max(0, floor(($this->endedAt->getTimestamp() - $this->occurredAt->getTimestamp()) / 60));
    }

    /** Whether this watch is still open — no check-out, and not closed for one. */
    public function isOpen(): bool
    {
        return null === $this->endedAt && !$this->notCheckedOut;
    }
}
