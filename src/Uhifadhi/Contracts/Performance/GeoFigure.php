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

namespace Uhifadhi\Contracts\Performance;

/**
 * ONE FIGURE ABOUT ONE PIECE OF GROUND.
 *
 * THE GROUND IS NAMED BY ITS IDENTIFIER, never by its geometry: the area
 * bundle owns the shapes and draws them, and a module publishing a
 * figure about an area must not have to carry a polygon to say so.
 */
final readonly class GeoFigure
{
    public function __construct(
        /** The area's or the zone's uuid — whichever this series is over. */
        public string $uuid,
        /** What it is called, for the plate's key and its hover. */
        public string $label,
        /** Null where the module published nothing for this ground. */
        public ?float $value,
    ) {
    }
}
