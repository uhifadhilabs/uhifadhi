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
 * ONE OF THE FIVE KPI CARDS a section's overview opens with.
 *
 * FIVE OR NONE. A card is a figure, an optional "of N" beside it and one
 * qualifying line — never a chart and never a sentence.
 *
 * IT CARRIES NO INDEX CODE. The drawn row labels its plates DP·K1 … DP·K5 so
 * that a designer and a reviewer can name the one they mean; those are
 * workshop labels and they do not ship. A person reading the product has no
 * use for them, and a specification enforces their absence.
 *
 * THE MOVEMENT IS ABSENT UNTIL THERE IS ONE. A delta pill is computed from this
 * period and the last closed one; where no previous figure was ever written
 * there is nothing to compute, and a pill reading "0" would be a claim the
 * installation cannot make.
 */
final readonly class SectionKpi
{
    public function __construct(
        public string $label,
        public string $value,
        public ?string $of = null,
        public ?string $qualifier = null,
        public ?float $delta = null,
        public bool $hot = false,
    ) {
    }

    /** good, bad or flat — the three the pill is drawn in. */
    public function tone(): string
    {
        return match (true) {
            null === $this->delta, 0.0 === $this->delta => 'flat',
            $this->delta > 0 => 'good',
            default => 'bad',
        };
    }
}
