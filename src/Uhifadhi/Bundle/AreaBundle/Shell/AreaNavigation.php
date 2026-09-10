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

namespace Uhifadhi\Bundle\AreaBundle\Shell;

use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Exception\RouteNotFoundException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Uhifadhi\Bundle\AreaBundle\Entity\AreaOfInterest;
use Uhifadhi\Bundle\AreaBundle\Repository\AreaOfInterestRepository;
use Uhifadhi\Bundle\AreaBundle\Service\AreaComposition;
use Uhifadhi\Bundle\ShellBundle\Contract\NavigationSourceInterface;
use Uhifadhi\Bundle\ShellBundle\Frame\Service\ModuleFrameService;
use Uhifadhi\Bundle\ShellBundle\Model\AreaTab;
use Uhifadhi\Bundle\ShellBundle\Model\NavItem;
use Uhifadhi\Bundle\ShellBundle\Model\NavSection;

/**
 * THE AREAS SECTION OF THE SIDEBAR — the register, and under it every area with
 * its own screens.
 *
 * This is the LOCATION tree the shell's nav is shaped around: an area is the
 * axis the whole product is filed under, so it is the one thing in the sidebar
 * that unfolds. Each area's children are {@see AreaShellSource::screensOf()} —
 * the SAME list the tab strip is drawn from, which is why a screen cannot appear
 * in one and not the other.
 *
 * GATING IS THIS CLASS'S JOB, not the shell's — the shell holds no authorization
 * service and asks nothing about the viewer. A viewer without `area.view` gets
 * no section at all, rather than a section whose rows all close in their face.
 *
 * ROUTE-TOLERANT. The addresses are mounted by the APPLICATION, so generating
 * one can fail, and a sidebar that took every page down because somebody
 * unmounted a route would be the worst possible way to learn it. No route, no
 * row.
 *
 * BUILT PER CALL, NEVER CACHED, and nothing is done in the constructor: the
 * shell reads its sources live on every render precisely so an area created this
 * morning is in the sidebar this morning.
 */
