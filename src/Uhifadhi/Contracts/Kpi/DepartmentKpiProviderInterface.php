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
 * THE CONTRACT a module bundle implements to put figures on a department's
 * performance surfaces.
 *
 * The model canon, restated as a contract:
 *
 *  - A department has no numbers of its own. Everything a performance page shows
 *    is an attached module's KPIs, so this interface is the ONLY way a figure
 *    gets there.
 *  - The figures are summed over the areas the module is switched on in — the
 *    implementation's business, since only the module knows what an "area" means
 *    to it.
 *  - They are SLICED BY THE RECORDING PERSON'S POSITION: a row counts for a
 *    department when the person who recorded it holds a position filed under
 *    that department. One shared module read by two departments therefore yields
 *    two different numbers from the same rows, and neither department is fenced
 *    out of the other's — the split is reporting, never permission.
 *
 * THE DEPARTMENT ARRIVES AS A REF, NOT AS AN ENTITY. Departments are the team
 * module's, and nothing published describes one; see {@see DepartmentRef} for
 * why a provider is handed id, uuid and name instead of somebody's class. A
 * provider that needs to know WHOSE rows these are reads the org chart the way
 * the platform already does — off the mapping, through the class the installation
 * resolved the user contract to — and never through a `getPosition()` no
 * contract promises.
 *
 * HOW AN IMPLEMENTOR IS COLLECTED. Whoever renders a performance surface reads
 * the {@see TAG}, and the tag is applied EXPLICITLY at both ends:
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
 * performance surface.
 */
interface DepartmentKpiProviderInterface
{
    public const string TAG = 'uhifadhi.department_kpi';

    /**
     * The slug of the module whose figures these are — the same slug the
     * module's ModuleProviderInterface declares.
     *
     * A provider is asked for numbers ONLY when a department attaches this
     * module, so a detached module's plates simply leave the page rather than
     * going to zero.
     */
    public function moduleSlug(): string;

    /**
     * This department's figures for the period containing `$now`, plus the
     * period before it for the month-over-month move.
     *
     * ONE SET OF KPIs PER CALL. A call asks about ONE department at ONE scope and
     * is answered with ONE figure per key — never the same key once per area,
     * because the surfaces draw one row of tiles per module and unheaded copies
     * of one label are unreadable. The scope is on the ref:
     *
     *  - `areaUuid` SET — the department is confined to that area, and the answer
     *    is that area's figures alone;
     *  - `areaUuid` NULL — the department is organisation-wide, and the answer is
     *    the ROLL-UP across every area the module is switched on in: summed,
     *    averaged or weighted as the KPI itself defines. THE PROVIDER DECIDES
     *    WHICH, and says so in the figure's caption, because a reader cannot tell
     *    a sum from an average by looking at it.
     *
     * A module that also wants to show the per-area split behind a roll-up hands
     * those sets back beside the total — a {@see DepartmentKpi} naming an area is
     * one area's share, and a nameless one is the total every headline plate and
     * goal is scored from.
     *
     * `$now` is handed in rather than read, so a performance page is testable
     * and a period picker is a parameter and not a second code path.
     *
     * Returning `[]` is a legitimate answer — an attached module with nothing to
     * report is a dashed slot, and MUST NOT be a zero.
     *
     * @return list<DepartmentKpi>
     */
    public function kpisFor(DepartmentRef $department, \DateTimeImmutable $now): array;
}
