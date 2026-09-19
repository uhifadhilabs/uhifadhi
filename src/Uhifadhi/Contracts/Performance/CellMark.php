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

namespace Uhifadhi\Contracts\Performance;

/**
 * ONE STATE IN A CELL THAT COUNTS STATES RATHER THAN MEASURING A FIGURE.
 *
 * NOT EVERY COLUMN IS A NUMBER. A department's goals PACE — met, met
 * early, at risk, missed — and a run of four goals is four states, not
 * an average of them. Averaging would answer a question nobody asked;
 * a figure a reader cannot get back to the goals from is worse than no
 * column.
 *
 * THE WORD IS THE PUBLISHER'S AND THE TONE IS THE PLATFORM'S, the same
 * bargain a calendar pill strikes: a topic says what happened and how
 * it reads, and the renderer owns what "good" looks like here.
 */
final readonly class CellMark
{
    public function __construct(
        /** What the chip says — "met", "at risk", "missed". */
        public string $label,
        public ColumnPolarity $reads = ColumnPolarity::None,
        /** What a reader is told on hover, where the word is not enough. */
        public ?string $title = null,
    ) {
        if ('' === trim($label)) {
            throw new \InvalidArgumentException('A mark says something: its label cannot be empty.');
        }
    }
}
