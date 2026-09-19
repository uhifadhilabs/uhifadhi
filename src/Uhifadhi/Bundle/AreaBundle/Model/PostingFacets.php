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

namespace Uhifadhi\Bundle\AreaBundle\Model;

/**
 * WHAT THE FILTER BAR OFFERS, AND HOW MANY EACH WOULD LEAVE.
 *
 * COUNTED AGAINST THE WHOLE BOARD, not against what is already filtered. A
 * dropdown whose counts moved as you filtered would be a dropdown that could
 * not tell you what picking an option would do — which is the one thing the
 * count is for.
 *
 * ORDERED BY WHAT THERE IS MOST OF, then alphabetically, because a board of
 * thirty is read by finding the big groups first.
 */
final readonly class PostingFacets
{
    /**
     * @param array<string, int> $roles       role title to how many hold it
     * @param array<string, int> $departments department name to how many belong to it
     * @param array<string, int> $sources     where the row was written to how many
     */
    public function __construct(
        public array $roles = [],
        public array $departments = [],
        public array $sources = [],
    ) {
    }

    /** Whether anybody answered the person seam at all — two columns depend on it. */
    public function knowsRoles(): bool
    {
        return [] !== $this->roles;
    }
}
