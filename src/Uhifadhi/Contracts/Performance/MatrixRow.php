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
 * ONE DEPARTMENT'S ROW IN A TOPIC'S MATRIX.
 *
 * THE BAND IS THE PLACING'S BOUNDARY. A tint is a placing inside one
 * column AND one band — org-wide departments among org-wide ones, an
 * area's among that area's — because a rank across two kinds of
 * department is a rank of nothing.
 */
final readonly class MatrixRow
{
    /**
     * @param array<string, MatrixCell> $cells keyed by the column's key
     */
    public function __construct(
        public string $departmentUuid,
        public string $departmentName,
        public array $cells,
        /** "Org-wide", or the area's name — what the row is placed among. */
        public string $band = '',
        /** The department's two letters, as every surface draws them. */
        public string $mark = '',
        /** Where the row's Open → goes. */
        public ?string $url = null,
    ) {
    }
}
