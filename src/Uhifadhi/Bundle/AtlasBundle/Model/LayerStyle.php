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

/**
 * HOW A FEATURE IS DRAWN, STATED IN PHP.
 *
 * {@see LayerShape} settles what a line, a fill and a point look like for the
 * whole platform. This is the layer of statement above it: the few things that
 * are genuinely a module's to say because they carry MEANING rather than
 * house style — a hollow mark for a closed case, a dashed ring for the serious
 * end, a wider stroke for the route somebody is pointing at.
 *
 * NO CALLBACK CROSSES THE WIRE. UX Map's own value objects are data that
 * `json_encode` can carry and a Stimulus controller can read; a PHP closure is
 * neither. So a style is a bag of stated properties and a rule ({@see
 * StyleRule}) is a condition on a feature's own properties — which is why one
 * controller can draw every module's layers without any of them shipping
 * JavaScript.
 *
 * IT STATES ONLY WHAT IT CHANGES. An unstated property is absent from
 * {@see self::toArray()} rather than null, because the plate merges a style
 * over the shape's own answer: a serialised null would arrive in the browser as
 * an instruction to unset a colour nobody asked to unset.
 *
 * The names are Leaflet's own path options, so what a module writes and what the
 * browser receives are the same word.
 *
 * @see https://leafletjs.com/reference.html#path-option
 * @see vendor/symfony/ux-map/src/Marker.php — the same "PHP value object → extra payload" shape
 */
final readonly class LayerStyle
{
    /**
     * @param string|null $color       the stroke colour
     * @param float|null  $weight      the stroke width in pixels
     * @param float|null  $opacity     the stroke opacity, 0 to 1
     * @param bool|null   $fill        whether the shape is filled at all
     * @param string|null $fillColor   the fill colour, where it differs from the stroke
     * @param float|null  $fillOpacity the fill opacity, 0 to 1
     * @param string|null $dashArray   an SVG dash pattern, e.g. "4 3"
     * @param float|null  $radius      the circle radius of a point feature, in pixels
     * @param int|null    $zIndex      which pane the feature is drawn in; higher is nearer the reader
     */
    public function __construct(
        public ?string $color = null,
        public ?float $weight = null,
        public ?float $opacity = null,
        public ?bool $fill = null,
        public ?string $fillColor = null,
        public ?float $fillOpacity = null,
        public ?string $dashArray = null,
        public ?float $radius = null,
        public ?int $zIndex = null,
    ) {
    }

    public function color(string $color): self
    {
        return $this->with(color: $color);
    }

    public function weight(float $weight): self
    {
        return $this->with(weight: $weight);
    }

    public function opacity(float $opacity): self
    {
        return $this->with(opacity: $opacity);
    }

    public function fill(bool $fill): self
    {
        return $this->with(fill: $fill);
    }

    public function fillColor(string $fillColor): self
    {
        return $this->with(fillColor: $fillColor);
    }

    public function fillOpacity(float $fillOpacity): self
    {
        return $this->with(fillOpacity: $fillOpacity);
    }

    public function dashArray(string $dashArray): self
    {
        return $this->with(dashArray: $dashArray);
    }

    public function radius(float $radius): self
    {
        return $this->with(radius: $radius);
    }

    /**
     * Which pane the feature is drawn in. The plate builds a pane per stated
     * value, so two layers that must not cover each other say so as numbers
     * rather than by the order they happened to be added in.
     */
    public function zIndex(int $zIndex): self
    {
        return $this->with(zIndex: $zIndex);
    }

    /**
     * What the plate controller merges over the shape's own answer — only the
     * properties this style actually states.
     *
     * @return array<string, bool|float|int|string>
     */
    public function toArray(): array
    {
        $stated = [];
        foreach ([
            'color' => $this->color,
            'weight' => $this->weight,
            'opacity' => $this->opacity,
            'fill' => $this->fill,
            'fillColor' => $this->fillColor,
            'fillOpacity' => $this->fillOpacity,
            'dashArray' => $this->dashArray,
            'radius' => $this->radius,
            'zIndex' => $this->zIndex,
        ] as $name => $value) {
            if (null !== $value) {
                $stated[$name] = $value;
            }
        }

        return $stated;
    }

    /**
     * One statement over the rest, answering a fresh object: a base style handed
     * to two layers cannot be edited through either of them.
     */
    private function with(
        ?string $color = null,
        ?float $weight = null,
        ?float $opacity = null,
        ?bool $fill = null,
        ?string $fillColor = null,
        ?float $fillOpacity = null,
        ?string $dashArray = null,
        ?float $radius = null,
        ?int $zIndex = null,
    ): self {
        return new self(
            $color ?? $this->color,
            $weight ?? $this->weight,
            $opacity ?? $this->opacity,
            $fill ?? $this->fill,
            $fillColor ?? $this->fillColor,
            $fillOpacity ?? $this->fillOpacity,
            $dashArray ?? $this->dashArray,
            $radius ?? $this->radius,
            $zIndex ?? $this->zIndex,
        );
    }
}
