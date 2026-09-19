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

use Uhifadhi\Contracts\Kpi\FigurePeriod;

/**
 * A MODULE'S FIGURES OVER THE GROUND, for the performance page's plates.
 *
 * SEPARATE FROM THE TOPIC CONTRACT, AND OPTIONAL — the same shape as
 * {@see \Uhifadhi\Bundle\AreaBundle\Overview\ContributesStylesheetInterface}:
 * a topic publishes five figures, its charts and its matrix, and most
 * topics have nothing to say about WHERE. Staffing does not; goals do
 * not. Adding `geo()` to the topic contract would make every module
 * answer a question it has no answer to, which is a contract that has
 * started guessing.
 *
 * A MODULE THAT DOES HAVE GROUND FIGURES implements this beside its
 * topic, and the page draws them on the atlas plate — the same plate,
 * the same chrome, the same legend as every other map in the product.
 * Until a module implements it the card says so in the house's own
 * words: nobody publishes ground figures yet, which is not an empty map.
 *
 * THE SERIES SAYS WHAT GROUND IT IS OVER — areas, or the zones of one
 * area — because the two are different plates and the page must not have
 * to match uuids against two tables to find out which it was handed.
 */
interface PerformanceGeoProviderInterface
{
    /** The tag that puts a module's ground figures on the page. */
    public const string TAG = 'uhifadhi.performance_geo';

    /**
     * WHOSE FIGURES THESE ARE — the module's slug, the same one its topic
     * and its {@see \Uhifadhi\Contracts\ModuleProviderInterface} return.
     */
    public function moduleSlug(): string;

    /**
     * WHAT THIS MODULE HAS TO SAY ABOUT THE GROUND, in this scope and
     * this period. An empty list is a legitimate answer.
     *
     * @return list<GeoSeries>
     */
    public function geo(PerformanceScope $scope, FigurePeriod $period): array;
}
