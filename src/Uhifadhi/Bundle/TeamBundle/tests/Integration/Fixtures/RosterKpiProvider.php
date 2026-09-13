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

use Uhifadhi\Contracts\Kpi\DepartmentKpi;
use Uhifadhi\Contracts\Kpi\DepartmentKpiProviderInterface;
use Uhifadhi\Contracts\Kpi\DepartmentRef;

/**
 * A SECOND MODULE WITH A FIGURE — the roster module of
 * {@see SilentModuleProvider}, registered so the strip can be asked the
 * question that matters: a provider is read only when the department attaches
 * its module, and a detached module's plate leaves the page rather than going to
 * zero.
 */
final class RosterKpiProvider implements DepartmentKpiProviderInterface
{
    public function moduleSlug(): string
    {
        return 'roster';
    }

    public function kpisFor(DepartmentRef $department, \DateTimeImmutable $now): array
    {
        return [
            new DepartmentKpi(
                key: 'shifts',
                label: 'Shifts filled',
                moduleSlug: 'roster',
                moduleName: 'Roster',
                value: 12.0,
            ),
        ];
    }
}
