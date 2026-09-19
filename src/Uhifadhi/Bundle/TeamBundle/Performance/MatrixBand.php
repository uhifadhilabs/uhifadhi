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

/**
 * THE DEPARTMENTS A PLACING IS MADE AMONG: org-wide ones, or one area's.
 *
 * It is a band and not a heading: the rows under it are ranked against
 * each other and against nobody else, and the page draws the boundary so
 * a reader can see what a tint was measured against.
 */
final readonly class MatrixBand
{
    /** @param list<MatrixViewRow> $rows */
    public function __construct(
        public string $name,
        public array $rows,
        /** What the band says about itself — "each reads every area". */
        public string $note = '',
    ) {
    }

    public function count(): int
    {
        return \count($this->rows);
    }
}
