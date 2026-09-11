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

namespace Uhifadhi\Bundle\ShellBundle\Frame\Service;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\RouterInterface;
use Uhifadhi\Bundle\ShellBundle\Frame\Model\ConfigureAction;
use Uhifadhi\Bundle\ShellBundle\Frame\Registry\ConfigurationSectionsRegistry;
use Uhifadhi\Bundle\ShellBundle\Frame\Registry\ModuleTabsRegistry;
use Uhifadhi\Bundle\ShellBundle\Model\AreaTab;
use Uhifadhi\Bundle\ShellBundle\Service\AreaShell;
use Uhifadhi\Contracts\Shell\ConfigurationSection;
use Uhifadhi\Contracts\Shell\ConfigurationSectionsInterface;

/**
 * WHICH STRIP THIS PAGE GETS, AND WHERE ITS `Configure` GOES.
 *
 * ONE STRIP, ONE POSITION, THREE POSSIBLE CONTENTS, and the request decides
 * which without any page saying so:
 *
 *   a configure page  → the surface's configure SECTIONS (the data tabs are not
 *                       shown there; the sections stand in their place)
 *   a module's page   → that module's DATA TABS
 *   anything else     → the area's own screens, as before
 *
 * IT RECOGNISES A MODULE BY THE MARKER, NOT BY NAME. A module page is one whose
 * route carries the platform's module marker — the same route default the
 * registry's gate reads — so this class names no module and needs no list. The
 * marker's spelling arrives as a constructor argument rather than as a class
 * constant read off the registry: the shell requires no registry, and a shell
 * that imported one to learn a string would have bought a dependency for a word.
 *
 * EVERYTHING IS BUILT AS {@see AreaTab}. Tabs and sections are different
 * declarations of different things, but a strip is a strip: one value object at
 * the render boundary means one template, one component and one place where
 * "exactly one is lit" is true.
 */
