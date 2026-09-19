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
use Uhifadhi\Bundle\AtlasBundle\Calendar\CalendarBuilder;
use Uhifadhi\Bundle\AtlasBundle\Model\AtlasCalendar;
use Uhifadhi\Bundle\AtlasBundle\Model\CalendarCell;
use Uhifadhi\Contracts\Atlas\CalendarDay;
use Uhifadhi\Contracts\Atlas\CalendarMonth;
use Uhifadhi\Contracts\Atlas\CalendarPill;
use Uhifadhi\Contracts\Atlas\PillHue;
use Uhifadhi\Contracts\Atlas\YearMonth;

/**
 * THE LAYOUT IS THE PLATFORM'S AND NOT THE MODULE'S.
 *
 * A module says what happened on which day. Which weekday the first falls
 * on, how many days spill in from the months either side, which square is
 * today and — the rule the whole component exists for — HOW MANY PILLS A
 * CELL HOLDS are all decided here, where the height is known.
 *
 * THE CELL IS ONE HEIGHT WHATEVER IT HOLDS. A busy week must not make the
 * month taller than a quiet one, so the surplus becomes "+N more"; a module
 * that had to guess the limit would guess it differently on every surface.
 */
#[CoversClass(CalendarBuilder::class)]
#[CoversClass(AtlasCalendar::class)]
#[CoversClass(CalendarCell::class)]
#[CoversClass(CalendarMonth::class)]
#[CoversClass(CalendarDay::class)]
#[CoversClass(YearMonth::class)]
final class CalendarBuilderTest extends TestCase
{
    /** September 2026 begins on a Tuesday and has 30 days. */
    private const string SEPTEMBER = '2026-09';

    public function testTheGridIsWholeWeeksBeginningOnMonday(): void
    {
        $calendar = $this->build([]);

        self::assertSame(0, \count($calendar->cells) % 7, 'a ragged last row would not align the columns');
        self::assertSame('2026-08-31', $calendar->cells[0]->localDate, 'the Monday on or before the first');
        self::assertSame(['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'], AtlasCalendar::WEEKDAYS);
    }

    /**
     * THE DAYS EITHER SIDE ARE REAL DATES, DRAWN FAINT. A grid that left them
     * blank would put the 1st in the wrong column or lose which month the
     * 31st belongs to.
     */
    public function testTheDaysEitherSideAreMarkedOutOfMonth(): void
    {
        $calendar = $this->build([]);

        self::assertFalse($calendar->cells[0]->inMonth);
        self::assertSame(31, $calendar->cells[0]->dayOfMonth);
        self::assertTrue($calendar->cells[1]->inMonth);
        self::assertSame(1, $calendar->cells[1]->dayOfMonth);
    }

    /** A month that fits in fewer weeks is drawn in fewer, never padded to six. */
    public function testAMonthIsNotPaddedToASixthWeekItDoesNotNeed(): void
    {
        $february = new CalendarMonth(new YearMonth(2027, 2));

        $calendar = new CalendarBuilder()->build($february);

        // February 2027 begins on a Monday and has 28 days: four rows exactly.
        self::assertCount(28, $calendar->cells);
    }

    /**
     * TODAY IS THE VIEWER'S TODAY and is handed in: every instant in this
     * product is read in the viewer's zone, and a builder that called the
     * clock itself would ring the wrong square for half the world.
     */
    public function testTodayIsTheDayTheCallerNamesAndNoOther(): void
    {
        $calendar = $this->build([], today: new \DateTimeImmutable('2026-09-19'));

        $ringed = array_values(array_filter($calendar->cells, static fn (CalendarCell $c): bool => $c->isToday));
        self::assertCount(1, $ringed);
        self::assertSame('2026-09-19', $ringed[0]->localDate);
    }

    /** A month the viewer is not in rings nothing at all. */
    public function testAMonthWithoutTodayInItRingsNothing(): void
    {
        $calendar = $this->build([], today: new \DateTimeImmutable('2026-11-04'));

        foreach ($calendar->cells as $cell) {
            self::assertFalse($cell->isToday);
        }
    }

    /** What a feed published lands on the day it published it for. */
    public function testAPublishedDaysPillsLandOnThatDay(): void
    {
        $calendar = $this->build([
            '2026-09-19' => new CalendarDay('2026-09-19', [$this->pill('day 2'), $this->pill('night 2', PillHue::Quiet)]),
        ]);

        $cell = $this->cellOn($calendar, '2026-09-19');
        self::assertCount(2, $cell->pills);
        self::assertSame('day 2', $cell->pills[0]->label);
        self::assertSame(PillHue::Quiet, $cell->pills[1]->hue);
    }

