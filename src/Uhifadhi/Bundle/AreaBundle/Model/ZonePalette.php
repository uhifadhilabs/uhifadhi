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
 * THE HUES A ZONE SET IS DRAWN IN, defined once and spent everywhere.
 *
 * HUE IS THE ZONE. On the configure plate a zone has no label of its own — the
 * colour is what ties a ring on the map to a row in the key and a swatch on its
 * card, so the same zone must be the same colour in all three or the page is
 * lying. That is why this is one list in one place rather than three literals.
 *
 * QUALITATIVE, NOT SEQUENTIAL. Zones are classes and nothing about them is
 * ordered, so the hues are chosen to be told apart from each other and from
 * satellite imagery — no earth tones, which vanish over dry ground — and never
 * to read as more and less of anything.
 *
 * THE LIST WRAPS. An area with more zones than hues reuses them from the start
 * rather than running out; two zones the same colour far apart on a plate is a
 * smaller problem than a zone with no colour at all.
 */
final readonly class ZonePalette
{
    /** @var list<string> */
    public const array HUES = [
        '#3FC7D4', '#C85A93', '#9DBF4A', '#E8C15A', '#D9584B', '#6E7FE0',
        '#4FA8E8', '#9B6BD8', '#5FBF7A', '#E0854A', '#C9A67E',
    ];

    public static function hueFor(int $position): string
    {
        return self::HUES[$position % \count(self::HUES)];
    }
}
