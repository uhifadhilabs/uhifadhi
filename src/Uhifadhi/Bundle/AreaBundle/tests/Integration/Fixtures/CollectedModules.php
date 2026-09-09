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

namespace Uhifadhi\Bundle\AreaBundle\Tests\Integration\Fixtures;

use Uhifadhi\Contracts\ModuleProviderInterface;

/**
 * Stands in for the registry's catalogue: it collects everything tagged
 * `uhifadhi.module`. Tagged services are private, so a collector is what makes a
 * bundle's contribution — or its deliberate absence — observable at all.
 */
final class CollectedModules
{
    /** @param iterable<ModuleProviderInterface> $providers */
    public function __construct(private readonly iterable $providers)
    {
    }

    /** @return list<string> */
    public function slugs(): array
    {
        $slugs = [];
        foreach ($this->providers as $provider) {
            $slugs[] = $provider->slug();
        }

        return $slugs;
    }
}
