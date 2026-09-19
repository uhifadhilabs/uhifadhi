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
 * THE CATEGORY IS THE THING'S OWN, and it is the same one the plate drew. A dot
 * beside an option is only worth drawing while it matches the ground.
 */
final readonly class FilterOption
{
    /**
     * @deprecated since 1.0, read {@see $cat} instead — removed in the next
     *             release. It resolves from the category, so a module reading
     *             it gets the token the palette would have given it. TWO
     *             releases rather than one: a shipped module reading a
     *             property that vanished is a 500 on somebody else's page.
     */
    public ?string $hue;

    public function __construct(
        public string $value,
        public string $label,
        public int $count = 0,
        /** The category this option's thing wears, 1 to 9; null where it wears none. */
        public ?int $cat = null,
    ) {
        $this->hue = null === $cat ? null : \sprintf('var(--cat-%d)', $cat);
    }
}
