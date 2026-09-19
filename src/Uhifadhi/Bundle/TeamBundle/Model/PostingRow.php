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

namespace Uhifadhi\Bundle\TeamBundle\Model;

/**
 * ONE PERSON AT ONE POST, with the two halves of the answer already joined.
 *
 * THE RANK IS THE POSITION, and the department is the position's. The ground
 * knows a person's uuid and nothing else about them; this bundle knows who
 * they are and what they hold, and the row is where the two meet. A person
 * posted while holding nothing is legal and reads as such — the post is a
 * fact about the ground, not a grant.
 */
final readonly class PostingRow
{
    public function __construct(
        public string $personUuid,
        public string $name,
        public ?string $rank,
        public ?string $department,
        public bool $leader = false,
    ) {
    }
}
