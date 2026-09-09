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

namespace Uhifadhi\Bundle\AreaBundle\Controller;

use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Twig\Environment;
use Uhifadhi\Bundle\AreaBundle\Entity\AreaOfInterest;
use Uhifadhi\Bundle\AreaBundle\Service\AreaComposition;
use Uhifadhi\Bundle\RegistryBundle\Service\AreaModuleService;

/**
 * THE PER-AREA MODULES SCREEN — the grid of what this area has switched on, and
 * the shop it is composed in.
 *
 * `area_modules` IS THE ROUTE NAME, AND THAT IS A FLEET CONTRACT rather
 * than a local choice. Three consumers already generate it blind and degrade
 * when nothing answers: this bundle's own {@see \Uhifadhi\Bundle\AreaBundle\Shell\AreaShellSource}
 * drops the Modules tab, and the patrol module's breadcrumb and dashboard
 * back-button print plain text. Mounting it here lights all three with no change
 * to any of them — which is what the name existing before the route was for.
 *
 * THIS BUNDLE OWNS THE SCREEN AND THE REGISTRY STAYS UI-LESS. The grid is a reading
 * of the registry's catalogue against an area's ledger, and "an area" is this
 * module's word: the registry holds that table for installations whose area model is
 * their own and cannot name an area class, let alone draw a page about one. So
 * the registry publishes the data and this bundle draws it. A test in the registry greps
 * its own source to keep it that way.
 *
 * THE PICTURE IS THE SHELL'S. The tiles are rendered by the shell's
 * `_module_grid.html.twig` — the same partial a department page or a search
 * result would use — because the catalogue picture must look identical wherever
 * it appears. What is NOT the shell's is which cards, in which groups, with
 * which URLs: that needs the area, the viewer and the ledger, none of which a
 * layout has. See {@see AreaComposition}.
 *
 * TWO PERMISSIONS, AND THE MAPPING IS DELIBERATE. `module.view` to see the grid;
 * `module.create` — the catalogue's "Modules / Add" — to reach the shop and to
 * move anything in it. A catalogue string rather than a role, because composing
 * an area is exactly the capability that permission describes and a role is not
 * grantable to a position.
 *
 * EVERY WRITE IS A POST WITH A TOKEN, and every write goes through the registry's
 * {@see AreaModuleService} rather than touching a row: the rule that a pinned
 * module cannot be parked lives there.
 */
