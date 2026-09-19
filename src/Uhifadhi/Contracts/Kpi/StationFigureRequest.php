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
 * EVERY STATION OF ONE AREA, ASKED ABOUT AT ONCE.
 *
 * THE WHOLE SET IN ONE CALL, and that is the point of the shape. The stations
 * tab draws a row per station and a table under it; asking a provider once per
 * station would be a round trip per row per module per page, and a provider
 * answering "incidents within 12 km" with a spatial query would make the page
 * unaffordable. Handed the set, a provider runs one query grouped by station.
 *
 * A REQUEST MAY NAME ONE STATION. A record page is the same question with a
 * set of one, so there is no second method and no second code path for it.
 */
final readonly class StationFigureRequest
{
    /**
     * @param list<StationRef> $stations every station the caller wants figures for, in the order it will draw them
     */
    public function __construct(
        public array $stations,
        public FigurePeriod $period,
    ) {
    }

    /** @return list<string> */
    public function stationUuids(): array
    {
        return array_map(static fn (StationRef $station): string => $station->stationUuid, $this->stations);
    }

    public function isEmpty(): bool
    {
        return [] === $this->stations;
    }
}