final readonly class AreaNavigation implements NavigationSourceInterface
{
    /** The heading the rows file under, as the design draws it. */
    public const string SECTION = 'Observatory';

    /** Above the organisation rows TeamBundle contributes at 20. */
    public const int POSITION = 10;

    /** The register: this bundle's front door, and the section's own row. */
    public const string ROUTE = 'area_index';

    public function __construct(
        private UrlGeneratorInterface $urls,
        private TokenStorageInterface $tokens,
        private AuthorizationCheckerInterface $authorization,
        private RequestStack $requests,
        private AreaOfInterestRepository $areas,
        private AreaShellSource $screens,
        private AreaComposition $composition,
        private ModuleFrameService $frame,
    ) {
    }

    public function sections(): iterable
    {
        /*
         * NO TOKEN, NO QUESTION. A page can render outside any firewall — an
         * error page, a console-rendered template — and asking the authorization
         * checker there throws rather than answering false. A viewer nobody can
         * identify holds nothing, which is the same answer without the 500.
         */
        if (null === $this->tokens->getToken()) {
            return;
        }

        if (!$this->authorization->isGranted('area.view')) {
            return;
        }

        try {
            $register = $this->urls->generate(self::ROUTE);
        } catch (RouteNotFoundException) {
            return;
        }

        $rows = [];
        foreach ($this->areas->findBy([], ['name' => 'ASC']) as $area) {
            $url = $this->screenUrl($area->getUuidString());
            if (null === $url) {
                continue;
            }

            /*
             * THE AREA'S OWN SCREENS, UNFOLDED — and read from the same method
             * the tab strip reads, so the branch and the strip cannot disagree.
             * Another area's zones are reachable without visiting that area
             * first, which is the whole reason the tree unfolds at all.
             *
             * AND THE MODULES SCREEN UNFOLDS ONE LEVEL FURTHER, but only for the
             * area actually being viewed: its own attached modules become the
             * rows beneath it, so from a module page the sidebar shows exactly
             * where you are — Northern Reserve › Modules › the module you are in, lit.
             * Drilling every area would mean a module query per area on every
             * render for rows that are folded away anyway, so the deeper branch
             * is built only where it can be seen.
             */
            $hereArea = $this->viewerIsHere($url);
            $children = [];
            foreach ($this->screens->screensOf($area) as $tab) {
                $children[] = $hereArea && $this->isModulesScreen($tab->url)
                    ? $this->modulesNode($area, $tab)
                    : new NavItem(label: $tab->label, url: $tab->url, current: $tab->current);
            }

            $rows[] = new NavItem(
                label: (string) $area->getName(),
                url: $url,
                icon: 'shell:map',
                current: $hereArea && [] === array_filter(
                    $children,
                    static fn (NavItem $c): bool => $c->current,
                ),
                // Unfolded only for the area being viewed: an installation with
                // eight areas would otherwise open with forty rows.
                open: $hereArea,
                children: $children,
            );
        }

        yield new NavSection(self::SECTION, [
            new NavItem(
                label: 'Areas',
                url: $register,
                icon: 'shell:layers',
                current: $this->viewerIsExactly($register),
                children: $rows,
            ),
        ], position: self::POSITION);
    }

    /**
     * THE "MODULES" SCREEN, WITH THE AREA'S OWN MODULES HANGING FROM IT.
     *
     * The parent yields the light to the module leaf you are actually on: on a
     * module's page the leaf is lit and "Modules" is only the open branch above
     * it, exactly as the design draws it (the tab is `par`, the module is `on`).
     * On the modules index itself no leaf is lit, so the tab keeps its own
     * current. Either way the branch is unfolded while you are inside the modules
     * space and folded — but still in the document — while you are not.
     *
     * The module row keeps `par` treatment while one of its own screens is lit,
     * exactly as the design draws it: the module is the legible ancestor and the
     * screen under it carries the accent.
     *
     * Each module row draws the shell's identity dot, jade by default. Carrying a
     * PER-MODULE hue waits on the shell publishing a NavItem tone passthrough (it
     * has the field on main but not in a release yet); when it ships, this hands
     * `$link->slug` through and a module colours its own `.mdot.<slug>`.
     */
    private function modulesNode(AreaOfInterest $area, AreaTab $tab): NavItem
    {
        $modules = [];
        foreach ($this->composition->moduleLinksFor($area) as $link) {
            /*
             * AND THE MODULE UNFOLDS TO ITS OWN DATA PLACES — the sidebar's
             * fourth level, and the SAME list the strip under the module's head
             * is drawn from. A module declares its tabs once; the branch and the
             * strip cannot disagree, because there is only one declaration.
             *
             * Only the module being viewed unfolds: drilling every module of
             * every area would build rows that are folded away anyway, and the
             * point of the level is to show where you are, not what exists.
             */
            $here = null !== $link->url && $this->viewerIsHere($link->url);

            /*
             * A MODULE'S CONFIGURE PAGE IS INSIDE THE MODULE, so the module row
             * stays the OPEN ANCESTOR with its data places under it — and takes
             * no accent, because a configure page is none of those places and
             * the accent is what says "this is the row you are on".
             */
            $configuring = $here && $this->screens->isConfiguring($this->here() ?? '/');

            $screens = [];
            foreach ($here ? $this->frame->tabsOf($link->slug, (string) $area->getUuidString()) : [] as $screen) {
                $screens[] = new NavItem(label: $screen->label, url: $screen->url, current: $screen->current);
            }

            $leafLit = [] !== array_filter($screens, static fn (NavItem $s): bool => $s->current);

            $modules[] = new NavItem(
                label: $link->title,
                url: $link->url,
                current: $here && !$leafLit && !$configuring,
                open: $here,
                children: $screens,
            );
        }

        $leafLit = [] !== array_filter($modules, static fn (NavItem $m): bool => $m->current);

        return new NavItem(
            label: $tab->label,
            url: $tab->url,
            current: $tab->current && !$leafLit,
            open: $tab->current || $leafLit,
            children: $modules,
        );
    }

    /**
     * The modules screen, recognised by the URL SPACE it owns — every area-scoped
     * module page lives under `/areas/{uuid}/modules`, the one contract they all
     * share — rather than by a route name this class would have to keep in step.
     * The same test {@see AreaShellSource::whereWeAre} lights the tab from.
     */
    private function isModulesScreen(?string $url): bool
    {
        if (null === $url) {
            return false;
        }

        $path = parse_url($url, \PHP_URL_PATH);

        return \is_string($path) && str_ends_with(rtrim($path, '/'), '/modules');
    }

    private function screenUrl(?string $uuid): ?string
    {
        if (null === $uuid) {
            return null;
        }

        try {
            return $this->urls->generate('area_show', ['uuid' => $uuid]);
        } catch (RouteNotFoundException) {
            return null;
        }
    }

    /**
     * WHETHER THE VIEWER IS ON THIS ROW'S SCREEN, or on one underneath it.
     *
     * Compared as PATHS rather than route names, because the addresses belong to
     * the application: it may mount this bundle under a prefix, and a list of
     * route names typed out here would go stale the first time a screen was
     * added.
     */
    private function viewerIsHere(string $url): bool
    {
        $here = $this->here();

        return null !== $here && ($here === $url || str_starts_with($here, rtrim($url, '/').'/'));
    }

    /** The register lights only on the register itself — never on an area beneath it. */
    private function viewerIsExactly(string $url): bool
    {
        return $this->here() === $url;
    }

    private function here(): ?string
    {
        $request = $this->requests->getCurrentRequest();

        return null === $request ? null : $request->getBaseUrl().$request->getPathInfo();
    }
}
