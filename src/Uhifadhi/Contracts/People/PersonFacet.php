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

namespace Uhifadhi\Contracts\People;

/**
 * WHAT A PERSON IS, in the two words a list filters by.
 *
 * A POSTINGS BOARD FILTERS BY ROLE AND BY DEPARTMENT, and neither is the area
 * module's to know: a position title and a department belong to whoever owns
 * people. The board still has to draw them on every line and count them in
 * two dropdowns, so the facts come across as this — two strings and the
 * identifier they belong to, and nothing else.
 *
 * NULL IS UNRECORDED, NEVER "none". Somebody may hold no position and belong
 * to no department; a filter renders that as the honest absence rather than
 * inventing a category for it.
 *
 * NOT AN ENTITY AND NOT THE WHOLE PERSON. The name and the face come from the
 * published user contract, which the caller already has; this carries only
 * what that contract deliberately does not, so the contract does not have to
 * grow two questions every implementor would then have to answer.
 */
final readonly class PersonFacet
{
    public function __construct(
        public string $userUuid,
        public ?string $position = null,
        public ?string $department = null,
    ) {
        if ('' === trim($userUuid)) {
            throw new \InvalidArgumentException('A person facet names whose it is.');
        }
    }
}
