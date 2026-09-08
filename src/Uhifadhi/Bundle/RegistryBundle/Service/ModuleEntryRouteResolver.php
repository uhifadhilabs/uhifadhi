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

use Uhifadhi\ModuleContracts\ModuleProviderInterface;

/**
 * WHERE A MODULE'S TILE LINKS — the one piece of routing the registry carries, and
 * it carries no routes of its own.
 *
 * A resolution, not a redirect: the answer is "what route name, if any, does
 * this module own?", and something in the shell turns that into a link with the
 * area's uuid. Nothing is rendered here.
 *
 * READ FROM THE PROVIDER, NEVER FROM A COLUMN. A route name is code: it changes
 * when a module refactors its controllers. Stored at seed time, a tile would
 * point at a dead route until somebody re-ran a command; read live, uninstalling
 * a bundle takes its route with it on the next request.
 *
 * An unknown slug is null, not an exception. The catalogue table can outlive a
 * provider, and a tile for a module nobody has installed must degrade to the
 * generic page rather than take the page down.
 */
final readonly class ModuleEntryRouteResolver
{
    /**
     * @param iterable<ModuleProviderInterface> $providers
     */
    public function __construct(
        private iterable $providers = [],
    ) {
    }

    public function entryRouteFor(string $slug): ?string
    {
        foreach ($this->providers as $provider) {
            if ($provider->slug() === $slug) {
                return $provider->entryRoute();
            }
        }

        return null;
    }
}
