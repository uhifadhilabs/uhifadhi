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
 * A MODULE'S ZONE-FIGURE PROVIDER, played by a fixture and tagged by hand in
 * the test kernel exactly as a real module tags its own.
 *
 * IT STANDS IN FOR A MODULE THE CORE MUST NOT NAME. Patrol and incident will
 * publish the real figures from their own repositories; what this proves is
 * the seam between them and the core — that a tagged class is collected, and
 * that the named key survives the trip.
 */
final class TaggedFigureProvider implements ZoneFigureProviderInterface
{
    public const string COVERAGE = ZoneFigureProviderInterface::COVERED;

    public function moduleSlug(): string
    {
        return 'patrols';
    }

    public function figuresFor(ZoneFigureRequest $request): ZoneFigures
    {
        $byZone = [];
        foreach ($request->zones as $zone) {
            $byZone[$zone->zoneUuid] = [new DepartmentKpi(
                key: self::COVERAGE,
                label: 'Covered',
                moduleSlug: 'patrols',
                moduleName: 'Patrols',
                value: 82.0,
                unit: DepartmentKpi::SHARE,
                caption: 'Patrols module · '.$zone->name,
            )];
        }

        return new ZoneFigures($byZone, $request->period);
    }
}
