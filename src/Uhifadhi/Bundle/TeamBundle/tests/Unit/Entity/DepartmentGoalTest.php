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

namespace Uhifadhi\Bundle\TeamBundle\Tests\Unit\Entity;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Uhifadhi\Bundle\TeamBundle\Entity\Department;
use Uhifadhi\Bundle\TeamBundle\Entity\DepartmentGoal;
use Uhifadhi\Bundle\TeamBundle\Enum\GoalDirectionEnum;
use Uhifadhi\Bundle\TeamBundle\Enum\GoalStateEnum;

/**
 * A DEPARTMENT'S GOAL, AND THE FIVE STATES IT CAN BE READ IN.
 *
 * THE STATE IS DERIVED, NEVER STORED. A goal that carried its own state
 * would be a goal somebody has to remember to update, and the rail would go
 * on saying "met" for a month after the figure moved. It is computed from
 * one figure and one clock, here, so every surface that draws the rail
 * draws the same answer.
 *
 * FIVE STATES, BECAUSE TWO EMPTINESSES ARE NOT ONE. A goal nobody declared
 * and a goal whose module has published nothing look alike on a page and
 * mean opposite things: nobody set a target, versus nobody has reported
 * one. The design draws them as a dashed ring and a solid hollow ring, and
 * the rail ships all five or it lies.
 *
 * DIRECTION IS THE GOAL'S OWN. "Cover 60 % of the ground" is met by going
 * up; "settle a claim in 10 days" is met by coming down. Without it a goal
 * cannot be judged at all, and a page would have to guess from the unit.
 */
#[CoversClass(DepartmentGoal::class)]
final class DepartmentGoalTest extends TestCase
{
    private const string NOW = '2026-09-19 10:00:00';

    /** No figure at all is its own state, and it is never a miss. */
    public function testAGoalNoModuleHasReportedOnReadsAsHavingNoFigure(): void
    {
        $goal = self::goal(60.0, GoalDirectionEnum::AtLeast);

        self::assertSame(GoalStateEnum::NoFigure, $goal->stateFor(null, self::now()));
    }

    #[DataProvider('judgements')]
    public function testTheFigureAndTheClockDecideTheState(
        GoalDirectionEnum $direction,
        float $target,
        float $figure,
        string $until,
        GoalStateEnum $expected,
    ): void {
        $goal = self::goal($target, $direction, $until);

        self::assertSame($expected, $goal->stateFor($figure, self::now()));
    }

    /** @return iterable<string, array{GoalDirectionEnum, float, float, string, GoalStateEnum}> */
    public static function judgements(): iterable
    {
        // AT LEAST: the figure has to reach the target.
        yield 'reached, and the period is still open' => [GoalDirectionEnum::AtLeast, 60.0, 61.0, '2026-09-30', GoalStateEnum::Met];
        yield 'reached, and the period has closed' => [GoalDirectionEnum::AtLeast, 60.0, 61.0, '2026-09-18', GoalStateEnum::Met];
        // A PERIOD THAT HAS CLOSED SHORT IS A MISS, and one still open is a
        // warning: the difference between them is the only thing anybody can
        // still act on.
        yield 'short, and the period has closed' => [GoalDirectionEnum::AtLeast, 60.0, 54.0, '2026-09-18', GoalStateEnum::Missed];
        yield 'short, with days left' => [GoalDirectionEnum::AtLeast, 60.0, 54.0, '2026-09-30', GoalStateEnum::AtRisk];

        // AT MOST: the figure has to stay under it.
        yield 'under, still open' => [GoalDirectionEnum::AtMost, 10.0, 9.6, '2026-09-30', GoalStateEnum::Met];
        yield 'over, still open' => [GoalDirectionEnum::AtMost, 10.0, 11.4, '2026-09-30', GoalStateEnum::AtRisk];
        yield 'over, closed' => [GoalDirectionEnum::AtMost, 10.0, 11.4, '2026-09-18', GoalStateEnum::Missed];
    }

    /** How long is left to act, which is what "at risk" is worth knowing by. */
    public function testItSaysHowManyDaysAreLeft(): void
    {
        self::assertSame(11, self::goal(60.0, GoalDirectionEnum::AtLeast, '2026-09-30')->daysLeft(self::now()));
        self::assertSame(0, self::goal(60.0, GoalDirectionEnum::AtLeast, '2026-09-18')->daysLeft(self::now()));
    }

    /**
     * WHAT THE REST OF THE PERIOD HAS TO DO — the pace. A goal that is
     * behind does not need to know by how much; it needs to know what the
     * days that are left have to deliver.
     */
    public function testThePaceIsWhatTheRemainingDaysMustDeliver(): void
    {
        $goal = self::goal(60.0, GoalDirectionEnum::AtLeast, '2026-09-30');

        self::assertEqualsWithDelta(6.0, $goal->paceFor(54.0, self::now()), 0.001);
        // A goal already met needs nothing of the days that are left.
        self::assertNull($goal->paceFor(61.0, self::now()));
        // And with no figure there is no pace to state, only the absence.
        self::assertNull($goal->paceFor(null, self::now()));
    }

    private static function goal(float $target, GoalDirectionEnum $direction, string $until = '2026-09-30'): DepartmentGoal
    {
        return new DepartmentGoal()
            ->setDepartment(new Department()->setName('Protection Service'))
            ->setStatement('Cover the ground')
            ->setKpiRef('patrols.covered')
            ->setTarget($target)
            ->setUnit('%')
            ->setDirection($direction)
            ->setOpensAt(new \DateTimeImmutable('2026-09-01'))
            ->setClosesAt(new \DateTimeImmutable($until));
    }

    private static function now(): \DateTimeImmutable
    {
        return new \DateTimeImmutable(self::NOW);
    }
}
