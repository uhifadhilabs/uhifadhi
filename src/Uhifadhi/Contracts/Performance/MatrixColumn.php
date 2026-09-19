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
 * ONE COLUMN OF A TOPIC'S MATRIX: what it is called, what it is counted
 * in, and which way is good.
 */
final readonly class MatrixColumn
{
    public function __construct(
        /** The published name — `staffing.filled`, `patrols.covered`. */
        public string $key,
        public string $label,
        public string $unit = '',
        public ColumnPolarity $polarity = ColumnPolarity::None,
        /** What the column means, for the header's own title. */
        public string $caption = '',
        /**
         * WHAT THE HOST MAY DO WITH THIS COLUMN, where it has to find a
         * figure PER DEPARTMENT among somebody else's columns — see
         * {@see KpiRole}. Null is the normal case: the column is the
         * topic's own and the host draws it without interpreting it.
         */
        public ?KpiRole $role = null,
        /**
         * WHAT THE WHOLE COLUMN COMES TO, published rather than summed.
         * A host that added the cells up would be right about seats and
         * wrong about days-to-settle, where a sum of averages is a
         * number nobody measured — so the topic that knows says, and a
         * column that stays silent simply has no total in its header.
         */
        public ?float $total = null,
        /** The total's movement on the compared period; null where there is nothing to compare with. */
        public ?float $totalDelta = null,
    ) {
    }
}
