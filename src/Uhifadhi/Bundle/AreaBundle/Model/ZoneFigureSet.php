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
use Uhifadhi\Contracts\Kpi\ZoneFigureProviderInterface;

/**
 * EVERY MODULE'S FIGURES FOR A SET OF ZONES, gathered into one answer.
 *
 * ONE SHAPE FOR EVERY ZONE SURFACE. The zones tab draws a row per zone, a zone
 * record draws one band, and the legend draws a key — all three read this,
 * so a module that starts publishing figures appears on all of them at once
 * and none of them can be the surface that forgot to ask.
 *
 * EMPTY IS A STATE, NOT AN OMISSION. Until a module implements the seam this
 * holds nothing, and {@see isEmpty()} is what lets a page say "no module
 * publishes figures for this zone" once, in the product's own words, rather
 * than drawing a row of dashes and leaving the reader to guess whether that
 * means nought or nobody asked.
 */
final readonly class ZoneFigureSet
{
    /**
     * @param array<string, list<DepartmentKpi>> $byZone zone uuid to every module's figures for it, in module order
     */
    public function __construct(
        public array $byZone,
        public FigurePeriod $period,
    ) {
    }

    /** @return list<DepartmentKpi> */
    public function forZone(string $zoneUuid): array
    {
        return $this->byZone[$zoneUuid] ?? [];
    }

    /**
     * THE COVERED FIGURE, BY ITS NAMED KEY. Three surfaces draw this one card
     * and none of them should be scanning a list for a label to find it.
     */
    public function covered(string $zoneUuid): ?DepartmentKpi
    {
        foreach ($this->forZone($zoneUuid) as $figure) {
            if (ZoneFigureProviderInterface::COVERED === $figure->key) {
                return $figure;
            }
        }

        return null;
    }

    /** Nobody published anything about any of these zones. */
    public function isEmpty(): bool
    {
        foreach ($this->byZone as $figures) {
            if ([] !== $figures) {
                return false;
            }
        }

        return true;
    }
}
