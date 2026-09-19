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
 * WHICH CATEGORY EACH ZONE IN A SET IS — its position in the register's
 * order, and never a colour.
 *
 * THE COLOUR IS THE ZONE. On the configure plate a zone has no label of its
 * own: the hue is what ties a ring on the map to a row in the key and a swatch
 * on its card, so the same zone must read the same in all three. That is why
 * this is one answer in one place.
 *
 * AND THE ANSWER IS A POSITION, NOT A HEX. This used to hand out eleven
 * literals of its own, so a zone was one colour on the map and the house
 * palette was another set entirely, and neither could follow the theme or the
 * imagery. A zone is one of a SET, which is exactly what the product's nine
 * categories are for: the register's order decides the position, `[data-cat]`
 * resolves it for the page, and {@see PlatePalette::category()} resolves it
 * for the plate.
 *
 * IT WRAPS AT NINE, FOR NOW. An area with a tenth zone starts the nine again
 * rather than running out — two zones alike at opposite ends of a plate is a
 * smaller problem than a zone with no colour at all. WHAT SHOULD ACTUALLY
 * HAPPEN at the tenth is an open question with the owner; when it is answered
 * this is the one place that changes.
 */
final readonly class ZonePalette
{
    /** How many categories the product has, and therefore where this wraps. */
    public const int CATEGORIES = 9;

    /** The category a zone at this position in its set wears, 1 to 9. */
    public static function catFor(int $position): int
    {
        return $position % self::CATEGORIES + 1;
    }
}
