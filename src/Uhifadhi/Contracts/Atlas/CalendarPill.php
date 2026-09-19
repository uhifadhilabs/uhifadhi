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

namespace Uhifadhi\Contracts\Atlas;

/**
 * ONE THING ON ONE DAY.
 *
 * SHORT, BECAUSE THE CELL IS. A month cell is one fixed height whatever
 * it holds, so a pill's label is a fragment — "day 2", "night 1 of 2",
 * "P-0145" — and never a sentence. What does not fit is elided by the
 * renderer; what does not belong goes on the page the pill links to.
 *
 * CLOSED IS HOLLOW. The same reading the map plate uses: filled means
 * open or live, hollow means finished — so a mark means one thing across
 * the product and a person learns it once.
 *
 * A URL IS OPTIONAL and is the module's own: the atlas cannot generate
 * an address inside a module it does not require. A pill without one is
 * a statement rather than a door, which is a legitimate thing for a day
 * to carry.
 */
final readonly class CalendarPill
{
    public function __construct(
        public string $label,
        public PillHue $hue = PillHue::Subject,
        public ?string $url = null,
        /** Finished: drawn hollow rather than filled. */
        public bool $closed = false,
        /** What a reader is told on hover, where the fragment is not enough. */
        public ?string $title = null,
    ) {
        if ('' === trim($label)) {
            throw new \InvalidArgumentException('A pill says something: its label cannot be empty.');
        }
    }
}
