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

use Uhifadhi\Bundle\RegistryBundle\Entity\Module;
use Uhifadhi\Bundle\RegistryBundle\Repository\ModuleRepository;
use Uhifadhi\ModuleContracts\ModuleProviderInterface;

/**
 * WHAT MODULES THIS DEPLOYMENT HAS — read once, in one place, by everything that
 * wants to draw a catalogue.
 *
 * That single reading is the point. Two surfaces querying the module table a
 * query apart is exactly how a card comes to say "8 in the catalogue" over two
 * rows that list seven. One catalogue, read once.
 *
 * THE CATALOGUE IS THE INSTALLED SET, NOT THE TABLE. A row outlives the bundle
 * that wrote it — an uninstalled module keeps its row and every area keeps its
 * data, so reinstalling finds the history where it was left. What goes away is
 * the OFFER: a module whose provider is no longer registered is not in the
 * catalogue, because a tile for it would link to code nobody has any more.
 *
 * The intersection is computed per call, from the providers actually registered
 * in this container and the rows actually in the table. Nothing is memoised;
 * see {@see \Uhifadhi\Bundle\RegistryBundle\Repository\AreaModuleRepository} for why.
 */
final readonly class ModuleCatalogue
{
    /**
     * @param iterable<ModuleProviderInterface> $providers every tagged provider, in registration order
     */
    public function __construct(
        private ModuleRepository $modules,
        private iterable $providers = [],
    ) {
    }

    /**
     * The catalogue, in catalogue order.
     *
     * @return list<Module>
     */
    public function all(): array
    {
        $installed = $this->installedSlugs();

        $catalogue = [];
        foreach ($this->modules->catalogue() as $module) {
            if (isset($installed[(string) $module->getSlug()])) {
                $catalogue[] = $module;
            }
        }

        return $catalogue;
    }

    /**
     * One module by slug, or null when this deployment does not have it —
     * whether because nothing ever declared it or because its bundle is gone.
     */
    public function find(string $slug): ?Module
    {
        if (!isset($this->installedSlugs()[$slug])) {
            return null;
        }

        return $this->modules->findBySlug($slug);
    }

    public function count(): int
    {
        return \count($this->all());
    }

    /**
     * @return array<string, true>
     */
    private function installedSlugs(): array
    {
        $slugs = [];
        foreach ($this->providers as $provider) {
            $slugs[$provider->slug()] = true;
        }

        return $slugs;
    }
}
