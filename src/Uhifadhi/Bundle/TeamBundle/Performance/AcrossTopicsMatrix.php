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

use Uhifadhi\Contracts\Kpi\FigurePeriod;
use Uhifadhi\Contracts\Performance\DepartmentDirectory;
use Uhifadhi\Contracts\Performance\DepartmentEntry;
use Uhifadhi\Contracts\Performance\MatrixCell;
use Uhifadhi\Contracts\Performance\MatrixColumn;
use Uhifadhi\Contracts\Performance\MatrixRow;
use Uhifadhi\Contracts\Performance\PerformanceScope;
use Uhifadhi\Contracts\Performance\PerformanceTopicProviderInterface;
use Uhifadhi\Contracts\Performance\TopicMatrix;

/**
 * DEPARTMENTS ACROSS THE TOPICS — the organization's own matrix, and
 * the only one whose columns are topics rather than figures.
 *
 * IT ANSWERS A DIFFERENT QUESTION FROM A TOPIC'S OWN MATRIX. A topic's
 * matrix says how each department is doing at patrols; this one says
 * where each department is reading at all, which is what somebody
 * opening the organization's page wants before any of the rest means
 * anything. The same renderer draws both, because it is the same grammar
 * — one cell a department, the placing inside one column and one band.
 *
 * IT IS BUILT FROM THE TOPICS AND NEVER FROM A QUERY. A module that
 * publishes a topic gets a column here the same day, and the host writes
 * no column for it — which is the whole of what makes adding a module a
 * change to this page and to nothing else.
 *
 * A TOPIC'S FIRST COLUMN IS ITS HEADLINE. The topic already decided
 * which of its figures leads its own matrix, and the overview does not
 * get a second opinion about somebody else's figures; picking by label
 * would be the host matching words a module chose.
 *
 * EVERY DEPARTMENT IS A ROW. A department a topic does not cover is the
 * honest dash in that topic's column — it attached nothing of the kind,
 * so it was never asked — and not a row missing from the organization's
 * own list.
 */
final readonly class AcrossTopicsMatrix
{
    /** What a band of org-wide departments reads, and what an area's does. */
    private const string ORG_BAND = 'each reads every area';
    private const string AREA_BAND = 'each reads one area only';

    /**
     * @param list<PerformanceTopicProviderInterface> $topics in the page's order
     * @param (\Closure(string): ?string)|null        $urlFor the way into one department, by uuid
     */
    public function build(
        array $topics,
        DepartmentDirectory $directory,
        PerformanceScope $scope,
        FigurePeriod $period,
        ?\Closure $urlFor = null,
    ): TopicMatrix {
        $columns = [];
        /** @var array<string, array<string, MatrixCell>> $published topic key to its cells by department */
        $published = [];

        foreach ($topics as $topic) {
            $matrix = $topic->matrix($scope, $period);
            $lead = $matrix->columns[0] ?? null;
            if (null === $lead) {
                // A TOPIC WITH NOTHING TO SHOW is not an empty column: a
                // column of dashes would say every department scored
                // nothing at something nobody measured.
                continue;
            }

            $columns[] = new MatrixColumn(
                key: $topic->key(),
                label: $topic->title(),
                unit: $lead->unit,
                // WHICH OF THE TOPIC'S FIGURES THIS IS. A reader looking
                // at a column called "Patrols" has to be told the number
                // under it is coverage.
                caption: $lead->label,
                polarity: $lead->polarity,
                total: $lead->total,
                totalDelta: $lead->totalDelta,
            );

            $cells = [];
            foreach ($matrix->rows as $row) {
                $cells[$row->departmentUuid] = $row->cells[$lead->key] ?? new MatrixCell();
            }

            $published[$topic->key()] = $cells;
        }

        $rows = [];
        $bandNotes = [];
        foreach ($directory->entries as $entry) {
            $cells = [];
            foreach ($published as $key => $byDepartment) {
                $cells[$key] = $byDepartment[$entry->uuid] ?? MatrixCell::notMine();
            }

            $bandNotes[$entry->band] = null === $entry->areaUuid ? self::ORG_BAND : self::AREA_BAND;

            $rows[] = new MatrixRow(
                departmentUuid: $entry->uuid,
                departmentName: $entry->name,
                cells: $cells,
                band: $entry->band,
                mark: $entry->mark,
                url: null === $urlFor ? null : $urlFor($entry->uuid),
                note: self::note($entry),
            );
        }

        return new TopicMatrix(
            $columns,
            $rows,
            'one cell a department in a topic',
            $bandNotes,
        );
    }

    /**
     * WHAT A DEPARTMENT ATTACHES, said on its row — the line that makes
     * the dashes beside it readable. A department with no module cannot
     * have a module figure, and a row that did not say so would leave a
     * reader to infer it from a line of dashes.
     */
    private static function note(DepartmentEntry $entry): string
    {
        $attached = \count($entry->attached);

        return match (true) {
            0 === $attached => 'no module attached',
            1 === $attached => '1 module',
            default => \sprintf('%d modules', $attached),
        };
    }
}
