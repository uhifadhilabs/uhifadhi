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
 * ONE CHART, STATED RATHER THAN DRAWN.
 *
 * THE MAP PLATE'S SIBLING, AND THE SAME BARGAIN. A module says what its
 * series is, what the axis reads, what it is counted in and what it was
 * measured against; the atlas owns the colours, the grid, the axes, the
 * legend and the height — so every chart in the product reads the same
 * way and a module cannot invent a fifth look.
 *
 * A FIXED BOX. A chart that grew with its data would make one topic's
 * page twice another's; the height is the platform's and the design's,
 * and a caller may state one only through the same custom-property door
 * a plate's height comes through.
 *
 * A TARGET IS A FACT. It is what somebody committed to, and a chart of
 * attainment without it is a chart of a number.
 */
final readonly class AtlasChart
{
    /**
     * @param list<string>      $labels the axis, one per point in every series
     * @param list<ChartSeries> $series
     */
    public function __construct(
        public ChartKind $kind,
        public array $labels,
        public array $series,
        public ?float $target = null,
        public string $unit = '',
        /** What the target line is called, where "Target" is not the word. */
        public string $targetLabel = 'Target',
    ) {
    }

    /** A chart nobody published a point in is not drawn at all. */
    public function isEmpty(): bool
    {
        foreach ($this->series as $series) {
            foreach ($series->points as $point) {
                if (null !== $point) {
                    return false;
                }
            }
        }

        return true;
    }
}
