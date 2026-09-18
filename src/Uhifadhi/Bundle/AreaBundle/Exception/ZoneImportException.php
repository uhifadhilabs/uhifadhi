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
 * THE SENTENCE IS THE REASON AND NOTHING ELSE. The card prints it beside the
 * file it turned away, so "coordinates are projected (UTM 36S), not degrees" is
 * the whole of it: the advice that follows from it — export as 4326 and try
 * again — is what somebody does next, not what happened, and a surface that
 * explains the next step in every refusal is a surface nobody reads.
 *
 * IT STILL NAMES THE OFFENDER where there is one, because a file holds a dozen
 * polygons and the person has to know which feature to go and fix.
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
        return new self('not a GeoJSON file — '.$detail, previous: $previous);
    }

    public static function notAFeatureCollection(string $type): self
    {
        return new self(\sprintf('not a GeoJSON FeatureCollection — this file is a "%s"', $type));
    }

    public static function projectedCrs(string $crs): self
    {
        return new self(\sprintf('coordinates are projected (%s), not degrees', $crs));
    }

    public static function noGeometry(string $name): self
    {
        return new self(\sprintf('"%s" has no geometry in the file', $name));
    }

    public static function unusableGeometry(string $name, string $detail, ?\Throwable $previous = null): self
    {
        return new self(\sprintf('"%s" is not a polygon: %s', $name, $detail), previous: $previous);
    }

    public static function noFeatures(): self
    {
        return new self('the file holds no polygon feature, so there is no zone in it');
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
            'no usable name property — feature %d is named by none of %s',
            $position,
            implode(', ', $properties),
        ));
    }

    /** Replacing one zone's ring takes one polygon: several is not a replacement. */
    public static function notOneRing(int $found): self
    {
        return new self(0 === $found
            ? 'the file holds no polygon, so there is no ring to put in its place'
            : \sprintf('the file holds %d polygons — replacing one zone\'s ring takes one', $found));
    }

    public static function ringOutsideTheBoundary(string $name, string $areaName): self
    {
        return new self(\sprintf('the new ring for "%s" falls outside the boundary of %s', $name, $areaName));
    }

    public static function ringOverlaps(string $conflicting): self
    {
        return new self(\sprintf('the new ring overlaps "%s" — zones may touch along an edge, never share interior', $conflicting));
    }
}
