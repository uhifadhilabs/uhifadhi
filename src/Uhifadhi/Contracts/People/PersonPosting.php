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

namespace Uhifadhi\Contracts\People;

/**
 * WHERE ONE PERSON WORKS OUT OF, as their own page reads it.
 *
 * THE SEAM POINTS THE OTHER WAY. A station's board asks who is posted here;
 * this answers where is this person posted — and the two are the same rows
 * read from opposite ends. The person's page belongs to whoever owns people
 * and the postings belong to the area, so the answer crosses as values.
 *
 * IT CARRIES WHAT THE PAGE PRINTS AND NOT A ROW. A name, a code, the area and
 * the zone the post stands in, since when, and whether they lead there —
 * everything a line on a person's page shows, and nothing that would make the
 * reader depend on the area's classes to show it.
 *
 * THE ZONE MAY BE NULL AND THAT IS LEGAL: zones are presence-driven, so a post
 * may stand on ground that belongs to none.
 */
final readonly class PersonPosting
{
    public function __construct(
        public string $stationUuid,
        public string $stationName,
        public ?string $stationCode,
        public string $areaUuid,
        public string $areaName,
        public ?string $zoneName,
        public \DateTimeImmutable $since,
        public bool $leader = false,
    ) {
    }
}
