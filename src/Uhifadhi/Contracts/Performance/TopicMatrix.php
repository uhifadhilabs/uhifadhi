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
 * A TOPIC'S MATRIX: one row per department the topic applies to, one
 * column per figure it publishes.
 *
 * A DEPARTMENT IS FOLDED OUT WHERE THE TOPIC DOES NOT APPLY TO IT, not
 * drawn as a row of empties: a department that does not read Patrols is
 * not a Patrols row full of dashes, it is not a Patrols row at all. The
 * host's own topics list every department, because every department has
 * seats and any department may declare a goal.
 *
 * THE TINT IS THE HOST'S TO DRAW and this says nothing about it: a
 * provider publishes figures, and how a placing is coloured — and whether
 * there are enough figures in a band to place anything at all — is one
 * decision made once, on the page.
 */
final readonly class TopicMatrix
{
    /**
     * @param list<MatrixColumn> $columns
     * @param list<MatrixRow>    $rows
     */
    public function __construct(
        public array $columns,
        public array $rows,
        public string $caption = '',
    ) {
    }

    public function isEmpty(): bool
    {
        return [] === $this->rows;
    }
}
