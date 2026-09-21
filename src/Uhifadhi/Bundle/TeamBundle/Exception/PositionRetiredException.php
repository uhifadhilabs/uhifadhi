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

/**
 * SOMEBODY WAS GIVEN A POSITION THAT HAS BEEN RETIRED.
 *
 * A retired position is absent from every picker, so this is a stale form
 * or a crafted post rather than a mistake somebody could make on screen.
 * It is refused rather than quietly reinstating the position: reopening one
 * is a decision, made on the position.
 */
final class PositionRetiredException extends \RuntimeException
{
    public function __construct(public readonly Position $position)
    {
        parent::__construct(\sprintf(
            '“%s” has been retired and cannot be given to anybody. Reinstate it on the position first.',
            (string) $position->getName(),
        ));
    }
}
