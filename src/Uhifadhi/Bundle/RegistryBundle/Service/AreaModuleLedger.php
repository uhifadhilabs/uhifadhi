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

use Uhifadhi\Bundle\RegistryBundle\Repository\AreaModuleRepository;
use Uhifadhi\Contracts\Entity\AreaInterface;

/**
 * WHAT THIS AREA HAS AND WHAT IT DOES NOT — read once, in one place.
 *
 * Several surfaces want this pair: a list of what an area is running, and,
 * muted underneath it, what the deployment has that this area has not taken.
 * They used to walk the catalogue separately, which is exactly how a card comes
 * to say "8 in the catalogue" over two rows: two readings of one table, made a
 * query apart, disagreeing about what the catalogue is. The catalogue is walked
 * once here and split, so the count and the rows can never describe two
 * different tables.
 *
 * THE PROMISE THIS KEEPS. Every contribution a module makes to a shared surface
 * is keyed by its slug, and the promise the platform makes to an area manager is
 * that switching a module off takes its contributions off the page the same day
 * — not on the next deploy, not after a cache warm. This reading is what those
 * surfaces are derived from, and it goes to the database every time. Anything
 * that caches it breaks the promise.
 *
 * NOTHING HERE INVENTS DATA. A module the area does not have has contributed
 * nothing, and the only things said about it are its own catalogue name and its
 * own words for what it draws on. What it WOULD contribute is a promise only an
 * installed module can make, and it has not been asked.
 */
final readonly class AreaModuleLedger
{
    public function __construct(
        private ModuleCatalogue $catalogue,
        private AreaModuleRepository $areaModules,
    ) {
    }

    /**
     * @return array{
     *     installed: list<array{slug: string, name: string, since: ?\DateTimeImmutable}>,
     *     absent: list<array{slug: string, name: string, source: ?string}>,
     *     catalogueCount: int,
     *     installedCount: int,
     * }
     */
    public function for(AreaInterface $area): array
    {
        $installed = [];
        foreach ($this->areaModules->activeForArea($area) as $areaModule) {
            $module = $areaModule->getModule();
            $slug = $module?->getSlug();
            if (null === $module || null === $slug) {
                continue;
            }
            // The AREA's own order, which is the order its surfaces head their
            // sections in — not the catalogue's.
            $installed[$slug] = [
                'slug' => $slug,
                'name' => $module->getName() ?? $slug,
                'since' => $areaModule->getCreatedAt(),
            ];
        }

        $catalogue = $this->catalogue->all();

        $absent = [];
        foreach ($catalogue as $module) {
            $slug = $module->getSlug();
            if (null === $slug || isset($installed[$slug])) {
                continue;
            }
            $absent[] = [
                'slug' => $slug,
                'name' => $module->getName() ?? $slug,
                'source' => $module->getDataSource(),
            ];
        }

        return [
            'installed' => array_values($installed),
            'absent' => $absent,
            'catalogueCount' => \count($catalogue),
            'installedCount' => \count($installed),
        ];
    }
}
