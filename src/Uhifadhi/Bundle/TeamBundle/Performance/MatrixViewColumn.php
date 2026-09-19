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
 * ONE COLUMN'S HEADER, written out.
 *
 * The total is the column's own — published, never summed by the page —
 * and it arrives here already printed, so the header has nothing to work
 * out either.
 */
final readonly class MatrixViewColumn
{
    public function __construct(
        public string $key,
        public string $label,
        public string $unit = '',
        public string $caption = '',
        /** What the whole column comes to, printed; '' where none was published. */
        public string $total = '',
        public string $totalDelta = '',
        /** 'good', 'bad', 'flat' or '' — never a colour. */
        public string $totalTone = '',
    ) {
    }
}
