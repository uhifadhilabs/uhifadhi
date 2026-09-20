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

namespace Uhifadhi\Bundle\TeamBundle\Service;

use Uhifadhi\Bundle\RegistryBundle\Service\ModuleCatalogue;
use Uhifadhi\Bundle\TeamBundle\Entity\Department;
use Uhifadhi\Bundle\TeamBundle\Entity\Position;
use Uhifadhi\Bundle\TeamBundle\Model\SectionBar;
use Uhifadhi\Bundle\TeamBundle\Model\SectionFact;
use Uhifadhi\Bundle\TeamBundle\Model\SectionKpi;
use Uhifadhi\Bundle\TeamBundle\Model\SectionLine;
use Uhifadhi\Bundle\TeamBundle\Repository\DepartmentGoalRepository;
use Uhifadhi\Bundle\TeamBundle\Repository\DepartmentRepository;
use Uhifadhi\Bundle\TeamBundle\Repository\PositionRepository;
use Uhifadhi\Bundle\TeamBundle\Repository\UserRepository;

/**
 * WHAT THE DEPARTMENTS SECTION'S OVERVIEW READS, AND OWNS NOTHING OF.
 *
 * Every figure on that page exists somewhere else already — on the register, in
 * Team, or on Performance. The overview is a reading of them, so this service
 * reads and never writes, and the page it feeds has no control that changes a
 * department. That is what makes it safe to open first.
 *
 * ONE PASS OVER THE POSITIONS, NOT ONE PER DEPARTMENT. Walking each
 * department's inverse collection is a lazy load per card and the inverse of a
 * OneToMany is only as true as whoever maintained it; the owning side is the
 * fact, and one query over it answers every figure here.
 *
 * A SEAT IS A POSITION, FOR NOW. The drawn page counts SEATS — a position that
 * can be held by more than one person — and the model has no such number: a
 * position is one post, held or not. Every count below is therefore positions,
 * which is the truth this installation can state; the multi-seat position is a
 * model change that rewrites Team's positions screen too and is not made here.
 */
