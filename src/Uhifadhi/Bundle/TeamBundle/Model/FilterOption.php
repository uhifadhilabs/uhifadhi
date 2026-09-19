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

namespace Uhifadhi\Bundle\TeamBundle\Model;

/**
 * ONE OPTION OF ONE GROUPED DROPDOWN — what a reader picks, what it is called
 * and how many rows carry it.
 *
 * THE VALUE AND THE LABEL ARE TWO FACTS, not one. An area filters by an
 * identifier and reads as a name; a rank filters by the word it is spelt
 * with. A control that assumed those were the same could offer only the
 * second kind, and every surface needing the first would grow its own bar.
 *
 * THE COUNT IS OF THE WHOLE SET, never of what is already filtered: a count
 * that moved as you filtered could not tell you what picking it would do,
 * which is the one thing it is for. A zero is DRAWN — an option that
 * disappeared when it emptied could not be told from one that never existed.
 */
final readonly class FilterOption
{
    public function __construct(
        public string $value,
        public string $label,
        public int $count = 0,
    ) {
    }
}
