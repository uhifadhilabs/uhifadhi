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

namespace Uhifadhi\Bundle\AreaBundle\Model;

/**
 * HOW MANY POSTS STAND IN EACH ZONE OF AN AREA, AND HOW MANY PEOPLE WORK OUT
 * OF THEM — the two numbers every zone card states shut.
 *
 * COUNTED FOR THE WHOLE AREA IN TWO QUERIES, not per zone. A page draws eleven
 * cards, and a count per zone per card is twenty-two round trips for two
 * numbers a GROUP BY already knows.
 *
 * A ZONE THAT IS NOT IN EITHER MAP HAS NONE. Absence is the ordinary answer —
 * most zones of a newly imported set have no post on them yet — so it is a
 * nought here and the word "no station" on the card, never a missing fact.
 */
final readonly class ZoneStaffing
{
    /**
     * @param array<string, int> $stations zone uuid to the posts standing in it
     * @param array<string, int> $people   zone uuid to the people posted to those posts
     */
    public function __construct(
        public array $stations,
        public array $people,
    ) {
    }

    public function stationsIn(string $zoneUuid): int
    {
        return $this->stations[$zoneUuid] ?? 0;
    }

    public function peopleIn(string $zoneUuid): int
    {
        return $this->people[$zoneUuid] ?? 0;
    }
}
