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
 * A SEAT COUNT WAS LOWERED PAST THE PEOPLE ALREADY SITTING IN IT.
 *
 * Six people hold the position and the count is asked to be four: the two
 * who would be sitting in seats that no longer exist are not named by the
 * product, and the product will not choose them. The refusal states the
 * floor.
 */
final class SeatsBelowHoldersException extends \RuntimeException
{
    public function __construct(
        public readonly Position $position,
        public readonly int $asked,
        public readonly int $holders,
    ) {
        parent::__construct(\sprintf(
            'Refused — %d %s hold “%s”, so the seat count cannot go below %d. %d was asked for.',
            $holders,
            1 === $holders ? 'person' : 'people',
            (string) $position->getName(),
            $holders,
            $asked,
        ));
    }
}
