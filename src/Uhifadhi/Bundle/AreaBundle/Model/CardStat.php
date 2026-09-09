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
 * ONE FIGURE ON A REGISTER CARD — a value and what it counts, and nothing the
 * the area page understands about either.
 *
 * The register card carries a handful of these: the area page's own "modules live",
 * and the operational figures a module contributed for the area. They are the
 * SAME now-tiles the area overview draws, read at register density — a value and
 * a short label — so a number can never read one way on a card and another on
 * the overview. The area page lays them out; it does not know that "23" is patrols
 * and "7" is incidents, which is what lets a module leave without a hard-coded
 * cell behind.
 */
final readonly class CardStat
{
    public function __construct(
        public string $value,
        public string $label,
    ) {
        if ('' === $value || '' === $label) {
            throw new \InvalidArgumentException('A card stat needs a value and a label.');
        }
    }
}
