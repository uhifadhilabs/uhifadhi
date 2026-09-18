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

namespace Uhifadhi\Bundle\AreaBundle\Exception;

/**
 * THE ZONE IMPORT REFUSED THE FILE, AND EVERY MESSAGE NAMES THE OFFENDER.
 *
 * A zoning scheme arrives as one file holding a dozen polygons, so "the import
 * failed" is useless: the person holding it has to know WHICH feature to go and
 * fix. Every refusal below therefore carries a zone's own name, or — where the
 * file broke before any name could be read — what the file is instead of what
 * it had to be.
 *
 * REFUSALS ARE WHOLE-FILE. A scheme is a subdivision, and half a subdivision is
 * not a smaller subdivision: it is a wrong one. Nothing is written until every
 * feature has passed, so an area refused an import has exactly the zones it had
 * before.
 *
 * Constructed through named factories rather than at call sites, because these
 * sentences are the product's words for the one failure each describes and they
 * are asserted on in the suite.
 */
final class ZoneImportException extends \RuntimeException
{
    public static function notGeoJson(string $detail, ?\Throwable $previous = null): self
    {
        return new self('The zones must be a GeoJSON file (.geojson or .json). '.$detail, previous: $previous);
    }

    public static function notAFeatureCollection(string $type): self
    {
        return new self(\sprintf(
            'A zoning scheme is a GeoJSON FeatureCollection with one polygon per zone; this file is a "%s".',
            $type,
        ));
    }

    public static function projectedCrs(string $crs): self
    {
        return new self(\sprintf(
            'The file declares the coordinate system "%s". Zones are stored in WGS84 (longitude/latitude), so export the layer as EPSG:4326 — or CRS84 — and import it again.',
            $crs,
        ));
    }

    public static function noGeometry(string $name): self
    {
        return new self(\sprintf('Zone "%s" has no geometry in the file. Every zone needs one polygon.', $name));
    }

    public static function unusableGeometry(string $name, string $detail, ?\Throwable $previous = null): self
    {
        return new self(
            \sprintf('Zone "%s" is not a polygon: %s Zones are Polygon or MultiPolygon features.', $name, $detail),
            previous: $previous,
        );
    }

    public static function noFeatures(): self
    {
        return new self('The file holds no features, so there is no zone in it to import.');
    }

    /**
     * The accepted spellings are handed in rather than read from the importer:
     * the sentence has to list exactly what that service looks for, and an
     * exception that imported the service to find out would be a cycle.
     *
     * @param list<string> $properties
     */
    public static function noName(int $position, array $properties): self
    {
        return new self(\sprintf(
            'Feature %d carries no zone name. Each feature needs a name in one of these properties: %s.',
            $position,
            implode(', ', $properties),
        ));
    }
}