final readonly class AreaModulesController
{
    /** Seeing the catalogue on an area. */
    public const string VIEW = 'module.view';

    /** Composing it: switching a module on or off, and setting the order. */
    public const string COMPOSE = 'module.create';

    public function __construct(
        private Environment $twig,
        private AreaComposition $composition,
        private AreaModuleService $areaModules,
        private CsrfTokenManagerInterface $csrf,
        private UrlGeneratorInterface $urls,
    ) {
    }

    /**
     * THE NAME IS THE CONTRACT; THE PATH IS A CONVENTION. `/areas/{uuid}/modules`
     * is the URL SPACE every area-scoped module page lives under — it is how
     * AreaShellSource recognises the Modules tab without knowing one route name
     * — so the grid sits at its root.
     */
    #[Route('/areas/{uuid}/modules', name: 'area_modules', requirements: ['uuid' => Requirement::UUID], methods: ['GET'])]
    #[IsGranted(self::VIEW)]
    public function grid(
        #[MapEntity(mapping: ['uuid' => 'uuid'])] AreaOfInterest $area,
    ): Response {
        return new Response($this->twig->render('@Area/area/modules.html.twig', [
            'area' => $area,
            'groups' => $this->composition->gridFor($area),
            'parkedCount' => $this->composition->parkedCountFor($area),
        ]));
    }

    /**
     * MOUNTED WITH A PRIORITY, and it is load-bearing. `/areas/{uuid}/modules`
     * cannot swallow this one, but a module bundle mounting its own page at
     * `/areas/{uuid}/modules/{slug}` could — and the shop is this bundle's, not
     * a module called "customize".
     */
    #[Route('/areas/{uuid}/modules/customize', name: 'area_module_customize', requirements: ['uuid' => Requirement::UUID], methods: ['GET'], priority: 1)]
    #[IsGranted(self::COMPOSE)]
    public function customize(
        #[MapEntity(mapping: ['uuid' => 'uuid'])] AreaOfInterest $area,
    ): Response {
        return new Response($this->twig->render('@Area/area/customize.html.twig', [
            'area' => $area,
            'active' => $this->composition->activeFor($area),
            'parkedByCategory' => $this->composition->parkedByCategoryFor($area),
            'parkedCount' => $this->composition->parkedCountFor($area),
            'token' => $this->csrf->getToken($this->tokenId($area))->getValue(),
        ]));
    }

    /**
     * SWITCH A MODULE ON. Idempotent, and it re-activates a parked row in place
     * rather than writing a second one — so a module that has been off and on
     * again keeps whatever it recorded while it was on.
     *
     * A SLUG THAT IS IN NO CATALOGUE WRITES NOTHING AND SAYS NOTHING. It is not
     * a 404: the shop is a live reading of a catalogue that can change under an
     * open page, and somebody clicking "+ Add" on a module uninstalled a second
     * ago has done nothing wrong. They get the page back, without it.
     */
    #[Route('/areas/{uuid}/modules/customize/install', name: 'area_module_install', requirements: ['uuid' => Requirement::UUID], methods: ['POST'], priority: 1)]
    #[IsGranted(self::COMPOSE)]
    public function install(
        #[MapEntity(mapping: ['uuid' => 'uuid'])] AreaOfInterest $area,
        Request $request,
    ): Response {
        $this->denyUnlessTokenValid($area, $request);
        $this->areaModules->install($area, $request->request->getString('module'));

        return $this->backToShop($area);
    }

    /**
     * PARK A MODULE. Its data stays — the row survives switched off, which is
     * what the shop's own caption promises. A pinned module is silently refused
     * by the registry rather than half-parked here.
     */
    #[Route('/areas/{uuid}/modules/customize/uninstall', name: 'area_module_uninstall', requirements: ['uuid' => Requirement::UUID], methods: ['POST'], priority: 1)]
    #[IsGranted(self::COMPOSE)]
    public function uninstall(
        #[MapEntity(mapping: ['uuid' => 'uuid'])] AreaOfInterest $area,
        Request $request,
    ): Response {
        $this->denyUnlessTokenValid($area, $request);
        $this->areaModules->uninstall($area, $request->request->getString('module'));

        return $this->backToShop($area);
    }

    /**
     * THE ORDER THE MODULES ARE SHOWN IN, as the pills were dragged into it. The
     * only batch write on the screen, because dragging one row moves every row
     * after it.
     */
    #[Route('/areas/{uuid}/modules/customize/reorder', name: 'area_module_reorder', requirements: ['uuid' => Requirement::UUID], methods: ['POST'], priority: 1)]
    #[IsGranted(self::COMPOSE)]
    public function reorder(
        #[MapEntity(mapping: ['uuid' => 'uuid'])] AreaOfInterest $area,
        Request $request,
    ): Response {
        $this->denyUnlessTokenValid($area, $request);

        $this->areaModules->reorder($area, array_values(array_filter(
            $request->request->all('order'),
            static fn (mixed $slug): bool => \is_string($slug) && '' !== $slug,
        )));

        return $this->backToShop($area);
    }

    /** One token for the whole shop, scoped to the area whose composition it changes. */
    public function tokenId(AreaOfInterest $area): string
    {
        return 'area_modules_'.$area->getUuidString();
    }

    private function denyUnlessTokenValid(AreaOfInterest $area, Request $request): void
    {
        if (!$this->csrf->isTokenValid(new CsrfToken($this->tokenId($area), $request->request->getString('_token')))) {
            throw new AccessDeniedException('Invalid CSRF token.');
        }
    }

    /**
     * POST-REDIRECT-GET, so a reload does not switch the same module twice. Back
     * to the shop rather than the grid: composing is several decisions in a row,
     * and the "Done" button is what leaves.
     */
    private function backToShop(AreaOfInterest $area): RedirectResponse
    {
        return new RedirectResponse($this->urls->generate('area_module_customize', ['uuid' => $area->getUuidString()]));
    }
}
