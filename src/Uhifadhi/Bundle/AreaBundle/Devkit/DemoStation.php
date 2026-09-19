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

namespace Uhifadhi\Bundle\AreaBundle\Devkit;

/**
 * ONE POST IN THE DEMO TABLE — its name, its code, where it stands, and the two
 * facts its page prints beside the map.
 *
 * A ROW, NOT A SERVICE. It is read only by {@see StationContentProvider}, holds
 * no behaviour and is never persisted; the station it describes is written
 * through {@see \Uhifadhi\Bundle\AreaBundle\Service\StationService} like any
 * other.
 *
 * THE COORDINATES ARE OFFSETS FROM THE AREA'S SOUTH-WEST CORNER, so one table
 * of posts lays out identically inside every demo area — see {@see DemoArea}.
 */
final readonly class DemoStation
{
    public function __construct(
        public string $name,
        public string $code,
        public float $eastOfCorner,
        public float $northOfCorner,
        public int $elevationM,
        public string $locality,
    ) {
    }
}
