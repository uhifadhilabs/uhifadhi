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

namespace Uhifadhi\Contracts\Kpi;

/**
 * EVERY ZONE OF ONE AREA, ASKED ABOUT AT ONCE.
 *
 * THE WHOLE SET IN ONE CALL, and that is the point of the shape. The all-zones
 * view draws a row per zone and the map legend draws another; asking a provider
 * once per zone would be two dozen round trips per module per page, and the
 * first provider to answer with a spatial query would make the page
 * unaffordable. Handed the set, a provider runs one query grouped by zone.
 *
 * A REQUEST MAY NAME ONE ZONE. A record page is the same question with a set of
 * one, so there is no second method and no second code path for it.
 */
final readonly class ZoneFigureRequest
{
    /**
     * @param list<ZoneRef> $zones every zone the caller wants figures for, in the order it will draw them
     */
    public function __construct(
        public array $zones,
        public FigurePeriod $period,
    ) {
    }

    /** @return list<string> */
    public function zoneUuids(): array
    {
        return array_map(static fn (ZoneRef $zone): string => $zone->zoneUuid, $this->zones);
    }

    public function isEmpty(): bool
    {
        return [] === $this->zones;
    }
}
