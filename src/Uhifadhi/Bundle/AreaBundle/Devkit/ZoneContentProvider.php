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

namespace Uhifadhi\Bundle\AreaBundle\Devkit;

use Symfony\Component\HttpFoundation\File\File;
use Uhifadhi\Bundle\AreaBundle\Repository\AreaOfInterestRepository;
use Uhifadhi\Bundle\AreaBundle\Repository\ZoneRepository;
use Uhifadhi\Bundle\AreaBundle\Service\ZoneImportService;
use Uhifadhi\Contracts\Devkit\ContentProviderInterface;

/**
 * EVERY DEMO AREA GETS A ZONING SCHEME — six sectors, subdividing the ground
 * the boundary encloses and stopping short of it, so the screens have both a
 * scheme to draw and ground outside one.
 *
 * IT ARRIVES AS A FILE, THROUGH THE IMPORT. A zoning scheme has exactly one
 * supported way in — one GeoJSON FeatureCollection, one feature per zone,
 * through {@see ZoneImportService} — and demo content that wrote the zones
 * straight through the zone service would be demo content exempt from the
 * checks every real scheme passes: the names, the boundary, the invariant that
 * two zones share no interior. So the table is written out as the file it
 * would have been exported as, imported, and let go.
 *
 * THE FILE IS TEMPORARY AND THE PROVENANCE IS NOT. What the import keeps is a
 * row saying a scheme arrived and under what name; the document behind it is
 * deleted here as it is discarded everywhere else.
 *
 * NOBODY IS RECORDED AS THE IMPORTER, for the reason provenance exists:
 * a person is named where one is known, and a seeder is not one.
 *
 * IT SEEDS ONCE, PER AREA. An area that already has zones is left exactly as it
 * is — an import ADDS, so a second run would otherwise be the moment a
 * developer's own zone found itself beside a demo one.
 */
final readonly class ZoneContentProvider implements ContentProviderInterface
{
    /** What the provenance row is named after, since no real document was uploaded. */
    private const string FILE_NAME = 'demo-zoning-scheme.geojson';

    public function __construct(
        private AreaOfInterestRepository $register,
        private ZoneRepository $zones,
        private ZoneImportService $import,
    ) {
    }

    public function key(): string
    {
        return 'zone';
    }

    public function label(): string
    {
        return 'Zones';
    }

    public function description(): string
    {
        return 'A zoning scheme for every demo area, imported as one FeatureCollection.';
    }

    public function dependsOn(): array
    {
        return ['area'];
    }

    public function load(): void
    {
        foreach (DemoArea::all() as $demo) {
            $area = $this->register->findOneBy(['name' => $demo->name]);

            if (null === $area || $this->zones->countFor($area) > 0) {
                continue;
            }

            $path = (string) tempnam(sys_get_temp_dir(), 'uhifadhi-demo-zones');
            file_put_contents($path, $demo->zoneScheme());

            try {
                // THE IMPORT WRITES ITS OWN LOG LINE. Nothing here says so
                // twice: the verb is what knows the scheme arrived, so an
                // area seeded this way reads exactly as one imported from the
                // screen does.
                $this->import->importInto($area, new File($path), self::FILE_NAME);
            } finally {
                unlink($path);
            }
        }
    }
}
