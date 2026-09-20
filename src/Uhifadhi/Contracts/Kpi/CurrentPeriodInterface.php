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

namespace Uhifadhi\Contracts\Kpi;

/**
 * WHAT PERIOD IT IS NOW — one answer, for every surface that captions one.
 *
 * WHY THIS IS A CONTRACT AND NOT A CLASS SOMEBODY IMPORTS. "This month's
 * figures" used to be decided separately wherever it was needed, each place
 * writing `FigurePeriod::month(new \DateTimeImmutable())` and asking the wall
 * clock: correct on the 14th, turned over on the 1st, and unpinnable by any
 * test that could not also set the server's date. One source ends that — but
 * a source is a SERVICE, and a bundle that named another bundle's service id
 * would be a bundle that cannot boot without it.
 *
 * SO THE CONSUMERS NAME THIS, AND WHOEVER IMPLEMENTS IT ANSWERS. A package
 * that ships the calendar vocabulary provides it; a package that merely
 * captions a period asks for it and says what it does when nobody answers.
 * That is the difference between a bundle declaring what it needs and a
 * bundle assuming what is registered — and it is what lets a kernel take one
 * bundle's ENTITIES without taking another bundle's SCREENS.
 *
 * IT IS FED BY A CLOCK, always. An implementation that read the wall clock
 * would put back exactly what this interface was extracted to remove.
 */
interface CurrentPeriodInterface
{
    /**
     * THE MOMENT, for a reading about an instant rather than a window — how
     * old a fix is, what is happening right now.
     *
     * Prefer a named window below wherever a page CAPTIONS the period. This
     * is not a way back to the wall clock: it is the same clock, so a test
     * that pins one pins both.
     */
    public function now(): \DateTimeImmutable;

    /** The whole calendar month we are in — what most figure bands ask for. */
    public function month(): FigurePeriod;

    /** The calendar quarter we are in. */
    public function quarter(): FigurePeriod;

    /** The calendar year we are in. */
    public function year(): FigurePeriod;

    /** The last N days, ending now — a rolling window rather than a calendar one. */
    public function days(int $days): FigurePeriod;
}
