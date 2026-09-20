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

namespace Uhifadhi\Bundle\AtlasBundle\Tests\Integration\Twig;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Twig\Environment;
use Uhifadhi\Bundle\AtlasBundle\Model\AtlasChart;
use Uhifadhi\Bundle\AtlasBundle\Model\ChartKind;
use Uhifadhi\Bundle\AtlasBundle\Model\ChartSeries;
use Uhifadhi\Bundle\AtlasBundle\Tests\Integration\TestKernel;
use Uhifadhi\Bundle\AtlasBundle\Twig\ChartRuntime;

/**
 * `atlas_chart()` THROUGH THE REAL LIBRARY, in a real container.
 *
 * A unit test can say what the builder produces; only this can say that a
 * module writing one line gets a chart — the runtime resolved, the
 * library's own canvas rendered, the card around it drawn.
 *
 * IT IS `atlas_chart` AND NOT `render_chart`, because the library ships a
 * function of that name and this platform's chart is not the library's: a
 * module states a kind and a series, and what that looks like is the
 * atlas's to decide. Both exist; the one to write is ours.
 */
#[CoversClass(ChartRuntime::class)]
final class AtlasChartTest extends TestCase
{
    public function testAStatedChartBecomesACardWithTheLibrarysCanvasInIt(): void
    {
        $html = self::render(new AtlasChart(
            ChartKind::Line,
            ['apr', 'may'],
            [new ChartSeries('Coverage', [58.0, 61.0])],
            target: 60.0,
            unit: '%',
        ), 'Coverage, twelve periods', 'What the organisation covered.');

        self::assertStringContainsString('class="chart-plate"', $html);
        self::assertStringContainsString('Coverage, twelve periods', $html);
        self::assertStringContainsString('What the organisation covered.', $html);
        // The library's own controller, mounted on its own element.
        self::assertStringContainsString('symfony--ux-chartjs--chart', $html);
        self::assertStringContainsString('&quot;type&quot;:&quot;line&quot;', $html);
    }

    /**
     * A CHART NOBODY PUBLISHED A POINT IN IS NOT DRAWN. A box with axes and
     * no line in it reads as a measurement of nought.
     */
    public function testAChartOfNothingSaysSoInsteadOfDrawingAnEmptyBox(): void
    {
        $html = self::render(new AtlasChart(ChartKind::Bar, ['apr'], [new ChartSeries('X', [null])]));

        self::assertStringContainsString('No figure for this period', $html);
        self::assertStringContainsString('which is not a nought', $html);
        self::assertStringNotContainsString('symfony--ux-chartjs--chart', $html);
    }

    /** The height comes through the same door a plate's does. */
    public function testACustomPropertySizesTheBoxAndNotTheCanvas(): void
    {
        $html = self::render(
            new AtlasChart(ChartKind::Bar, ['apr'], [new ChartSeries('X', [1.0])]),
            attributes: ['--chart-height' => '240px', 'aria-label' => 'Seats'],
        );

        self::assertStringContainsString('style="--chart-height:240px"', $html);
        self::assertStringContainsString('data-controller="uhifadhi--atlas-bundle--chart-plate"', $html);
        self::assertStringContainsString('aria-label="Seats"', $html);
        self::assertStringNotContainsString('--chart-height', substr($html, strpos($html, 'chart-box') ?: 0));
    }

    /** @param array<string, bool|string> $attributes */
    private static function render(AtlasChart $chart, string $title = '', string $caption = '', array $attributes = []): string
    {
        $kernel = new TestKernel('test', true);
        $kernel->boot();

        /** @var Environment $twig */
        $twig = $kernel->getContainer()->get('test.twig');
        $html = $twig->createTemplate('{{ atlas_chart(chart, title, caption, attributes) }}')->render([
            'chart' => $chart,
            'title' => $title,
            'caption' => $caption,
            'attributes' => $attributes,
        ]);

        $kernel->shutdown();

        return $html;
    }
}
