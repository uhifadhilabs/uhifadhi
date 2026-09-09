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

use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Uhifadhi\Bundle\TeamBundle\Entity\DepartmentScopeChange;
use Uhifadhi\Bundle\TeamBundle\Entity\User;
use Uhifadhi\Bundle\TeamBundle\Exception\MissingScopeChangeReasonException;
use Uhifadhi\Bundle\TeamBundle\Service\DepartmentService;
use Uhifadhi\Contracts\Entity\AreaInterface;

/**
 * WHAT A DEPARTMENT BECOMES when it is created, renamed, re-scoped or wound
 * down — asked of the objects, with no database in the room.
 *
 * The area is reached through the contract rather than an area package, exactly
 * as it is in the shipped code: an installation's areas are whatever class the
 * platform's {@see AreaInterface} resolves to, and nothing here needs to know
 * which.
 */
#[CoversClass(DepartmentService::class)]
final class DepartmentServiceTest extends TestCase
{
    public function testADepartmentWithNoAreaSpansEveryArea(): void
    {
        $department = self::service()->create('Ecology', null);

        self::assertTrue($department->isOrgLevel());
        self::assertNull($department->getArea());
    }

    public function testADepartmentWithAnAreaIsConfinedToIt(): void
    {
        $area = self::area();

        $department = self::service()->create('Anti-Poaching', $area);

        self::assertTrue($department->isAreaLevel());
        self::assertSame($area, $department->getArea());
    }

    public function testRenamingChangesTheNameAndNotTheScope(): void
    {
        $area = self::area();
        $department = self::service()->create('Anti-Poaching', $area);

        self::service()->rename($department, 'Protection Service');

        self::assertSame('Protection Service', $department->getName());
        self::assertSame($area, $department->getArea());
    }

    public function testConfiningAnOrgWideDepartmentMovesItAndRecordsWhy(): void
    {
        $department = self::service()->create('Ecology', null);

        self::service()->changeScope($department, $area = self::area(), new User(), 'Reorganised under the northern reserve.');

        self::assertSame($area, $department->getArea());

        $changes = $department->getScopeChanges()->toArray();
        self::assertCount(1, $changes);

        $change = reset($changes);
        self::assertInstanceOf(DepartmentScopeChange::class, $change);
        self::assertSame('Reorganised under the northern reserve.', $change->getReason());
    }

    public function testPromotingADepartmentWidensItAndRecordsWhy(): void
    {
        $department = self::service()->create('Ecology', self::area());

        self::service()->changeScope($department, null, null, 'Now serving every reserve.');

        self::assertTrue($department->isOrgLevel());
        self::assertCount(1, $department->getScopeChanges());
    }

    /** A scope change with no reason is refused before the area moves. */
    public function testAScopeChangeWithNoReasonIsRefusedAndNothingMoves(): void
    {
        $area = self::area();
        $department = self::service()->create('Ecology', $area);

        try {
            self::service()->changeScope($department, null, null, '  ');
            self::fail('A scope change with no reason has to be refused.');
        } catch (MissingScopeChangeReasonException) {
            self::assertSame($area, $department->getArea());
            self::assertCount(0, $department->getScopeChanges());
        }
    }

    public function testWindingOneDownIsNotDeletingItAndComesBack(): void
    {
        $department = self::service()->create('Ecology', null);

        self::service()->deactivate($department);
        self::assertFalse($department->isActive());

        self::service()->reactivate($department);
        self::assertTrue($department->isActive());
    }

    private static function service(): DepartmentService
    {
        return new DepartmentService(self::createStub(EntityManagerInterface::class));
    }

    private static function area(): AreaInterface
    {
        $area = self::createStub(AreaInterface::class);
        $area->method('getName')->willReturn('Northern Reserve');

        return $area;
    }
}
