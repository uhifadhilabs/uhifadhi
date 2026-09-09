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

use Uhifadhi\Bundle\AreaBundle\Entity\AreaOfInterest;
use Uhifadhi\Bundle\AreaBundle\Repository\ZoneRepository;

/**
 * THE AREA'S OWN BASE CONTENT FOR THE OPERATIONAL MAP — the boundary and the
 * zones, as the GeoJSON the browser plate draws.
 *
 * This is the plate's INFRASTRUCTURE, not a module's layer: the AOI boundary is
 * always drawn, and the zones are the area's own polygons, so both belong to the
 * the area itself rather than to anything installed. Operational layers — patrol
 * tracks, open incidents, stations — arrive separately through the map-layer
 * contributions and are not this service's concern.
 *
 * The geometry travels as GeoJSON text exactly as the database returns it
 * (ST_AsGeoJSON, via the postgis type); it is never parsed in PHP. The plate's
 * Stimulus controller parses it and anything unusable is simply not drawn.
 */
final readonly class AreaMapPayload
{
    public function __construct(private ZoneRepository $zones)
    {
    }

    /**
     * @return array{boundary: string|null, zones: list<array{name: string|null, geom: string|null}>}
     */
    public function forArea(AreaOfInterest $area): array
    {
        $zones = [];
        foreach ($this->zones->zonesFor($area) as $zone) {
            $zones[] = ['name' => $zone->getName(), 'geom' => $zone->getGeom()];
        }

        return [
            'boundary' => $area->getGeom(),
            'zones' => $zones,
        ];
    }
}
