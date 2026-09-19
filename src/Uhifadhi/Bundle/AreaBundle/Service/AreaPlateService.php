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
use Uhifadhi\Bundle\AreaBundle\Model\ZoneFeaturePlan;
use Uhifadhi\Bundle\AreaBundle\Model\ZonePalette;
use Uhifadhi\Bundle\AreaBundle\Model\ZoneRow;
use Uhifadhi\Bundle\AreaBundle\Repository\ZoneRepository;
use Uhifadhi\Bundle\AtlasBundle\Map\MapBuilderInterface;
use Uhifadhi\Bundle\AtlasBundle\Model\AtlasMap;
use Uhifadhi\Bundle\AtlasBundle\Model\Boundary;
use Uhifadhi\Bundle\AtlasBundle\Model\GeoJsonLayer;
use Uhifadhi\Bundle\AtlasBundle\Model\LayerShape;
use Uhifadhi\Bundle\AtlasBundle\Model\LegendItem;
use Uhifadhi\Bundle\AtlasBundle\Model\StyleRule;

/**
 * THE AREA'S GROUND, DRAWN — every plate the area's own pages read their
 * geography off: the zone set, a file being previewed, and the ground around
 * one post.
 *
 * THE PLATE IS THE ATLAS'S, wearing the house contract: the boundary with its
 * scrim, one layer of rings, the controls where every other map in the product
 * puts them, and the key beneath. Nothing about this map is drawn differently
 * because it is a configure page.
 *
 * A PREVIEW IS THE SAME PLATE WITH DIFFERENT RINGS. The features a file carries
 * are drawn exactly as stored zones are, so what somebody approves looks like
 * what they will get.
 *
 * SEPARATE FROM {@see ZoneSetService}, because the atlas is the PLATE's
 * dependency and not the SET's: counting a zone set needs a database, drawing
 * one needs a map builder, and an installation that carries the area model and
 * mounts no page has the first without the second.
 */
