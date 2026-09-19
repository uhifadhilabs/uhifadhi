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

namespace Uhifadhi\Contracts\Atlas;

/**
 * WHAT A PILL'S COLOUR MEANS — a role, never a colour.
 *
 * A MODULE NAMES A MEANING AND THE ATLAS PICKS THE PAINT. A module that
 * handed over `#3ED9A8` would be deciding what green is, in a product
 * with a light theme and a dark one and a palette that moves; two
 * modules doing it would disagree by a shade nobody could fix in one
 * place. So a feed says "this one is a problem" and the atlas knows
 * what a problem looks like here.
 *
 * FIVE, BECAUSE FIVE IS WHAT A GLANCE CAN TELL APART. Anything finer is
 * a label, and a label is what the pill already carries.
 */
enum PillHue: string
{
    /** The ordinary thing this module puts on a day. */
    case Subject = 'subject';

    /** On, done, gone well. */
    case Good = 'good';

    /** Worth a look before it becomes a problem. */
    case Attention = 'attention';

    /** A problem: a hole, a miss, a failure. */
    case Problem = 'problem';

    /** Present but not the point — background, context, a quiet second shift. */
    case Quiet = 'quiet';
}
