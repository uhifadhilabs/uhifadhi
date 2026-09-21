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

namespace Uhifadhi\Bundle\TeamBundle\Tests\Integration\Fixtures;

use Uhifadhi\Contracts\People\StationPlate;
use Uhifadhi\Contracts\People\StationPlateProviderInterface;

/** THE GROUND, DRAWN BY A FIXTURE: one marked element for the one station the fixture knows. */
final class FakeStationPlates implements StationPlateProviderInterface
{
    public function plateFor(string $stationUuid): ?StationPlate
    {
        if (FakePersonPostings::STATION !== $stationUuid) {
            return null;
        }

        return new StationPlate('<div class="viewer zplate stplate" data-fixture-plate="'.$stationUuid.'">the ground</div>');
    }
}
