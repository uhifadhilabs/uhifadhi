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

use Uhifadhi\Bundle\TeamBundle\Entity\Department;
use Uhifadhi\Bundle\TeamBundle\Repository\DepartmentRepository;
use Uhifadhi\Bundle\TeamBundle\Service\PerformanceHistory;
use Uhifadhi\Bundle\TeamBundle\Service\StaffingFigures;
use Uhifadhi\Contracts\Kpi\FigurePeriod;
use Uhifadhi\Contracts\Performance\ChartKind;
use Uhifadhi\Contracts\Performance\ChartSeries;
use Uhifadhi\Contracts\Performance\ColumnPolarity;
use Uhifadhi\Contracts\Performance\MatrixCell;
use Uhifadhi\Contracts\Performance\MatrixColumn;
use Uhifadhi\Contracts\Performance\MatrixRow;
use Uhifadhi\Contracts\Performance\PerformanceScope;
use Uhifadhi\Contracts\Performance\PerformanceTopicProviderInterface;
use Uhifadhi\Contracts\Performance\TopicChart;
use Uhifadhi\Contracts\Performance\TopicKpi;
use Uhifadhi\Contracts\Performance\TopicMatrix;

/**
 * SEATS AND PEOPLE — the host's own topic, and the reason the host has
 * topics at all.
 *
 * EVERY DEPARTMENT IS A ROW HERE whatever it attaches: how many posts it
 * holds and how many of them somebody stands in is true of a department
 * that reads no module at all. That is what makes this comparable, and
 * what made the old board's module columns incomparable.
 *
 * IT PUBLISHES THROUGH THE SAME SEAM A MODULE DOES, deliberately. One
 * renderer draws the host's topics and the modules' alike, so the host's
 * cannot quietly acquire an ability a module's lacks — and a module
 * author reading this file is reading a worked example of the contract.
 *
 * THE MOVEMENT COMES OUT OF THE HISTORY, never out of a second count:
 * "↑ 3 on July" is a comparison against a period that has closed, and a
 * closed period cannot be recomputed. Where nothing was written down the
 * figures still draw and their deltas say there is no history yet.
 */
