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
 * THE LIVE SET, BACK OUT AS THE FILE IT CAME IN AS.
 *
 * ALWAYS AVAILABLE, AND NOT ONLY BEFORE A DELETION. No superseded geometry is
 * kept anywhere, so the export is the only way an earlier state of a zone set
 * can be put back — which makes it the thing offered first when somebody is
 * about to remove the lot, and a plain download the rest of the time.
 *
 * ONE FEATURE PER ZONE, ITS NAME AND ITS STORED PROPERTIES. A zone stores its
 * name and its ground and nothing else, so that is the whole of `properties`;
 * inventing a colour or an index here would export something the product does
 * not actually hold.
 *
 * NO `crs` MEMBER. RFC 7946 defines GeoJSON as WGS84 and removed `crs`
 * altogether, so a file that says so again is a file whose reader has to decide
 * which of the two statements to believe.
 */
final readonly class ZoneExportService
{
    public function __construct(
        private ZoneRepository $zones,
    ) {
    }

    public function featureCollection(AreaOfInterest $area): string
    {
        $features = [];
        foreach ($this->zones->zonesFor($area) as $zone) {
            $geometry = json_decode((string) $zone->getGeom(), true);
            if (!\is_array($geometry)) {
                continue;
            }

            $features[] = [
                'type' => 'Feature',
                'properties' => ['name' => $zone->getName()],
                'geometry' => $geometry,
            ];
        }

        return (string) json_encode(
            ['type' => 'FeatureCollection', 'features' => $features],
            \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_SLASHES,
        );
    }

    /**
     * WHAT THE DOWNLOAD IS CALLED. The area's own name, because somebody who
     * exports three areas on one afternoon has three files in one folder and
     * "zones.geojson" three times is the state that loses one of them.
     */
    public function fileName(AreaOfInterest $area): string
    {
        $slug = strtolower((string) preg_replace('/[^a-z0-9]+/i', '-', (string) $area->getName()));
        $slug = trim($slug, '-');

        return ('' === $slug ? 'area' : $slug).'-zones.geojson';
    }
}
