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

namespace Uhifadhi\Bundle\AreaBundle\Model;

/**
 * A STATION'S POINT, READ OFF THE STORED GEOMETRY.
 *
 * THE STORED ORDER IS LONGITUDE FIRST — GeoJSON's and PostGIS's, and this
 * product never reverses that anywhere it matters. What a person reads off a
 * handheld and says into a radio is lat-then-lon, so {@see label()} is the
 * one place the order flips, on its way to being read aloud.
 *
 * FIVE DECIMALS is about a metre, which is the precision a post has. More
 * digits would be a claim about the survey that nobody made.
 */
final readonly class StationPoint
{
    public const int DECIMALS = 5;

    public function __construct(
        public float $lon,
        public float $lat,
    ) {
    }

    /** A point, or null where the geometry is missing or not a point. */
    public static function of(?string $geoJson): ?self
    {
        if (null === $geoJson || '' === $geoJson) {
            return null;
        }

        $decoded = json_decode($geoJson, true);
        $pair = \is_array($decoded) ? ($decoded['coordinates'] ?? null) : null;

        if (!\is_array($pair) || !is_numeric($pair[0] ?? null) || !is_numeric($pair[1] ?? null)) {
            return null;
        }

        return new self((float) $pair[0], (float) $pair[1]);
    }

    /** Latitude first, as it is written down and read out. */
    public function label(): string
    {
        return \sprintf('%s, %s', $this->latitude(), $this->longitude());
    }

    public function latitude(): string
    {
        return number_format($this->lat, self::DECIMALS, '.', '');
    }

    public function longitude(): string
    {
        return number_format($this->lon, self::DECIMALS, '.', '');
    }
}
