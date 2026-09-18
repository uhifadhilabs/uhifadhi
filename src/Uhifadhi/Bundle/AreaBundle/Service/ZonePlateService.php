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
 * THE ZONE SET, DRAWN — the plate the configure page reads its geography off.
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
final readonly class ZonePlateService
{
    /** The key's heading — the zones are the whole of it on this page. */
    public const string ZONES_GROUP = 'The zones';

    /** What the preview's key is headed, so nobody reads it as the live set. */
    public const string ARRIVING_GROUP = 'Arriving';

    public const string ZONES_LAYER = 'area.zones.set';

    /** The property every feature carries so a style rule can colour it by zone. */
    private const string HUE_PROPERTY = 'zone';

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
