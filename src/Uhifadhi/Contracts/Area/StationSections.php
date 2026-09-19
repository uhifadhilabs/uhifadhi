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
 * WHAT ONE MODULE CONTRIBUTES TO A SET OF POSTS, keyed by the post it is
 * about.
 *
 * KEYED, NOT ORDERED, for the reason the figures answer is: the caller
 * asked about a set and draws them in its own order, and matching by
 * position survives only until a contributor skips a post it has nothing
 * for.
 *
 * A POST THE CONTRIBUTOR HAD NOTHING FOR IS AN EMPTY LIST, and the surface
 * draws NOTHING — no band, no heading, no placeholder. A module that does
 * not keep this post on its books is silent about it, and silence is the
 * honest answer.
 */
final readonly class StationSections
{
    /**
     * @param array<string, list<StationSection>> $byStation station uuid to the sections this module puts on it
     */
    public function __construct(
        public array $byStation,
    ) {
    }

    /** A contributor with nothing to add here — an honest answer, and a common one. */
    public static function none(): self
    {
        return new self([]);
    }

    /** @return list<StationSection> */
    public function forStation(string $stationUuid): array
    {
        return $this->byStation[$stationUuid] ?? [];
    }

    public function isEmpty(): bool
    {
        foreach ($this->byStation as $sections) {
            if ([] !== $sections) {
                return false;
            }
        }

        return true;
    }
}
