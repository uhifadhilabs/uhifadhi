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
 * ONE OPTION OF ONE GROUPED DROPDOWN — what a reader picks, what it is
 * called, how many it would leave and, where the thing has one, its colour.
 *
 * THE VALUE AND THE LABEL ARE TWO FACTS, not one. A role filters by the word
 * it is spelt with, but a zone filters by an identifier and reads as a name;
 * a control that assumed those were the same could only ever offer the first
 * kind, and every surface that needed the second would grow its own bar.
 *
 * THE HUE IS THE THING'S OWN, and it is the same hue the plate drew. A dot
 * beside an option is only worth drawing while it matches the ground.
 */
final readonly class FilterOption
{
    public function __construct(
        public string $value,
        public string $label,
        public int $count = 0,
        public ?string $hue = null,
    ) {
    }
}
