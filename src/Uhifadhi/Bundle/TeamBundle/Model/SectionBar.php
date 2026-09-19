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
 * ONE ROW OF A RANKED BAR CARD — the thing, how much of it is filled, and the
 * figure read off the end of the bar rather than off an axis.
 *
 * THE WIDTHS ARE COMPUTED HERE AND NOT IN THE TEMPLATE. A percentage worked out
 * in Twig is a percentage nothing can test; worked out here it is one method
 * with one specification, and the template writes the number it is handed.
 */
final readonly class SectionBar
{
    public function __construct(
        public string $label,
        public int $value,
        public int $total,
        public int $people,
        public string $note,
        public float $filledWidth = 0.0,
        public float $restWidth = 0.0,
    ) {
    }

    /** The same row, with its two widths taken against the largest row's total. */
    public function scaledTo(int $largest): self
    {
        if ($largest <= 0 || 0 === $this->total) {
            return $this;
        }

        return new self(
            label: $this->label,
            value: $this->value,
            total: $this->total,
            people: $this->people,
            note: $this->note,
            filledWidth: round($this->value / $largest * 100, 1),
            restWidth: round(max(0, $this->total - $this->value) / $largest * 100, 1),
        );
    }

    /** A row with nothing in it is drawn quiet, and says why instead of drawing a bar. */
    public function isQuiet(): bool
    {
        return 0 === $this->total;
    }
}
