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

namespace Uhifadhi\Bundle\AtlasBundle\Model;

/**
 * ONE LINE, ONE SET OF BARS, ONE BAND OF A STACK.
 *
 * THE POINTS KEEP THEIR HOLES. A period nobody reported is null all the
 * way down to the library, which draws a gap; turning it into a nought
 * on the way would be a chart inventing a quiet month.
 *
 * A SWATCH IS THE EXCEPTION. A module whose colour is its own identity —
 * the hue its dot, its layer and its chip already wear — states it and
 * keeps it; everything else takes the platform's own, in order.
 */
final readonly class ChartSeries
{
    /**
     * @param list<float|null> $points one per label, in the same order
     */
    public function __construct(
        public string $label,
        public array $points,
        public ?string $swatch = null,
    ) {
    }
}
