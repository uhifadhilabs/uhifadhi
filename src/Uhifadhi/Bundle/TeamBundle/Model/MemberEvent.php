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
 * ONE LINE OF A PERSON'S HISTORY — what happened, what it says under it, and
 * when.
 *
 * EVERY ONE OF THESE IS DERIVED FROM A STORED FACT, never from a log: the
 * model keeps when somebody was invited, when their account was made, when a
 * posting began and when they were deactivated, and each of those is a line.
 * An installation without an audit trail can still say all of that truthfully,
 * and saying it is better than an empty card.
 */
final readonly class MemberEvent
{
    public function __construct(
        public string $title,
        public string $note,
        public \DateTimeImmutable $when,
    ) {
    }
}
