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

namespace Uhifadhi\Contracts\Performance;

/**
 * HOW A PERIOD'S MOVEMENT READS — the four a briefing distinguishes.
 *
 * NOT {@see ColumnPolarity}. That says which DIRECTION is good for a
 * column; this says what a topic's own sentence about the period
 * amounts to, which is a different question with a fourth answer:
 * something may need looking at without having gone wrong yet, and a
 * board that had only good and bad would have to call it one of them.
 */
enum MovementTone: string
{
    /** It went the way it should. */
    case Good = 'good';

    /** It went the other way. */
    case Bad = 'bad';

    /** Nothing has gone wrong and somebody should look. */
    case Attention = 'attention';

    /** It moved, and the topic makes no claim about whether that is good. */
    case Quiet = 'quiet';
}
