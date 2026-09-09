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

namespace Uhifadhi\Bundle\AreaBundle\Tests\Integration\Web;

use Uhifadhi\Bundle\AreaBundle\Entity\AreaOfInterest;
use Uhifadhi\Bundle\AreaBundle\Overview\MapLayer;
use Uhifadhi\Bundle\AreaBundle\Overview\MapLayerProviderInterface;

/**
 * A MODULE'S MAP-LAYER CONTRIBUTION, STOOD IN FOR.
 *
 * The real patrol and incident modules put their layers on the area page's plate
 * through {@see MapLayerProviderInterface}; this bundle must never depend on
 * them, so the suite ships its own contributor over the same contribution. What is being
 * tested is that the area page GATHERS a layer from a module switched on here and
 * RENDERS its legend group and its geometry — not what any particular module
 * draws.
 *
 * It contributes one on-by-default line layer and one off-by-default fill layer,
 * so both the drawn state and the demoted-but-legended state are asserted.
 */
final readonly class FakeMapLayers implements MapLayerProviderInterface
{
    public function __construct(
        private string $slug,
        private string $group,
    ) {
    }

    public function moduleSlug(): string
    {
        return $this->slug;
    }

    public function mapLayersFor(AreaOfInterest $area, \DateTimeImmutable $now): array
    {
        return [
            new MapLayer(
                id: $this->slug.'.live',
                moduleSlug: $this->slug,
                groupLabel: $this->group,
                label: 'Out right now',
                swatch: '#3ED9A8',
                features: [
                    'type' => 'FeatureCollection',
                    'features' => [[
                        'type' => 'Feature',
                        'geometry' => ['type' => 'LineString', 'coordinates' => [[-29.6, -3.2], [-29.4, -3.1]]],
                        'properties' => ['color' => '#3ED9A8'],
                    ]],
                ],
                style: MapLayer::STYLE_LINE,
                count: 3,
                live: true,
            ),
            new MapLayer(
                id: $this->slug.'.buffer',
                moduleSlug: $this->slug,
                groupLabel: $this->group,
                label: '2 km coverage buffer',
                swatch: '#3ED9A8',
                features: ['type' => 'FeatureCollection', 'features' => []],
                style: MapLayer::STYLE_FILL,
                on: false,
            ),
        ];
    }
}
