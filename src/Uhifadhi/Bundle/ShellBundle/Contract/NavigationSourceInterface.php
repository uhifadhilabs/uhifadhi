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

namespace Uhifadhi\Bundle\ShellBundle\Contract;

use Uhifadhi\Bundle\ShellBundle\Model\NavSection;
use Uhifadhi\Bundle\ShellBundle\ShellBundle;
use Uhifadhi\Contracts\Shell\NavGroup;

/**
 * WHERE THE SIDEBAR'S CONTENT COMES FROM.
 *
 * The shell owns the nav's SHAPE — sections, rows, the location tree, carets,
 * the current-row treatment, the collapsed rail — and none of its CONTENT.
 * Content arrives here, from whoever knows something worth putting there:
 *
 *   - the HOST implements one, and that is where domain data enters the shell.
 *     It has the areas, the viewer, the permission voters and the module contract's
 *     per-area ledger; folding those four into "these rows, in this order" is a
 *     reading for a person on a page, and it is the host's job.
 *   - a MODULE BUNDLE may implement one too, for the rare platform-wide row
 *     that belongs to nobody's area.
 *
 * A SECTION LABEL NAMES A PLACE, NOT YOUR SECTION, and there are FOUR places:
 * {@see NavGroup::OBSERVATORY}, {@see NavGroup::ORGANIZATION},
 * {@see NavGroup::SYSTEM}, {@see NavGroup::SETTINGS} — what the organisation
 * watches, what it is and holds, what the system raises to you, and
 * configuration last. Join one BY CONSTANT; anything else is refused with the
 * four named, because a near-miss label used to grow a heading nobody designed.
 *
 * Sources that name the same group are contributing to one heading, and the
 * shell draws it once with every contributed row under it, in the contract's
 * group order, the rows ordered by the position each contribution declared.
 * So file a row under the heading the design draws it under — the collision IS
 * the grouping.
 *
 * GATING IS YOURS, NOT THE SHELL'S. The shell holds no authorization service
 * and asks nothing about the viewer. A row the viewer may not have is simply
 * not in what you return — there is no "hidden" flag, because a hidden row is a
 * row that leaks its existence to whoever reads the HTML.
 *
 * READ LIVE, NEVER CACHED. This is called on every render, so switching a
 * module off takes its row with it the same day rather than after a deploy.
 * Build the answer in the method; do not build it in the constructor.
 *
 * Tag the service {@see ShellBundle::NAV_TAG} — by hand, in your own
 * extension, because a reusable bundle's services are not autoconfigured:
 *
 *     $services->set(App\Shell\HostNavigation::class)->tag('shell.nav_section');
 */
interface NavigationSourceInterface
{
    /**
     * @return iterable<NavSection>
     */
    public function sections(): iterable;
}
