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

use Symfony\Component\HttpFoundation\Response;
use Uhifadhi\Bundle\AreaBundle\Api\DutyApiContext;
use Uhifadhi\Bundle\AreaBundle\Api\DutyResponse;
use Uhifadhi\Bundle\AreaBundle\Service\CheckInService;

/**
 * `POST /areas/{areaUuid}/checkins` — API-CONTRACT.md §13A.
 *
 * 201 the first time and 200 on a re-send, which is the line the
 * contract draws and the app reads.
 */
final class CreateCheckInProcessor extends DutyProcessor
{
    public function __construct(
        private readonly DutyApiContext $api,
        private readonly CheckInService $checkIns,
    ) {
    }

    protected function handle(array $uriVariables): Response
    {
        $area = $this->api->area(self::uri($uriVariables, 'areaUuid'));
        $ranger = $this->api->requireRanger($area);

        [$checkIn, $duplicate] = $this->checkIns->claim($area, $ranger, $this->api->body());

        return DutyResponse::checkIn($checkIn, $duplicate, created: !$duplicate);
    }
}
