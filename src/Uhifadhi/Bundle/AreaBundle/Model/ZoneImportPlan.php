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
 * WHAT A FILE WOULD DO TO AN AREA, BEFORE ANYTHING IS WRITTEN.
 *
 * A PREVIEW IS NOT A DRY RUN OF A WRITE, it is the thing the person confirms.
 * Every feature is listed with its verdict, the arriving ones are the default
 * subset, and the confirm names the subset it means — so a partial import is
 * something chosen rather than something that happened.
 *
 * THE FILE IS ALREADY GONE. Parsing produced this, and this holds geometry as
 * strings; the document is neither stored nor re-read. What survives a confirm
 * is the geometry in PostGIS and the provenance beside it.
 *
 * THE SUMMARY STATES WHAT WAS READ PAST. A file carries KML residue and merge
 * fields, and the properties nothing was taken from are named rather than
 * silently dropped; so are the features that were not polygons at all.
 */
final readonly class ZoneImportPlan
{
    /**
     * @param string                $fileName          the name of the file that was read, and not kept
     * @param string                $nameProperty      which property supplied the names
     * @param list<string>          $ignoredProperties every other property the file carried, sorted
     * @param list<ZoneFeaturePlan> $features          every feature, in file order, arriving or flagged
     * @param array<string,int>     $skippedGeometries geometry types that are not areas, by count — "2 points, 1 line"
     */
    public function __construct(
        public string $fileName,
        public string $nameProperty,
        public array $ignoredProperties,
        public array $features,
        public array $skippedGeometries = [],
    ) {
    }

    /** @return list<ZoneFeaturePlan> */
    public function arriving(): array
    {
        return array_values(array_filter($this->features, static fn (ZoneFeaturePlan $f): bool => $f->isArriving()));
    }

    /** @return list<ZoneFeaturePlan> */
    public function flagged(): array
    {
        return array_values(array_filter($this->features, static fn (ZoneFeaturePlan $f): bool => !$f->isArriving()));
    }

    /** @return list<string> */
    public function arrivingNames(): array
    {
        return array_map(static fn (ZoneFeaturePlan $f): string => $f->name, $this->arriving());
    }

    public function count(): int
    {
        return \count($this->features);
    }

    /** The feature of that name, or null — how a confirm resolves the subset it was given. */
    public function feature(string $name): ?ZoneFeaturePlan
    {
        foreach ($this->features as $feature) {
            if ($feature->name === $name) {
                return $feature;
            }
        }

        return null;
    }
}
