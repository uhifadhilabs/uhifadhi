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

use Uhifadhi\Bundle\AtlasBundle\Exception\LayerException;

/**
 * A body of GeoJSON drawn on a plate, and the legend row that switches it.
 *
 * WHY THE ATLAS MODELS THIS AND UX MAP DOES NOT. UX Map models the shapes a
 * person places one at a time — a marker, a polygon, a circle — and those are
 * passed straight through to it. What a module actually has is a
 * FeatureCollection out of a geometry column: hundreds of features that share
 * one colour, one meaning and one switch. That is a LAYER, and it is what this
 * class is.
 *
 * A layer names exactly ONE source:
 *
 *   - `features` — a decoded FeatureCollection the server already had. It
 *     travels in the page, so the plate draws on the first paint.
 *   - `url` — an endpoint the plate fetches once it is mounted. For a
 *     collection too large to put in a document, or one that is only worth
 *     asking for when someone switches the layer on.
 *
 * Naming both is refused, and naming neither is refused: both are a layer that
 * draws nothing, and a plate that renders and shows nothing is a fault with no
 * message.
 */
final readonly class GeoJsonLayer
{
    /** The atlas's own line colour, so a layer that states no colour is still legible. */
    public const string DEFAULT_SWATCH = '#49E6B4';

    /**
     * @param string                    $id       the id the legend row and the drawn layer share; a
     *                                            module namespaces it with its own word ("patrol.tracks")
     * @param array<string, mixed>|null $features a decoded GeoJSON FeatureCollection
     * @param string|null               $url      an endpoint answering with one, fetched by the plate
     * @param string                    $swatch   the layer's colour, in the legend and on the map; a
     *                                            feature may override it with its own `color` property
     * @param bool                      $visible  whether the layer starts drawn — an invisible layer is
     *                                            still built, so its first switch costs no round trip
     * @param int|null                  $count    how many features the legend row states
     * @param string|null               $group    the legend heading this row sits under
     */
    public function __construct(
        public string $id,
        public string $label,
        public ?array $features = null,
        public ?string $url = null,
        public string $swatch = self::DEFAULT_SWATCH,
        public LayerShape $shape = LayerShape::Fill,
        public bool $visible = true,
        public ?int $count = null,
        public ?string $group = null,
    ) {
        if (null === $features && null === $url) {
            throw new LayerException(\sprintf('The layer "%s" names no source: give it either "features" or "url".', $id));
        }

        if (null !== $features && null !== $url) {
            throw new LayerException(\sprintf('The layer "%s" names two sources: give it either "features" or "url", not both.', $id));
        }
    }

    /**
     * The legend row this layer states about itself, keyed by the same id the
     * plate keys the drawn layer by — which is what makes the row a switch.
     */
    public function legendItem(): LegendItem
    {
        return new LegendItem(
            label: $this->label,
            swatch: $this->swatch,
            shape: $this->shape,
            group: $this->group,
            count: $this->count,
            layerId: $this->id,
            visible: $this->visible,
        );
    }

    /**
     * What the plate controller is told. The legend's own words — the label,
     * the count, the heading — are rendered by the server and are not repeated
     * here.
     *
     * @return array{id: string, features: array<string, mixed>|null, url: string|null, swatch: string, shape: string, visible: bool}
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'features' => $this->features,
            'url' => $this->url,
            'swatch' => $this->swatch,
            'shape' => $this->shape->value,
            'visible' => $this->visible,
        ];
    }
}
