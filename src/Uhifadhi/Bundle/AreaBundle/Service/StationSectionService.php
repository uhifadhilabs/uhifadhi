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

use Uhifadhi\Bundle\AreaBundle\Model\ContributedStationSection;
use Uhifadhi\Contracts\Area\StationSectionRequest;
use Uhifadhi\Contracts\Area\StationSectionsInterface;
use Uhifadhi\Contracts\Area\StationSurface;
use Uhifadhi\Contracts\Kpi\StationRef;

/**
 * THE CORE ASKS THE MODULES THAT ARE SWITCHED ON, AND NOBODY ELSE.
 *
 * NO MODULE IS NAMED HERE, and that is the whole design — the same design the
 * station's figures already follow. A module with something to say about a
 * post tags a contributor in its own extension; this reads the tag, asks the
 * area's ledger which of those modules the area actually runs, and gathers
 * the answers in registration order.
 *
 * THE LEDGER IS ASKED THROUGH A CALLABLE, not through the registry's service:
 * the caller already has both, and a collector that imported the registry
 * could not be unit-tested without it.
 *
 * ONE CALL PER MODULE FOR THE WHOLE SET, because the configure page draws a
 * card per post and asking post by post would be a query per card per module
 * per page.
 *
 * THE AREA'S OWN SECTIONS COME FIRST. Contributed bands are appended after
 * whatever the surface drew itself, in the order the modules were registered
 * in — a ruled order the contributors cannot argue with.
 */
final readonly class StationSectionService
{
    /** @param iterable<StationSectionsInterface> $contributors */
    public function __construct(
        private iterable $contributors,
    ) {
    }

    /**
     * @param list<StationRef>       $stations every post the caller is about to draw
     * @param \Closure(string): bool $running  answers whether the area runs the module with that slug
     *
     * @return array<string, list<ContributedStationSection>> station uuid to its bands, in ruled order
     */
    public function collect(array $stations, StationSurface $surface, \Closure $running): array
    {
        if ([] === $stations) {
            return [];
        }

        $request = new StationSectionRequest($stations, $surface);

        /** @var array<string, list<ContributedStationSection>> $byStation */
        $byStation = array_fill_keys($request->stationUuids(), []);

        foreach ($this->contributors as $contributor) {
            $slug = $contributor->moduleSlug();
            if (!$running($slug)) {
                continue;
            }

            $contributed = $contributor->sectionsFor($request);

            foreach ($byStation as $uuid => $already) {
                foreach ($contributed->forStation($uuid) as $section) {
                    $already[] = new ContributedStationSection($slug, $section);
                }

                $byStation[$uuid] = $already;
            }
        }

        return $byStation;
    }

    /**
     * The bands for ONE post — the record page's question, which is the same
     * question with a set of one.
     *
     * @param \Closure(string): bool $running
     *
     * @return list<ContributedStationSection>
     */
    public function forOne(StationRef $station, StationSurface $surface, \Closure $running): array
    {
        return $this->collect([$station], $surface, $running)[$station->stationUuid] ?? [];
    }
}
