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

namespace Uhifadhi\Bundle\AreaBundle\Overview;

use Uhifadhi\Bundle\ShellBundle\Widget\Model\Widget;
use Uhifadhi\Bundle\ShellBundle\Widget\Model\WidgetGroup;
use Uhifadhi\Contracts\Shell\Scope;

/**
 * THE CONTRACT A MODULE PUTS CELLS ON THE ORGANISATION DASHBOARD THROUGH.
 *
 * `/` IS THE AREA OVERVIEW ONE SCOPE WIDER, and this interface is the area
 * one with the area taken out: same groups-are-contributors rule, same
 * partial-per-contributor rule, same open/closed promise. A module that has
 * learnt {@see OverviewContributorInterface} has learnt this.
 *
 * WHY IT IS A SECOND INTERFACE AND NOT A WIDER FIRST ONE. The area contract
 * is answered by every installed module against an AREA ENTITY, and widening
 * its signature would break every module that implements it — for a screen
 * most of them have no org-level reading for. A module opts in here
 * deliberately, and one that has nothing to say across areas says nothing.
 *
 * EVERY FIGURE IS THE MODULE'S OWN PER-AREA READING ONE SCOPE WIDER. The
 * {@see Scope} is handed in for exactly that: the module answers
 * `forScope($scope)` and the organisation's answer is the areas' answers. A
 * module that grew a second aggregate for this would have two numbers for one
 * question and no way to say which was right — which is the rule the core
 * holds itself to in `PresenceService::forScope()`.
 *
 * A GROUP HERE IS A CONTRIBUTOR, NOT A DESIGN DIRECTION, for the reason it is
 * on the area overview: a person needs to know that "Patrols out right now"
 * came from the patrols module, so that the day it is uninstalled its
 * disappearance reads as the system working rather than as a bug. The five
 * directions the surface was drawn in are PRESETS.
 *
 * TAGGED EXPLICITLY AT BOTH ENDS. A module bundle tags its contributor in its
 * own extension, because a reusable bundle is not autoconfigured; an
 * application service carries `#[AutoconfigureTag(self::TAG)]` on its own
 * class, because PHP does not inherit attributes from an interface and one
 * written here would be silently dead.
 */
interface OrgOverviewContributorInterface
{
    public const string TAG = 'uhifadhi.overview.org_widget_provider';

    /**
     * The slug of the module these cells belong to — the same slug its
     * ModuleProviderInterface declares, and what the cell's contributor tag
     * prints so a reader knows whose figure they are looking at.
     */
    public function moduleSlug(): string;

    /** The library's headed section for this contributor, and what it puts here. */
    public function group(): WidgetGroup;

    /**
     * The cells it contributes, in the order the library lists them.
     *
     * @return list<Widget>
     */
    public function widgets(): array;

    /**
     * A sprintf pattern naming the Twig partial for one cell id, e.g.
     * `'@Patrol/org/_w_%s.html.twig'` — a pattern per CONTRIBUTOR, because
     * each cell is drawn from its own bundle's namespace and the dashboard's
     * own template contains no widget markup at all.
     */
    public function partialPattern(): string;

    /**
     * THE FOUR-TO-A-ROW FIGURES THIS CONTRIBUTOR PUBLISHES, if any.
     *
     * The strip at the top of the dashboard is not a cell anybody owns: it is
     * assembled, one tile per contributor, the way the area overview's
     * right-now strip is. A contributor with no headline figure returns
     * nothing and takes no slot.
     *
     * THE SAME VALUE OBJECT THE AREA STRIP USES, deliberately: a module that
     * publishes "Patrols out · 3" in an area publishes the same shape across
     * the organisation, and a reader meets one kind of tile in both places.
     *
     * @return list<NowTile>
     */
    public function figures(Scope $scope, \DateTimeImmutable $now): array;

    /**
     * Everything this contributor's own partials read, at this scope.
     *
     * Returned as one array rather than resolved per cell because a module's
     * cells share their reading of the day: computing it once is the
     * difference between one query and nine, and between two cards that agree
     * and two measured a second apart.
     *
     * @return array<string, mixed>
     */
    public function context(Scope $scope, \DateTimeImmutable $now): array;
}