final readonly class ModuleFrameService
{
    /**
     * The suffix the shell's two configure routes end in. It is what lets the
     * area's `Configure` find its way back to the area's own page without the
     * shell knowing the area's route name — the same URL-space reading the
     * area's own sources already use to recognise the module space.
     */
    private const string CONFIGURE_SUFFIX = '/configure';

    /**
     * THE PLATFORM'S MODULE MARKER — the route default a module writes on its
     * own routes, and the one reading that tells this class it is inside a
     * module. It is spelled here rather than imported because the shell requires
     * no registry: a page frame that had to be installed alongside a module
     * ledger would not be a page frame. The registry publishes the same string
     * as its own constant, and the two are frozen together by a specification.
     */
    public const string MODULE_ROUTE_ATTRIBUTE = '_uhifadhi_module';

    /** The route parameter every area-scoped address carries. */
    public const string AREA_PARAMETER = 'uuid';

    public function __construct(
        private RequestStack $requests,
        private RouterInterface $router,
        private ModuleTabsRegistry $moduleTabs,
        private ConfigurationSectionsRegistry $sections,
        private AreaShell $areaShell,
        private string $areaConfigureRoute,
        private string $moduleConfigureRoute,
        private string $moduleRouteAttribute,
        private string $areaParameter,
    ) {
    }

    /**
     * THE STRIP, as it should render — sections on a configure page, the
     * module's data places on a module page, the area's screens everywhere else.
     *
     * @return list<AreaTab>
     */
    public function tabs(): array
    {
        $request = $this->requests->getCurrentRequest();
        $surface = $this->surface($request);

        if (null === $request || null === $surface) {
            return $this->areaShell->tabs();
        }

        $route = self::routeOf($request);

        if ([] !== ($strip = $this->sectionStrip($request, $surface, $route))) {
            return $strip;
        }

        if (ConfigurationSectionsInterface::AREA === $surface) {
            return $this->areaShell->tabs();
        }

        $tabs = [];
        foreach ($this->moduleTabs->tabs($surface) as $tab) {
            $url = $this->url($tab->routeName, $this->withArea($request, $tab->parameters));
            if (null === $url) {
                continue;
            }

            $tabs[] = new AreaTab(label: $tab->label, url: $url, current: $tab->lightsFor($route));
        }

        /*
         * A MODULE ON ONE OF ITS OWN PAGES ALWAYS LIGHTS ONE TAB. If none of
         * them claims this route the module has grown a screen it never told the
         * frame about, and a strip that lights nothing reads as a row of links
         * to somewhere else. Say nothing rather than say the wrong thing — the
         * same rule the area's source keeps.
         */
        return $this->lights($tabs) ? $tabs : [];
    }

    /**
     * THE `Configure` ACTION, or null for a page whose surface has nothing to
     * configure — a module that declared no sections, and every page outside an
     * area.
     */
    public function configure(): ?ConfigureAction
    {
        $request = $this->requests->getCurrentRequest();
        $surface = $this->surface($request);

        if (null === $request || null === $surface || !$this->sections->has($surface)) {
            return null;
        }

        $back = $this->frontDoorUrl($request, $surface);
        if ($this->isConfiguring($request, $surface)) {
            return null === $back
                ? null
                : new ConfigureAction($back, current: true, hint: 'Back to the dashboard');
        }

        $url = $this->configureUrl($request, $surface);

        return null === $url ? null : new ConfigureAction($url);
    }

    /**
     * The sections of the surface the viewer is configuring, as a strip — empty
     * when the viewer is not on a configure page at all.
     *
     * @return list<AreaTab>
     */
    private function sectionStrip(Request $request, string $surface, string $route): array
    {
        if (!$this->isConfiguring($request, $surface)) {
            return [];
        }

        $onTheShellsOwnPage = \in_array($route, [$this->areaConfigureRoute, $this->moduleConfigureRoute], true);
        $here = $onTheShellsOwnPage ? $this->currentSectionId($request, $surface) : null;

        $strip = [];
        foreach ($this->sections->sections($surface) as $section) {
            [$url, $current] = $section->isRendered()
                ? [$this->renderedSectionUrl($request, $surface, $section), $section->id === $here]
                : [$this->url($section->routeName ?? '', $this->withArea($request, $section->parameters)), $section->routeName === $route];

            if (null === $url) {
                continue;
            }

            $strip[] = new AreaTab(label: $section->label, url: $url, current: $current);
        }

        return $this->lights($strip) ? $strip : [];
    }

    /**
     * A MODULE'S DATA PLACES, FOR A NAMED AREA — the same list the strip is
     * drawn from, asked for by whoever draws the sidebar's fourth level.
     *
     * It takes the area rather than reading the request because the tree lists
     * every area, not only the one the viewer is inside; only the module
     * actually being viewed has one of its places lit, and that is decided by
     * the current route as everywhere else.
     *
     * @return list<AreaTab>
     */
    public function tabsOf(string $slug, string $areaUuid): array
    {
        $route = self::routeOf($this->requests->getCurrentRequest() ?? new Request());

        $tabs = [];
        foreach ($this->moduleTabs->tabs($slug) as $tab) {
            $url = $this->url($tab->routeName, [$this->areaParameter => $areaUuid] + $tab->parameters);
            if (null === $url) {
                continue;
            }

            $tabs[] = new AreaTab(label: $tab->label, url: $url, current: $tab->lightsFor($route));
        }

        return $tabs;
    }

    /**
     * The surface's sections, in the ruled order — what the configure page has
     * to draw a strip out of, and the emptiness that says a surface has no
     * configure page at all.
     *
     * @return list<ConfigurationSection>
     */
    public function sectionsOf(string $surface): array
    {
        return $this->sections->sections($surface);
    }

    /**
     * The declaration itself, for the two things only the surface can answer —
     * what its configure page is called and what it is for.
     */
    public function declarationOf(string $surface): ?ConfigurationSectionsInterface
    {
        return $this->sections->declaration($surface);
    }

    /** Where this surface's `Configure` goes back to, for a crumb to name. */
    public function frontDoorOf(Request $request, string $surface): ?string
    {
        return $this->frontDoorUrl($request, $surface);
    }

    /**
     * WHICH SECTION THE SHELL'S OWN CONFIGURE PAGE IS SHOWING — the one named in
     * the request, or the surface's FIRST rendered section, which is Widget
     * library by the ruled order. A configure page opened with no section named
     * opens on the first thing anybody opens one for; a page that opened on the
     * last one made them hunt for it.
     */
    public function currentSectionId(Request $request, string $surface): ?string
    {
        $rendered = array_values(array_filter(
            $this->sections->sections($surface),
            static fn (ConfigurationSection $section): bool => $section->isRendered(),
        ));

        if ([] === $rendered) {
            return null;
        }

        $named = $request->attributes->get('section');
        if (\is_string($named) && '' !== $named) {
            foreach ($rendered as $section) {
                if ($section->id === $named) {
                    return $named;
                }
            }
        }

        return $rendered[0]->id;
    }

    /**
     * WHERE THE BARE CONFIGURE ADDRESS SENDS THE VIEWER, or null when it renders
     * something itself.
     *
     * The bare address belongs to the surface's FIRST section whichever shape it
     * has. A section the shell renders is drawn there; a section that keeps an
     * address of its own cannot be, so the bare address is a redirect to it —
     * one rule, two shapes, and no configure page that opens on a section the
     * surface did not put first.
     *
     * A request that NAMES a section is never redirected: the viewer asked for
     * that one.
     */
    public function bareAddressRedirect(Request $request, string $surface): ?string
    {
        $named = $request->attributes->get('section');
        if (\is_string($named) && '' !== $named) {
            return null;
        }

        $sections = $this->sections->sections($surface);
        if ([] === $sections || $sections[0]->isRendered()) {
            return null;
        }

        return $this->url($sections[0]->routeName ?? '', $this->withArea($request, $sections[0]->parameters));
    }

    /**
     * The section the shell's configure page is to render, or null when the
     * surface has nothing for the shell to draw.
     */
    public function currentSection(Request $request, string $surface): ?ConfigurationSection
    {
        $id = $this->currentSectionId($request, $surface);
        foreach ($this->sections->sections($surface) as $section) {
            if ($section->id === $id) {
                return $section;
            }
        }

        return null;
    }

    /**
     * WHOSE FRAME THIS REQUEST IS INSIDE: a module's slug, the reserved area
     * slug, or null for a page that is inside neither.
     */
    public function surface(?Request $request): ?string
    {
        if (null === $request) {
            return null;
        }

        $route = self::routeOf($request);

        if ($route === $this->areaConfigureRoute) {
            return ConfigurationSectionsInterface::AREA;
        }

        if ($route === $this->moduleConfigureRoute) {
            $slug = $request->attributes->get('slug');

            return \is_string($slug) && '' !== $slug ? $slug : null;
        }

        $declared = $request->attributes->get($this->moduleRouteAttribute);
        if (\is_string($declared) && '' !== $declared) {
            return $declared;
        }

        // Not a module page. It is the AREA's frame when the request names an
        // area at all — which is the same reading the area's own source makes.
        return \is_string($request->attributes->get($this->areaParameter)) ? ConfigurationSectionsInterface::AREA : null;
    }

    /**
     * IS THE VIEWER CONFIGURING THIS SURFACE? True on the shell's own configure
     * page, and on a section that keeps an address of its own — that screen is
     * part of the configure page even though it is served elsewhere, so it wears
     * the same strip and the same lit `Configure`.
     */
    private function isConfiguring(Request $request, string $surface): bool
    {
        $route = self::routeOf($request);

        if (\in_array($route, [$this->areaConfigureRoute, $this->moduleConfigureRoute], true)) {
            return true;
        }

        foreach ($this->sections->sections($surface) as $section) {
            if (!$section->isRendered() && $section->routeName === $route) {
                return true;
            }
        }

        return false;
    }

    /**
     * The configure page's address — bare for the surface's first section, and
     * with the section appended for every other section the shell renders. The
     * route carries the section as an optional trailing parameter, so the bare
     * form is what the router generates when none is named.
     */
    private function configureUrl(Request $request, string $surface, ?string $section = null): ?string
    {
        $area = $request->attributes->get($this->areaParameter);
        if (!\is_string($area) || '' === $area) {
            return null;
        }

        $parameters = [$this->areaParameter => $area];
        if (null !== $section) {
            $parameters['section'] = $section;
        }

        return ConfigurationSectionsInterface::AREA === $surface
            ? $this->url($this->areaConfigureRoute, $parameters)
            : $this->url($this->moduleConfigureRoute, $parameters + ['slug' => $surface]);
    }

    /**
     * The address of a section the shell renders. The surface's FIRST section —
     * Widget library, by the ruled order — is the configure page itself, so it
     * keeps the bare address the `Configure` action opens; every other section
     * hangs one segment below it.
     *
     * The first SECTION, not the first rendered one: when a surface leads with a
     * screen of its own the bare address is that screen's redirect
     * ({@see bareAddressRedirect()}) and belongs to no section the shell draws.
     */
    private function renderedSectionUrl(Request $request, string $surface, ConfigurationSection $section): ?string
    {
        return $this->configureUrl(
            $request,
            $surface,
            $section->id === $this->firstSectionId($surface) ? null : $section->id,
        );
    }

    private function firstSectionId(string $surface): ?string
    {
        return $this->sections->sections($surface)[0]->id ?? null;
    }

    /**
     * WHERE `Configure` GOES BACK TO — the surface's front door.
     *
     * A module's is its FIRST declared tab, because a module orders its own data
     * places and the first of them is the one it opens on. The area's is the
     * page its configure route sits under, read off the URL space rather than
     * off a route name the shell would otherwise have to be told.
     */
    private function frontDoorUrl(Request $request, string $surface): ?string
    {
        if (ConfigurationSectionsInterface::AREA !== $surface) {
            $tabs = $this->moduleTabs->tabs($surface);

            return [] === $tabs ? null : $this->url($tabs[0]->routeName, $this->withArea($request, $tabs[0]->parameters));
        }

        $configure = $this->configureUrl($request, $surface);
        if (null === $configure || !str_ends_with($configure, self::CONFIGURE_SUFFIX)) {
            return null;
        }

        return substr($configure, 0, -\strlen(self::CONFIGURE_SUFFIX));
    }

    /**
     * The declared parameters, with the area of the request the viewer is in.
     * A declaration's own parameters win, so a module that scopes a screen
     * differently still can.
     *
     * @param array<string, scalar> $parameters
     *
     * @return array<string, scalar>
     */
    private function withArea(Request $request, array $parameters): array
    {
        $area = $request->attributes->get($this->areaParameter);

        return \is_string($area) && '' !== $area
            ? [$this->areaParameter => $area] + $parameters
            : $parameters;
    }

    /**
     * A URL FOR A ROUTE THAT MAY NOT BE MOUNTED, and for parameters that may not
     * fit it. The addresses belong to the application, and a strip that took the
     * page down because one screen was unmounted would be the worst possible way
     * to learn it. No route, no entry.
     *
     * @param array<string, scalar> $parameters
     */
    private function url(string $name, array $parameters): ?string
    {
        if ('' === $name || null === $this->router->getRouteCollection()->get($name)) {
            return null;
        }

        try {
            return $this->router->generate($name, $parameters);
        } catch (\Throwable) {
            return null;
        }
    }

    /** @param list<AreaTab> $tabs */
    private function lights(array $tabs): bool
    {
        foreach ($tabs as $tab) {
            if ($tab->current) {
                return true;
            }
        }

        return false;
    }

    private static function routeOf(Request $request): string
    {
        $route = $request->attributes->get('_route');

        return \is_string($route) ? $route : '';
    }
}
