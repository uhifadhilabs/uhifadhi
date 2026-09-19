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

namespace Uhifadhi\Bundle\AreaBundle\Model;

use Uhifadhi\Contracts\Kpi\DepartmentKpi;
use Uhifadhi\Contracts\Kpi\FigurePeriod;
use Uhifadhi\Contracts\Kpi\StationFigureProviderInterface;

/**
 * EVERY MODULE'S FIGURES FOR A SET OF STATIONS, gathered into one answer.
 *
 * ONE SHAPE FOR EVERY STATION SURFACE. A record's dock draws a row per
 * module, the stations tab draws a column, and both read this — so a module
 * that starts publishing appears on both at once and neither can be the
 * surface that forgot to ask.
 *
 * EMPTY IS A STATE, NOT AN OMISSION. Until a module implements the seam this
 * holds nothing, and {@see isEmpty()} is what lets the dock say "no module
 * publishes figures for this post" once, in the product's own words, rather
 * than drawing four empty rows and leaving the reader to guess whether that
 * means nought or nobody asked.
 */
final readonly class StationFigureSet
{
    /**
     * @param array<string, list<DepartmentKpi>> $byStation station uuid to every module's figures for it, in module order
     */
    public function __construct(
        public array $byStation,
        public FigurePeriod $period,
    ) {
    }

    /** @return list<DepartmentKpi> */
    public function forStation(string $stationUuid): array
    {
        return $this->byStation[$stationUuid] ?? [];
    }

    /**
     * THE DOCK'S ROWS FOR ONE POST: each module's headline figure, in the
     * order the modules were asked. A module that published something under
     * another key is not a dock row — the dock draws one row per module and
     * the key is how it knows which figure that is.
     *
     * @return list<DepartmentKpi>
     */
    public function dockFor(string $stationUuid): array
    {
        $rows = [];
        foreach ($this->forStation($stationUuid) as $figure) {
            if (StationFigureProviderInterface::HEADLINE === $figure->key) {
                $rows[] = $figure;
            }
        }

        return $rows;
    }

    /** Nobody published anything about any of these zones. */
    public function isEmpty(): bool
    {
        foreach ($this->byStation as $figures) {
            if ([] !== $figures) {
                return false;
            }
        }

        return true;
    }
}
