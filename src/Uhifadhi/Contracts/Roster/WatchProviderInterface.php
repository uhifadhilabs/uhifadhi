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

namespace Uhifadhi\Contracts\Roster;

/**
 * WHO IS ROSTERED WHEN — asked by whoever needs it, answered by whoever
 * keeps the roster.
 *
 * THE AREA ASKS AND DOES NOT KEEP. A handset reads its month from the
 * area's own endpoint, because that is the one address it already
 * knows; but who is on which watch is the ROSTER's, a module this
 * platform has not written yet. So the area asks through this, and an
 * installation with no roster answers nothing — which is not an error
 * and not an empty screen: **a day with no watch is a rest day**, and
 * the app draws no row, no dot and no reminder for it.
 *
 * IT IS READ-ONLY AND PER PERSON. A roster module may hold a great deal
 * more — swaps, requests, cover — and none of it is this: the question
 * is "what is this person rostered for between these dates", and the
 * answer is what the phone needs to show a month.
 *
 * A reusable bundle is not autoconfigured, so the tag goes on by hand:
 *
 *     $services->set('roster.watches', RosterWatches::class)
 *         ->tag(WatchProviderInterface::TAG);
 */
interface WatchProviderInterface
{
    /** The tag that makes a roster answerable. */
    public const string TAG = 'uhifadhi.roster.watches';

    /**
     * ONE PERSON'S WATCHES IN ONE AREA, between two days inclusive.
     *
     * @param string $from `2026-09-01`
     * @param string $to   `2026-09-30`
     *
     * @return list<Watch>
     */
    public function watchesFor(string $areaUuid, string $personUuid, string $from, string $to): array;
}