final readonly class DepartmentSectionOverview
{
    /** How many rows a bounded card shows before it hands the rest to a register. */
    private const int BOUND = 5;

    public function __construct(
        private DepartmentRepository $departments,
        private PositionRepository $positions,
        private UserRepository $users,
        private DepartmentGoalRepository $goals,
        private ModuleCatalogue $catalogue,
    ) {
    }

    /**
     * @return array{
     *     facts: list<SectionFact>,
     *     kpis: list<SectionKpi>,
     *     staffing: list<SectionBar>,
     *     scope: array{orgWide: int, areaLevel: int, areas: list<array{name: string, areaLevel: int}>},
     *     modules: list<SectionBar>,
     *     unattached: list<SectionLine>,
     *     unattachedTotal: int,
     *     vacancies: list<SectionLine>,
     *     vacanciesTotal: int,
     *     goals: list<SectionLine>,
     *     goalsTotal: int,
     * }
     */
    public function read(): array
    {
        $departments = $this->departments->findAllActiveOrdered();
        $held = $this->heldByPosition();

        $orgWide = $areaLevel = 0;
        $areas = [];
        foreach ($departments as $department) {
            if ($department->isOrgLevel()) {
                ++$orgWide;
                continue;
            }

            ++$areaLevel;
            $name = (string) $department->getArea()?->getName();
            $areas[$name] = ($areas[$name] ?? 0) + 1;
        }
        ksort($areas);

        $staffing = $this->staffing($departments, $held);
        $modules = $this->modules($departments);

        $seats = $filled = $people = 0;
        foreach ($staffing as $bar) {
            $seats += $bar->total;
            $filled += $bar->value;
            $people += $bar->people;
        }

        $attached = 0;
        foreach ($modules as $bar) {
            $attached += $bar->value;
        }

        $goals = $this->goals->findAllOrdered();

        return [
            'facts' => [
                new SectionFact('Departments', (string) \count($departments), \sprintf('org-wide %d · area-level %d', $orgWide, $areaLevel)),
                new SectionFact('Org-wide', (string) $orgWide, 'every area reads them'),
                new SectionFact('Area-level', (string) $areaLevel, $this->areasPhrase($areas)),
                new SectionFact('Positions', (string) $seats, \sprintf('across %d departments', \count($departments))),
                new SectionFact('People', (string) $people, \sprintf('%d of %d positions filled', $filled, $seats)),
            ],
            /*
             * FOUR TO A ROW, NEVER FIVE (ruled) — and the one dropped is the
             * COUNT OF DEPARTMENTS, because the band directly above this strip
             * opens with exactly that fact and the register below is the list
             * of them. A figure a reader can already read on the same screen
             * is the cheapest of the five to lose.
             */
            'kpis' => [
                new SectionKpi('Positions filled', (string) $filled, \sprintf('of %d', $seats), \sprintf('%d vacant', $seats - $filled)),
                new SectionKpi('People', (string) $people, null, \sprintf('%d in a position', $people)),
                new SectionKpi('Modules attached', (string) $attached, null, \sprintf('%d of %d departments · %d installed', $this->departmentsReadingSomething($modules), \count($departments), $this->catalogue->count())),
                new SectionKpi('Goals declared', (string) \count($goals), null, \sprintf('across %d departments', $this->departmentsWithAGoal($goals)), hot: true),
            ],
            'staffing' => $staffing,
            'scope' => [
                'orgWide' => $orgWide,
                'areaLevel' => $areaLevel,
                'areas' => array_map(
                    static fn (string $name, int $count): array => ['name' => $name, 'areaLevel' => $count],
                    array_keys($areas),
                    array_values($areas),
                ),
            ],
            'modules' => $modules,
            'unattached' => $this->unattached($departments, $held, self::BOUND),
            'unattachedTotal' => \count($this->unattached($departments, $held, null)),
            'vacancies' => $this->vacancies($held, self::BOUND),
            'vacanciesTotal' => \count($this->vacancies($held, null)),
            'goals' => $this->goalLines($goals, self::BOUND),
            'goalsTotal' => \count($goals),
        ];
    }

    /**
     * How many people hold each position, keyed by the position's id — the one
     * pass the rest of this class reads from.
     *
     * @return array<int, array{position: Position, holders: int}>
     */
    private function heldByPosition(): array
    {
        $held = [];
        foreach ($this->positions->findAllOrdered() as $position) {
            $id = $position->getId();
            if (null === $id) {
                continue;
            }

            $held[$id] = [
                'position' => $position,
                'holders' => $this->users->countActiveHoldingAnyPosition([$position]),
            ];
        }

        return $held;
    }

    /**
     * POSITIONS FILLED PER DEPARTMENT, LONGEST FIRST — the ranked bars, scaled
     * to the largest department. A department with no position keeps its row
     * and says so rather than drawing an empty bar.
     *
     * @param list<Department>                                    $departments
     * @param array<int, array{position: Position, holders: int}> $held
     *
     * @return list<SectionBar>
     */
    private function staffing(array $departments, array $held): array
    {
        $bars = [];
        foreach ($departments as $department) {
            $total = $filled = $people = 0;
            foreach ($held as $row) {
                if ($row['position']->getDepartment()?->getId() !== $department->getId()) {
                    continue;
                }

                ++$total;
                $people += $row['holders'];
                if ($row['holders'] > 0) {
                    ++$filled;
                }
            }

            $bars[] = new SectionBar(
                label: (string) $department->getName(),
                value: $filled,
                total: $total,
                people: $people,
                note: 0 === $total
                    ? 'no positions · nothing filed here yet'
                    : \sprintf('%d/%d · %d vacant', $filled, $total, $total - $filled),
            );
        }

        usort($bars, static fn (SectionBar $a, SectionBar $b): int => $b->total <=> $a->total ?: strcmp($a->label, $b->label));

        return self::scaled($bars);
    }

    /**
     * HOW MANY MODULES EACH DEPARTMENT READS, out of the installed catalogue.
     *
     * @param list<Department> $departments
     *
     * @return list<SectionBar>
     */
    private function modules(array $departments): array
    {
        $installed = max(1, $this->catalogue->count());

        $bars = [];
        foreach ($departments as $department) {
            $names = [];
            foreach ($department->getModules() as $module) {
                $names[] = $module->getName() ?? $module->getSlug();
            }
            sort($names);

            $bars[] = new SectionBar(
                label: (string) $department->getName(),
                value: \count($names),
                total: $installed,
                people: 0,
                note: [] === $names ? 'reads no module' : \sprintf('%d · %s', \count($names), implode(', ', $names)),
            );
        }

        usort($bars, static fn (SectionBar $a, SectionBar $b): int => $b->value <=> $a->value ?: strcmp($a->label, $b->label));

        return self::scaled($bars);
    }

    /**
     * THE DEPARTMENTS THAT READ NO MODULE — a department with none has people
     * and positions but no figure of its own, so it never reaches Performance.
     *
     * @param list<Department>                                    $departments
     * @param array<int, array{position: Position, holders: int}> $held
     *
     * @return list<SectionLine>
     */
    private function unattached(array $departments, array $held, ?int $bound): array
    {
        $lines = [];
        foreach ($departments as $department) {
            if (0 !== $department->getModules()->count()) {
                continue;
            }

            $total = $filled = 0;
            foreach ($held as $row) {
                if ($row['position']->getDepartment()?->getId() !== $department->getId()) {
                    continue;
                }
                ++$total;
                if ($row['holders'] > 0) {
                    ++$filled;
                }
            }

            $lines[] = new SectionLine(
                label: (string) $department->getName(),
                uuid: $department->getUuidString(),
                note: \sprintf(
                    '%s · %d of %d positions filled',
                    $department->isOrgLevel() ? 'org-wide' : (string) $department->getArea()?->getName(),
                    $filled,
                    $total,
                ),
            );
        }

        return null === $bound ? $lines : \array_slice($lines, 0, $bound);
    }

    /**
     * THE POSITIONS NOBODY HOLDS, widest gap first — longest vacant at the top,
     * because that is the one a reader is deciding about.
     *
     * @param array<int, array{position: Position, holders: int}> $held
     *
     * @return list<SectionLine>
     */
    private function vacancies(array $held, ?int $bound): array
    {
        $vacant = [];
        foreach ($held as $row) {
            if ($row['holders'] > 0) {
                continue;
            }

            $vacant[] = $row['position'];
        }

        usort($vacant, static fn (Position $a, Position $b): int => ($a->getVacantSince()?->getTimestamp() ?? \PHP_INT_MAX) <=> ($b->getVacantSince()?->getTimestamp() ?? \PHP_INT_MAX));

        $lines = array_map(
            static fn (Position $position): SectionLine => new SectionLine(
                label: $position->getQualifiedName(),
                uuid: null,
                note: 'nobody holds it',
                tone: 'w',
            ),
            $vacant,
        );

        return null === $bound ? $lines : \array_slice($lines, 0, $bound);
    }

    /**
     * @param list<\Uhifadhi\Bundle\TeamBundle\Entity\DepartmentGoal> $goals
     *
     * @return list<SectionLine>
     */
    private function goalLines(array $goals, int $bound): array
    {
        $lines = [];
        foreach (\array_slice($goals, 0, $bound) as $goal) {
            $lines[] = new SectionLine(
                label: \sprintf('%s · %s', (string) $goal->getDepartment()?->getName(), $goal->getStatement()),
                uuid: null,
                note: \sprintf('%s %s', self::trim($goal->getTarget()), $goal->getUnit()),
            );
        }

        return $lines;
    }

    /** @param array<string, int> $areas */
    private function areasPhrase(array $areas): string
    {
        if ([] === $areas) {
            return 'no area carries one of its own';
        }

        $parts = [];
        foreach ($areas as $name => $count) {
            $parts[] = \sprintf('%s %d', $name, $count);
        }

        return implode(' · ', $parts);
    }

    /** @param list<SectionBar> $bars */
    private function departmentsReadingSomething(array $bars): int
    {
        return \count(array_filter($bars, static fn (SectionBar $bar): bool => $bar->value > 0));
    }

    /** @param list<\Uhifadhi\Bundle\TeamBundle\Entity\DepartmentGoal> $goals */
    private function departmentsWithAGoal(array $goals): int
    {
        $seen = [];
        foreach ($goals as $goal) {
            $seen[(string) $goal->getDepartment()?->getUuidString()] = true;
        }

        return \count($seen);
    }

    /**
     * SCALED TO THE LARGEST ROW, not to the page. A bar read against its own
     * department's total would make a department of two look like one of
     * thirty-five, which is the one comparison the card exists to make.
     *
     * @param list<SectionBar> $bars
     *
     * @return list<SectionBar>
     */
    private static function scaled(array $bars): array
    {
        $largest = 0;
        foreach ($bars as $bar) {
            $largest = max($largest, $bar->total);
        }

        if (0 === $largest) {
            return $bars;
        }

        return array_map(static fn (SectionBar $bar): SectionBar => $bar->scaledTo($largest), $bars);
    }

    private static function trim(float $value): string
    {
        return rtrim(rtrim(number_format($value, 2, '.', ','), '0'), '.');
    }
}
