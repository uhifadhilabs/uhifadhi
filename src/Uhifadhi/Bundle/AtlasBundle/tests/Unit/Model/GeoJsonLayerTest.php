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

namespace Uhifadhi\Bundle\AtlasBundle\Tests\Unit\Model;

use PHPUnit\Framework\TestCase;
use Uhifadhi\Bundle\AtlasBundle\Exception\LayerException;
use Uhifadhi\Bundle\AtlasBundle\Model\GeoJsonLayer;
use Uhifadhi\Bundle\AtlasBundle\Model\LayerShape;

/**
 * A layer is either features the server already has or a url the browser
 * fetches. The two ways of saying "nothing to draw" — neither source, or both —
 * are refused where the module wrote them rather than in a browser at 3am.
 */
final class GeoJsonLayerTest extends TestCase
{
    private const array FEATURES = ['type' => 'FeatureCollection', 'features' => []];

    public function testAnInlineLayerCarriesItsFeatureCollection(): void
    {
        $layer = new GeoJsonLayer(id: 'sightings.recent', label: 'Recent sightings', features: self::FEATURES);

        self::assertSame([
            'id' => 'sightings.recent',
            'features' => self::FEATURES,
            'url' => null,
            'swatch' => GeoJsonLayer::DEFAULT_SWATCH,
            'shape' => 'fill',
            'visible' => true,
        ], $layer->toArray());
    }

    public function testAUrlLayerCarriesTheUrlTheControllerFetches(): void
    {
        $layer = new GeoJsonLayer(
            id: 'sightings.all',
            label: 'Every sighting',
            url: '/sightings/features.geojson',
            swatch: '#B9C8BD',
            shape: LayerShape::Point,
            visible: false,
        );

        self::assertSame([
            'id' => 'sightings.all',
            'features' => null,
            'url' => '/sightings/features.geojson',
            'swatch' => '#B9C8BD',
            'shape' => 'point',
            'visible' => false,
        ], $layer->toArray());
    }

    public function testALayerWithNoSourceIsRefused(): void
    {
        $this->expectException(LayerException::class);

        new GeoJsonLayer(id: 'sightings.recent', label: 'Recent sightings');
    }

    public function testALayerWithTwoSourcesIsRefused(): void
    {
        $this->expectException(LayerException::class);

        new GeoJsonLayer(
            id: 'sightings.recent',
            label: 'Recent sightings',
            features: self::FEATURES,
            url: '/sightings/features.geojson',
        );
    }

    /**
     * The legend row a layer states about itself: the same id the controller
     * keys the drawn layer by, so clicking the row reaches the layer.
     */
    public function testALayerStatesItsOwnLegendRow(): void
    {
        $item = new GeoJsonLayer(
            id: 'sightings.recent',
            label: 'Recent sightings',
            features: self::FEATURES,
            count: 12,
            group: 'Sightings',
        )->legendItem();

        self::assertSame('sightings.recent', $item->layerId);
        self::assertSame('Recent sightings', $item->label);
        self::assertSame(12, $item->count);
        self::assertSame('Sightings', $item->group);
        self::assertTrue($item->visible);
    }
}
