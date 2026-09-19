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

namespace Uhifadhi\Contracts\Area;

/**
 * HOW A MODULE PUTS A SECTION ON A STATION — on the post's record page, and
 * on its card on the area's Stations configure page.
 *
 * THE STATION IS THE AREA'S AND THE WATCH IS THE ROSTER'S. The area owns the
 * post, its point, its catchment and who is posted there; when a watch is
 * expected at it, who is on it tonight, and what silence from it means are
 * the roster's — and an area naming any of that would be the area bundle
 * depending on a bundle it does not require. So the section is CONTRIBUTED,
 * exactly as the departments strip is contributed to the area's configure
 * page.
 *
 * WITH NO CONTRIBUTOR THERE IS NO BAND. An installation without the module
 * reads a shorter page, and nothing is missing that is not also absent: the
 * area draws no placeholder for a section nobody offered.
 *
 * ONE SEAM, TWO SURFACES. {@see StationSurface} says which is being drawn,
 * and a contributor that speaks to only one answers the other with
 * {@see StationSections::none()}.
 *
 * WHAT A CONTRIBUTOR DRAWS AND WHAT IT DOES NOT: the band, its heading, the
 * contributor tag and the summary line are the surface's chrome, written
 * once by the bundle that owns the page. A contributed template writes rows
 * and nothing around them — see {@see StationSection}.
 *
 * A CONTRIBUTOR IS ASKED ONLY WHERE ITS MODULE IS SWITCHED ON. The collector
 * reads the area's ledger first, so a module parked in an area contributes
 * nothing there and a module nobody installed contributes nothing anywhere —
 * without a line in the core naming any module.
 *
 * A REUSABLE BUNDLE IS NOT AUTOCONFIGURED, so the tag goes on by hand, at
 * both ends:
 *
 *     $services->set('roster.station_sections', RosterStationSections::class)
 *         ->tag(StationSectionsInterface::TAG);
 *
 * An APPLICATION service carries `#[AutoconfigureTag(self::TAG)]` ON ITS OWN
 * CLASS instead — Symfony reads autoconfigure attributes off the definition's
 * own class and PHP does not inherit them from an interface, so one written
 * here would be silently dead and the only symptom would be every band
 * quietly disappearing.
 */
interface StationSectionsInterface
{
    public const string TAG = 'uhifadhi.station_sections';

    /**
     * The slug of the module these sections belong to — the same slug its
     * `ModuleProviderInterface` declares.
     *
     * It is what the ledger is read with, and what the surface prints in the
     * tag beside the heading, so that a band disappearing when the module is
     * parked reads as the system working rather than as a bug.
     */
    public function moduleSlug(): string;

    /**
     * The sections this module puts on each post in the request, for the
     * surface it asks about.
     *
     * A POST LEFT OUT OF THE ANSWER IS ONE THIS MODULE HAS NOTHING TO SAY
     * ABOUT, which is not the same as having nothing to report about it: the
     * first draws no band at all, the second draws the band and says so in
     * the module's own words.
     */
    public function sectionsFor(StationSectionRequest $request): StationSections;
}
