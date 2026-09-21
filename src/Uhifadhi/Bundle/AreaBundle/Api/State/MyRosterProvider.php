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
use Uhifadhi\Bundle\AreaBundle\Api\DutyApiException;
use Uhifadhi\Bundle\AreaBundle\Service\DutyRosterService;

/**
 * `GET /api/areas/{areaUuid}/me/roster?from=&to=` — API-CONTRACT.md §13D.
 *
 * "ME" IS THE TOKEN'S ACCOUNT AND NOTHING ELSE. There is no person in
 * this URI and there must not be one: a handset reading somebody
 * else's month would be reading where that person is expected to be
 * every night, which is not a thing this product hands out on a query
 * parameter.
 */
final class MyRosterProvider extends DutyProvider
{
    /**
     * A MONTH, WHEN THE CALLER NAMES NO WINDOW. The phone always sends
     * both, but a read that answered nothing without them would make the
     * endpoint impossible to try, and "the month around today" is what
     * every caller means.
     */
    private const int DEFAULT_DAYS_EITHER_SIDE = 31;

    public function __construct(
        private readonly DutyApiContext $api,
        private readonly DutyRosterService $roster,
    ) {
    }

    protected function read(array $uriVariables): array
    {
        $area = $this->api->area(self::uri($uriVariables, 'areaUuid'));
        $ranger = $this->api->requireRanger($area);

        $today = new \DateTimeImmutable('today');

        return $this->roster->readFor(
            $area,
            $ranger,
            $this->day('from') ?? $today->modify('-'.self::DEFAULT_DAYS_EITHER_SIDE.' days')->format('Y-m-d'),
            $this->day('to') ?? $today->modify('+'.self::DEFAULT_DAYS_EITHER_SIDE.' days')->format('Y-m-d'),
        );
    }

    /**
     * A DAY OR NOTHING. A window the caller mistyped is refused by name
     * rather than silently widened: a month quietly answered for the
     * wrong dates is a month the ranger plans around.
     *
     * @throws DutyApiException
     */
    private function day(string $key): ?string
    {
        $value = $this->api->query($key);
        if (null === $value) {
            return null;
        }

        if (1 !== preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            throw DutyApiException::invalidPayload(\sprintf('"%s" is a day, written 2026-09-19.', $key), ['field' => $key, 'value' => $value]);
        }

        return $value;
    }
}
