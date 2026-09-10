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

namespace Uhifadhi\Bundle\AtlasBundle\Tests\Unit\Map;

use PHPUnit\Framework\TestCase;
use Symfony\UX\Map\Bridge\Leaflet\LeafletOptions;
use Uhifadhi\Bundle\AtlasBundle\Map\MapBuilder;
use Uhifadhi\Bundle\AtlasBundle\Map\MapBuilderInterface;
use Uhifadhi\Bundle\AtlasBundle\Model\AtlasMap;

/**
 * The one way a module gets a map. Every plate in the product starts here, so
 * what this returns is what every plate has before a module has drawn anything.
 */
final class MapBuilderTest extends TestCase
{
    public function testItIsTheContractsOnlyImplementation(): void
    {
        self::assertInstanceOf(MapBuilderInterface::class, new MapBuilder());
    }

    public function testEachCallReturnsAFreshMap(): void
    {
        $builder = new MapBuilder();

        self::assertNotSame($builder->createMap(), $builder->createMap());
    }

    /**
     * A plate that is never told where to look must still be a map: an opening
     * view is set so `Map::toArray()` has the centre and zoom it insists on,
     * and the controller re-frames on what was actually drawn.
     */
    public function testAMapOpensOnADefaultViewSoItCanAlwaysBeRendered(): void
    {
        $rendered = new MapBuilder()->createMap()->toUxMap()->toArray();

        self::assertSame(['lat' => MapBuilder::DEFAULT_LATITUDE, 'lng' => MapBuilder::DEFAULT_LONGITUDE], $rendered['center']);
        self::assertSame(MapBuilder::DEFAULT_ZOOM, $rendered['zoom']);
    }

    /**
     * THE CHROME IS THE ATLAS'S, AND SO ARE THE TILES. The bridge's own zoom
     * control and its default tile layer are both switched off in PHP: the
     * plate wears one control stack and draws the imagery the deployment
     * configured, never a second set underneath.
     *
     * @see vendor/symfony/ux-leaflet-map/src/LeafletOptions.php
     */
    public function testTheBridgeDrawsNeitherItsOwnTilesNorItsOwnZoomControl(): void
    {
        $options = new MapBuilder()->createMap()->ux()->getOptions();

        self::assertInstanceOf(LeafletOptions::class, $options);

        $array = $options->toArray();
        self::assertFalse($array['tileLayer']);
        self::assertArrayNotHasKey('zoomControlOptions', $array);
    }

    public function testTheMapItReturnsIsAnAtlasMap(): void
    {
        self::assertInstanceOf(AtlasMap::class, new MapBuilder()->createMap());
    }
}
