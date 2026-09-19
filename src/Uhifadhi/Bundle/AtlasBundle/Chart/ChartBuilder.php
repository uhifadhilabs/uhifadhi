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

namespace Uhifadhi\Bundle\AtlasBundle\Chart;

use Symfony\UX\Chartjs\Builder\ChartBuilderInterface;
use Symfony\UX\Chartjs\Model\Chart;
use Uhifadhi\Bundle\AtlasBundle\Model\AtlasChart;
use Uhifadhi\Bundle\AtlasBundle\Model\ChartKind;
use Uhifadhi\Bundle\AtlasBundle\Model\ChartSeries;

/**
 * WHAT A STATED CHART BECOMES — the one place the platform decides what a
 * chart looks like.
 *
 * THE MAP BUILDER'S SIBLING. Everything a module would otherwise have to
 * know about Chart.js is here and nowhere else: the type behind each
 * shape, the scales, the legend, the grid, the tension of a line, the
 * colours. A module states a kind and a series; two modules cannot
 * disagree about what a bar chart is.
 *
 * NO ANIMATION, AND NO ASPECT RATIO OF ITS OWN. A chart in a card is
 * given a box by the card, and one that animated on every render would
 * be a page that moves while it is being read.
 *
 * NULLS SURVIVE. Chart.js draws a gap where a point is null, which is
 * the truthful reading of a period nobody reported.
 */
final readonly class ChartBuilder
{
    /**
     * THE PLATFORM'S SERIES COLOURS, in order.
     *
     * A module that owns a hue states it and keeps it; everything else
     * takes these, so two charts built by two modules on one page do not
     * read as one chart with nine series.
     */
    public const array SWATCHES = [
        '#49E6B4',
        '#4FA8E8',
        '#E8C15A',
        '#9B6BD8',
        '#E05B41',
        '#9DBF4A',
    ];

    public function __construct(private ChartBuilderInterface $charts)
    {
    }

    public function chart(AtlasChart $chart): Chart
    {
        $built = $this->charts->createChart(self::typeOf($chart->kind));

        $datasets = [];
        foreach ($chart->series as $position => $series) {
            $datasets[] = self::dataset($series, $position, $chart->kind);
        }

        if (null !== $chart->target) {
            // THE TARGET IS A FLAT LINE ACROSS THE WHOLE AXIS, drawn as a
            // series of its own so it carries a legend row and a hover
            // like everything else on the chart.
            $datasets[] = [
                'label' => $chart->targetLabel,
                'data' => array_fill(0, \count($chart->labels), $chart->target),
                'type' => Chart::TYPE_LINE,
                'borderColor' => 'rgba(120,130,125,.85)',
                'borderDash' => [5, 4],
                'borderWidth' => 1.5,
                'pointRadius' => 0,
                'fill' => false,
            ];
        }

        $built->setData(['labels' => $chart->labels, 'datasets' => $datasets]);
        $built->setOptions(self::options($chart));

        return $built;
    }

    /** @return array<string, mixed> */
    private static function dataset(ChartSeries $series, int $position, ChartKind $kind): array
    {
        $colour = $series->swatch ?? self::SWATCHES[$position % \count(self::SWATCHES)];

        $dataset = [
            'label' => $series->label,
            'data' => $series->points,
            'backgroundColor' => $colour,
            'borderColor' => $colour,
            'borderWidth' => 1.5,
        ];

        if (ChartKind::Line === $kind) {
            // A GENTLE CURVE, NOT A SPLINE: enough to read the shape,
            // never enough to invent a value between two months.
            $dataset['tension'] = 0.25;
            $dataset['fill'] = false;
            $dataset['pointRadius'] = 2;
            $dataset['backgroundColor'] = 'transparent';
        }

        if (ChartKind::Stacked === $kind) {
            $dataset['stack'] = 'one';
        }

        return $dataset;
    }

    /** @return array<string, mixed> */
    private static function options(AtlasChart $chart): array
    {
        $scales = [
            'x' => ['grid' => ['display' => false], 'ticks' => ['maxRotation' => 0]],
            'y' => ['beginAtZero' => ChartKind::Diverging !== $chart->kind, 'grid' => ['drawBorder' => false]],
        ];

        if (ChartKind::Stacked === $chart->kind) {
            $scales['x']['stacked'] = true;
            $scales['y']['stacked'] = true;
        }

        return [
            'responsive' => true,
            'maintainAspectRatio' => false,
            // A PAGE THAT MOVES WHILE IT IS BEING READ is a page nobody
            // can compare two figures on.
            'animation' => false,
            'plugins' => [
                'legend' => ['display' => \count($chart->series) > 1 || null !== $chart->target, 'position' => 'bottom'],
            ],
            'scales' => $scales,
        ];
    }

    private static function typeOf(ChartKind $kind): string
    {
        return match ($kind) {
            ChartKind::Line => Chart::TYPE_LINE,
            // A DIVERGING CHART IS BARS EITHER SIDE OF NOUGHT — the shape
            // is the data's, not a chart type of its own.
            ChartKind::Bar, ChartKind::Stacked, ChartKind::Diverging => Chart::TYPE_BAR,
        };
    }
}
