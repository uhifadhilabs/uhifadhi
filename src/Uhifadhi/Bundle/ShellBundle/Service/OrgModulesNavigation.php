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

namespace Uhifadhi\Bundle\ShellBundle\Service;

use Uhifadhi\Bundle\ShellBundle\Contract\NavigationSourceInterface;
use Uhifadhi\Bundle\ShellBundle\Model\NavItem;
use Uhifadhi\Bundle\ShellBundle\Model\NavSection;
use Uhifadhi\Contracts\Shell\NavGroup;
use Uhifadhi\Contracts\Shell\OrgPagesInterface;

/**
 * A MODULE THAT ANSWERS AT ORGANIZATION LEVEL GETS A ROW IN OBSERVATORY.
 *
 * ONE MORE PROVIDER, NOT A NEW SECTION. A module contributes its org-level
 * page set through {@see OrgPagesInterface} and the shell mounts it: the
 * module writes no sidebar item, no tab strip and no scope control, and the
 * host writes no module code. Adding patrols and incidents beside the roster
 * is two more tagged providers.
 *
 * THE SHELL COLLECTS THIS ITSELF, and names no module doing it. It is the
 * same shape as every other seam here — a tag, an interface from the
 * contracts package, and whatever answers is drawn — so the shell does not
 * learn about the registry and the registry does not learn about layout.
 * A module bundle that is not installed is not in the container, which is
 * what "installed" means here.
 *
 * AFTER PERFORMANCE, IN MODULE ORDER. Observatory reads outward: the areas,
 * the organization's own performance, then each module's organization
 * reading, in the order the modules declare — which is the order they are
 * drawn in everywhere else.
 *
 * A ROUTE THE APPLICATION HAS NOT MOUNTED IS SKIPPED, not drawn inert. The
 * addresses belong to the application, and a row pointing at a route nobody
 * mounted is a link to a 404 — unlike a planned surface, which is a promise
 * the product is making on purpose.
 */
final readonly class OrgModulesNavigation implements NavigationSourceInterface
{
    /** The same heading the areas and the performance section file under. */
    public const string SECTION = NavGroup::OBSERVATORY;

    /** After the areas (10) and Performance (15). */
    public const int POSITION = 20;

    /**
     * ONE READER FOR ONE LIST. The rows here and the tab strip on the page
     * are the same `orgPages()` declaration filtered the same way — see
     * {@see OrgShell} — so a tab and a sidebar row cannot disagree about
     * which screens a module has.
     */
    public function __construct(private OrgShell $orgShell)
    {
    }

    public function sections(): iterable
    {
        $rows = [];
        foreach ($this->orgShell->modules() as $module) {
            $row = $this->rowFor($module);
            if (null !== $row) {
                $rows[] = $row;
            }
        }

        if ([] === $rows) {
            return;
        }

        yield new NavSection(self::SECTION, $rows, position: self::POSITION);
    }

    /**
     * One module's row and its screens — the same shape a section wears
     * everywhere: the row is the module, its children are its own screens,
     * and `screens: true` says there is no place rung between them. A module
     * with nothing mounted gets no row rather than an empty one.
     */
    private function rowFor(OrgPagesInterface $module): ?NavItem
    {
        $screens = [];
        foreach ($this->orgShell->screensOf($module) as $screen) {
            $screens[] = new NavItem(label: $screen->page->label, url: $screen->url, current: $screen->current);
        }

        if ([] === $screens) {
            return null;
        }

        return new NavItem(
            label: $this->orgShell->nameOf($module),
            url: $screens[0]->url,
            icon: $this->orgShell->iconOf($module),
            // MARKED ANYWHERE INSIDE THE MODULE'S SET, and the child says
            // which screen: that is one path, and the shell turns it into
            // one ground and a line of ink.
            current: [] !== array_filter($screens, static fn (NavItem $one): bool => $one->current),
            children: $screens,
            screens: true,
        );
    }
}
