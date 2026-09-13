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
 * A DEPARTMENT, NAMED WITHOUT NAMING ANYBODY'S CLASS.
 *
 * Departments belong to TeamBundle, and NO PACKAGE PUBLISHES A CONTRACT
 * FOR ONE — there is no `DepartmentInterface` in the contracts and none in
 * team. A KPI contract typed against TeamBundle's own entity would therefore
 * make every module that reports a figure depend on TeamBundle, and a contract
 * typed against nothing would hand providers an `object` to guess at.
 *
 * So the caller — whoever holds the department, which is the surface rendering
 * the page — resolves it to this: the id a provider files rows under, the uuid a
 * URL names it by, the name a plate prints, and THE AREA IT IS CONFINED TO. That
 * is the whole of what a figure needs, and it is the SAME DISCIPLINE the platform
 * already applies to reading a person's department: walk the mapping, never the
 * type.
 *
 * THE REF CARRIES THE SCOPE, and it has to. A department is either confined to
 * one area or spans the organisation, and a provider handed no scope either
 * guesses or answers with every area's figures at once — which is one set of
 * labels per area on a page that draws one. So the scope travels with the ref,
 * and {@see DepartmentKpiProviderInterface::kpisFor()} states what a provider
 * owes for each of its two values.
 *
 * It is deliberately not an entity and not persisted. A ref is made for one
 * render and thrown away.
 */
final readonly class DepartmentRef
{
    /**
     * @param int         $id       the key rows are filed under — what a provider's
     *                              user→department map answers with
     * @param string      $name     what a plate prints
     * @param string|null $uuid     how a URL names it, when the caller has one
     * @param string|null $areaUuid THE AREA AN AREA-LEVEL DEPARTMENT IS CONFINED
     *                              TO, as the uuid string {@see \Uhifadhi\Contracts\Entity\AreaInterface}
     *                              publishes. NULL MEANS ORGANISATION-WIDE — the
     *                              department has no single area, so a provider
     *                              rolls up across every area rather than
     *                              reporting them one by one
     */
    public function __construct(
        public int $id,
        public string $name,
        public ?string $uuid = null,
        public ?string $areaUuid = null,
    ) {
        if ('' === $name) {
            throw new \InvalidArgumentException('A department ref must carry the name a plate prints.');
        }
    }
}
