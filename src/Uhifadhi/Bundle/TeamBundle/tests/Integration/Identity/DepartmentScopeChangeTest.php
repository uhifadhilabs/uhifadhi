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

namespace Uhifadhi\Bundle\TeamBundle\Tests\Integration\Identity;

use Uhifadhi\Bundle\TeamBundle\Entity\Department;
use Uhifadhi\Bundle\TeamBundle\Entity\DepartmentScopeChange;
use Uhifadhi\Bundle\TeamBundle\Entity\User;
use Uhifadhi\Bundle\TeamBundle\Enum\DepartmentScopeEnum;
use Uhifadhi\Bundle\TeamBundle\Enum\TeamRoleEnum;
use Uhifadhi\Bundle\TeamBundle\Exception\MissingScopeChangeReasonException;
use Uhifadhi\Bundle\TeamBundle\Repository\DepartmentRepository;
use Uhifadhi\Bundle\TeamBundle\Repository\DepartmentScopeChangeRepository;
use Uhifadhi\Bundle\TeamBundle\Tests\Integration\Fixtures\Area\HostArea;
use Uhifadhi\Bundle\TeamBundle\Tests\Integration\IntegrationTestCase;

/**
 * CHANGING A DEPARTMENT'S SCOPE RECORDS WHO, WHEN AND WHY — an audit line, on
 * the transition, in both directions.
 *
 * The reason is the payload of that line, so a change without one is refused
 * before the area moves. The record survives its actor and its area being
 * removed, because an audit trail that a later deletion can erase proves nothing.
 */
final class DepartmentScopeChangeTest extends IntegrationTestCase
{
    private function administrator(): User
    {
        $user = new User()
            ->setEmail('naomi@example.test')
            ->setFirstName('Naomi')->setLastName('Kileo')
            ->setPassword('x')->setTeamRole(TeamRoleEnum::SuperAdmin)->setVerified(true);
        $this->em->persist($user);

        return $user;
    }

    /**
     * CONFINE (org → area): the department narrows to one area and the audit line
     * holds every one of who/when/why plus which way it went.
     */
    public function testConfiningAnOrgDepartmentRecordsAnAuditedReason(): void
    {
        $admin = $this->administrator();
        $area = new HostArea()->setName('Northern Reserve');
        $this->em->persist($area);

        $department = new Department()->setName('Ecology');
        $this->em->persist($department);

        $change = $department->changeScopeTo($area, $admin, 'Ecology now works only in the north.');
        $this->em->flush();
        $this->em->clear();

        $stored = $this->service(DepartmentRepository::class)->findOneByName('Ecology');
        self::assertInstanceOf(Department::class, $stored);
        self::assertTrue($stored->isAreaLevel(), 'The department is confined after the change.');

        self::assertNotNull($change->getId());
        $trail = $this->service(DepartmentScopeChangeRepository::class)->findForDepartment($stored);
        self::assertCount(1, $trail);

        $entry = $trail[0];
        self::assertSame(DepartmentScopeEnum::Org, $entry->getFromScope());
        self::assertSame(DepartmentScopeEnum::Area, $entry->getToScope());
        self::assertSame('Ecology now works only in the north.', $entry->getReason());
        self::assertInstanceOf(User::class, $entry->getChangedBy());
        self::assertSame('naomi@example.test', $entry->getChangedBy()->getEmail());
        self::assertNotNull($entry->getArea(), 'Confining records the area it was confined to.');
    }

    /** PROMOTE (area → org): the department widens, and the transition is still audited. */
    public function testPromotingAnAreaDepartmentRecordsAnAuditedReason(): void
    {
        $admin = $this->administrator();
        $area = new HostArea()->setName('Northern Reserve');
        $this->em->persist($area);

        $department = new Department()->setName('Wetland Management')->setArea($area);
        $this->em->persist($department);

        $department->changeScopeTo(null, $admin, 'Its remit is now the whole park.');
        $this->em->flush();
        $this->em->clear();

        $stored = $this->service(DepartmentRepository::class)->findOneByName('Wetland Management');
        self::assertInstanceOf(Department::class, $stored);
        self::assertTrue($stored->isOrgLevel(), 'The department is org-wide after promotion.');
        self::assertNull($stored->getArea());

        $trail = $this->service(DepartmentScopeChangeRepository::class)->findForDepartment($stored);
        self::assertCount(1, $trail);
        self::assertSame(DepartmentScopeEnum::Area, $trail[0]->getFromScope());
        self::assertSame(DepartmentScopeEnum::Org, $trail[0]->getToScope());
    }

    /** A BLANK REASON IS REFUSED, and the area does not move. */
    public function testABlankReasonIsRefusedAndNothingChanges(): void
    {
        $area = new HostArea()->setName('Northern Reserve');
        $this->em->persist($area);
        $department = new Department()->setName('Ecology');
        $this->em->persist($department);
        $this->em->flush();

        try {
            $department->changeScopeTo($area, null, '   ');
            self::fail('A blank reason should be refused.');
        } catch (MissingScopeChangeReasonException) {
            // expected
        }

        self::assertTrue($department->isOrgLevel(), 'The refusal happens before the area moves.');
        self::assertCount(0, $department->getScopeChanges());
    }

    /** The trail accumulates in order across several changes. */
    public function testTheTrailAccumulatesInOrder(): void
    {
        $admin = $this->administrator();
        $north = new HostArea()->setName('Northern Reserve');
        $south = new HostArea()->setName('Southern Reserve');
        $this->em->persist($north);
        $this->em->persist($south);

        $department = new Department()->setName('Ecology');
        $this->em->persist($department);

        $department->changeScopeTo($north, $admin, 'Confine to Northern Reserve.');
        $department->changeScopeTo($south, $admin, 'Move to Southern Reserve.');
        $department->changeScopeTo(null, $admin, 'Widen to the whole org.');
        $this->em->flush();
        $this->em->clear();

        $stored = $this->service(DepartmentRepository::class)->findOneByName('Ecology');
        self::assertInstanceOf(Department::class, $stored);

        $trail = $this->service(DepartmentScopeChangeRepository::class)->findForDepartment($stored);
        self::assertCount(3, $trail);
        self::assertSame('Confine to Northern Reserve.', $trail[0]->getReason());
        self::assertSame('Move to Southern Reserve.', $trail[1]->getReason());
        self::assertSame('Widen to the whole org.', $trail[2]->getReason());
    }

    /** A console or seed transition has no signed-in actor; the line is still truthful. */
    public function testAChangeMayHaveNoActor(): void
    {
        $area = new HostArea()->setName('Northern Reserve');
        $this->em->persist($area);
        $department = new Department()->setName('Ecology');
        $this->em->persist($department);

        $department->changeScopeTo($area, null, 'Seeded as area-level.');
        $this->em->flush();
        $this->em->clear();

        $stored = $this->service(DepartmentRepository::class)->findOneByName('Ecology');
        self::assertInstanceOf(Department::class, $stored);
        $trail = $this->service(DepartmentScopeChangeRepository::class)->findForDepartment($stored);
        self::assertCount(1, $trail);
        self::assertNull($trail[0]->getChangedBy());
    }

    /** The scope-change table is prefixed, like every table this module owns. */
    public function testTheTableIsPrefixed(): void
    {
        self::assertSame(
            'team_department_scope_change',
            $this->em->getClassMetadata(DepartmentScopeChange::class)->getTableName(),
        );
    }
}
