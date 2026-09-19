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
 * ONE OF A TOPIC'S TWO OR THREE CHARTS.
 *
 * A FIXED HEIGHT AND A STATED SHAPE. The page gives every chart the same
 * box — a chart that grew with its data would make one topic's page twice
 * another's — and the provider says what the series is rather than how to
 * draw it.
 *
 * A TARGET LINE IS A FACT, NOT A DECORATION: it is what somebody committed
 * to, and a chart of attainment without it is a chart of a number.
 */
final readonly class TopicChart
{
    /**
     * @param list<string>      $labels the axis, one per point in every series
     * @param list<ChartSeries> $series
     * @param float|null        $target the declared line, where there is one
     */
    public function __construct(
        public string $key,
        public string $title,
        public ChartKind $kind,
        public array $labels,
        public array $series,
        public ?float $target = null,
        public string $unit = '',
        public string $caption = '',
    ) {
    }

    /** A chart nobody published any point for is not drawn at all. */
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
