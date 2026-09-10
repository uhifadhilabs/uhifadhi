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

namespace Uhifadhi\Bundle\AreaBundle\Tests\Unit\Service;

use PHPUnit\Framework\TestCase;
use Uhifadhi\Bundle\AreaBundle\Overview\MapLayer;
use Uhifadhi\Bundle\AreaBundle\Service\AreaMapService;
use Uhifadhi\Bundle\AtlasBundle\Map\MapBuilder;
use Uhifadhi\Bundle\AtlasBundle\Model\AtlasMap;
use Uhifadhi\Bundle\AtlasBundle\Model\LegendItem;

/**
 * THE AREA'S PLATES, BUILT IN PHP. What used to be two Stimulus controllers is
 * this class and a Twig call: the area states what is on its map and the atlas
 * draws it.
 *
 * The geometry arrives as the text the geometry column returns. Anything
 * unusable is simply not drawn — a boundary that will not parse is a plate with
 * no boundary, never a page that fails.
 */
final class AreaMapServiceTest extends TestCase
{
    private const string BOUNDARY = '{"type":"Polygon","coordinates":[[[-29.5,-3.2],[-29.4,-3.2],[-29.4,-3.1],[-29.5,-3.1],[-29.5,-3.2]]]}';
    private const string ZONE = '{"type":"Polygon","coordinates":[[[-29.48,-3.18],[-29.44,-3.18],[-29.44,-3.14],[-29.48,-3.14],[-29.48,-3.18]]]}';

    public function testTheOverviewPlateDrawsTheBoundaryAndTheZones(): void
    {
        $map = self::areaMap()->overview(self::payload());

        self::assertSame(['geojson' => json_decode(self::BOUNDARY, true), 'scrim' => true], $map->toArray()['boundary']);
        self::assertSame(['area.zones'], array_column($map->toArray()['layers'], 'id'));
    }

    /**
     * A zone carries its name as the feature's label, which is what the plate
     * draws as a permanent halo over the polygon.
     */
    public function testEachZoneTravelsWithItsNameOnTheFeature(): void
    {
        $features = self::features(self::areaMap()->overview(self::payload()), 0);

        self::assertSame(
            [['type' => 'Feature', 'properties' => ['label' => 'The northern block'], 'geometry' => json_decode(self::ZONE, true)]],
            $features,
        );
    }

    public function testAnAreaWithNoBoundaryHasAPlateWithNoBoundary(): void
    {
        $map = self::areaMap()->overview(['boundary' => null, 'zones' => []]);

        self::assertNull($map->toArray()['boundary']);
    }

    public function testGeometryThatWillNotParseIsSimplyNotDrawn(): void
    {
        $map = self::areaMap()->overview(['boundary' => 'not json', 'zones' => [['name' => 'A', 'geom' => '{']]]);

        self::assertNull($map->toArray()['boundary']);
        self::assertSame([], self::features($map, 0));
    }

    /**
     * The zones row states zero rather than vanishing: a legend that comes and
     * goes with the data is a legend nobody can read.
     */
    public function testTheZonesRowIsThereEvenWhenTheAreaHasNoZones(): void
    {
        $layer = self::areaMap()->overview(['boundary' => null, 'zones' => []])->toArray()['layers'][0];

        self::assertSame('area.zones', $layer['id']);
        self::assertFalse($layer['visible']);
    }

    /**
     * ONE PLATE, MANY OWNERS. A module's layer arrives with the colour, shape
     * and count the module stated, under the module's own legend heading.
     */
    public function testEveryModuleLayerBecomesALayerWithItsOwnLegendRow(): void
    {
        $map = self::areaMap()->overview(self::payload(), [
            new MapLayer(
                id: 'sightings.recent',
                moduleSlug: 'sightings',
                groupLabel: 'Sightings',
                label: 'This week',
                swatch: '#E5C15A',
                features: ['type' => 'FeatureCollection', 'features' => []],
                style: MapLayer::STYLE_LINE,
                count: 7,
                on: false,
            ),
        ]);

        $layer = $map->toArray()['layers'][1];
        self::assertSame('sightings.recent', $layer['id']);
        self::assertSame('line', $layer['shape']);
        self::assertSame('#E5C15A', $layer['swatch']);
        self::assertFalse($layer['visible']);

        $row = self::legendRow($map, 'This week');
        self::assertSame('Sightings', $row->group);
        self::assertSame(7, $row->count);
    }

    /**
     * The boundary is a switch too, under the id the plate keys it by — so the
     * legend can say the outline is off and mean it.
     */
    public function testTheBoundaryHasALegendRowThatSwitchesIt(): void
    {
        $row = self::legendRow(self::areaMap()->overview(self::payload()), 'Boundary');

        self::assertSame(AtlasMap::BOUNDARY_LAYER_ID, $row->layerId);
        self::assertSame('The area', $row->group);
    }

    public function testTheZonesRowCountsTheZones(): void
    {
        $row = self::legendRow(self::areaMap()->overview(self::payload()), 'Zones');

        self::assertSame(1, $row->count);
        self::assertSame('area.zones', $row->layerId);
    }

    /**
     * THE MAP OF THE NETWORK. Every area with a boundary is drawn, live ones
     * bold and the rest quiet, with a marker on each that opens it.
     */
    public function testTheRegisterPlateDrawsEveryAreaThatHasABoundary(): void
    {
        $map = self::areaMap()->register([
            ['name' => 'Northern reserve', 'live' => true, 'href' => '/areas/a', 'boundary' => self::BOUNDARY],
            ['name' => 'Southern reserve', 'live' => false, 'href' => '/areas/b', 'boundary' => self::ZONE],
            ['name' => 'Unmapped', 'live' => false, 'href' => '/areas/c', 'boundary' => null],
        ]);

        self::assertSame(['area.live', 'area.setup'], array_column($map->toArray()['layers'], 'id'));
        self::assertCount(1, self::features($map, 0));
        self::assertCount(1, self::features($map, 1));

        // An area with no boundary has no place on the map, and no marker.
        $markers = $map->toUxMap()->toArray()['markers'];
        self::assertIsArray($markers);
        self::assertCount(2, $markers);
    }

    public function testTheRegisterPlateStatesWhatItsTwoColoursMean(): void
    {
        $labels = array_map(
            static fn (LegendItem $item) => $item->label,
            self::areaMap()->register([])->legend(),
        );

        self::assertSame(['Live', 'Boundary only'], $labels);
    }

    /**
     * The features one of the map's layers carries, narrowed for the analyser.
     *
     * @return list<mixed>
     */
    private static function features(AtlasMap $map, int $index): array
    {
        $collection = $map->toArray()['layers'][$index]['features'];

        self::assertIsArray($collection);
        self::assertIsList($collection['features'] ?? null);

        return $collection['features'];
    }

    private static function legendRow(AtlasMap $map, string $label): LegendItem
    {
        foreach ($map->legend() as $item) {
            if ($item->label === $label) {
                return $item;
            }
        }

        self::fail(\sprintf('The legend has no row labelled "%s".', $label));
    }

    /**
     * @return array{boundary: string|null, zones: list<array{name: string|null, geom: string|null}>}
     */
    private static function payload(): array
    {
        return [
            'boundary' => self::BOUNDARY,
            'zones' => [['name' => 'The northern block', 'geom' => self::ZONE]],
        ];
    }

    private static function areaMap(): AreaMapService
    {
        return new AreaMapService(new MapBuilder());
    }
}
