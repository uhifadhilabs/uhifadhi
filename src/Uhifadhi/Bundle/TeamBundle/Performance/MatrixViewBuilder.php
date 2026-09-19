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

use Uhifadhi\Contracts\Performance\CellMark;
use Uhifadhi\Contracts\Performance\ColumnPolarity;
use Uhifadhi\Contracts\Performance\MatrixCell;
use Uhifadhi\Contracts\Performance\MatrixColumn;
use Uhifadhi\Contracts\Performance\MatrixRow;
use Uhifadhi\Contracts\Performance\TopicMatrix;

/**
 * A PUBLISHED MATRIX, TURNED INTO SOMETHING A TEMPLATE CAN ONLY WRITE
 * DOWN.
 *
 * EVERY DECISION IS MADE HERE. What a figure looks like printed, which
 * of the three absences a cell is and what it says, whether a movement
 * reads well, where the sparkline breaks, what tone a state chip wears —
 * a template that decided any of it would decide it differently on the
 * next page, and a module that decided it would decide it differently
 * from the host.
 *
 * ONE SHAPE FOR BOTH KINDS OF CELL. A figure and a run of states are the
 * same {@see MatrixViewCell} with a different {@see CellKind}, so the
 * template has one rule for a cell and the grid cannot drift between the
 * two.
 */
