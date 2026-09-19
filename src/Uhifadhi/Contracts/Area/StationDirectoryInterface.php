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
 * EVERY STATION ON THE INSTALLATION AND WHO STANDS AT EACH — published by the
 * bundle that owns the ground and the posts.
 *
 * THE STATION-SHAPED HALF OF {@see \Uhifadhi\Contracts\People\PersonPosting}.
 * That seam answers "where does this person work", one row per person; a
 * postings board asks the other question — "who is at this post" — and
 * assembling it out of the person-shaped answer would lose every station
 * nobody stands at, which is exactly the reading the board exists for.
 *
 * ACROSS EVERY AREA AT ONCE, which is the other reason it exists: the area's
 * own reads are all scoped to one area, and a reader who has to open four
 * areas to count the postings cannot count them at all.
 *
 * STANDING POSTINGS ONLY. Who stood here in 2024 is a history, and a history
 * is a different page with a different question.
 *
 * TAGGED EXPLICITLY AT BOTH ENDS. Nothing autoconfigures a reusable bundle's
 * services, and an `#[AutoconfigureTag]` on this interface would be silently
 * dead — PHP does not inherit attributes from an interface, and the only
 * symptom would be a postings board that said the installation had no ground.
 */
interface StationDirectoryInterface
{
    public const string TAG = 'uhifadhi.station_directory';

    /**
     * Every station, in the order a board reads them: by area, then by the
     * station's own code.
     *
     * @return list<PostedStation>
     */
    public function stations(): array;

    /**
     * EVERY AREA, whether or not it holds a station.
     *
     * The board's own rows cannot answer this: an area with no station has no
     * row, and a filter that offered only the areas already on the board would
     * hide the three an installation has not built out yet — which is exactly
     * what the reader is trying to see.
     *
     * @return list<DirectoryArea>
     */
    public function areas(): array;
}
