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
use Uhifadhi\Bundle\ShellBundle\Model\AreaTab;
use Uhifadhi\Bundle\ShellBundle\Model\OrgFrame;
use Uhifadhi\Bundle\ShellBundle\Model\OrgScreen;
use Uhifadhi\Bundle\ShellBundle\ShellBundle;
use Uhifadhi\Contracts\Shell\OrgPagesInterface;

/**
 * THE ORGANIZATION-LEVEL PAGE SETS, READ — for the sidebar that mounts them
 * and for the frame that draws around them.
 *
 * ONE READER, BECAUSE IT IS ONE LIST. The sidebar's screens and the page's
 * tab strip are the same `orgPages()` declaration filtered the same way, and
 * two readers of it would eventually disagree about which screens a module
 * has — which is the drift the ruling is about: the strip is the shell's,
 * like the sidebar row.
 *
 * A ROUTE THE APPLICATION HAS NOT MOUNTED IS SKIPPED, not drawn inert. The
 * addresses belong to the application, and a tab pointing at a route nobody
 * mounted is a link to a 404.
 *
 * THE SHELL RECOGNISES NO MODULE. What it has is an interface from the
 * contracts package and a tag; a module's own name and glyph are read off
 * the object it happens to be, never off a list of module names.
 */
final readonly class OrgShell
{
    /**
     * @param iterable<OrgPagesInterface> $modules every provider tagged {@see ShellBundle::ORG_PAGES_TAG}
     */
    public function __construct(
        private iterable $modules,
        private UrlGeneratorInterface $urls,
        private RequestStack $requests,
        private Scopes $scopes,
    ) {
    }

    /**
     * Every contributing module, in registration order — which is the order
     * they are drawn in everywhere else.
     *
     * @return list<OrgPagesInterface>
     */
    public function modules(): array
    {
        $modules = [];
        foreach ($this->modules as $module) {
            $modules[] = $module;
        }

        return $modules;
    }

    /**
     * One module's screens, mounted ones only, with the one the viewer is on
     * marked.
     *
     * @return list<OrgScreen>
     */
    public function screensOf(OrgPagesInterface $module): array
    {
        $here = $this->routeHere();

        $screens = [];
        foreach ($module->orgPages() as $page) {
            $url = $this->urlOf($page->route);
            if (null !== $url) {
                $screens[] = new OrgScreen($page, $url, $page->route === $here);
            }
        }

        return $screens;
    }

    /**
     * WHOSE PAGE SET THE REQUEST IS INSIDE, or null where it is inside
     * nobody's — a page reached at an address its module never declared, or
     * any other page in the product.
     */
    public function here(): ?OrgPagesInterface
    {
        foreach ($this->modules() as $module) {
            foreach ($this->screensOf($module) as $screen) {
                if ($screen->current) {
                    return $module;
                }
            }
        }

        return null;
    }

    /**
     * EVERYTHING THE ORG BASE DRAWS, or null where this request is in no
     * module's set and there is therefore nothing to draw around.
     */
    public function frame(): ?OrgFrame
    {
        $module = $this->here();
        if (null === $module) {
            return null;
        }

        $screens = $this->screensOf($module);
        $slice = $this->sliceNamed();

        $tabs = [];
        $page = null;
        foreach ($screens as $screen) {
            $tabs[] = new AreaTab($screen->page->label, $this->at($screen->url, $slice), $screen->current);
            if ($screen->current) {
                $page = $screen->page;
            }
        }

        return new OrgFrame(
            $this->nameOf($module),
            $page,
            // ONE TAB IS NOT A CHOICE — the rule the area strip already
            // keeps. A lone underlined word is furniture pretending to be
            // navigation.
            \count($tabs) < 2 ? [] : $tabs,
            $this->scopes->available(),
            $this->scopes->current(),
        );
    }

    /**
     * WHAT THE MODULE CALLS ITSELF. A module that also declares itself to the
     * registry has a name already, and using it keeps the sidebar, the module
     * grid and this page head saying the same word; one that does not falls
     * back to its first page's own label rather than to a slug nobody writes
     * on a screen.
     */
    public function nameOf(OrgPagesInterface $module): string
    {
        $named = method_exists($module, 'name') ? $module->name() : null;

        if (\is_string($named) && '' !== $named) {
            return $named;
        }

        $pages = $module->orgPages();

        return [] === $pages ? '' : $pages[0]->label;
    }

    /** Its own glyph where it has one, and the shell's default where it has none. */
    public function iconOf(OrgPagesInterface $module): string
    {
        $icon = method_exists($module, 'icon') ? $module->icon() : null;

        return \is_string($icon) && '' !== $icon ? $icon : 'shell:layout-grid';
    }

    /**
     * THE SLICE SURVIVES A TAB. Moving from Overview to Today while reading
     * one area keeps reading that area: the strip carries the address's own
     * `?area=`, because a control that silently reset every time you changed
     * screen would be a control nobody could trust. Only what the address
     * actually names is carried — a default slice is the default everywhere,
     * and pinning it into every link would make every link stale the day the
     * default moved.
     */
    private function at(string $url, ?string $slice): string
    {
        if (null === $slice) {
            return $url;
        }

        return $url.(str_contains($url, '?') ? '&' : '?').http_build_query([Scopes::PARAMETER => $slice]);
    }

    /** The slice the address names, or null where it names none. */
    private function sliceNamed(): ?string
    {
        $named = $this->requests->getCurrentRequest()?->query->get(Scopes::PARAMETER);

        return \is_string($named) && '' !== $named ? $named : null;
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
