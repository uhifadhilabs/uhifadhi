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

namespace Uhifadhi\Bundle\TeamBundle\Exception;

use Uhifadhi\Bundle\TeamBundle\Entity\Position;
use Uhifadhi\Bundle\TeamBundle\Entity\User;

/**
 * SOMEBODY WAS ASSIGNED TO A POSITION THAT HAS NO SEAT LEFT.
 *
 * SOME POSTS ARE SINGULAR AND SOME ARE NOT: Head of Protection is one seat,
 * Data Analyst is many, and a position says which it is. Adding one more
 * person after a singular post is occupied is not a thing an organization
 * meant to allow.
 *
 * IT NAMES THE HOLDER, because "that position is full" is not actionable and
 * "Joseph Mollel holds it" is: the administrator's next move is to end that
 * holding or to pick another position, and they cannot choose without the
 * name.
 */
final class PositionFullException extends \RuntimeException
{
    /**
     * @param list<User> $holders everybody standing in it right now
     */
    public function __construct(
        public readonly Position $position,
        public readonly array $holders,
    ) {
        $names = array_map(static fn (User $u): string => $u->getFullName(), $holders);

        parent::__construct(\sprintf(
            '“%s” has %s and %s. End a holding, or raise the seat count on the position.',
            (string) $position->getName(),
            1 === $position->getSeatCount() ? 'one seat' : \sprintf('%d seats', (int) $position->getSeatCount()),
            [] === $names
                ? 'they are taken'
                : \sprintf('%s %s', implode(', ', $names), 1 === \count($names) ? 'holds it' : 'hold them'),
        ));
    }
}
