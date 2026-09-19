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

namespace Uhifadhi\Bundle\TeamBundle\Performance;

/** A WHOLE MATRIX, ready to draw. */
final readonly class MatrixView
{
    /**
     * @param list<MatrixViewColumn> $columns
     * @param list<MatrixBand>       $bands
     */
    public function __construct(
        public array $columns,
        public array $bands,
        public string $caption = '',
    ) {
    }

    public function isEmpty(): bool
    {
        return [] === $this->bands;
    }

    /** How many departments the matrix draws, across every band. */
    public function rowCount(): int
    {
        $rows = 0;
        foreach ($this->bands as $band) {
            $rows += $band->count();
        }

        return $rows;
    }
}
