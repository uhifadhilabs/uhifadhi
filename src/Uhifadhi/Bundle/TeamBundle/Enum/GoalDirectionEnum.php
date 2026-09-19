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

namespace Uhifadhi\Bundle\TeamBundle\Enum;

/**
 * WHICH WAY A GOAL IS MET.
 *
 * "Cover 60 % of the ground" is met by going UP; "settle a claim within 10
 * days" is met by staying DOWN. Without this a goal cannot be judged at
 * all, and a page would have to guess from the unit — which gets it wrong
 * the first time somebody declares a goal about a count of incidents.
 */
enum GoalDirectionEnum: string
{
    /** The figure has to reach the target and may pass it. */
    case AtLeast = 'at_least';

    /** The figure has to stay at or under the target. */
    case AtMost = 'at_most';

    public function label(): string
    {
        return match ($this) {
            self::AtLeast => 'at least',
            self::AtMost => 'at most',
        };
    }
}