final readonly class MatrixViewBuilder
{
    /** The sparkline's box, as the design draws it. */
    private const float SPARK_WIDTH = 70.0;
    private const float SPARK_HEIGHT = 18.0;

    public function __construct(
        private MatrixPlacing $placing,
    ) {
    }

    public function build(TopicMatrix $matrix): MatrixView
    {
        $tints = $this->placing->forMatrix($matrix);

        /** @var array<string, list<MatrixViewRow>> $bands */
        $bands = [];
        foreach ($matrix->rows as $row) {
            $bands[$row->band][] = $this->row($row, $matrix->columns, $tints[$row->departmentUuid] ?? []);
        }

        $built = [];
        foreach ($bands as $name => $rows) {
            $built[] = new MatrixBand((string) $name, $rows, $matrix->bandNotes[(string) $name] ?? '');
        }

        return new MatrixView(array_map(self::column(...), $matrix->columns), $built, $matrix->caption);
    }

    private static function column(MatrixColumn $column): MatrixViewColumn
    {
        return new MatrixViewColumn(
            $column->key,
            $column->label,
            $column->unit,
            $column->caption,
            null === $column->total ? '' : self::figure($column->total),
            self::delta($column->totalDelta),
            self::tone($column->totalDelta, $column->polarity),
        );
    }

    /**
     * @param list<MatrixColumn>    $columns
     * @param array<string, string> $tints
     */
    private function row(MatrixRow $row, array $columns, array $tints): MatrixViewRow
    {
        $cells = [];
        foreach ($columns as $column) {
            $cell = $row->cells[$column->key] ?? new MatrixCell();
            $cells[] = $this->cell($cell, $column, $tints[$column->key] ?? '');
        }

        return new MatrixViewRow(
            $row->departmentUuid,
            $row->departmentName,
            $row->mark,
            $cells,
            $row->url,
            $row->note,
        );
    }

    private function cell(MatrixCell $cell, MatrixColumn $column, string $tint): MatrixViewCell
    {
        // A COLUMN THAT IS NOT THIS DEPARTMENT'S. Not a nought, not a
        // silence — a question this department was never asked.
        if ($cell->notMine) {
            return new MatrixViewCell(
                CellKind::Blank,
                word: 'not its topic',
                title: \sprintf("%s is not one of this department\u{2019}s topics", $column->label),
            );
        }

        if ($cell->isMarked()) {
            return new MatrixViewCell(
                CellKind::Marks,
                marks: array_map(self::chip(...), $cell->marks),
                title: $column->caption,
            );
        }

        // A MODULE THAT HAS PUBLISHED NOTHING. There is a module and it
        // is silent, which is not the same as a nought it measured.
        if (null === $cell->value) {
            return new MatrixViewCell(
                CellKind::Blank,
                word: 'no figure',
                title: 'No figure to read',
            );
        }

        return new MatrixViewCell(
            CellKind::Figure,
            tint: $tint,
            figure: self::figure($cell->value),
            unit: $column->unit,
            delta: self::delta($cell->delta),
            deltaTone: self::tone($cell->delta, $column->polarity),
            spark: self::spark($cell->history),
            sparkTone: self::sparkTone($cell->delta, $column->polarity),
            title: $column->caption,
            sort: $cell->value,
        );
    }

    private static function chip(CellMark $mark): CellChip
    {
        return new CellChip(
            $mark->label,
            match ($mark->reads) {
                ColumnPolarity::Up => 'good',
                ColumnPolarity::Down => 'bad',
                ColumnPolarity::None => '',
            },
            $mark->title ?? '',
        );
    }

    /** As every plate prints one: thousands separated, a fraction kept to one place. */
    private static function figure(float $value): string
    {
        $whole = round($value) === round($value, 1);

        return number_format($value, $whole ? 0 : 1, '.', ',');
    }

    /** "+2", "−2" with a real minus sign, or the words for a figure that did not move. */
    private static function delta(?float $delta): string
    {
        if (null === $delta) {
            return '';
        }
        if (0.0 === $delta) {
            return 'no change';
        }

        $magnitude = number_format(abs($delta), abs($delta) === round(abs($delta)) ? 0 : 1, '.', ',');

        return ($delta < 0 ? "\u{2212}" : '+').$magnitude;
    }

    /** Which way a movement reads, according to the column and never to its sign. */
    private static function tone(?float $delta, ColumnPolarity $polarity): string
    {
        if (null === $delta) {
            return '';
        }
        if (0.0 === $delta) {
            return 'flat';
        }

        return match ($polarity->isGood($delta)) {
            true => 'good',
            false => 'bad',
            null => '',
        };
    }

    private static function sparkTone(?float $delta, ColumnPolarity $polarity): string
    {
        return match (self::tone($delta, $polarity)) {
            'good' => 'up',
            'bad' => 'dn',
            default => 'fl',
        };
    }

    /**
     * THE HISTORY AS A LINE, WITH ITS HOLES LEFT OPEN. A period nobody
     * wrote down is not a nought on the line: the line stops there and
     * starts again after it, so the gap is something a reader can see
     * rather than a dip somebody measured.
     *
     * Every run is scaled against the WHOLE history, so two runs of the
     * same series are on one scale and the break is the only thing the
     * eye has to read.
     *
     * @param list<float|null> $history
     *
     * @return list<string> one polyline's points per unbroken run
     */
    private static function spark(array $history): array
    {
        $readings = array_values(array_filter($history, static fn (?float $point): bool => null !== $point));
        $count = \count($history);
        if (\count($readings) < 2 || $count < 2) {
            return [];
        }

        $low = min($readings);
        $high = max($readings);
        $range = $high - $low;
        $top = 3.0;
        $bottom = self::SPARK_HEIGHT - 3.0;

        $runs = [];
        $run = [];
        foreach ($history as $index => $reading) {
            if (null === $reading) {
                if (\count($run) > 1) {
                    $runs[] = implode(' ', $run);
                }
                $run = [];

                continue;
            }

            $x = self::SPARK_WIDTH * $index / ($count - 1);
            // A flat series sits on the baseline rather than dividing by zero.
            $y = 0.0 === $range ? $bottom : $bottom - ($reading - $low) / $range * ($bottom - $top);
            $run[] = \sprintf('%.1f,%.1f', $x, $y);
        }

        if (\count($run) > 1) {
            $runs[] = implode(' ', $run);
        }

        return $runs;
    }
}
