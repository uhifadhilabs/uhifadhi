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

use Uhifadhi\Contracts\Kpi\StationRef;

/**
 * THE POSTS A SURFACE IS ABOUT TO DRAW, ASKED ABOUT AT ONCE.
 *
 * THE WHOLE SET IN ONE CALL, for the reason {@see \Uhifadhi\Contracts\Kpi\StationFigureRequest}
 * gives: the configure page draws a card per station, and a contributor
 * asked once per card would run a query per card per module per page.
 * Handed the set, it runs one query grouped by station.
 *
 * A REQUEST MAY NAME ONE STATION. The record page is the same question with
 * a set of one, so there is no second method and no second code path for it.
 *
 * THE STATION ARRIVES AS A REF and not as an entity — the same ref the
 * figures seam uses, because it is the same fact about the same post and a
 * second class saying it would be one to keep in step.
 */
final readonly class StationSectionRequest
{
    /**
     * @param list<StationRef> $stations every post the caller is about to draw, in the order it will draw them
     */
    public function __construct(
        public array $stations,
        public StationSurface $surface,
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
