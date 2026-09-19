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
 * ONE STATION AND EVERYBODY STANDING AT IT, across every area at once.
 *
 * A STATION WITH NOBODY IS STILL A STATION. Its `posts` are empty and it is
 * still in the answer, because "nobody is posted here" is a reading somebody
 * came for — a station dropped from the list because it is empty is a station
 * the reader cannot tell from one that does not exist.
 *
 * THE ZONE MAY BE NULL AND THAT IS LEGAL: zones are presence-driven, so a post
 * may stand on ground that belongs to none.
 */
final readonly class PostedStation
{
    /**
     * @param list<StationPost> $posts everybody standing here today, leader first
     */
    public function __construct(
        public string $uuid,
        public string $name,
        public ?string $code,
        public string $areaUuid,
        public string $areaName,
        public ?string $zoneName,
        public array $posts = [],
    ) {
    }

    public function isEmpty(): bool
    {
        return [] === $this->posts;
    }
}
