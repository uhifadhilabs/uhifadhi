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

namespace Uhifadhi\Bundle\TeamBundle\Tests\Integration\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use Uhifadhi\Bundle\TeamBundle\Entity\User;
use Uhifadhi\Bundle\TeamBundle\Enum\DepartmentScopeEnum;
use Uhifadhi\Bundle\TeamBundle\Enum\TeamRoleEnum;
use Uhifadhi\Bundle\TeamBundle\Exception\NameNotUniqueException;
use Uhifadhi\Bundle\TeamBundle\Repository\DepartmentRepository;
use Uhifadhi\Bundle\TeamBundle\Repository\DepartmentScopeChangeRepository;
use Uhifadhi\Bundle\TeamBundle\Service\DepartmentService;
use Uhifadhi\Bundle\TeamBundle\Tests\Integration\Fixtures\Area\HostArea;
use Uhifadhi\Bundle\TeamBundle\Tests\Integration\IntegrationTestCase;

/**
 * WHAT REACHES THE TABLE when a department is shaped — and where its name has
 * to be unique.
 *
 * A name is unique WITHIN a scope: two areas may each run an Anti-Poaching
 * unit, and the organisation-wide ones each stand alone. Which of those the
 * schema believes is a question only the schema can answer, so it is asked here.
 * The audit line a scope change leaves is asked here too, because a trail that
 * is only in memory is not a trail.
 */
#[CoversClass(DepartmentService::class)]
final class DepartmentServiceTest extends IntegrationTestCase
{
    public function testAnOrgWideDepartmentIsStoredWithNoArea(): void
    {
        $this->departments()->create('Ecology', null);

        $stored = $this->stored()->findOrgLevelOrdered();
        self::assertCount(1, $stored);
        self::assertNull($stored[0]->getArea());
    }

    public function testAnAreaLevelDepartmentIsStoredUnderItsArea(): void
    {
        $this->departments()->create('Anti-Poaching', $this->area('Northern Reserve'));

        $stored = $this->stored()->findAreaLevelOrdered();
        self::assertCount(1, $stored);
        self::assertSame('Northern Reserve', $stored[0]->getArea()?->getName());
    }

    public function testTwoAreasMayEachRunADepartmentOfTheSameName(): void
    {
        $this->departments()->create('Anti-Poaching', $this->area('Northern Reserve'));
        $this->departments()->create('Anti-Poaching', $this->area('Southern Reserve'));

        self::assertCount(2, $this->stored()->findAreaLevelOrdered());
    }

    public function testOneAreaMayNotRunTheSameDepartmentTwice(): void
    {
        $north = $this->area('Northern Reserve');
        $this->departments()->create('Anti-Poaching', $north);

        $this->expectException(NameNotUniqueException::class);

        $this->departments()->create('Anti-Poaching', $north);
    }

    public function testTwoOrgWideDepartmentsOfOneNameAreTheSameDepartmentEnteredTwice(): void
    {
        $this->departments()->create('Ecology', null);

        $this->expectException(NameNotUniqueException::class);

        $this->departments()->create('Ecology', null);
    }

    public function testRenamingIsStored(): void
    {
        $department = $this->departments()->create('Ecology', null);

        $this->departments()->rename($department, 'Science');

        self::assertSame('Science', $this->stored()->findOrgLevelOrdered()[0]->getName());
    }

    public function testConfiningADepartmentStoresTheMoveAndItsAuditedReason(): void
    {
        $admin = $this->administrator();
        $department = $this->departments()->create('Ecology', null);

        $this->departments()->changeScope($department, $this->area('Northern Reserve'), $admin, 'Reorganised under the northern reserve.');

        $this->em->clear();
        $changes = $this->service(DepartmentScopeChangeRepository::class)->findBy([]);
        self::assertCount(1, $changes);
        self::assertSame(DepartmentScopeEnum::Org, $changes[0]->getFromScope());
        self::assertSame(DepartmentScopeEnum::Area, $changes[0]->getToScope());
        self::assertSame('Reorganised under the northern reserve.', $changes[0]->getReason());
        self::assertSame('naomi@example.test', $changes[0]->getChangedBy()?->getEmail());
    }

    public function testWindingADepartmentDownKeepsTheRowAndReactivatingBringsItBack(): void
    {
        $department = $this->departments()->create('Ecology', null);

        $this->departments()->deactivate($department);
        self::assertCount(0, $this->stored()->findAllActiveOrdered());
        self::assertCount(1, $this->stored()->findAllOrdered());

        $revived = $this->stored()->findAllOrdered()[0];
        $this->departments()->reactivate($revived);
        self::assertCount(1, $this->stored()->findAllActiveOrdered());
    }

    private function administrator(): User
    {
        $user = new User()
            ->setEmail('naomi@example.test')
            ->setFirstName('Naomi')
            ->setLastName('Kileo')
            ->setPassword('x')
            ->setTeamRole(TeamRoleEnum::SuperAdmin)
            ->setVerified(true);
        $this->em->persist($user);
        $this->em->flush();

        return $user;
    }

    private function area(string $name): HostArea
    {
        $area = new HostArea()->setName($name);
        $this->em->persist($area);
        $this->em->flush();

        return $area;
    }

    private function departments(): DepartmentService
    {
        return $this->service(DepartmentService::class);
    }

    private function stored(): DepartmentRepository
    {
        $this->em->clear();

        return $this->service(DepartmentRepository::class);
    }
}
