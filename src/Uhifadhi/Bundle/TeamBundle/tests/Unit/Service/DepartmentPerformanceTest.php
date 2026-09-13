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

namespace Uhifadhi\Bundle\TeamBundle\Tests\Unit\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Uhifadhi\Bundle\RegistryBundle\Entity\Module;
use Uhifadhi\Bundle\TeamBundle\Entity\Department;
use Uhifadhi\Bundle\TeamBundle\Service\DepartmentPerformance;
use Uhifadhi\Contracts\Entity\AreaInterface;
use Uhifadhi\Contracts\Kpi\DepartmentKpi;
use Uhifadhi\Contracts\Kpi\DepartmentKpiProviderInterface;
use Uhifadhi\Contracts\Kpi\DepartmentRef;

/**
 * WHAT A PROVIDER IS TOLD — the ref the core hands it, asked of the objects.
 *
 * THE REF CARRIES THE SCOPE. A department is either confined to one area or
 * spans the organisation, and a provider cannot answer for the right rows unless
 * it is told which: handed a ref with no scope, a provider either guesses or
 * answers with every area's figures at once, and the second is what put one
 * module's labels on a page three times.
 */
#[CoversClass(DepartmentPerformance::class)]
final class DepartmentPerformanceTest extends TestCase
{
    /** AN AREA-LEVEL DEPARTMENT NAMES ITS AREA, so a provider reports that area alone. */
    public function testTheRefCarriesTheAreaAnAreaLevelDepartmentIsConfinedTo(): void
    {
        $area = self::createStub(AreaInterface::class);
        $area->method('getUuidString')->willReturn('0191f2c2-0000-7000-8000-0000000000aa');

        $ref = $this->refHandedTo($this->department()->setArea($area));

        self::assertSame('0191f2c2-0000-7000-8000-0000000000aa', $ref->areaUuid);
    }

    /** AN ORG-LEVEL ONE NAMES NO AREA, which is the ref's word for "roll up every area". */
    public function testAnOrgLevelDepartmentSendsNoArea(): void
    {
        self::assertNull($this->refHandedTo($this->department())->areaUuid);
    }

    /** And the rest of the ref is what a figure is filed under. */
    public function testTheRefCarriesTheNameAPlatePrints(): void
    {
        self::assertSame('Ecology', $this->refHandedTo($this->department())->name);
    }

    /** A department with the surveys module attached, and nothing else. */
    private function department(): Department
    {
        $module = (new Module())->setSlug('surveys')->setName('Surveys');

        return (new Department())->setName('Ecology')->attachModule($module);
    }

    private function refHandedTo(Department $department): DepartmentRef
    {
        $provider = new class implements DepartmentKpiProviderInterface {
            public ?DepartmentRef $asked = null;

            public function moduleSlug(): string
            {
                return 'surveys';
            }

            public function kpisFor(DepartmentRef $department, \DateTimeImmutable $now): array
            {
                $this->asked = $department;

                return [new DepartmentKpi('surveys', 'Surveys logged', 'surveys', 'Surveys', 88.0)];
            }
        };

        new DepartmentPerformance([$provider])->kpisFor($department, new \DateTimeImmutable('2026-09-13'));

        self::assertInstanceOf(DepartmentRef::class, $provider->asked);

        return $provider->asked;
    }
}
