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

namespace Uhifadhi\Bundle\AreaBundle\Tests\Integration\Fixtures;

use Uhifadhi\Contracts\Kpi\DepartmentKpi;
use Uhifadhi\Contracts\Kpi\ZoneFigureProviderInterface;
use Uhifadhi\Contracts\Kpi\ZoneFigureRequest;
use Uhifadhi\Contracts\Kpi\ZoneFigures;

/**
 * A SECOND MODULE, AND A TALKATIVE ONE — four figures about one zone,
 * each with the kind of caption a real module writes.
 *
 * IT EXISTS TO HOLD A BAND DOWN. Every figure every module published
 * used to go into the zone record's identity band, so an area running
 * two modules drew twelve facts over four rows and repeated a module's
 * name in the header once per figure. A fixture that publishes one
 * figure could never have caught that, which is why this one publishes
 * four.
 */
final class ChattyFigureProvider implements ZoneFigureProviderInterface
{
    public const string SLUG = 'incidents';

    /** The one the band is expected to take: a module's FIRST figure leads. */
    public const string HEADLINE = 'incidents.recorded';

    public function moduleSlug(): string
    {
        return self::SLUG;
    }

    public function figuresFor(ZoneFigureRequest $request): ZoneFigures
    {
        $byZone = [];
        foreach ($request->zones as $zone) {
            $byZone[$zone->zoneUuid] = [
                $this->figure(self::HEADLINE, 'Recorded', 31.0, 'every incident inside the ring, '.$zone->name),
                $this->figure('incidents.open', 'Open', 7.0, 'still open at the end of the period'),
                $this->figure('incidents.fines', 'Fines', 4.0, 'issued and not yet paid'),
                $this->figure('incidents.compensation', 'Compensation', 2.0, 'claims against this ground'),
            ];
        }

        return new ZoneFigures($byZone, $request->period);
    }

    private function figure(string $key, string $label, float $value, string $caption): DepartmentKpi
    {
        return new DepartmentKpi(
            key: $key,
            label: $label,
            moduleSlug: self::SLUG,
            moduleName: 'Incidents',
            value: $value,
            caption: $caption,
        );
    }
}
