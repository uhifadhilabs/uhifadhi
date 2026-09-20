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

namespace Uhifadhi\Bundle\AtlasBundle\Tests\Unit\Calendar;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use Uhifadhi\Bundle\AtlasBundle\Calendar\Periods;

/**
 * WHAT PERIOD IT IS NOW — and the month boundary, which is the whole reason
 * this class exists.
 *
 * THE DEFECT IT ENDS. "This month's figures" was written
 * `FigurePeriod::month(new \DateTimeImmutable())` in eleven places across two
 * bundles. Every one of them asked the WALL CLOCK, so every one of them was
 * correct on the 14th and turned over on the 1st with nothing able to pin it:
 * a suite could not ask "what does this page say on the last minute of
 * January", because the only way to answer was to change the server's date.
 *
 * SO THE BOUNDARY IS THE TEST. A minute before midnight on the 31st and a
 * minute after must give different periods, in one run, from one object —
 * which is only possible because the instant comes from a clock somebody can
 * set.
 */
#[CoversClass(Periods::class)]
final class PeriodsTest extends TestCase
{
    /** THE LAST MINUTE OF JANUARY IS JANUARY. */
    public function testAMinuteBeforeMidnightOnTheLastOfTheMonthIsStillThatMonth(): void
    {
        $periods = new Periods(new MockClock('2026-01-31 23:59:00'));

        self::assertSame('January 2026', $periods->month()->label);
    }

    /** AND A MINUTE LATER IT IS THE NEXT — the turn this class makes testable. */
    public function testAMinuteAfterMidnightIsTheNextMonth(): void
    {
        $periods = new Periods(new MockClock('2026-02-01 00:01:00'));

        self::assertSame('February 2026', $periods->month()->label);
    }

    /**
     * ONE OBJECT, ONE CLOCK, BOTH ANSWERS — which is the proof that the
     * period follows the clock and nothing else. Two tests that each passed
     * on their own could both be reading a server date.
     */
    public function testThePeriodFollowsTheClockAndNothingElse(): void
    {
        $clock = new MockClock('2026-01-31 23:59:00');
        $periods = new Periods($clock);

        self::assertSame('January 2026', $periods->month()->label);

        $clock->sleep(120);

        self::assertSame('February 2026', $periods->month()->label, 'The same object, two minutes later.');
    }

    /** The month is the whole calendar month, not a rolling thirty days. */
    public function testTheMonthIsTheWholeCalendarMonth(): void
    {
        $period = new Periods(new MockClock('2026-02-14 11:42:00'))->month();

        self::assertSame('2026-02-01 00:00:00', $period->from->format('Y-m-d H:i:s'));
        self::assertSame('2026-03-01 00:00:00', $period->until->format('Y-m-d H:i:s'));
    }

    /** A rolling window ends now, which is what makes it rolling. */
    public function testDaysIsARollingWindowEndingNow(): void
    {
        $period = new Periods(new MockClock('2026-02-14 11:42:00'))->days(7);

        self::assertTrue($period->contains(new \DateTimeImmutable('2026-02-10 09:00:00')));
        self::assertFalse($period->contains(new \DateTimeImmutable('2026-02-01 09:00:00')));
    }

    /** The quarter and the year turn over on their own boundaries, from the same clock. */
    public function testTheQuarterAndTheYearComeFromTheSameClock(): void
    {
        $periods = new Periods(new MockClock('2026-12-31 23:59:00'));

        self::assertSame('2026-10-01', $periods->quarter()->from->format('Y-m-d'));
        self::assertSame('2026-01-01', $periods->year()->from->format('Y-m-d'));
    }

    /**
     * AND THE INSTANT IS THE SAME CLOCK'S. A caller that needs a moment
     * rather than a window takes it here — not from the wall clock, which is
     * the escape hatch this class exists to close.
     */
    public function testTheInstantIsTheClocksToo(): void
    {
        $periods = new Periods(new MockClock('2026-02-14 11:42:00'));

        self::assertSame('2026-02-14 11:42:00', $periods->now()->format('Y-m-d H:i:s'));
    }
}
