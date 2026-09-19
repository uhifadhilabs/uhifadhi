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

/** ONE DEPARTMENT'S ROW, drawn. */
final readonly class MatrixViewRow
{
    /** @param list<MatrixViewCell> $cells in the columns' order, one per column */
    public function __construct(
        public string $uuid,
        public string $name,
        public string $mark,
        public array $cells,
        public ?string $url = null,
        /** What the row says under its name — "3 positions · org-wide". */
        public string $note = '',
    ) {
    }
}
