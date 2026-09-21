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
 * A POSITION SOMEBODY STILL HOLDS WAS ASKED TO CLOSE.
 *
 * RETIRING IS NOT DELETING, and it is still not free: a retired position
 * cannot be given to anybody, so retiring one that six people hold would
 * leave six people holding a thing that no longer exists. The refusal names
 * the count, because the administrator's next move is to move those people
 * and they cannot judge the size of that job without the number.
 */
final class PositionHeldException extends \RuntimeException
{
    public function __construct(
        public readonly Position $position,
        public readonly int $holders,
    ) {
        parent::__construct(\sprintf(
            'Refused — %d %s hold%s “%s”. Move them to another position first; a retired position cannot be given to anybody.',
            $holders,
            1 === $holders ? 'person' : 'people',
            1 === $holders ? 's' : '',
            (string) $position->getName(),
        ));
    }
}
