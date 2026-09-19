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
 * WHERE A POSTING WAS WRITTEN — the station's page, or the person's.
 *
 * TWO DOORS TO ONE FACT. Somebody staffing a post works down a list of people;
 * somebody looking at a person assigns them a post. Both write the same row,
 * and the design prints which, because two people reading one posting from two
 * places should be able to tell how it got there — and because a row that
 * appeared "from their page" is a row the station's own staffing list did not
 * plan.
 *
 * NOT AN AUDIT TRAIL. Who did it and when is the station's log; this is one
 * fact about the row itself, shown beside it.
 */
enum PostingSource: string
{
    /** Written on the station's own page, by somebody staffing the post. */
    case WrittenHere = 'here';

    /** Written on the person's page, by somebody assigning them somewhere. */
    case FromTheirPage = 'person';
}
