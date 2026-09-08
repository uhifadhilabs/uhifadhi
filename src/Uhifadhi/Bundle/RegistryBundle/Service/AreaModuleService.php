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

namespace Uhifadhi\Bundle\RegistryBundle\Service;

use Doctrine\ORM\EntityManagerInterface;
use Uhifadhi\Bundle\RegistryBundle\Entity\AreaModule;
use Uhifadhi\Bundle\RegistryBundle\Repository\AreaModuleRepository;
use Uhifadhi\ModuleContracts\Entity\AreaInterface;

/**
 * PER-AREA INSTALL STATE: what THIS area has switched on, and the writes that
 * change it.
 *
 * The vocabulary, because it is easy to blur. A module is INSTALLED on the
 * deployment — its bundle is in composer.json, and only composer changes that.
 * A module is ACTIVE on an area — an admin switched it on there, and only this
 * service changes that. "Uninstalling for an area" is the second thing:
 * parking. It never deletes the area's data, and it never removes the module
 * from the deployment.
 *
 * IT DECIDES NOTHING ABOUT WHAT IS DRAWN. It answers in rows; grouping them into
 * a grid, a shop or a sub-nav is a picture, and pictures are the shell's.
 */
final readonly class AreaModuleService
{
    public function __construct(
        private EntityManagerInterface $em,
        private AreaModuleRepository $areaModules,
        private ModuleCatalogue $catalogue,
    ) {
    }

    /**
     * The area's active modules, in its own sub-nav order.
     *
     * @return list<AreaModule>
     */
    public function activeFor(AreaInterface $area): array
    {
        return $this->areaModules->activeForArea($area);
    }

    /**
     * Every assignment the area owns, active and parked alike.
     *
     * @return list<AreaModule>
     */
    public function allFor(AreaInterface $area): array
    {
        return $this->areaModules->forArea($area);
    }

    public function assignmentFor(AreaInterface $area, string $slug): ?AreaModule
    {
        foreach ($this->areaModules->forArea($area) as $areaModule) {
            if ($slug === $areaModule->getModule()?->getSlug()) {
                return $areaModule;
            }
        }

        return null;
    }

    public function isActive(AreaInterface $area, string $slug): bool
    {
        return true === $this->assignmentFor($area, $slug)?->isActive();
    }

    /**
     * SWITCH A MODULE ON FOR THIS AREA. Idempotent, because the screen that
     * calls it posts a form and a form gets double-submitted — and two rows for
     * one module is how an area's sub-nav grows a duplicate tab.
     *
     * An existing row is REACTIVATED where it lies: same row, same history, same
     * position, so an admin who parks a module and changes their mind gets their
     * ordering back rather than a module appended to the end of their sub-nav.
     * A row is created only when the area has none — an area created after the
     * last seed still sees the whole catalogue.
     */
    public function install(AreaInterface $area, string $slug): ?AreaModule
    {
        $existing = $this->assignmentFor($area, $slug);
        if (null !== $existing) {
            $existing->setActive(true);
            $this->em->flush();

            return $existing;
        }

        $module = $this->catalogue->find($slug);
        if (null === $module) {
            // Not a module this deployment has. Nothing to switch on, and no
            // reason to take a page down over it.
            return null;
        }

        $assignment = new AreaModule()
            ->setArea($area)
            ->setModule($module)
            ->setActive(true)
            ->setPosition($this->nextPosition($area));

        $this->em->persist($assignment);
        $this->em->flush();

        return $assignment;
    }

    /**
     * PARK IT. The row stays and so does everything keyed to it; what changes is
     * that the area stops counting the module present, from the next read
     * onwards.
     *
     * A PINNED MODULE IS NEVER SWITCHED OFF — an area without its pinned module
     * has no front door. Note what is known here and what is not: the flag is
     * enforced, and which module carries it is nobody's business but the
     * provider's. Refusing is silent, because this is not an admin's mistake to
     * be told about; it is a choice that was never on offer.
     */
    public function uninstall(AreaInterface $area, string $slug): void
    {
        $assignment = $this->assignmentFor($area, $slug);
        if (null === $assignment || true === $assignment->getModule()?->isPinned()) {
            return;
        }

        $assignment->setActive(false);
        $this->em->flush();
    }

    /**
     * THE ORDER IS THE AREA'S. An admin dragging a sub-nav is stating a
     * preference about their own area, and nothing else — no other area moves,
     * and no deploy renumbers it afterwards.
     *
     * Modules left out of the submitted order keep their relative order behind
     * the ones named, so a partial submission is a partial reordering rather
     * than a shuffle. A pinned module holds the front and is not part of the
     * conversation.
     *
     * @param list<string> $orderedSlugs the slugs an admin arranged, in their new order
     */
    public function reorder(AreaInterface $area, array $orderedSlugs): void
    {
        $rank = array_flip($orderedSlugs);
        $unranked = 1; // a pinned module holds 0.

        foreach ($this->areaModules->activeForArea($area) as $areaModule) {
            $module = $areaModule->getModule();
            if (true === $module?->isPinned()) {
                $areaModule->setPosition(0);

                continue;
            }

            $slug = (string) $module?->getSlug();
            $areaModule->setPosition(
                isset($rank[$slug]) ? $rank[$slug] + 1 : $unranked + 1_000,
            );
            ++$unranked;
        }

        $this->em->flush();
    }

    private function nextPosition(AreaInterface $area): int
    {
        $max = 0;
        foreach ($this->areaModules->forArea($area) as $areaModule) {
            $max = max($max, $areaModule->getPosition());
        }

        return $max + 1;
    }
}
