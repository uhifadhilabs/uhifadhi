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
 * WHERE EVERYBODY IS, RIGHT NOW — published by the bundle that owns the
 * ground, the posts and the pings.
 *
 * THE READ A MAP IS MADE OF. A module draws "where everybody is" by
 * feeding these to an atlas plate: one marker a person, each carrying
 * who, which post, how the watch reads and how old the fix is. Without
 * a seam the module would have to read `duty_position` and the posts'
 * catchments across a package boundary it does not depend on, and it
 * would derive "at post, verified" for itself — a second answer to a
 * question this platform already answers once.
 *
 * WHY THIS IS A SECOND INTERFACE AND NOT A METHOD ON
 * {@see PresenceProviderInterface}. That one answers about a DAY,
 * keyed by the ranger's own local date, and everything it returns is
 * settled once the day is over. This answers about an INSTANT, and is
 * never settled: the same watch gives a different answer a minute
 * later. One interface with two clocks would be one interface whose
 * caller has to know which of its methods goes stale — and the
 * contracts package already keeps one question per interface, which is
 * why {@see StationSectionsInterface} is not a method on the presence
 * seam either.
 *
 * ONE DERIVATION, THOUGH. The state on a live position is the SAME
 * reading the day board draws, from the same code — the area bundle
 * answers both contracts with one service, so a ranger cannot be "at
 * post, verified" on the map and unverified on the board.
 *
 * READ-ONLY, AND NOT A SEAM TO IMPLEMENT. Exactly one implementation
 * exists, in the area bundle; a module type-hints the interface and is
 * wired to it by name.
 */
interface LivePositionsInterface
{
    /**
     * THE LATEST FIX OF EVERY PERSON ON AN OPEN WATCH in this area.
     *
     * OPEN WATCHES ONLY, which is what makes this "live" rather than
     * "recent". A watch somebody checked out of is finished, and its
     * last ping is where they were when they stopped — drawing it on a
     * live map would put a marker at a post nobody is standing at.
     *
     * A PERSON WITH NO FIX IS NOT HERE. They may well be on duty and at
     * their post; what is absent is a POSITION, and inventing one at
     * the post's own point would turn a claim into proof. Who is on
     * duty is {@see PresenceProviderInterface}'s answer, and a surface
     * that needs both joins them — the rail beside the map lists
     * everybody, and the map draws those whose phones have said where.
     *
     * @param \DateTimeImmutable $asOf the moment to answer for — passed, never taken from the clock,
     *                                 so a caller can reproduce an answer and a test can fix one
     */
    public function liveIn(string $areaUuid, \DateTimeImmutable $asOf): LivePresence;
}