final readonly class AreaPlateService
{
    /** The key's heading — the zones are the whole of it on this page. */
    public const string ZONES_GROUP = 'The zones';

    /** What the preview's key is headed, so nobody reads it as the live set. */
    public const string ARRIVING_GROUP = 'Arriving';

    public const string ZONES_LAYER = 'area.zones.set';

    /** The posts, as one layer: this one accented, the rest quiet. */
    public const string STATIONS_LAYER = 'area.stations';
    public const string STATIONS_GROUP = 'The posts';

    /** What the design draws around a post, so distance is read and not guessed. */
    public const array RINGS_KM = [5, 10];

    /** The property every feature carries so a style rule can colour it by zone. */
    private const string HUE_PROPERTY = 'zone';

    /** The post the page is about, in the platform's own accent. */
    private const string HERE_SWATCH = '#49E6B4';

    public function __construct(
        private ZoneRepository $zones,
        private MapBuilderInterface $maps,
    ) {
    }

    /**
     * The live set on the area's own ground.
     *
     * @param list<ZoneRow> $rows the rows {@see ZoneSetService::view()} produced, so the hues agree
     */
    public function plate(AreaOfInterest $area, array $rows): AtlasMap
    {
        $named = [];
        foreach ($this->zones->zonesFor($area) as $zone) {
            $named[(string) $zone->getName()] = (string) $zone->getGeom();
        }

        $features = [];
        foreach ($rows as $row) {
            $features[] = [$row->name, $named[$row->name] ?? null, $row->hue, $row->km2];
        }

        return $this->draw($area, $features, self::ZONES_GROUP);
    }

    /**
     * The same plate, drawn from a file nobody has confirmed yet. Only the
     * features that would ARRIVE are drawn: a flagged ring over the zone it
     * clashes with would be read as a zone that exists.
     *
     * @param list<ZoneFeaturePlan> $arriving
     */
    public function previewPlate(AreaOfInterest $area, array $arriving): AtlasMap
    {
        $features = [];
        foreach ($arriving as $position => $feature) {
            $features[] = [$feature->name, $feature->geom, ZonePalette::hueFor($position), $feature->km2 ?? 0];
        }

        return $this->draw($area, $features, self::ARRIVING_GROUP);
    }

    /**
     * THE GROUND AROUND ONE POST: the area's zones behind it, every station in
     * the area, this one accented, and the two rings the design draws so a
     * distance is read rather than guessed.
     *
     * THE ZONES ARE STILL THE ZONES. A station's plate is not a different map
     * — same hues, same key, same contract — with the post's own surroundings
     * added. The same ground rendered two ways on two pages is the defect this
     * avoids by having one service draw both.
     *
     * @param list<ZoneRow>                                                                        $rows  the area's zones, hued as everywhere else
     * @param list<array{uuid: string, name: string, point: string|null, posted: int, here: bool}> $posts every station in the area
     */
    public function aroundStation(AreaOfInterest $area, array $rows, array $posts): AtlasMap
    {
        $named = [];
        foreach ($this->zones->zonesFor($area) as $zone) {
            $named[(string) $zone->getName()] = (string) $zone->getGeom();
        }

        $features = [];
        foreach ($rows as $row) {
            $features[] = [$row->name, $named[$row->name] ?? null, $row->hue, $row->km2];
        }

        $map = $this->draw($area, $features, self::ZONES_GROUP);

        $pins = [];
        foreach ($posts as $post) {
            $point = self::decode($post['point']);
            if (null === $point) {
                continue;
            }

            $pins[] = [
                'type' => 'Feature',
                'properties' => [
                    'label' => $post['name'],
                    'posted' => $post['posted'],
                    // The one the page is about is drawn apart from the rest,
                    // by a property rather than by a second layer: one layer
                    // means one legend row and one thing to switch off.
                    'here' => $post['here'],
                ],
                'geometry' => $point,
            ];
        }

        $map->addLayer(new GeoJsonLayer(
            id: self::STATIONS_LAYER,
            label: 'Stations',
            features: ['type' => 'FeatureCollection', 'features' => $pins],
            swatch: self::HERE_SWATCH,
            shape: LayerShape::Fill,
            visible: [] !== $pins,
            count: \count($pins),
            group: self::STATIONS_GROUP,
            rules: [StyleRule::when('here', true)->color(self::HERE_SWATCH)->fillColor(self::HERE_SWATCH)],
        ));

        return $map;
    }

    /**
     * ONE LAYER, COLOURED BY THE ZONE PROPERTY, and one key row per zone. A
     * layer each would give the key eleven switches where the design has a key,
     * and a zone set is read as one thing.
     *
     * @param list<array{0: string, 1: string|null, 2: string, 3: int}> $features name, geometry, hue, size
     */
    private function draw(AreaOfInterest $area, array $features, string $group): AtlasMap
    {
        $map = $this->maps->createMap();

        $boundary = self::decode($area->getGeom());
        if (null !== $boundary) {
            // EVERY LAYER SHIPS A LEGEND ROW, the boundary included: a line on
            // a plate that nothing in the key accounts for is a line nobody can
            // name.
            $map->boundary(new Boundary($boundary));
            $map->addLegendItem(new LegendItem(
                label: 'Boundary',
                swatch: AreaMapService::BOUNDARY_SWATCH,
                shape: LayerShape::Line,
                group: AreaMapService::OWN_GROUP,
                layerId: AtlasMap::BOUNDARY_LAYER_ID,
            ));
        }

        $collection = [];
        $rules = [];
        foreach ($features as [$name, $geometry, $hue, $km2]) {
            $decoded = self::decode($geometry);
            if (null === $decoded) {
                continue;
            }

            $collection[] = [
                'type' => 'Feature',
                'properties' => [self::HUE_PROPERTY => $name, 'label' => $name],
                'geometry' => $decoded,
            ];
            $rules[] = StyleRule::when(self::HUE_PROPERTY, $name)->color($hue)->fillColor($hue);

            // THE KEY CARRIES THE SIZE, because on this page the key is the only
            // place a ring is named at all — the plate itself draws colour and
            // nothing else.
            $map->addLegendItem(new LegendItem(
                label: \sprintf('%s · %s km²', $name, number_format($km2)),
                swatch: $hue,
                group: $group,
            ));
        }

        /*
         * THE LAYER'S OWN ROW IS THE SWITCH; the rows above it are the key. The
         * atlas gives every layer a row whether or not a caller asks, so the
         * count goes on it and the colours stay on the zones, where hue means
         * something.
         */
        $map->addLayer(new GeoJsonLayer(
            id: self::ZONES_LAYER,
            label: 'Zones',
            features: ['type' => 'FeatureCollection', 'features' => $collection],
            visible: [] !== $collection,
            count: \count($collection),
            group: $group,
            rules: $rules,
        ));

        return $map;
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function decode(?string $geoJson): ?array
    {
        if (null === $geoJson || '' === $geoJson) {
            return null;
        }

        $decoded = json_decode($geoJson, true);
        if (!\is_array($decoded)) {
            return null;
        }

        /** @var array<string, mixed> $decoded */
        return $decoded;
    }
}
