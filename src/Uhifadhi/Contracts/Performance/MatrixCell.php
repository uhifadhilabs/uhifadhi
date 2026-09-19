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
 * ONE FIGURE IN ONE DEPARTMENT'S ROW.
 *
 * THREE EMPTINESSES, AND THE PAGE DRAWS THEM DIFFERENTLY. `notMine` is a
 * column that does not apply to this department at all — it attaches no
 * module that computes it, and a dash there is honest; `null` with a
 * module attached is "no KPI yet", which is a module that has published
 * nothing; and a real nought is a measurement. Collapsing any two of them
 * turns a department that was never asked into a department that scored
 * nothing.
 */
final readonly class MatrixCell
{
    /**
     * @param list<float|null> $history six periods, oldest first, holes kept
     */
    public function __construct(
        public ?float $value = null,
        public ?float $delta = null,
        public array $history = [],
        /** True where this column is not this department's to answer. */
        public bool $notMine = false,
    ) {
    }

    public static function notMine(): self
    {
        return new self(notMine: true);
    }

    public function isKnown(): bool
    {
        return !$this->notMine && null !== $this->value;
    }
}
