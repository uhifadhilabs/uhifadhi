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
 * WHO WAS ON DUTY, AND HOW THEIR DAY READS — published by the bundle
 * that owns the ground and the posts.
 *
 * ONE DERIVATION, READ BY EVERYBODY. The roster module will draw a
 * board from it, the area's overview a tile, a department's page a
 * count; if each computed "at post, verified" for itself there would be
 * three answers, and the one that disagreed would be the one somebody
 * acted on.
 *
 * READ-ONLY, AND NOT A SEAM TO IMPLEMENT. Exactly one implementation
 * exists, in the area bundle; a module type-hints the interface and is
 * wired to it by name.
 */
interface PresenceProviderInterface
{
    /**
     * EVERY PERSON WHO REPORTED THIS DAY IN THIS AREA, as their day
     * reads now.
     *
     * A person who reported nothing is not in the answer: who was
     * SUPPOSED to be on is the roster's question, and the area does not
     * hold it. A caller with a roster joins the two — the absence of a
     * row against a rostered watch is exactly the "no check-in" a board
     * draws.
     *
     * @param string $localDate `2026-09-19`, the ranger's own day
     *
     * @return list<PersonDay>
     */
    public function dayIn(string $areaUuid, string $localDate): array;

    /** One person's day, or null where they reported none. */
    public function dayFor(string $areaUuid, string $personUuid, string $localDate): ?PersonDay;
}
