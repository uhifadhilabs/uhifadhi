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

namespace Uhifadhi\Contracts\Shell;

/**
 * HOW A BUNDLE UNFOLDS ONE OF AN AREA'S SCREENS IN THE SIDEBAR.
 *
 * THE SAME SHAPE AS {@see AreaSectionsInterface}, and for the same reason: an
 * area's Zones row unfolds to its zones and its Modules row to its modules,
 * because the area bundle owns both. Its Departments row unfolds to
 * departments, which the area bundle may not name — so the bundle that owns
 * them contributes the rungs, and an installation without that bundle reads
 * a row that does not unfold rather than one that is missing.
 *
 * WHICH ROW, BY ROUTE NAME. A contributor names the screen its rungs hang
 * under ({@see screenRoute()}); the tree matches it against the screens the
 * area actually has, so a contribution to a screen this installation does
 * not serve simply lands nowhere.
 *
 * ONLY THE AREA BEING VIEWED DRILLS. The tree asks contributors about that
 * one area, because rows nobody can see cost a query per area on every
 * render.
 *
 * A reusable bundle is not autoconfigured, so the tag goes on by hand:
 *
 *     $services->set('team.area_nav_children', DepartmentAreaNavChildren::class)
 *         ->tag(AreaNavChildrenInterface::TAG);
 */
interface AreaNavChildrenInterface
{
    /** The tag that puts a contribution in the tree. */
    public const string TAG = 'uhifadhi.area_nav_children';

    /** The route of the area screen these rungs hang under. */
    public function screenRoute(): string;

    /**
     * The rungs, for one area, in the order they should read.
     *
     * @return list<AreaNavChild>
     */
    public function childrenFor(string $areaUuid, string $areaName): array;
}
