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
 * THE FOUR SHAPES A TOPIC'S CHART MAY BE DRAWN IN.
 *
 * A KIND, NOT A LIBRARY CALL. A provider says what its series IS — a run
 * over time, a comparison across departments, parts of a whole, a movement
 * either side of nought — and the atlas draws it. A module handing over
 * chart options would be a module deciding what the platform's charts look
 * like, and the second module would decide differently.
 */
enum ChartKind: string
{
    /** A run over time. */
    case Line = 'line';

    /** A comparison across departments or categories. */
    case Bar = 'bar';

    /** Parts of a whole, period by period. */
    case Stacked = 'stacked';

    /** A movement either side of nought — gained and lost, met and missed. */
    case Diverging = 'diverging';
}
