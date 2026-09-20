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
use Uhifadhi\Bundle\AreaBundle\Entity\Station;
use Uhifadhi\Bundle\AreaBundle\Repository\StationRepository;

/**
 * THE POSTS A HANDSET MAY CLAIM, WITH WHAT "INSIDE" EACH ONE MEANS —
 * API-CONTRACT.md §13E.
 *
 * THE WHOLE LIST, SMALL ENOUGH TO HOLD. An area has posts in the tens,
 * not the thousands, and the phone has to be able to offer the picker
 * with no network — so this is not paged and never will be.
 *
 * `near` IS A HINT AND NOTHING MORE. The app sorts by the distance IT
 * measures, because the list it actually offers is the one already
 * cached; ordering here is a courtesy for a client reading the endpoint
 * fresh. It is done in PHP rather than in the database on purpose: the
 * set is already in memory, a spatial ORDER BY would be a second query
 * against an index nobody needs for tens of rows, and a `near` that
 * does not parse must narrow nothing rather than fail a read the Duty
 * tab depends on.
 *
 * NULL CATCHMENT IS A REAL STATE. A post with no ring has no inside,
 * and the handset says so rather than picking a radius of its own —
 * which is why nothing here substitutes a default.
 *
 * INACTIVE POSTS ARE NOT OFFERED. A post that has stopped taking
 * postings is not one somebody can be at today; the days already
 * recorded against it keep their answer, because a claim holds the post
 * it named.
 */
final readonly class DutyStationService
{
    public function __construct(
        private StationRepository $stations,
    ) {
    }

    /**
     * @param string|null $near `lat,lon` — a hint, never a filter
     *
     * `code` has been sent since the phone learned to join a watch's station
     * to its cached posts; the shape said otherwise, which is how a caller
     * ends up parsing a field the type says is not there
     *
     * @return list<array{uuid: string, name: string, code: string|null, lat: float|null, lon: float|null, catchmentM: int|null}>
     */
    public function listFor(AreaOfInterest $area, ?string $near = null): array
    {
        $rows = [];
        foreach ($this->stations->findByArea($area) as $station) {
            if (!$station->isActive()) {
                continue;
            }

            $uuid = $station->getUuidString();
            if (null === $uuid) {
                continue;
            }

            [$lat, $lon] = self::coordinatesOf($station);

            $rows[] = [
                'uuid' => $uuid,
                'name' => (string) $station->getName(),
                /*
                 * WHAT THE INSTALLATION CALLS IT ON THE RADIO. The
                 * picker and the confirm screen print it beside the
                 * name, because "ST-01" is what a ranger says out loud
                 * and a uuid is what nobody says at all. Null where the
                 * installation uses no codes, which is a real answer
                 * rather than an empty string.
                 */
                'code' => $station->getCode(),
                'lat' => $lat,
                'lon' => $lon,
                'catchmentM' => $station->getCatchmentM(),
            ];
        }

        $from = self::pairIn($near);
        if (null === $from) {
            return $rows;
        }

        /*
         * SORTED BY A FLAT-EARTH DISTANCE, DELIBERATELY. Over the tens of
         * kilometres an area spans, squared degrees with the longitude
         * scaled by the latitude order posts exactly as a spheroid would,
         * and nothing downstream reads the number — only the order. A post
         * with no point sorts last: it cannot be near anything.
         */
        usort($rows, static function (array $a, array $b) use ($from): int {
            return self::roughDistance($from, $a['lat'], $a['lon']) <=> self::roughDistance($from, $b['lat'], $b['lon']);
        });

        return $rows;
    }

    /**
     * @return array{0: float|null, 1: float|null} latitude and longitude, or two nulls
     */
    private static function coordinatesOf(Station $station): array
    {
        $point = json_decode((string) $station->getPoint(), true);
        $pair = \is_array($point) ? ($point['coordinates'] ?? null) : null;

        if (!\is_array($pair) || !is_numeric($pair[0] ?? null) || !is_numeric($pair[1] ?? null)) {
            return [null, null];
        }

        // GeoJSON IS LON/LAT, RFC 7946; the wire here is lat then lon.
        return [(float) $pair[1], (float) $pair[0]];
    }

    /** @return array{0: float, 1: float}|null */
    private static function pairIn(?string $near): ?array
    {
        if (null === $near) {
            return null;
        }

        $parts = explode(',', $near);
        if (2 !== \count($parts) || !is_numeric(trim($parts[0])) || !is_numeric(trim($parts[1]))) {
            return null;
        }

        $lat = (float) trim($parts[0]);
        $lon = (float) trim($parts[1]);

        return $lat >= -90.0 && $lat <= 90.0 && $lon >= -180.0 && $lon <= 180.0 ? [$lat, $lon] : null;
    }

    /** @param array{0: float, 1: float} $from */
    private static function roughDistance(array $from, ?float $lat, ?float $lon): float
    {
        if (null === $lat || null === $lon) {
            return \PHP_FLOAT_MAX;
        }

        $dLat = $lat - $from[0];
        $dLon = ($lon - $from[1]) * cos(deg2rad($from[0]));

        return $dLat * $dLat + $dLon * $dLon;
    }
}