    /**
     * THE RULE THE COMPONENT EXISTS FOR: what does not fit becomes "+N more",
     * decided here and never by the module.
     */
    public function testACellBoundsItsPillsAndCountsTheRest(): void
    {
        $calendar = $this->build([
            '2026-09-19' => new CalendarDay('2026-09-19', [
                $this->pill('one'), $this->pill('two'), $this->pill('three'), $this->pill('four'), $this->pill('five'),
            ]),
        ]);

        $cell = $this->cellOn($calendar, '2026-09-19');
        self::assertCount(CalendarBuilder::PILLS_PER_CELL, $cell->pills);
        self::assertSame(2, $cell->hidden);
        self::assertSame(5, $cell->total);
    }

    /**
     * A FEED MAY PUBLISH THREE PILLS FOR A DAY THAT HAD FORTY, and the count
     * it states is the truth about the day. A cell computing "+N" from the
     * pills it received would say "+0" for a day of forty.
     */
    public function testTheHiddenCountIsAgainstTheDaysRealTotal(): void
    {
        $calendar = $this->build([
            '2026-09-19' => new CalendarDay('2026-09-19', [$this->pill('one'), $this->pill('two')], count: 40),
        ]);

        $cell = $this->cellOn($calendar, '2026-09-19');
        self::assertCount(2, $cell->pills);
        self::assertSame(38, $cell->hidden);
        self::assertSame(40, $cell->total);
    }

    /** A caller that sized the cell differently bounds it differently. */
    public function testATallerCellHoldsMore(): void
    {
        $calendar = $this->build(
            ['2026-09-19' => new CalendarDay('2026-09-19', [$this->pill('one'), $this->pill('two'), $this->pill('three'), $this->pill('four')])],
            pillsPerCell: 4,
        );

        $cell = $this->cellOn($calendar, '2026-09-19');
        self::assertCount(4, $cell->pills);
        self::assertSame(0, $cell->hidden);
    }

    /**
     * A QUIET DAY IS EMPTY, NOT NOUGHT. The feed sent nothing for it, so the
     * cell carries no count and no pills — and the grid still draws it.
     */
    public function testADayTheFeedSaidNothingAboutIsAnEmptyCell(): void
    {
        $calendar = $this->build(['2026-09-19' => new CalendarDay('2026-09-19', [$this->pill('day 2')])]);

        $quiet = $this->cellOn($calendar, '2026-09-20');
        self::assertTrue($quiet->isEmpty());
        self::assertSame(0, $quiet->total);
        self::assertSame([], $quiet->pills);
    }

    /**
     * AN EMPTY SEPTEMBER IS A FACT ABOUT SEPTEMBER. The grid is still built —
     * a missing month is a broken page — and it says of itself that nothing
     * is on it, so the renderer can print that in the house's own words.
     */
    public function testAMonthNobodyPublishedAnythingForIsBuiltAndSaysItIsEmpty(): void
    {
        $calendar = $this->build([]);

        self::assertNotEmpty($calendar->cells);
        self::assertTrue($calendar->isEmpty());
    }

    /**
     * AND A MONTH IS NOT CALLED EMPTY BECAUSE OF ITS NEIGHBOURS. Something on
     * a day that spilled in from August is not something on September.
     */
    public function testAPillOnAnOutOfMonthDayDoesNotMakeTheMonthNonEmpty(): void
    {
        $calendar = $this->build(['2026-08-31' => new CalendarDay('2026-08-31', [$this->pill('day 2')])]);

        self::assertTrue($calendar->isEmpty());
        self::assertCount(1, $this->cellOn($calendar, '2026-08-31')->pills);
    }

    /** The day's own address travels with the cell — it is where "+N more" goes. */
    public function testTheDaysUrlIsCarriedToTheCell(): void
    {
        $calendar = $this->build([
            '2026-09-19' => new CalendarDay('2026-09-19', [$this->pill('day 2')], url: '/modules/roster/plan?d=2026-09-19'),
        ]);

        self::assertSame('/modules/roster/plan?d=2026-09-19', $this->cellOn($calendar, '2026-09-19')->url);
    }

    // ---------------------------------------------------------------- fixtures

    /** @param array<string, CalendarDay> $days */
    private function build(array $days, ?\DateTimeImmutable $today = null, int $pillsPerCell = CalendarBuilder::PILLS_PER_CELL): AtlasCalendar
    {
        [$year, $month] = array_map(intval(...), explode('-', self::SEPTEMBER));

        return new CalendarBuilder()->build(new CalendarMonth(new YearMonth($year, $month), $days), $today, $pillsPerCell);
    }

    private function cellOn(AtlasCalendar $calendar, string $localDate): CalendarCell
    {
        foreach ($calendar->cells as $cell) {
            if ($localDate === $cell->localDate) {
                return $cell;
            }
        }

        self::fail(\sprintf('the grid draws %s', $localDate));
    }

    private function pill(string $label, PillHue $hue = PillHue::Subject): CalendarPill
    {
        return new CalendarPill($label, $hue);
    }
}
