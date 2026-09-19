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

namespace Uhifadhi\Bundle\AreaBundle\Model;

/**
 * ONE POST AS A CARD — what an open zone shows about each station standing on
 * its ground: who is there, who leads, and the way through to the record.
 *
 * THE FACES ARE CAPPED AND THE COUNT IS NOT. A card inside a card must stay
 * the same height whether three people are posted or thirty, so the stack
 * draws {@see FACES} of them and the line beside it states the whole. A card
 * that grew with its data would push every zone below it down the page.
 *
 * NOBODY POSTED IS A STATE, NOT A GAP. A post is recorded before it is
 * staffed as a matter of course — the point is surveyed, the people arrive
 * later — so the card says so in words rather than drawing an empty stack.
 */
final readonly class StationCard
{
    /** How many faces the stack draws before it stops and lets the count speak. */
    public const int FACES = 6;

    /** @param list<PostingRow> $faces the people drawn, never more than {@see FACES} */
    public function __construct(
        public string $uuid,
        public string $name,
        public ?string $code,
        public int $posted,
        public ?string $leaderName,
        public array $faces,
    ) {
    }

    /** People posted here beyond the faces the stack drew. */
    public function beyondTheFaces(): int
    {
        return max(0, $this->posted - \count($this->faces));
    }
}
