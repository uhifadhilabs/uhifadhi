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

namespace Uhifadhi\Bundle\AtlasBundle\Tests\Unit\Model;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\UX\Chartjs\Model\Chart as UxChart;
use Uhifadhi\Bundle\AtlasBundle\Chart\ChartBuilder;
use Uhifadhi\Bundle\AtlasBundle\Model\AtlasChart;
use Uhifadhi\Bundle\AtlasBundle\Model\ChartKind;
use Uhifadhi\Bundle\AtlasBundle\Model\ChartSeries;

/**
 * THE PLATFORM'S ONE CHART, AND WHAT A MODULE MAY SAY ABOUT IT.
 *
 * THE SAME BARGAIN THE MAP PLATE MAKES. A module states WHAT its series
 * is — a run over time, a comparison, parts of a whole, a movement
 * either side of nought — and the atlas decides what that looks like:
 * the colours, the grid, the axes, the legend, the height. A module
 * handing over chart options would be a module deciding what the
 * platform's charts look like, and the second module would decide
 * differently.
 *
 * A HOLE IS A HOLE. A period nobody reported is null all the way down to
 * Chart.js, which draws a gap; turning it into a nought on the way would
 * be the library inventing a quiet month.
 */
#[CoversClass(AtlasChart::class)]
#[CoversClass(ChartBuilder::class)]
final class AtlasChartTest extends TestCase
{
    public function testARunOverTimeIsALineWithItsLabelsAndItsHoles(): void
    {
        $chart = self::builder()->chart(new AtlasChart(
            ChartKind::Line,
            ['apr', 'may', 'jun'],
            [new ChartSeries('Open', [9.0, null, 11.0])],
        ));

        self::assertSame(UxChart::TYPE_LINE, $chart->getType());
        self::assertSame(['apr', 'may', 'jun'], self::at($chart->getData(), 'labels'));
        self::assertSame([9.0, null, 11.0], self::at(self::dataset($chart, 0), 'data'));
        self::assertSame('Open', self::at(self::dataset($chart, 0), 'label'));
    }

    /** Parts of a whole are bars that stack; a comparison is bars that do not. */
    public function testAStackIsBarsThatStackAndAComparisonIsBarsThatDoNot(): void
    {
        $stacked = self::builder()->chart(new AtlasChart(
            ChartKind::Stacked,
            ['apr'],
            [new ChartSeries('Filled', [9.0]), new ChartSeries('Vacant', [2.0])],
        ));
        $bars = self::builder()->chart(new AtlasChart(ChartKind::Bar, ['apr'], [new ChartSeries('Filled', [9.0])]));

        self::assertSame(UxChart::TYPE_BAR, $stacked->getType());
        self::assertTrue(self::at(self::scale($stacked, 'x'), 'stacked'));
        self::assertTrue(self::at(self::scale($stacked, 'y'), 'stacked'));
        self::assertSame(UxChart::TYPE_BAR, $bars->getType());
        self::assertArrayNotHasKey('stacked', self::scale($bars, 'x'));
    }

    /**
     * A TARGET LINE IS A FACT, not a decoration: it is what somebody
     * committed to, and a chart of attainment without it is a chart of a
     * number.
     */
    public function testATargetIsDrawnAsItsOwnLine(): void
    {
        $chart = self::builder()->chart(new AtlasChart(
            ChartKind::Line,
            ['apr', 'may'],
            [new ChartSeries('Coverage', [58.0, 61.0])],
            target: 60.0,
        ));

        $datasets = self::at($chart->getData(), 'datasets');
        self::assertIsArray($datasets);
        self::assertCount(2, $datasets, 'the series, and the line it is measured against');
        self::assertSame([60.0, 60.0], self::at(self::dataset($chart, 1), 'data'));
        self::assertSame('Target', self::at(self::dataset($chart, 1), 'label'));
    }

    /** A module states no colour and gets the platform's, in order. */
    public function testTheSeriesTakeThePlatformsOwnColoursInOrder(): void
    {
        $chart = self::builder()->chart(new AtlasChart(
            ChartKind::Bar,
            ['apr'],
            [new ChartSeries('One', [1.0]), new ChartSeries('Two', [2.0])],
        ));

        self::assertNotSame(
            self::at(self::dataset($chart, 0), 'backgroundColor'),
            self::at(self::dataset($chart, 1), 'backgroundColor'),
        );
        // A TOKEN, NEVER A VALUE: the first series takes the first category
        // and the chart's own controller resolves it where it is drawn, so
        // the same series follows the theme and matches the third zone on a
        // plate beside it.
        self::assertSame('var(--cat-1)', self::at(self::dataset($chart, 0), 'backgroundColor'));
        self::assertSame('var(--cat-2)', self::at(self::dataset($chart, 1), 'backgroundColor'));
    }

    /** A SERIES MAY STATE ITS CATEGORY, and then it wears that one wherever it sits. */
    public function testASeriesStatesItsCategoryAndKeepsIt(): void
    {
        $chart = self::builder()->chart(new AtlasChart(
            ChartKind::Bar,
            ['apr'],
            [new ChartSeries('One', [1.0], cat: 7), new ChartSeries('Two', [2.0])],
        ));

        self::assertSame('var(--cat-7)', self::at(self::dataset($chart, 0), 'backgroundColor'));
        // And the one that states nothing still takes its place in order.
        self::assertSame('var(--cat-2)', self::at(self::dataset($chart, 1), 'backgroundColor'));
    }

    /** A category outside the palette is refused where it is stated, not drawn as nothing. */
    public function testACategoryOutsideThePaletteIsRefused(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new ChartSeries('One', [1.0], cat: 19);
    }

    /** And a module that owns a colour keeps it — a module's hue is its own. */
    public function testASeriesWithItsOwnSwatchKeepsIt(): void
    {
        $chart = self::builder()->chart(new AtlasChart(
            ChartKind::Bar,
            ['apr'],
            [new ChartSeries('Incidents', [1.0], '#E05B41')],
        ));

        self::assertSame('#E05B41', self::at(self::dataset($chart, 0), 'backgroundColor'));
    }

    /** A chart nobody published a point in is not drawn at all. */
    public function testAChartOfNothingIsEmpty(): void
    {
        self::assertTrue(new AtlasChart(ChartKind::Line, ['apr'], [new ChartSeries('X', [null])])->isEmpty());
        self::assertFalse(new AtlasChart(ChartKind::Line, ['apr'], [new ChartSeries('X', [0.0])])->isEmpty());
    }

    /**
     * One dataset of a built chart, asserted down to it rather than cast:
     * the library types its payload as a plain array, and a test that
     * casts its way in is a test that passes when the shape changes.
     *
     * @return array<array-key, mixed>
     */
    private static function dataset(UxChart $chart, int $position): array
    {
        $datasets = self::at($chart->getData(), 'datasets');
        self::assertIsArray($datasets);
        self::assertArrayHasKey($position, $datasets);
        self::assertIsArray($datasets[$position]);

        return $datasets[$position];
    }

    /** @return array<array-key, mixed> */
    private static function scale(UxChart $chart, string $axis): array
    {
        $scales = self::at($chart->getOptions(), 'scales');
        self::assertIsArray($scales);
        $scale = self::at($scales, $axis);
        self::assertIsArray($scale);

        return $scale;
    }

    /** @param array<array-key, mixed> $payload */
    private static function at(array $payload, string|int $key): mixed
    {
        self::assertArrayHasKey($key, $payload);

        return $payload[$key];
    }

    private static function builder(): ChartBuilder
    {
        return new ChartBuilder(new \Symfony\UX\Chartjs\Builder\ChartBuilder());
    }
}
