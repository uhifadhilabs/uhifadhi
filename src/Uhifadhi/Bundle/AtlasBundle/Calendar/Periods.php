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

namespace Uhifadhi\Bundle\AtlasBundle\Calendar;

use Psr\Clock\ClockInterface;
use Uhifadhi\Contracts\Kpi\CurrentPeriodInterface;
use Uhifadhi\Contracts\Kpi\FigurePeriod;

/**
 * WHAT PERIOD IT IS NOW — asked once, of something a test can set.
 *
 * THE DEFECT THIS EXISTS TO END. "This month's figures" was written
 * `FigurePeriod::month(new \DateTimeImmutable())` in eleven places across two
 * bundles: six area surfaces and five performance reads. Every one of them
 * decided, separately, which month a page is about, by asking the wall clock
 * — so a suite was correct on the 14th and flipped on the 1st, two pages
 * rendered in the same request could straddle midnight at a month boundary,
 * and not one of the eleven could be pinned by a test without pinning the
 * server.
 *
 * ONE SOURCE, AND IT IS FED BY A CLOCK. The decision is made here, once, and
 * the instant comes from `psr/clock` — which `symfony/clock` answers with a
 * `MockClock` in a test. Pinning the clock pins every period on every page,
 * which is the only way a month boundary is testable at all.
 *
 * CONSUMERS NAME THE CONTRACT, NOT THIS CLASS. A bundle that captions a
 * period asks for {@see CurrentPeriodInterface} and says what it does when
 * nobody answers; only this one, which ships the calendar vocabulary, is the
 * answer. A team bundle naming `atlas.periods` in its own wiring would be a
 * team bundle that cannot boot in a kernel taking its entities and not its
 * screens — which is exactly what it did.
 *
 * WHY IT LIVES IN THE ATLAS. A reporting period is the vocabulary a chart, a
 * calendar and a figure band are drawn in, which is this bundle's subject;
 * and it is the one bundle BOTH the area and the team already depend on, so
 * "one source" can be literally one class rather than one per bundle. It
 * holds no domain: a calendar month is a calendar month wherever it is read.
 *
 * IT ANSWERS THE WINDOW, NOT THE INSTANT, for everything a page captions.
 * {@see now()} is there for the readings that genuinely need a moment — a
 * live plate, an age — and a caller that takes the instant to build a period
 * from has re-introduced the very thing this class removed.
 */
final readonly class Periods implements CurrentPeriodInterface
{
    public function __construct(private ClockInterface $clock)
    {
    }

    /**
     * THE MOMENT, for a reading that is about an instant rather than a
     * window: how old a fix is, what is happening right now.
     *
     * Prefer a named window below wherever a page CAPTIONS the period. This
     * is not an escape hatch back to the wall clock — it is the same clock,
     * so a test pins it too.
     */
    public function now(): \DateTimeImmutable
    {
        return $this->clock->now();
    }

    /** The whole calendar month we are in — what most figure bands ask for. */
    public function month(): FigurePeriod
    {
        return FigurePeriod::month($this->now());
    }

    /** The calendar quarter we are in. */
    public function quarter(): FigurePeriod
    {
        return FigurePeriod::quarter($this->now());
    }

    /** The calendar year we are in. */
    public function year(): FigurePeriod
    {
        return FigurePeriod::year($this->now());
    }

    /**
     * THE LAST N DAYS, ENDING NOW — a rolling window rather than a calendar
     * one, for a card that is about "recently" and not about a month.
     */
    public function days(int $days): FigurePeriod
    {
        return FigurePeriod::days($days, $this->now());
    }
}
