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
 * The outline of the ground a plate is about, and the dimming of everything
 * outside it.
 *
 * It is its own thing rather than one more layer because the platform draws it
 * one way everywhere — a white casing under a jade line, no fill — and because
 * the scrim it carries is not a layer at all: it covers the world with the
 * outline punched out of it, so its bounds are the planet and fitting a map to
 * them would zoom every plate out to nothing.
 *
 * @see assets/boundary.js
 */
final readonly class Boundary
{
    /**
     * @param array<string, mixed> $geoJson a GeoJSON geometry, Feature or FeatureCollection
     * @param bool                 $scrim   whether the outside starts dimmed; the control is built
     *                                      either way, so a close plate can switch it on
     */
    public function __construct(
        public array $geoJson,
        public bool $scrim = true,
    ) {
    }

    /**
     * @return array{geojson: array<string, mixed>, scrim: bool}
     */
    public function toArray(): array
    {
        return ['geojson' => $this->geoJson, 'scrim' => $this->scrim];
    }
}
