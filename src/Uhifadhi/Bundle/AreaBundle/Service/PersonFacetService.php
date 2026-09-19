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

namespace Uhifadhi\Bundle\AreaBundle\Service;

use Uhifadhi\Contracts\People\PersonFacet;
use Uhifadhi\Contracts\People\PersonFacetProviderInterface;

/**
 * WHAT A POSTINGS BOARD KNOWS ABOUT THE PEOPLE ON IT, asked of whoever owns
 * them.
 *
 * NOBODY IS NAMED HERE. The board filters by role and by department, neither
 * of which is this bundle's to know; the team bundle tags a provider and this
 * reads the tag. An installation without one draws its boards without those
 * two columns, which is the honest outcome and not an error.
 *
 * ONE CALL PER PROVIDER FOR THE WHOLE PAGE, and the first answer wins: two
 * bundles claiming to own the same person's position is a configuration
 * somebody should notice, and silently merging them would be how they never
 * do.
 */
final readonly class PersonFacetService
{
    /** @param iterable<PersonFacetProviderInterface> $providers */
    public function __construct(
        private iterable $providers,
    ) {
    }

    /**
     * @param list<string> $userUuids
     *
     * @return array<string, PersonFacet>
     */
    public function facetsFor(array $userUuids): array
    {
        if ([] === $userUuids) {
            return [];
        }

        $facets = [];
        foreach ($this->providers as $provider) {
            foreach ($provider->facetsFor($userUuids) as $uuid => $facet) {
                $facets[$uuid] ??= $facet;
            }
        }

        return $facets;
    }
}
