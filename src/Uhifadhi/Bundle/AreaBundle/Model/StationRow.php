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

use Uhifadhi\Bundle\AreaBundle\Entity\Station;

/**
 * ONE STATION AS THE REGISTER READS IT, SHUT — the name, the code, the zone
 * it turned out to be in and a plain count of who stands there.
 *
 * NO AVATARS AND NO "WHO LEADS" HERE. Those are the station page's; this
 * register is a configuration surface, and a row that carried them would be a
 * second, worse version of a page that already exists.
 *
 * THE ZONE IS DERIVED, NEVER CHOSEN, and the row says so wherever it prints
 * it. A post standing on ground no zone covers has no zone, which is legal
 * and common, and the register offers it as something to look for.
 */
final readonly class StationRow
{
    public function __construct(
        public string $uuid,
        public string $name,
        public ?string $code,
        public ?string $zoneUuid,
        public ?string $zoneName,
        public ?string $zoneHue,
        public int $posted,
        public bool $active,
        public ?StationPoint $point,
        public ?int $elevationM,
        public ?string $locality,
        public ?\DateTimeImmutable $openedAt,
    ) {
    }

    /** The zone as a filter value: its identifier, or the word for having none. */
    public function zoneValue(): string
    {
        return $this->zoneUuid ?? StationQuery::UNZONED;
    }

    /** What the search reads: the name and the code, and nothing else. */
    public function matches(string $term): bool
    {
        $term = mb_strtolower(trim($term));

        return '' === $term
            || str_contains(mb_strtolower($this->name), $term)
            || str_contains(mb_strtolower((string) $this->code), $term);
    }

    public static function of(Station $station, int $posted, ?string $zoneHue): self
    {
        $zone = $station->getZone();

        return new self(
            uuid: (string) $station->getUuidString(),
            name: (string) $station->getName(),
            code: $station->getCode(),
            zoneUuid: null === $zone ? null : (string) $zone->getUuidString(),
            zoneName: $zone?->getName(),
            zoneHue: $zoneHue,
            posted: $posted,
            active: $station->isActive(),
            point: StationPoint::of($station->getPoint()),
            elevationM: $station->getElevationM(),
            locality: $station->getLocality(),
            openedAt: $station->getOpenedAt(),
        );
    }
}
