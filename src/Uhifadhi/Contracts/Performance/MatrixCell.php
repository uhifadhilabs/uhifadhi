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
 * ONE CELL OF ONE DEPARTMENT'S ROW — a figure, or a run of states.
 *
 * MOST COLUMNS MEASURE AND SOME COUNT STATES. A department's goals pace
 * is four goals in four states, and averaging them would answer a
 * question nobody asked; such a cell carries {@see CellMark}s instead of
 * a value, and the renderer draws chips where it would otherwise draw a
 * number. A cell never carries both — a column is one kind of question.
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
        /**
         * WHERE THE COLUMN COUNTS STATES RATHER THAN MEASURING. Empty on
         * every ordinary cell.
         *
         * @var list<CellMark>
         */
        public array $marks = [],
    ) {
    }

    /** A cell that reads as a run of states — the goals pace, and its like. */
    public static function marking(CellMark ...$marks): self
    {
        return new self(marks: array_values($marks));
    }

    public static function notMine(): self
    {
        return new self(notMine: true);
    }

    public function isKnown(): bool
    {
        return !$this->notMine && (null !== $this->value || [] !== $this->marks);
    }

    /** Whether this cell counts states rather than measuring a figure. */
    public function isMarked(): bool
    {
        return [] !== $this->marks;
    }
}
