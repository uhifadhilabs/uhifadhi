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

namespace Uhifadhi\Bundle\AreaBundle\Api\State;

use Uhifadhi\Bundle\AreaBundle\Api\DutyApiContext;
use Uhifadhi\Bundle\AreaBundle\Service\DutyStationService;

/**
 * `GET /api/areas/{areaUuid}/stations?near=lat,lon` — API-CONTRACT.md §13E.
 *
 * THE LIST THE PICKER TOPS UP FROM. The posts themselves already reach
 * the phone with the area's words at sign-in; what this adds is the
 * catchment each of them carries, which is what the confirm screen
 * draws its ring from and what a duty officer reads as "inside".
 */
final class DutyStationsProvider extends DutyProvider
{
    public function __construct(
        private readonly DutyApiContext $api,
        private readonly DutyStationService $stations,
    ) {
    }

    protected function read(array $uriVariables): array
    {
        $area = $this->api->area(self::uri($uriVariables, 'areaUuid'));
        $this->api->requireRanger($area);

        return ['stations' => $this->stations->listFor($area, $this->api->query('near'))];
    }
}
