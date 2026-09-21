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

namespace Uhifadhi\Contracts\People;

/**
 * WHO DRAWS THE GROUND AROUND A STATION on a person's record.
 *
 * A person's page says where they are stationed and shows the place; the
 * package that owns stations is the only one that can draw it, and it does so
 * through this seam rather than the person's page reaching into it. Tagged
 * explicitly at both ends, like every seam here.
 */
interface StationPlateProviderInterface
{
    public const string TAG = 'uhifadhi.station_plates';

    /** The plate for this station, or null when the station is not this provider's. */
    public function plateFor(string $stationUuid): ?StationPlate;
}
