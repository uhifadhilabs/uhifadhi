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

namespace Uhifadhi\Contracts\Settings;

/**
 * WHETHER A HEALTH CHECK IS SATISFIED, OR WANTS SOMEBODY TO LOOK.
 *
 * TWO STATES, AND DELIBERATELY NOT THREE. A check that failed and a check
 * that is merely overdue both end in the same place — a person opens the
 * installation screen and looks — and a third level would invite every source
 * to grade its own findings against a scale nobody published. What separates
 * them is the detail line, which says what to look at.
 */
enum CheckVerdict: string
{
    /** Nothing to do: the thing this check is about is as it should be. */
    case Pass = 'pass';

    /** Somebody should look. The detail says at what. */
    case Check = 'check';
}
