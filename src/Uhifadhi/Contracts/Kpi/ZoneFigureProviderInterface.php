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
 * THE CONTRACT a module bundle implements to put figures on a zone.
 *
 * The model canon, restated as a contract:
 *
 *  - A ZONE HAS NO NUMBERS OF ITS OWN, exactly as a department has none. The
 *    area module owns the ground, the name and the ring; every count over that
 *    ground — patrols walked, incidents recorded, how much of it was covered —
 *    belongs to whichever module recorded it. This interface is the ONLY way
 *    such a figure reaches a zone surface.
 *  - A ZONE IS A LENS, NOT A FENCE. A provider reports what it holds inside the
 *    ring; it never decides who may read it, and a zone's figures hide nothing
 *    from the area's.
 *  - WHO RECORDED A ROW DECIDES NOTHING, as on the department seam: a zone's
 *    figures are every record over that ground, whoever logged it and whether
 *    or not they hold a position today.
 *
 * THE ZONE ARRIVES AS A REF, NOT AS AN ENTITY — see {@see ZoneRef} for why a
 * provider is handed identifiers and a name instead of somebody's class.
 *
 * THE FIGURES ARE {@see DepartmentKpi}, DELIBERATELY AND NOT BY ACCIDENT. It is
 * already the product's word for "one figure a module computed over one
 * period", with the two rules that matter baked in — null is unknown and never
 * zero, a share moves in points while a count moves in percent — and one KPI
 * card renderer serving both scopes is what stops a zone page and a performance
 * page from drifting apart. A zone figure leaves `areaName` null: the ref
 * already says which area, and the field means "one area's share of a
 * roll-up", which a zone figure never is.
 *
 * ONE CALL FOR THE WHOLE SET. {@see ZoneFigureRequest} carries every zone the
 * caller is about to draw, and the answer is keyed by zone, because the
 * all-zones view and the map legend each draw a row per zone and asking once
 * per zone would be a round trip per zone per module per page.
 *
 * HOW AN IMPLEMENTOR IS COLLECTED. Whoever renders a zone surface reads the
 * {@see TAG}, and the tag is applied EXPLICITLY at both ends:
 *
 *  - a MODULE BUNDLE tags its provider in its extension, because a reusable
 *    bundle is not autoconfigured (Symfony's bundle best practices forbid it) —
 *    exactly as it already does for `uhifadhi.module`;
 *  - an APPLICATION service carries `#[AutoconfigureTag(self::TAG)]` ON ITS OWN
 *    CLASS.
 *
 * That last line is a real constraint, not a style note: Symfony's
 * `RegisterAutoconfigureAttributesPass` reads attributes off the DEFINITION'S
 * OWN CLASS only, and PHP does not inherit attributes from an implemented
 * interface — so an `#[AutoconfigureTag]` written here would be silently dead,
 * and the only symptom would be every figure quietly disappearing from every
 * zone surface.
 *
 * A PROVIDER IS ASKED ONLY WHERE ITS MODULE IS SWITCHED ON. The collector reads
 * the area's ledger first, so a module parked in an area contributes nothing
 * there and a module nobody installed contributes nothing anywhere — without a
 * line in the core naming any module.
 */
interface ZoneFigureProviderInterface
{
    public const string TAG = 'uhifadhi.zone_kpi';

    /**
     * HOW MUCH OF A ZONE'S GROUND WAS WORKED IN THE PERIOD, as a share.
     *
     * NAMED HERE RATHER THAN AGREED BY CONVENTION, because three surfaces read
     * this one key — the zones tab's Covered card, a zone record's identity
     * band and the plate's legend — and a provider that spelled it differently
     * would leave all three blank with nothing on the page to point at.
     *
     * Published by whoever measures ground covered; the unit is
     * {@see DepartmentKpi::SHARE}, so it moves in points and a null means the
     * module did not measure, never that it measured nothing.
     */
    public const string COVERED = 'covered';

    /**
     * The slug of the module whose figures these are — the same slug the
     * module's `ModuleProviderInterface` declares.
     *
     * A provider is asked for numbers ONLY where an area runs this module, so
     * a parked module's figures simply leave the page rather than going to
     * zero.
     */
    public function moduleSlug(): string;

    /**
     * This module's figures for each zone in the request, over the period it
     * asks for.
     *
     * ONE FIGURE PER KEY PER ZONE. A surface draws one card per key, so two
     * figures under one key for one zone is a card that cannot be rendered.
     *
     * ANSWERING WITH NOTHING IS LEGITIMATE — {@see ZoneFigures::none()} — and a
     * zone left out of the answer is a zone this module has nothing to say
     * about. Neither is a zero: the surfaces render an absence as an absence.
     *
     * THE ANSWER STATES THE PERIOD IT COVERS, which may be wider than the one
     * asked for when that is all the module can measure; the caption a surface
     * prints comes from there and not from the request.
     */
    public function figuresFor(ZoneFigureRequest $request): ZoneFigures;
}
