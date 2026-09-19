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

namespace Uhifadhi\Bundle\TeamBundle\People;

use Uhifadhi\Bundle\TeamBundle\Repository\UserRepository;
use Uhifadhi\Contracts\People\PersonFacet;
use Uhifadhi\Contracts\People\PersonFacetProviderInterface;

/**
 * TEAM'S ANSWER TO "WHAT IS THIS PERSON, AND WHOSE?".
 *
 * A POSITION AND A DEPARTMENT, AND NOTHING ELSE. Somewhere else in the
 * product a list of people is filtered by role and by department; both facts
 * are this bundle's and the list is not, so they cross as two strings rather
 * than as an entity nobody else may name.
 *
 * THE DEPARTMENT COMES THROUGH THE POSITION, because that is where it lives:
 * a person holds a position and a position belongs to a department, so a
 * person's department is their position's. Somebody holding no position has
 * neither, and null is the honest answer to both — a filter renders that as
 * an absence rather than inventing a category.
 *
 * IT IS A CONTRACT IMPLEMENTATION, named for what it fulfils. Nothing here
 * knows which surface asked or what it draws.
 */
final readonly class TeamPersonFacets implements PersonFacetProviderInterface
{
    public function __construct(
        private UserRepository $users,
    ) {
    }

    public function facetsFor(array $userUuids): array
    {
        if ([] === $userUuids) {
            return [];
        }

        $facets = [];
        foreach ($this->users->findByUuids($userUuids) as $user) {
            $uuid = $user->getUuidString();
            if (null === $uuid) {
                continue;
            }

            $position = $user->getPosition();
            $facets[$uuid] = new PersonFacet(
                userUuid: $uuid,
                position: $position?->getName(),
                department: $position?->getDepartment()?->getName(),
            );
        }

        return $facets;
    }
}
