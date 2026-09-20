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

use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Exception\RouteNotFoundException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Uhifadhi\Bundle\ShellBundle\Contract\NavigationSourceInterface;
use Uhifadhi\Bundle\ShellBundle\Model\NavItem;
use Uhifadhi\Bundle\ShellBundle\Model\NavSection;
use Uhifadhi\Bundle\ShellBundle\ShellBundle;
use Uhifadhi\Contracts\Shell\NavGroup;
use Uhifadhi\Contracts\Shell\OrgPagesInterface;

/**
 * A MODULE THAT ANSWERS AT ORGANISATION LEVEL GETS A ROW IN OBSERVATORY.
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
 * the organisation's own performance, then each module's organisation
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
     * @param iterable<OrgPagesInterface> $modules every provider tagged {@see ShellBundle::ORG_PAGES_TAG}
     */
    public function __construct(
        private iterable $modules,
        private UrlGeneratorInterface $urls,
        private RequestStack $requests,
    ) {
    }

    public function sections(): iterable
    {
        $rows = [];
        foreach ($this->modules as $module) {
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
     * and `screens: true` says there is no place rung between them.
     */
    private function rowFor(OrgPagesInterface $module): ?NavItem
    {
        $pages = $module->orgPages();
        if ([] === $pages) {
            return null;
        }

        $screens = [];
        foreach ($pages as $page) {
            $url = $this->urlOf($page->route);
            if (null === $url) {
                continue;
            }

            $screens[] = new NavItem(label: $page->label, url: $url, current: $page->route === $this->routeHere());
        }

        if ([] === $screens) {
            return null;
        }

        return new NavItem(
            label: $this->nameOf($module),
            url: $screens[0]->url,
            icon: $this->iconOf($module),
            // MARKED ANYWHERE INSIDE THE MODULE'S SET, and the child says
            // which screen: that is one path, and the shell turns it into
            // one ground and a line of ink.
            current: [] !== array_filter($screens, static fn (NavItem $one): bool => $one->current),
            children: $screens,
            screens: true,
        );
    }

    /**
     * WHAT THE ROW SAYS. A module that also declares itself to the registry
     * has a name already, and using it keeps the sidebar and the module grid
     * saying the same word; one that does not falls back to its first page's
     * own label rather than to a slug nobody writes on a screen.
     *
     * READ OFF THE INTERFACE IT HAPPENS TO IMPLEMENT, never off a list of
     * module names — the shell recognises no module.
     */
    private function nameOf(OrgPagesInterface $module): string
    {
        $named = method_exists($module, 'name') ? $module->name() : null;

        return \is_string($named) && '' !== $named ? $named : $module->orgPages()[0]->label;
    }

    /** Its own glyph where it has one, and the shell's default where it has none. */
    private function iconOf(OrgPagesInterface $module): string
    {
        $icon = method_exists($module, 'icon') ? $module->icon() : null;

        return \is_string($icon) && '' !== $icon ? $icon : 'shell:layout-grid';
    }

    /** The address, or null where the application has not mounted that route. */
    private function urlOf(string $route): ?string
    {
        try {
            return $this->urls->generate($route);
        } catch (RouteNotFoundException) {
            return null;
        }
    }

    /** The route the request matched, or '' outside a request. */
    private function routeHere(): string
    {
        $route = $this->requests->getCurrentRequest()?->attributes->get('_route');

        return \is_string($route) ? $route : '';
    }
}
