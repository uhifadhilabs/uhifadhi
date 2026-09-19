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
 * THE CONTRACT a module bundle implements to put figures on a station.
 *
 * The model canon, restated as a contract:
 *
 *  - A STATION HAS NO NUMBERS OF ITS OWN, exactly as a zone and a department
 *    have none. The area module owns the post, its point and who is posted
 *    there; every count ABOUT that post — patrols that went out of it,
 *    incidents near it, observations logged from it — belongs to whichever
 *    module recorded them, and this interface is the only way one reaches a
 *    station surface.
 *  - WHAT "ABOUT THIS POST" MEANS IS THE PROVIDER'S. Patrols are the ones that
 *    STARTED here; incidents are the ones within some distance of it. Neither
 *    is the core's definition to make, which is why the caption carries the
 *    qualifier — "out of here · 412 km", "within 12 km · 1 open" — and the
 *    core prints it without interpreting it.
 *  - WHO RECORDED A ROW DECIDES NOTHING, as on the other two seams.
 *
 * THE ZONE ARRIVES AS A REF, NOT AS AN ENTITY — see {@see StationRef} for why a
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
 * ONE CALL FOR THE WHOLE SET. {@see StationFigureRequest} carries every zone the
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
interface StationFigureProviderInterface
{
    public const string TAG = 'uhifadhi.station_kpi';

    /**
     * THE HEADLINE FIGURE A MODULE PUTS ON A POST — one per module, which is
     * exactly what the dock draws: a name, a number, the words that qualify
     * it, and a link the core resolves from the module's own entry route.
     *
     * NAMED HERE RATHER THAN AGREED BY CONVENTION, because the dock reads one
     * key per module and a provider that spelled it differently would leave
     * its row blank with nothing on the page to point at. A module with more
     * to say says it in the caption — "out of here · 412 km" — and not in a
     * second key.
     */
    public const string HEADLINE = 'headline';

    /**
     * The slug of the module whose figures these are — the same slug the
     * module's `ModuleProviderInterface` declares.
     *
     * A provider is asked for numbers ONLY where an area runs this module, so
     * a parked module's row simply leaves the dock rather than going to zero.
     * THE DOCK'S LINK IS NOT THE PROVIDER'S to supply: the core already knows
     * each module's entry route from the registry and resolves it from this
     * slug, so no figure carries a URL.
     */
    public function moduleSlug(): string;

    /**
     * This module's figures for each station in the request, over the period
     * it asks for.
     *
     * ONE FIGURE PER KEY PER STATION. The dock draws one row per module, so
     * two figures under one key for one station is a row that cannot be
     * rendered.
     *
     * ANSWERING WITH NOTHING IS LEGITIMATE — {@see StationFigures::none()} —
     * and a station left out of the answer is a post this module has nothing
     * to say about. Neither is a zero: the surfaces render an absence as an
     * absence.
     *
     * THE ANSWER STATES THE PERIOD IT COVERS, which may be wider than the one
     * asked for when that is all the module can measure; the caption a surface
     * prints comes from there and not from the request.
     */
    public function figuresFor(StationFigureRequest $request): StationFigures;
}
