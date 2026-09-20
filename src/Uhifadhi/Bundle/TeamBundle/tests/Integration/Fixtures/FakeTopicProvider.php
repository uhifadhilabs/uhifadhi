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

namespace Uhifadhi\Bundle\TeamBundle\Tests\Integration\Fixtures;

use Uhifadhi\Contracts\Kpi\FigurePeriod;
use Uhifadhi\Contracts\Performance\ChartKind;
use Uhifadhi\Contracts\Performance\ChartSeries;
use Uhifadhi\Contracts\Performance\ColumnPolarity;
use Uhifadhi\Contracts\Performance\KpiRole;
use Uhifadhi\Contracts\Performance\MatrixCell;
use Uhifadhi\Contracts\Performance\MatrixColumn;
use Uhifadhi\Contracts\Performance\MatrixRow;
use Uhifadhi\Contracts\Performance\PerformanceScope;
use Uhifadhi\Contracts\Performance\PerformanceTopicProviderInterface;
use Uhifadhi\Contracts\Performance\TopicChart;
use Uhifadhi\Contracts\Performance\TopicKpi;
use Uhifadhi\Contracts\Performance\TopicMatrix;

/**
 * A MODULE PUBLISHING A TOPIC, playing the contract and nothing else.
 *
 * IT IS THE MODULE SIDE OF THE SEAM, standing in for patrols and incidents
 * until those are briefed: four headline figures, a chart of a stated
 * shape, and a matrix of only the departments that read it — the last of
 * which is the whole difference from the board this replaces, where one
 * module's columns were imposed on every department.
 */
final readonly class FakeTopicProvider implements PerformanceTopicProviderInterface
{
    /**
     * @param string|null $departmentUuid the one department this module's
     *                                    matrix has a row for; the fixed
     *                                    stand-in where a suite does not care
     */
    public function __construct(private string $slug, private ?string $departmentUuid = null)
    {
    }

    public function moduleSlug(): string
    {
        return $this->slug;
    }

    public function key(): string
    {
        return $this->slug;
    }

    public function title(): string
    {
        return ucfirst($this->slug);
    }

    public function kpis(PerformanceScope $scope, FigurePeriod $period): array
    {
        // FIVE, ALWAYS — and the fifth says it has nothing, which is the
        // state a real module is in before its first period closes.
        return [
            new TopicKpi($this->slug.'.open', 'Open', 12.0, delta: 3.0, history: [9.0, 10.0, null, 11.0, 12.0, 12.0], polarity: ColumnPolarity::Down),
            new TopicKpi($this->slug.'.closed', 'Closed', 31.0, delta: -2.0, polarity: ColumnPolarity::Up),
            new TopicKpi($this->slug.'.total', 'Records', 561.0, polarity: ColumnPolarity::None),
            // AND ONE THE STAND-IN CANNOT ANSWER, because the contract's
            // fourth slot is filled with a figure that states its own
            // absence rather than left short.
            new TopicKpi($this->slug.'.unowned', 'Unowned', null, caption: 'nobody has published this yet'),
        ];
    }

    public function charts(PerformanceScope $scope, FigurePeriod $period): array
    {
        return [
            new TopicChart(
                key: $this->slug.'.run',
                title: 'Opened and closed',
                kind: ChartKind::Line,
                labels: ['apr', 'may', 'jun', 'jul', 'aug', 'sep'],
                series: [new ChartSeries('Open', [9.0, 10.0, null, 11.0, 12.0, 12.0])],
                target: 10.0,
            ),
        ];
    }

    public function matrix(PerformanceScope $scope, FigurePeriod $period): TopicMatrix
    {
        return new TopicMatrix(
            // THE COLUMN CARRIES ITS ROLE, as a real module's does: the
            // host's Attention topic finds "items raised" by role and never
            // by label, because a module may call them cases or sightings.
            [new MatrixColumn($this->slug.'.open', 'Open', polarity: ColumnPolarity::Down, role: KpiRole::ItemsRaised)],
            [new MatrixRow($this->departmentUuid ?? '0198f0a0-0000-7000-8000-00000000dead', 'Protection Service', [
                $this->slug.'.open' => new MatrixCell(value: 12.0, delta: 3.0, history: [9.0, 10.0, null, 11.0, 12.0, 12.0]),
            ], band: 'Org-wide')],
            'Only the departments that read this module are rows.',
        );
    }
}
