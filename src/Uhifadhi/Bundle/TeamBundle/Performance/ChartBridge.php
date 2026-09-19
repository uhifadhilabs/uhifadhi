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

namespace Uhifadhi\Bundle\TeamBundle\Performance;

use Uhifadhi\Bundle\AtlasBundle\Model\AtlasChart;
use Uhifadhi\Bundle\AtlasBundle\Model\ChartKind as AtlasKind;
use Uhifadhi\Bundle\AtlasBundle\Model\ChartSeries as AtlasSeries;
use Uhifadhi\Contracts\Performance\ChartSeries;
use Uhifadhi\Contracts\Performance\TopicChart;

/**
 * A CHART A TOPIC STATED, HANDED TO THE ATLAS THAT DRAWS IT.
 *
 * TWO SHAPES FOR ONE THING, AND THAT IS ON PURPOSE. A topic states its
 * chart in the CONTRACTS package, which depends on nothing — a module
 * publishing a topic must not have to install a drawing library to
 * describe a series. The atlas's own shape is the drawing one. The
 * translation is a dozen lines and it belongs to the host, which is the
 * only party that has both.
 *
 * THE TWO ENUMS ARE KEPT IN STEP BY VALUE, not by a match arm per case:
 * a kind added to the contract and not to the atlas should fail where
 * it is drawn rather than silently become a bar.
 */
final readonly class ChartBridge
{
    public static function atlas(TopicChart $chart): AtlasChart
    {
        return new AtlasChart(
            kind: AtlasKind::from($chart->kind->value),
            labels: $chart->labels,
            series: array_map(
                /*
                 * THE CATEGORY DOES NOT CROSS YET, and passing it as a
                 * colour is exactly what it must not become. A topic may
                 * state `cat` on a series; the atlas's own series still
                 * takes a colour STRING, and Chart.js — like Leaflet
                 * before the plate learnt to resolve one — paints nothing
                 * at all when handed `var(--cat-3)`. Until the chart
                 * canvas resolves a token the way the plate now does, a
                 * series is coloured by its position, which is what the
                 * atlas already does and is never wrong, only unchosen.
                 */
                static fn (ChartSeries $series): AtlasSeries => new AtlasSeries(
                    label: $series->label,
                    points: $series->points,
                ),
                $chart->series,
            ),
            target: $chart->target,
            unit: $chart->unit,
        );
    }
}
