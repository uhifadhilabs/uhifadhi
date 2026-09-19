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

namespace Uhifadhi\Bundle\TeamBundle\Tests\Integration\Performance;

use PHPUnit\Framework\Attributes\CoversClass;
use Uhifadhi\Bundle\TeamBundle\Entity\Department;
use Uhifadhi\Bundle\TeamBundle\Service\PerformanceHistory;
use Uhifadhi\Bundle\TeamBundle\Tests\Integration\IntegrationTestCase;

/**
 * WHAT A FIGURE WAS LAST PERIOD, WHICH NOTHING IN THE CORE USED TO KNOW.
 *
 * A PAGE THAT SAYS "↑ 3 ON JULY" HAS TO HAVE KNOWN JULY. Every movement,
 * every sparkline and every rank change on the performance page is a
 * comparison against a period that has closed, and a closed period cannot
 * be recomputed: positions are filled and emptied, people move, and the
 * tables hold only what is true now. So the host writes down what each
 * figure was, once per period, and reads its own record afterwards.
 *
 * ONE ROW PER DEPARTMENT, PERIOD AND FIGURE, and writing the same one twice
 * overwrites rather than duplicates — a snapshot run by hand after a
 * scheduled one must not double the history.
 *
 * AND NO HISTORY IS ITS OWN ANSWER. An installation that has never run the
 * snapshot has no past, which the surfaces say in words; they do not read
 * the absence as nought, because a department that did not exist last July
 * and one nobody measured are not the same thing.
 */
#[CoversClass(PerformanceHistory::class)]
final class PerformanceHistoryTest extends IntegrationTestCase
{
    public function testAFigureIsWrittenDownAndReadBack(): void
    {
        $ecology = $this->department('Ecology');

        $this->history()->record($ecology, '2026-08', 'staffing.filled', 9.0);

        self::assertSame(9.0, $this->history()->valueAt($ecology, 'staffing.filled', '2026-08'));
    }

    /** Writing the same figure twice is one row, not two. */
    public function testRecordingTheSamePeriodTwiceOverwrites(): void
    {
        $ecology = $this->department('Ecology');

        $this->history()->record($ecology, '2026-08', 'staffing.filled', 9.0);
        $this->history()->record($ecology, '2026-08', 'staffing.filled', 11.0);

        self::assertSame(11.0, $this->history()->valueAt($ecology, 'staffing.filled', '2026-08'));
        self::assertSame(1, $this->rowCount());
    }

    /** A period nobody wrote down has no value, and that is not a nought. */
    public function testAPeriodNobodyWroteDownHasNoValue(): void
    {
        self::assertNull($this->history()->valueAt($this->department('Ecology'), 'staffing.filled', '2026-07'));
    }

    /**
     * THE SPARKLINE'S OWN SHAPE: the last so many periods, oldest first, with
     * a hole where nobody wrote. A run of six that quietly closed up over a
     * missing month would draw a line that never happened.
     */
    public function testTheRunOfPeriodsKeepsItsHoles(): void
    {
        $ecology = $this->department('Ecology');
        $this->history()->record($ecology, '2026-06', 'staffing.filled', 7.0);
        $this->history()->record($ecology, '2026-08', 'staffing.filled', 9.0);

        self::assertSame(
            ['2026-05' => null, '2026-06' => 7.0, '2026-07' => null, '2026-08' => 9.0],
            $this->history()->runFor($ecology, 'staffing.filled', ['2026-05', '2026-06', '2026-07', '2026-08']),
        );
    }

    /** One read for a whole page: every department's figure for one period. */
    public function testOnePeriodIsReadForEveryDepartmentAtOnce(): void
    {
        $ecology = $this->department('Ecology');
        $tourism = $this->department('Tourism');
        $this->history()->record($ecology, '2026-08', 'staffing.filled', 9.0);
        $this->history()->record($tourism, '2026-08', 'staffing.filled', 4.0);

        self::assertSame(
            [(string) $ecology->getUuidString() => 9.0, (string) $tourism->getUuidString() => 4.0],
            $this->history()->acrossDepartments('staffing.filled', '2026-08'),
        );
    }

    private function department(string $name): Department
    {
        $department = new Department()->setName($name);
        $this->em->persist($department);
        $this->em->flush();

        return $department;
    }

    private function history(): PerformanceHistory
    {
        /** @var PerformanceHistory $history */
        $history = static::getContainer()->get('test_public.'.PerformanceHistory::class);

        return $history;
    }

    private function rowCount(): int
    {
        $rows = $this->em->getConnection()->fetchOne('SELECT COUNT(*) FROM team_department_period_figure');
        self::assertIsNumeric($rows);

        return (int) (string) $rows;
    }
}
