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
use Uhifadhi\Bundle\TeamBundle\Repository\DepartmentGoalRepository;
use Uhifadhi\Bundle\TeamBundle\Repository\DepartmentRepository;
use Uhifadhi\Bundle\TeamBundle\Service\StaffingFigures;
use Uhifadhi\Contracts\Kpi\FigurePeriod;
use Uhifadhi\Contracts\Performance\ChartKind;
use Uhifadhi\Contracts\Performance\ChartSeries;
use Uhifadhi\Contracts\Performance\ColumnPolarity;
use Uhifadhi\Contracts\Performance\KpiRole;
use Uhifadhi\Contracts\Performance\MatrixCell;
use Uhifadhi\Contracts\Performance\MatrixColumn;
use Uhifadhi\Contracts\Performance\MatrixRow;
use Uhifadhi\Contracts\Performance\MovementTone;
use Uhifadhi\Contracts\Performance\PerformanceScope;
use Uhifadhi\Contracts\Performance\PerformanceTopicProviderInterface;
use Uhifadhi\Contracts\Performance\TopicChart;
use Uhifadhi\Contracts\Performance\TopicKpi;
use Uhifadhi\Contracts\Performance\TopicMatrix;
use Uhifadhi\Contracts\Performance\TopicMovement;
use Uhifadhi\Contracts\Performance\TopicMovementInterface;

/**
 * WHAT WAS PUT IN FRONT OF SOMEBODY, AND WHAT GOT WRITTEN DOWN — the
 * host's third topic, and the only one it cannot compute by itself.
 *
 * ONLY A MODULE RAISES AN ITEM OR WRITES A RECORD. Seats are the host's
 * and goals are the host's, but an overdue patrol is the patrol
 * module's; so this topic ADDS UP what the other topics publish, found
 * by role rather than by label — a module calls its records "cases",
 * "sightings" or "patrols logged", and the host must not be matching
 * words.
 *
 * AND THAT IS WHY IT FOLDS. A department that attaches nothing has no
 * items and cannot have any: drawing it at nought would say it was
 * asked and answered none, which is a different and untrue statement.
 * The departments with no computing module fold into ONE LINE per
 * scope band — and the line states their seats and their goals, so a
 * reader can see they are not hidden, only silent here.
 *
 * IT READS THE OTHER TOPICS AND NOT THE COLLECTOR, because the
 * collector holds this topic too and asking it would be a circle. The
 * tagged iterator is lazy, and the host's own topics are skipped by
 * slug rather than by identity so that a second host topic added later
 * cannot start counting itself.
 */
