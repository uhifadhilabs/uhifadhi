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
 * ONE ZONE AS THE CONFIGURE PAGE READS IT — the card, the key row and the ring
 * on the plate all take their facts from here, which is what makes the colour
 * in the three places the same colour.
 *
 * THE SIZE IS THE DATABASE'S ANSWER, measured on the spheroid, because a number
 * computed from degrees is wrong by a factor that grows with latitude.
 *
 * THE SHARE IS NULL, NOT ZERO, WHERE THERE IS NO BOUNDARY. An area is gazetted
 * and named before its edge is imported, and "5.1% of nothing" is a number that
 * would be believed.
 */
final readonly class ZoneRow
{
    public function __construct(
        public string $uuid,
        public string $name,
        public string $hue,
        public int $km2,
        public ?float $shareOfArea,
    ) {
    }
}
