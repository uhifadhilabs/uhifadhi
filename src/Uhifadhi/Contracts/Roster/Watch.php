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

namespace Uhifadhi\Contracts\Roster;

/**
 * ONE WATCH SOMEBODY IS ROSTERED FOR.
 *
 * THE DAY IS THE RANGER'S, and it is stated rather than derived: a 06:00
 * watch belongs to that date whatever offset the instants carry.
 *
 * A DAY WITH NO WATCH IS A REST DAY. There is no "rest" value here and
 * there should not be: an absence is the answer, and a value for it
 * would be a thing a roster has to remember to write.
 */
final readonly class Watch
{
    public function __construct(
        /** `2026-09-19` — the ranger's own day. */
        public string $localDate,
        public \DateTimeImmutable $startsAt,
        public \DateTimeImmutable $endsAt,
        /** The post, where the watch names one. */
        public ?string $stationUuid = null,
        /** Printed as given: "Day watch", "Night watch", "Gate relief". */
        public string $label = 'Day watch',
    ) {
    }
}