final readonly class AttentionTopic implements PerformanceTopicProviderInterface, TopicMovementInterface
{
    public const string KEY = 'attention';

    /** @param iterable<PerformanceTopicProviderInterface> $topics every topic, this one included */
    public function __construct(
        private DepartmentRepository $departments,
        private DepartmentGoalRepository $goals,
        private StaffingFigures $staffing,
        private iterable $topics = [],
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
        return 'Attention & output';
    }

    public function kpis(PerformanceScope $scope, FigurePeriod $period): array
    {
        $measured = $this->measured($scope, $period);

        $raised = self::sum($measured, KpiRole::ItemsRaised);
        $unowned = self::sum($measured, KpiRole::ItemsUnowned);
        $records = self::sum($measured, KpiRole::Records);

        $measuring = \count($measured);
        $all = \count($this->departmentsIn($scope));

        return [
            new TopicKpi(
                key: 'attention.raised',
                label: 'Items raised',
                value: $raised,
                caption: \sprintf('across %d measuring %s', $measuring, 1 === $measuring ? 'department' : 'departments'),
                polarity: ColumnPolarity::Down,
            ),
            /*
             * RAISED AGAINST A DEPARTMENT AND OWNED BY NOBODY. A gap in
             * the org chart rather than workload, which is why it is its
             * own figure and not folded into the one above.
             */
            new TopicKpi(
                key: 'attention.unowned',
                label: 'Unowned',
                value: $unowned,
                caption: 'raised against no position',
                polarity: ColumnPolarity::Down,
            ),
            new TopicKpi(
                key: 'attention.records',
                label: 'Records this period',
                value: $records,
                caption: 'written by every module that writes',
                polarity: ColumnPolarity::Up,
                role: KpiRole::Records,
            ),
            new TopicKpi(
                key: 'attention.measuring',
                label: 'Measuring',
                value: (float) $measuring,
                caption: \sprintf('of %d departments', $all),
                polarity: ColumnPolarity::None,
            ),
            /*
             * AND HOW MANY ARE FOLDED. Stated as a figure of its own so
             * that "three measuring" can never be read as "six scored
             * nothing".
             */
            new TopicKpi(
                key: 'attention.folded',
                label: 'Folded',
                value: (float) max(0, $all - $measuring),
                caption: 'no module that raises or writes',
                polarity: ColumnPolarity::None,
            ),
        ];
    }

    /**
     * WHAT MOVED IN THE WORKLOAD — and the sentence this topic exists
     * to write is about the items NOBODY OWNS.
     *
     * "52 raised, up 18" is workload and it is on the card. An item
     * raised against a department with no position answerable for it is
     * a gap in the org chart, and it is the one figure here that does
     * not fix itself by somebody working harder.
     */
    public function movement(PerformanceScope $scope, FigurePeriod $period): ?TopicMovement
    {
        $kpis = $this->kpis($scope, $period);
        $raised = self::figureOf($kpis, 'attention.raised');
        $unowned = self::figureOf($kpis, 'attention.unowned');

        $items = (int) ($raised->value ?? 0.0);
        $orphans = (int) ($unowned->value ?? 0.0);
        $moved = (int) ($raised->delta ?? 0.0);

        if (0 === $items && 0 === $orphans) {
            return null;
        }

        if (0 < $orphans) {
            return new TopicMovement(
                \sprintf(
                    '%d of the %d raised %s no owning position — a gap in the org chart rather than a workload.',
                    $orphans,
                    $items,
                    1 === $orphans ? 'carries' : 'carry',
                ),
                MovementTone::Bad,
            );
        }

        if (0 === $moved) {
            return new TopicMovement(\sprintf('%d raised, the same as last period, and every one owned.', $items), MovementTone::Quiet);
        }

        return new TopicMovement(
            \sprintf('%d raised, %s on last period, and every one owned.', $items, 0 < $moved ? 'up '.$moved : 'down '.abs($moved)),
            0 < $moved ? MovementTone::Attention : MovementTone::Good,
        );
    }

    /**
     * One of this topic's own five, by the key it published it under.
     *
     * @param list<TopicKpi> $kpis
     */
    private static function figureOf(array $kpis, string $key): ?TopicKpi
    {
        foreach ($kpis as $kpi) {
            if ($key === $kpi->key) {
                return $kpi;
            }
        }

        return null;
    }

    public function charts(PerformanceScope $scope, FigurePeriod $period): array
    {
        $measured = $this->measured($scope, $period);

        $names = [];
        $raised = [];
        foreach ($measured as $uuid => $roles) {
            $names[] = $this->nameOf($scope, $uuid);
            $raised[] = $roles[KpiRole::ItemsRaised->value] ?? 0.0;
        }

        return [
            new TopicChart(
                key: 'attention.raised',
                title: 'Items raised, by department',
                kind: ChartKind::Bar,
                labels: $names,
                series: [new ChartSeries('Items', $raised)],
                caption: 'Only the departments whose modules raise items — the rest are folded, not at nought.',
            ),
        ];
    }

    public function matrix(PerformanceScope $scope, FigurePeriod $period): TopicMatrix
    {
        $columns = [
            new MatrixColumn('attention.raised', 'Items raised', polarity: ColumnPolarity::Down, role: KpiRole::ItemsRaised),
            new MatrixColumn('attention.unowned', 'Unowned', polarity: ColumnPolarity::Down, role: KpiRole::ItemsUnowned),
            new MatrixColumn('attention.records', 'Records this period', polarity: ColumnPolarity::Up, role: KpiRole::Records),
        ];

        $measured = $this->measured($scope, $period);

        $rows = [];
        $folded = [];
        foreach ($this->departmentsIn($scope) as $department) {
            $uuid = (string) $department->getUuidString();
            $band = null === $department->getArea() ? 'Org-wide' : (string) $department->getArea()->getName();

            if (!\array_key_exists($uuid, $measured)) {
                $folded[$band][] = $department;

                continue;
            }

            $cells = [];
            foreach ($columns as $column) {
                $role = $column->role;
                $cells[$column->key] = new MatrixCell(
                    value: null === $role ? null : ($measured[$uuid][$role->value] ?? null),
                );
            }

            $rows[] = new MatrixRow(
                departmentUuid: $uuid,
                departmentName: (string) $department->getName(),
                cells: $cells,
                band: $band,
            );
        }

        foreach ($folded as $band => $departments) {
            $rows[] = $this->foldedLine($band, $departments, $columns);
        }

        return new TopicMatrix(
            $columns,
            $rows,
            'Only a module raises an item or writes a record, so a department that attaches none is folded rather than drawn at nought.',
        );
    }

    /**
     * ONE LINE FOR THE DEPARTMENTS THAT CANNOT ANSWER.
     *
     * IT SAYS WHAT THEY DO HAVE. Seats and goals are the host's own and
     * every department has them, so the folded line carries both and
     * points back at Staffing — a reader can see the six are accounted
     * for rather than dropped.
     *
     * ITS CELLS ARE `notMine`, NOT NOUGHT. The column does not apply to
     * these departments at all, which is the emptiness the matrix draws
     * as a dash.
     *
     * @param list<Department>   $departments
     * @param list<MatrixColumn> $columns
     */
    private function foldedLine(string $band, array $departments, array $columns): MatrixRow
    {
        $seats = 0.0;
        foreach ($departments as $department) {
            $seats += $this->staffing->of($department)[StaffingFigures::POSITIONS] ?? 0.0;
        }

        $goals = 0;
        foreach ($departments as $department) {
            $goals += \count($this->goals->findForDepartment($department));
        }

        $cells = [];
        foreach ($columns as $column) {
            $cells[$column->key] = MatrixCell::notMine();
        }

        $count = \count($departments);

        return new MatrixRow(
            departmentUuid: '',
            departmentName: \sprintf(
                '%d %s with no computing module — %s seats, %d %s',
                $count,
                1 === $count ? 'department' : 'departments',
                number_format($seats, 0, '.', ','),
                $goals,
                1 === $goals ? 'goal' : 'goals',
            ),
            cells: $cells,
            band: $band,
        );
    }

    /**
     * WHAT EVERY OTHER TOPIC PUBLISHES PER DEPARTMENT, BY ROLE.
     *
     * Keyed by department and then by role, and a department appears
     * only where some module topic actually has a row for it — which is
     * exactly the set this topic measures, and its complement is the
     * set it folds.
     *
     * @return array<string, array<string, float>>
     */
    private function measured(PerformanceScope $scope, FigurePeriod $period): array
    {
        $measured = [];

        foreach ($this->topics as $topic) {
            // THE HOST'S OWN TOPICS ARE NOT MODULES and this one is in
            // the iterator it is reading; skipping by slug also stops a
            // later host topic counting itself.
            if (PerformanceTopicProviderInterface::HOST === $topic->moduleSlug()) {
                continue;
            }

            // ASKED ONCE. A matrix is a query, and asking a module for
            // the same one per row would be a query per department.
            $matrix = $topic->matrix($scope, $period);

            foreach ($matrix->rows as $row) {
                if ('' === $row->departmentUuid) {
                    continue;
                }

                foreach ($matrix->columns as $column) {
                    $role = $column->role;
                    if (null === $role) {
                        continue;
                    }

                    $cell = $row->cells[$column->key] ?? null;
                    if (null === $cell || !$cell->isKnown() || null === $cell->value) {
                        continue;
                    }

                    $measured[$row->departmentUuid][$role->value]
                        = ($measured[$row->departmentUuid][$role->value] ?? 0.0) + $cell->value;
                }
            }
        }

        return $measured;
    }

    /** @param array<string, array<string, float>> $measured */
    private static function sum(array $measured, KpiRole $role): float
    {
        $total = 0.0;
        foreach ($measured as $roles) {
            $total += $roles[$role->value] ?? 0.0;
        }

        return $total;
    }

    private function nameOf(PerformanceScope $scope, string $uuid): string
    {
        foreach ($this->departmentsIn($scope) as $department) {
            if ($uuid === $department->getUuidString()) {
                return (string) $department->getName();
            }
        }

        return '';
    }

    /** @return list<Department> */
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
}
