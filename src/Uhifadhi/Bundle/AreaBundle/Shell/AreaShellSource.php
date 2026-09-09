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

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Uid\Uuid;
use Uhifadhi\Bundle\AreaBundle\Entity\AreaOfInterest;
use Uhifadhi\Bundle\AreaBundle\Repository\AreaOfInterestRepository;
use Uhifadhi\Bundle\ShellBundle\Contract\AreaShellSourceInterface;
use Uhifadhi\Bundle\ShellBundle\Model\AreaTab;

/**
 * WHICH AREA THE VIEWER IS IN, AND WHICH OF ITS SCREENS THEY MAY REACH.
 *
 * THE ONE PLACE THE ANSWER IS DECIDED. The tab list is read twice — as the strip
 * above the page, and as the branch under an area in the sidebar — and both
 * readings come from {@see self::screensOf()}, so they cannot disagree the way
 * two hand-kept copies eventually would.
 *
 * WHAT THIS DECIDES AND THE SHELL DOES NOT: which screens an area has, and which
 * of them this viewer may reach. Both are this module's model of an area, and
 * neither is a layout's business. A tab the viewer may not have is simply absent
 * from what this returns — never a greyed-out word, because a disabled
 * "Settings" tells a ranger that a settings screen exists and they are not
 * trusted with it.
 *
 * ROUTE-TOLERANT, and that is what lets ONE implementation cover screens this
 * module does not ship. The module grid is the seam's page, not this module's:
 * where an installation mounts it the tab appears, and where it does not the
 * strip is simply shorter. The alternative — every bundle claiming to know where
 * you are — is the disagreement the shell's alias exists to prevent.
 */
final class AreaShellSource implements AreaShellSourceInterface
{
    /**
     * The area's screens in the order the design draws them, each with the route
     * that serves it and the permission that grants it.
     *
     * ZONES CARRIES NO GATE OF ITS OWN, exactly like the overview: a zone is a
     * lens, and a lens nobody may look through explains nothing. Reading the
     * zoning scheme is for anyone who can reach the area; the writes are gated
     * inside the page.
     */
    private const array SCREENS = [
        ['Overview', 'area_show', null],
        ['Modules', 'seam_area_modules', 'module.view'],
        ['Zones', 'area_zones', null],
        ['Settings', 'area_settings', 'area.edit'],
    ];

    public function __construct(
        private readonly RequestStack $requestStack,
        private readonly RouterInterface $router,
        private readonly AreaOfInterestRepository $areas,
        private readonly AuthorizationCheckerInterface $authorization,
    ) {
    }

    /** @return list<AreaTab> */
    public function tabs(): array
    {
        $area = $this->currentArea($this->requestStack->getCurrentRequest());

        return null === $area ? [] : $this->screensOf($area);
    }

    /**
     * The screens of ANY area, whether or not the viewer is in it — because the
     * sidebar lists every area and each one unfolds to its own screens, so
     * another area's zones are reachable without visiting the area first. Only
     * the area actually being viewed has one of them lit.
     *
     * @return list<AreaTab>
     */
    public function screensOf(AreaOfInterest $area): array
    {
        $request = $this->requestStack->getCurrentRequest();
        $route = $request?->attributes->get('_route');
        $uuid = ['uuid' => $area->getUuidString()];

        $current = $this->currentArea($request);
        $inThisArea = null !== $current && $current->getId() === $area->getId();

        $here = $inThisArea ? $this->whereWeAre(\is_string($route) ? $route : '', $request?->getPathInfo() ?? '/') : null;
        if ($inThisArea && null === $here) {
            /*
             * WE DO NOT KNOW WHERE WE ARE, SO WE DO NOT CLAIM TO. A strip whose
             * job is to say which of these screens you are on, and which cannot
             * say it, is worse than no strip: it would light nothing and read as
             * links to somewhere else.
             */
            return [];
        }

        $tabs = [];
        foreach (self::SCREENS as [$label, $routeName, $permission]) {
            if (null !== $permission && !$this->authorization->isGranted($permission)) {
                continue;
            }
            $url = $this->url($routeName, $uuid);
            if (null === $url) {
                continue;
            }

            $tabs[] = new AreaTab(label: $label, url: $url, current: $label === $here);
        }

        // The screen we are on turned out to be one this viewer may not reach —
        // possible only if a permission changed mid-session. Say nothing rather
        // than light nothing.
        return !$inThisArea || $this->lights($tabs) ? $tabs : [];
    }

    /**
     * WHICH OF THE AREA'S SCREENS THIS REQUEST IS ON, by name — or null if it is
     * on none of them.
     *
     * The modules space is recognised by the URL SPACE it owns rather than by a
     * route-name allowlist, and that is what keeps this module blind to the rest:
     * a module's own pages are its own bundle's routes, and this class must light
     * "Modules" for all of them without knowing one of their names. Every
     * area-scoped module page lives under `/areas/{uuid}/modules/`, which is the
     * one contract they all share.
     */
    private function whereWeAre(string $route, string $path): ?string
    {
        if (1 === preg_match('#^/areas/[^/]+/modules(?:/|$)#', $path)) {
            return 'Modules';
        }

        foreach (['Zones' => 'area_zones', 'Settings' => 'area_settings', 'Overview' => 'area_show'] as $label => $name) {
            if ($route === $name) {
                return $label;
            }
        }

        return null;
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

    /**
     * The area's name, for the page title's middle segment. Null when the
     * request is not inside an area at all — the register, a sign-in page.
     */
    public function place(): ?string
    {
        return $this->currentArea($this->requestStack->getCurrentRequest())?->getName();
    }

    private function currentArea(?Request $request): ?AreaOfInterest
    {
        $uuid = $request?->attributes->get('uuid');
        if (!\is_string($uuid) || !Uuid::isValid($uuid)) {
            return null;
        }

        return $this->areas->findOneBy(['uuid' => Uuid::fromString($uuid)]);
    }

    /**
     * A URL for a route that may not be registered — a screen another package
     * serves, or a slice of the application that has not merged yet.
     *
     * @param array<string, string|null> $parameters
     */
    private function url(string $name, array $parameters = []): ?string
    {
        if (null === $this->router->getRouteCollection()->get($name)) {
            return null;
        }

        return $this->router->generate($name, array_filter(
            $parameters,
            static fn (?string $value): bool => null !== $value,
        ), UrlGeneratorInterface::ABSOLUTE_PATH);
    }
}
