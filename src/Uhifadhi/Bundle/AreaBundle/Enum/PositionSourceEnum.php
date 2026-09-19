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

namespace Uhifadhi\Bundle\AreaBundle\Enum;

/**
 * WHERE A DUTY PING'S POSITION CAME FROM.
 *
 * KEPT BECAUSE IT CHANGES WHAT THE FIX IS WORTH. A satellite fix and a
 * cell-tower estimate are both positions and only one of them says
 * anything about standing inside a two-hundred-metre ring; a last-known
 * position says only where the phone was when it last knew.
 *
 * @see API-CONTRACT.md §13C
 */
enum PositionSourceEnum: string
{
    case Gps = 'gps';
    case Network = 'network';
    case LastKnown = 'last_known';
}
