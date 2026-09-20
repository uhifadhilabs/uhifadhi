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

namespace Uhifadhi\Contracts\Tests\Kpi;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Uhifadhi\Contracts\Kpi\FigurePeriod;

/**
 * THE THREE WINDOWS A PERFORMANCE PAGE OFFERS, and the one rule they all
 * keep: half-open, so no record is counted twice at a boundary.
 *
 * A QUARTER AND A YEAR ARE CALENDAR WINDOWS, not ninety and three
 * hundred and sixty-five days. A reader asking for "this quarter" is
 * asking about the quarter the organisation reports in, and a rolling
 * window would answer a question about the last ninety days instead —
 * a different number, in a period nobody closed.
 */
#[CoversClass(FigurePeriod::class)]
final class FigurePeriodTest extends TestCase
{
    /** The quarter an instant falls in, from its first day to the next quarter's. */
    public function testAQuarterIsTheCalendarQuarterTheInstantFallsIn(): void
    {
        $period = FigurePeriod::quarter(new \DateTimeImmutable('2026-08-14 10:00:00'));

        self::assertSame('2026-07-01 00:00:00', $period->from->format('Y-m-d H:i:s'));
        self::assertSame('2026-10-01 00:00:00', $period->until->format('Y-m-d H:i:s'));
        self::assertSame('Quarter 3 2026', $period->label);
    }

    /** Every month of a quarter answers with the same window. */
    public function testEveryMonthOfAQuarterNamesTheSameQuarter(): void
    {
        foreach (['2026-01-01', '2026-02-28', '2026-03-31 23:00:00'] as $day) {
            $period = FigurePeriod::quarter(new \DateTimeImmutable($day));

            self::assertSame('Quarter 1 2026', $period->label);
            self::assertSame('2026-01-01', $period->from->format('Y-m-d'));
            self::assertSame('2026-04-01', $period->until->format('Y-m-d'));
        }
    }

    /** A year is the calendar year, and it ends where the next one starts. */
    public function testAYearIsTheCalendarYear(): void
    {
        $period = FigurePeriod::year(new \DateTimeImmutable('2026-08-14 10:00:00'));

        self::assertSame('2026-01-01 00:00:00', $period->from->format('Y-m-d H:i:s'));
        self::assertSame('2027-01-01 00:00:00', $period->until->format('Y-m-d H:i:s'));
        self::assertSame('2026', $period->label);
    }

    /**
     * AND THE COMPARED PERIOD IS THE ONE BEFORE, OF THE SAME LENGTH —
     * which is what every movement on the page is measured against.
     */
    public function testTheComparedPeriodIsTheOneBeforeIt(): void
    {
        self::assertSame(
            '2026-04-01',
            FigurePeriod::quarter(new \DateTimeImmutable('2026-08-14'))->previous()->from->format('Y-m-d'),
        );
    }

    /**
     * A PERIOD NAMES ITSELF SHORT AS WELL AS LONG, because a band has
     * room for one word — and because a surface that shortened the
     * long one would be formatting an instant in the server's zone.
     */
    public function testAPeriodNamesItselfShortToo(): void
    {
        self::assertSame('aug', FigurePeriod::month(new \DateTimeImmutable('2026-08-14'))->shortLabel());
        self::assertSame('q3', FigurePeriod::quarter(new \DateTimeImmutable('2026-08-14'))->shortLabel());
        self::assertSame('2026', FigurePeriod::year(new \DateTimeImmutable('2026-08-14'))->shortLabel());
    }
}
