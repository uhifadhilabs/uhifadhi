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

namespace Uhifadhi\Bundle\AtlasBundle\Model;

use Symfony\UX\Map\Map as UxMap;

/**
 * A map as the platform draws it: a UX Map map, plus everything the atlas adds
 * on top of it.
 *
 * IT WRAPS, IT DOES NOT REPLACE. Markers, polygons, polylines, circles and
 * rectangles are UX Map's own model and stay there — {@see self::ux()} hands
 * the underlying map over so a module uses `Symfony\UX\Map\Marker` and friends
 * directly. What this class adds is what UX Map has no model for: GeoJSON
 * layers, the area outline, which grounds the base-layer menu offers, the
 * legend, and whether the plate wears fullscreen.
 *
 * ALL OF IT TRAVELS UNDER ONE KEY. UX Map forwards a map's `extra` payload to
 * the browser untouched and documents it as the extension point for exactly
 * this; the atlas writes its whole payload under `extra.atlas` so a module's own
 * extra data sits beside it and neither can overwrite the other.
 *
 * @see https://symfony.com/bundles/ux-map/current/index.html#advanced-passing-extra-data-from-php-to-the-stimulus-controller
 * @see vendor/symfony/ux-map/src/Map.php
 */
final class AtlasMap
{
    /** The one key of `extra` the atlas owns. Everything else there is the module's. */
    public const string EXTRA_KEY = 'atlas';

    /**
     * The id the plate keys the drawn boundary by.
     *
     * The boundary is not a layer — it has its own treatment and its own scrim —
     * but it is still something a person may want to switch off, so it is
     * reachable by a legend row like any layer is. A map states that row itself,
     * because only the map knows what to call it and which heading it sits
     * under.
     */
    public const string BOUNDARY_LAYER_ID = 'atlas.boundary';

    /** @var list<GeoJsonLayer> */
    private array $layers = [];

    /** @var list<LegendItem> */
    private array $statedLegendItems = [];

    private ?Boundary $boundary = null;

    /** @var list<BaseLayer> */
    private array $baseLayers = [BaseLayer::Satellite, BaseLayer::Street];

    private bool $fullscreen = true;

    private bool $fit = true;

    /** @var array<string, mixed> */
    private array $extra = [];

    public function __construct(
        private readonly UxMap $map,
    ) {
    }

    /**
     * The UX Map map underneath, for everything UX Map already models: the
     * centre and zoom, markers, polygons, polylines, circles, rectangles.
     */
    public function ux(): UxMap
    {
        return $this->map;
    }

    public function boundary(Boundary $boundary): self
    {
        $this->boundary = $boundary;

        return $this;
    }

    public function addLayer(GeoJsonLayer $layer): self
    {
        $this->layers[] = $layer;

        return $this;
    }

    /**
     * A legend row that switches nothing — a key for a colour the plate uses
     * without a layer behind it.
     */
    public function addLegendItem(LegendItem $item): self
    {
        $this->statedLegendItems[] = $item;

        return $this;
    }

    /**
     * Which grounds the base-layer menu offers, in the order it offers them.
     * The first is the one the plate opens on.
     */
    public function baseLayers(BaseLayer ...$layers): self
    {
        $this->baseLayers = array_values($layers);

        return $this;
    }

    /**
     * Whether the plate wears the fullscreen control. Off for a plate that is
     * already the whole screen, or one small enough that expanding it says
     * nothing.
     */
    public function fullscreen(bool $enable = true): self
    {
        $this->fullscreen = $enable;

        return $this;
    }

    /**
     * Whether the plate re-frames itself on what it drew.
     *
     * On by default, because a map is nearly always about its content rather
     * than about a coordinate. Off for a plate whose centre and zoom are the
     * point — a fixed view of one place, a locator inset.
     */
    public function fit(bool $enable = true): self
    {
        $this->fit = $enable;

        return $this;
    }

    /**
     * The module's own data for the browser, forwarded beside the atlas key.
     *
     * @param array<string, mixed> $extra
     */
    public function extra(array $extra): self
    {
        $this->extra = $extra;

        return $this;
    }

    /**
     * The whole legend as one list: each layer's own row first, in the order
     * the layers were added, then the rows the map stated by hand.
     *
     * @return list<LegendItem>
     */
    public function legend(): array
    {
        return [
            ...array_map(static fn (GeoJsonLayer $layer) => $layer->legendItem(), $this->layers),
            ...$this->statedLegendItems,
        ];
    }

    /**
     * The atlas payload, as the plate controller reads it off
     * `event.detail.extra.atlas`.
     *
     * @return array{
     *     layers: list<array<string, mixed>>,
     *     boundary: array{geojson: array<string, mixed>, scrim: bool}|null,
     *     baseLayers: list<string>,
     *     fullscreen: bool,
     *     fit: bool,
     * }
     */
    public function toArray(): array
    {
        return [
            'layers' => array_map(static fn (GeoJsonLayer $layer) => $layer->toArray(), $this->layers),
            'boundary' => $this->boundary?->toArray(),
            'baseLayers' => array_map(static fn (BaseLayer $base) => $base->value, $this->baseLayers),
            'fullscreen' => $this->fullscreen,
            'fit' => $this->fit,
        ];
    }

    /**
     * The UX Map map with the atlas payload written onto it — what the renderer
     * is handed.
     */
    public function toUxMap(): UxMap
    {
        return $this->map->extra([...$this->extra, self::EXTRA_KEY => $this->toArray()]);
    }
}