final readonly class StaffingTopic implements PerformanceTopicProviderInterface
{
    public const string KEY = 'staffing';

    /** What the sparkline is drawn over, and the run the charts use. */
    private const int PERIODS = 6;

    public function __construct(
        private DepartmentRepository $departments,
        private StaffingFigures $staffing,
        private PerformanceHistory $history,
    ) {
    }

    public function moduleSlug(): string
    {
        return self::HOST;
    }

    public function key(): string
    {
        return self::KEY;
    }

    public function title(): string
    {
        return 'Staffing';
    }

    public function kpis(PerformanceScope $scope, FigurePeriod $period): array
    {
        $departments = $this->departmentsIn($scope);

        $now = [];
        foreach ($departments as $department) {
            foreach ($this->staffing->of($department) as $key => $value) {
                $now[$key] = ($now[$key] ?? 0.0) + $value;
            }
        }

        $filled = $now[StaffingFigures::FILLED] ?? 0.0;
        $seats = $now[StaffingFigures::POSITIONS] ?? 0.0;

        return [
            $this->figure(StaffingFigures::FILLED, 'Positions filled', $filled, $departments, $period, ColumnPolarity::Up,
                \sprintf('of %s', self::plainly($seats))),
            $this->figure(StaffingFigures::VACANT, 'Vacant', $now[StaffingFigures::VACANT] ?? 0.0, $departments, $period, ColumnPolarity::Down),
            $this->figure(StaffingFigures::PEOPLE, 'People', $now[StaffingFigures::PEOPLE] ?? 0.0, $departments, $period, ColumnPolarity::Up,
                \sprintf('in %s positions', self::plainly($seats))),
            $this->figure(StaffingFigures::POSITIONS, 'Positions', $seats, $departments, $period, ColumnPolarity::None),
            /*
             * THE FIFTH SLOT IS HELD OPEN AND SAYS SO. How long a post has
             * stood vacant is a fact nothing in the core records yet, and
             * a page of four where the design has five is a different
             * page — so the figure states its own absence rather than
             * being dropped or invented.
             */
            new TopicKpi(
                key: 'staffing.over_threshold',
                label: 'Vacant too long',
                value: null,
                caption: 'awaiting the day a post fell vacant',
                polarity: ColumnPolarity::Down,
            ),
        ];
    }

    public function charts(PerformanceScope $scope, FigurePeriod $period): array
    {
        $months = PerformanceHistory::monthsEndingAt($period->from, self::PERIODS);

        $filled = [];
        $vacant = [];
        foreach ($months as $month) {
            $filled[] = $this->sumAcross(StaffingFigures::FILLED, $month, $scope);
            $vacant[] = $this->sumAcross(StaffingFigures::VACANT, $month, $scope);
        }

        return [
            new TopicChart(
                key: 'staffing.seats',
                title: 'Seats filled and vacant',
                kind: ChartKind::Stacked,
                labels: array_map(static fn (string $month): string => mb_strtolower(new \DateTimeImmutable($month.'-01')->format('M')), $months),
                series: [
                    new ChartSeries('Filled', $filled),
                    new ChartSeries('Vacant', $vacant),
                ],
                caption: 'What the organisation held, period by period — from the periods it wrote down.',
            ),
        ];
    }

    public function matrix(PerformanceScope $scope, FigurePeriod $period): TopicMatrix
    {
        $months = PerformanceHistory::monthsEndingAt($period->from, self::PERIODS);
        $previous = PerformanceHistory::monthKey($period->from->modify('-1 month'));

        $columns = [
            new MatrixColumn(StaffingFigures::FILLED, 'Positions filled', polarity: ColumnPolarity::Up),
            new MatrixColumn(StaffingFigures::VACANT, 'Vacant', polarity: ColumnPolarity::Down),
            new MatrixColumn(StaffingFigures::PEOPLE, 'People', polarity: ColumnPolarity::Up),
            new MatrixColumn(StaffingFigures::POSITIONS, 'Positions', polarity: ColumnPolarity::None),
        ];

        $rows = [];
        foreach ($this->departmentsIn($scope) as $department) {
            $today = $this->staffing->of($department);

            $cells = [];
            foreach ($columns as $column) {
                $was = $this->history->valueAt($department, $column->key, $previous);
                $value = $today[$column->key] ?? null;

                $cells[$column->key] = new MatrixCell(
                    value: $value,
                    delta: null === $was || null === $value ? null : $value - $was,
                    history: array_values($this->history->runFor($department, $column->key, $months)),
                );
            }

            $rows[] = new MatrixRow(
                departmentUuid: (string) $department->getUuidString(),
                departmentName: (string) $department->getName(),
                cells: $cells,
                band: null === $department->getArea() ? 'Org-wide' : (string) $department->getArea()->getName(),
            );
        }

        return new TopicMatrix(
            $columns,
            $rows,
            'Every department has seats and people whatever it attaches, so every department is a row.',
        );
    }

    /**
     * ONE HEADLINE FIGURE, with its movement and its run — both read out
     * of what was written down, because neither can be recomputed.
     *
     * @param list<Department> $departments
     */
    private function figure(
        string $key,
        string $label,
        float $value,
        array $departments,
        FigurePeriod $period,
        ColumnPolarity $polarity,
        string $caption = '',
    ): TopicKpi {
        $months = PerformanceHistory::monthsEndingAt($period->from, self::PERIODS);
        $previous = PerformanceHistory::monthKey($period->from->modify('-1 month'));

        $history = [];
        foreach ($months as $month) {
            $history[] = $this->sumAcross($key, $month, null, $departments);
        }

        $was = $this->sumAcross($key, $previous, null, $departments);

        return new TopicKpi(
            key: $key,
            label: $label,
            value: $value,
            delta: null === $was ? null : $value - $was,
            history: $history,
            caption: $caption,
            polarity: $polarity,
        );
    }

    /**
     * WHAT THE WHOLE SCOPE WAS IN ONE PERIOD — null where nobody wrote any
     * of it down, because a sum of nothing is not nought.
     *
     * @param list<Department>|null $departments the ones already resolved, where the caller has them
     */
    private function sumAcross(string $key, string $periodKey, ?PerformanceScope $scope = null, ?array $departments = null): ?float
    {
        $departments ??= $this->departmentsIn($scope ?? PerformanceScope::organisation());

        $sum = null;
        foreach ($departments as $department) {
            $value = $this->history->valueAt($department, $key, $periodKey);
            if (null !== $value) {
                $sum = ($sum ?? 0.0) + $value;
            }
        }

        return $sum;
    }

    /**
     * THE DEPARTMENTS THIS SCOPE HOLDS: every one for the organisation, and
     * for an area the ones that read it — its own and the org-wide ones,
     * which is what "reads this area" means everywhere else in the product.
     *
     * @return list<Department>
     */
    private function departmentsIn(PerformanceScope $scope): array
    {
        $all = $this->departments->findAllActiveOrdered();
        if ($scope->isOrganisation()) {
            return $all;
        }

        return array_values(array_filter($all, static function (Department $department) use ($scope): bool {
            $area = $department->getArea();

            return null === $area || $scope->areaUuid === $area->getUuidString();
        }));
    }

    private static function plainly(float $value): string
    {
        return number_format($value, 0, '.', ',');
    }
}
