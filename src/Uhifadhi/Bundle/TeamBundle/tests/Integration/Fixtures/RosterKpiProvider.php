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
 *
 * IT ALSO PLAYS THE PROVIDER THAT HAS NOT HONOURED THE ONE-SET RULE, answering
 * one set PER AREA to a single call. The contract asks for one set — a page
 * cannot read three unheaded copies of the same label — so the lens draws each
 * set under the area name it carries rather than stacking them namelessly. That
 * is the defensive half of the rule, and it needs a provider that breaks it.
 */
final class RosterKpiProvider implements DepartmentKpiProviderInterface
{
    /** The areas this provider files a separate set under, instead of one roll-up. */
    private const array AREAS = ['Northern Reserve', 'Southern Plains'];

    public function moduleSlug(): string
    {
        return 'roster';
    }

    public function kpisFor(DepartmentRef $department, \DateTimeImmutable $now): array
    {
        $kpis = [];
        foreach (self::AREAS as $index => $area) {
            $kpis[] = new DepartmentKpi(
                key: 'shifts',
                label: 'Shifts filled',
                moduleSlug: 'roster',
                moduleName: 'Roster',
                value: 12.0 + $index,
                areaName: $area,
            );
        }

        return $kpis;
    }
}
