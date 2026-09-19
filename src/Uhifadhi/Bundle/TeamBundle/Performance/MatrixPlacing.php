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

use Uhifadhi\Contracts\Performance\ColumnPolarity;
use Uhifadhi\Contracts\Performance\MatrixCell;
use Uhifadhi\Contracts\Performance\MatrixRow;
use Uhifadhi\Contracts\Performance\TopicMatrix;

/**
 * WHERE A DEPARTMENT STANDS IN ONE COLUMN OF ONE BAND.
 *
 * THE TINT IS THE HOST'S, AND THIS IS THE HOST. A provider publishes
 * figures and says which way is good; whether 61 % is a lead or a trail
 * depends on what the others published, which only the page can see. It
 * is worked out in one place so that two topics cannot disagree about
 * what a shade means, and a module cannot colour itself well.
 *
 * FOUR RULES, AND THEY ARE ALL REFUSALS:
 *
 *  - A COLUMN THAT MAKES NO CLAIM IS NEVER TINTED. `Positions` is the
 *    size of a department, and a department shaded red for being small
 *    has been accused of something nobody measured.
 *  - A BAND IS THE BOUNDARY. Org-wide departments are placed among
 *    org-wide ones and an area's among that area's, because a rank
 *    across two kinds of department is a rank of nothing.
 *  - FEWER THAN {@see FEWEST} FIGURES PLACE NOTHING. First-and-last out
 *    of two is a verdict on a field of two.
 *  - AN ABSENCE IS NOT A FIGURE, and neither is a run of states: a cell
 *    nobody published, a column that is not this department's and a
 *    goals pace are all left out of the ranking and out of the count.
 */
final readonly class MatrixPlacing
{
    /** Below this many figures in a band, a column is not placed at all. */
    public const int FEWEST = 3;

    /** The scale, best first — a reader learns it once. */
    private const array SCALE = ['h5', 'h4', 'h3', 'h2', 'h1'];

    /**
     * Every row's tints, keyed by the row's department and then by column.
     * A row with nothing placed keeps an empty array rather than dropping
     * out, so a template can read every row the same way.
     *
     * @return array<string, array<string, string>>
     */
    public function forMatrix(TopicMatrix $matrix): array
    {
        $tints = [];
        foreach ($matrix->rows as $row) {
            $tints[$row->departmentUuid] = [];
        }

        foreach ($matrix->columns as $column) {
            if (!$column->polarity->judges()) {
                continue;
            }

            foreach (self::bands($matrix->rows) as $band) {
                foreach ($this->place($band, $column->key, $column->polarity) as $uuid => $tint) {
                    $tints[$uuid][$column->key] = $tint;
                }
            }
        }

        return $tints;
    }

    /**
     * One column of one band, placed. Empty where the band is too thin.
     *
     * @param list<MatrixRow> $rows
     *
     * @return array<string, string>
     */
    private function place(array $rows, string $key, ColumnPolarity $polarity): array
    {
        $figures = [];
        foreach ($rows as $row) {
            $cell = $row->cells[$key] ?? null;
            if (!$cell instanceof MatrixCell || $cell->isMarked() || null === $cell->value) {
                continue;
            }

            $figures[$row->departmentUuid] = $cell->value;
        }

        $count = \count($figures);
        if ($count < self::FEWEST) {
            return [];
        }

        // Best first, whichever way this column counts.
        uasort(
            $figures,
            static fn (float $a, float $b): int => ColumnPolarity::Down === $polarity ? $a <=> $b : $b <=> $a,
        );

        $tints = [];
        $place = 0;
        $seen = 0;
        $previous = null;
        foreach ($figures as $uuid => $value) {
            // AN EQUAL FIGURE TAKES AN EQUAL PLACE: the tie shares the
            // first place of its group, and the group still consumes the
            // places it occupies.
            if ($value !== $previous) {
                $place = $seen;
                $previous = $value;
            }

            $tints[$uuid] = self::SCALE[self::step($place, $count)];
            ++$seen;
        }

        return $tints;
    }

    /**
     * Which shade a place takes, spread so that the leader is always the
     * top of the scale and the trailer always the bottom, however many
     * are being placed.
     */
    private static function step(int $place, int $count): int
    {
        $last = \count(self::SCALE) - 1;

        return (int) round($place * $last / ($count - 1));
    }

    /**
     * The rows grouped by what each is placed among, in the order they
     * arrived — the provider's order is the page's order.
     *
     * @param list<MatrixRow> $rows
     *
     * @return list<list<MatrixRow>>
     */
    private static function bands(array $rows): array
    {
        $bands = [];
        foreach ($rows as $row) {
            $bands[$row->band][] = $row;
        }

        return array_values($bands);
    }
}
