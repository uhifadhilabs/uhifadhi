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

namespace Uhifadhi\Contracts\Area;

/**
 * WHICH OF A STATION'S TWO SURFACES A SECTION IS FOR.
 *
 * A POST IS READ IN ONE PLACE AND SET UP IN ANOTHER, and what a module has
 * to say differs between them: the record answers "what is happening at
 * this post", the configure card asks "what should happen at it". The same
 * module contributes to both, so one seam carries both and the surface says
 * which is being drawn.
 *
 * TWO, AND THE ENUM IS WHY THERE ARE NOT TWO INTERFACES. A contributor that
 * only speaks to one of them answers the other with nothing, which is a
 * legitimate answer and costs it one line.
 */
enum StationSurface: string
{
    /** `/areas/{uuid}/stations/{station}` — the post's own page. */
    case Record = 'record';

    /** The post's card on the area's Stations configure page. */
    case Configure = 'configure';
}
