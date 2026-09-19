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
 * ONE LINE, ONE SET OF BARS, ONE BAND OF A STACK.
 *
 * THE POINTS KEEP THEIR HOLES. A period nobody wrote down is null and the
 * chart draws a gap; closing it up would draw a line through a month that
 * never reported, which is the one thing a chart must not invent.
 */
final readonly class ChartSeries
{
    /**
     * @param list<float|null> $points one per label on the chart's axis, in the same order
     * @param int|null         $cat    the category this series wears, 1 to 9 — its
     *                                 position in its own declared order, never a
     *                                 colour: the host owns the palette and it turns
     *                                 over with the theme
     */
    public function __construct(
        public string $label,
        public array $points,
        public ?int $cat = null,
    ) {
    }
}
