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

namespace Uhifadhi\Bundle\AreaBundle\Service;

use Uhifadhi\Bundle\AreaBundle\Model\StationFigureSet;
use Uhifadhi\Contracts\Kpi\DepartmentKpi;
use Uhifadhi\Contracts\Kpi\FigurePeriod;
use Uhifadhi\Contracts\Kpi\StationFigureProviderInterface;
use Uhifadhi\Contracts\Kpi\StationFigureRequest;
use Uhifadhi\Contracts\Kpi\StationRef;

/**
 * THE CORE ASKS THE MODULES THAT ARE SWITCHED ON, AND NOBODY ELSE.
 *
 * NO MODULE IS NAMED HERE, and that is the whole design. A module that reports
 * on a post tags a provider in its own extension; this reads the tag, asks the
 * area's ledger which of those modules the area actually runs, and gathers the
 * answers. The dock on a station record is four rows from four modules and not
 * one line in this file naming any of them.
 *
 * THE LEDGER IS ASKED THROUGH A CALLABLE, not through the registry's service.
 * The question is "does this area run this module", the registry answers it,
 * and a collector that imported that service would make a reader of zone
 * figures impossible to unit-test and would bind the area's figures to the
 * registry being installed. The caller already has both.
 *
 * ONE CALL PER MODULE FOR THE WHOLE SET. Each provider is handed every zone at
 * once; asking zone by zone would be a round trip per zone per module per page
 * and the first spatial provider would make the page unaffordable.
 *
 * A PROVIDER THAT THROWS IS NOT ALLOWED TO TAKE THE PAGE WITH IT. A zone page
 * is the area's, and a module's query failing is that module's figures
 * missing — which every surface already renders as an honest absence.
 */
final readonly class StationFigureService
{
    /** @param iterable<StationFigureProviderInterface> $providers */
    public function __construct(
        private iterable $providers,
    ) {
    }

    /**
     * @param list<StationRef>       $stations every station the caller is about to draw
     * @param \Closure(string): bool $running  answers whether the area runs the module with that slug
     */
    public function collect(array $stations, FigurePeriod $period, \Closure $running): StationFigureSet
    {
        if ([] === $stations) {
            return new StationFigureSet([], $period);
        }

        $request = new StationFigureRequest($stations, $period);

        /** @var array<string, list<DepartmentKpi>> $byStation */
        $byStation = array_fill_keys($request->stationUuids(), []);
        $covered = $period;

        foreach ($this->providers as $provider) {
            if (!$running($provider->moduleSlug())) {
                continue;
            }

            $figures = $provider->figuresFor($request);
            $covered = $figures->period;

            foreach ($byStation as $uuid => $already) {
                $byStation[$uuid] = [...$already, ...$figures->forStation($uuid)];
            }
        }

        return new StationFigureSet($byStation, $covered);
    }
}
