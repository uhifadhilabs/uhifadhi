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

namespace Uhifadhi\Bundle\TeamBundle\Performance;

/**
 * WHAT ONE CELL OF A MATRIX IS, once the page has looked at it.
 *
 * THE THREE ARE DRAWN DIFFERENTLY AND MEAN DIFFERENT THINGS. A figure is
 * a measurement and may be placed among the others; a run of states is
 * four goals in four states, which has no average and is never placed; a
 * blank is one of the absences, and which one it is the cell's own words
 * say.
 */
enum CellKind
{
    case Figure;
    case Marks;
    case Blank;
}
