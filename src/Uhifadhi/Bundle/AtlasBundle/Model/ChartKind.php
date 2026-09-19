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

namespace Uhifadhi\Bundle\AtlasBundle\Model;

/**
 * THE FOUR SHAPES A CHART IN THIS PLATFORM COMES IN.
 *
 * A KIND, NOT A LIBRARY TYPE. A module says what its series IS and the
 * atlas decides what that looks like — which library, which options,
 * which colours, which grid. Chart.js has eight types and this has four
 * shapes, and the difference is the whole point: `pie` is a decision
 * about presentation that no module gets to make.
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
