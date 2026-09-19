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

namespace Uhifadhi\Contracts\Atlas;

/**
 * WHAT A MODULE IMPLEMENTS TO HAVE A MONTH DRAWN.
 *
 * THE PLATE'S BARGAIN, ON A CALENDAR. A module says what happened on
 * which day and what each thing should read as; the atlas owns the
 * grid, the day head, the cell, its fixed height, the day number, the
 * "+N more" and the month stepper. Patrols drew the month first and
 * incidents draws the same month with different marks in it — the cell
 * belongs to neither of them.
 *
 * THE CELL HAS ONE HEIGHT WHATEVER IT HOLDS, which is the rule the
 * whole component exists to keep: a busy week must not make the month
 * taller than a quiet one. A feed hands over everything it has for a
 * day and the renderer bounds it; a feed that trimmed to fit would be
 * deciding a layout it cannot see.
 *
 * NO TAG AND NO COLLECTION. A surface NAMES the feed it wants, exactly
 * as it names a plate's subject — because a month of patrols and a
 * month of incidents are different pages, not one page that merged
 * them. Any surface may render any module's feed; nothing is gathered
 * behind its back.
 *
 * SO A MODULE WIRES ITS FEED AS AN ORDINARY SERVICE:
 *
 *     $services->set('patrol.calendar', PatrolCalendar::class)
 *         ->args([service(PatrolRepository::class)]);
 *
 * and a controller that wants that month asks for that service.
 */
interface CalendarFeedInterface
{
    /**
     * ONE MONTH, FOR ONE SUBJECT.
     *
     * THE SCOPE IS THE SURFACE'S SUBJECT and is opaque to the atlas —
     * an area, a post, a person, a department, whatever the feed's own
     * records are narrowed by. Null is "everything this feed can see",
     * which is the right answer for an installation-wide month.
     *
     * A FEED WITH NOTHING FOR THIS MONTH ANSWERS
     * {@see CalendarMonth::none()}, and the atlas draws the month empty
     * rather than drawing nothing: an empty September is a fact about
     * September, and a missing grid is a broken page.
     */
    public function month(YearMonth $month, ?string $scope = null): CalendarMonth;
}
