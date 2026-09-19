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
 * ONE PERSON STANDING AT ONE STATION, as the ground reports them.
 *
 * IT CARRIES A UUID AND NOT A NAME. Who the person IS — their name, the
 * position they hold, the department that position belongs to — is held by
 * whoever owns people, and a posting that carried a name would be a second
 * copy of it, stale the day somebody married.
 */
final readonly class StationPost
{
    public function __construct(
        public string $personUuid,
        public \DateTimeImmutable $since,
        public bool $leader = false,
    ) {
    }
}
