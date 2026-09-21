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
 * `POST /areas/{areaUuid}/positions` — API-CONTRACT.md §13C.
 *
 * The §5 part-ack: what comes back is what was stored, and the phone
 * deletes exactly those.
 */
final class UploadPositionsProcessor extends DutyProcessor
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

        $stored = $this->checkIns->ping($area, $ranger, $this->api->body());

        return DutyResponse::partAck($stored['accepted'], $stored['duplicate']);
    }
}
