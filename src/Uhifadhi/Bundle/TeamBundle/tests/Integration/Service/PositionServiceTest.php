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
use Uhifadhi\Bundle\TeamBundle\Entity\Department;
use Uhifadhi\Bundle\TeamBundle\Enum\PermissionEnum;
use Uhifadhi\Bundle\TeamBundle\Exception\NameNotUniqueException;
use Uhifadhi\Bundle\TeamBundle\Repository\PositionRepository;
use Uhifadhi\Bundle\TeamBundle\Service\PositionService;
use Uhifadhi\Bundle\TeamBundle\Tests\Integration\IntegrationTestCase;

/**
 * WHAT REACHES THE TABLE when a position is shaped — and, above all, WHERE its
 * name has to be unique.
 *
 * That last one is the whole reason this suite has a database: the index is on
 * (department, name), so the same word in two departments is two different jobs
 * and the same word twice in one department is the same job entered twice. No
 * amount of reasoning about objects proves which of those the schema believes.
 */
#[CoversClass(PositionService::class)]
final class PositionServiceTest extends IntegrationTestCase
{
    public function testACreatedPositionIsStoredUnderItsDepartment(): void
    {
        $ecology = $this->department('Ecology');

        $this->positions()->create('Analyst', $ecology);

        $stored = $this->stored()->findAllOrdered();
        self::assertCount(1, $stored);
        self::assertSame('Ecology / Analyst', $stored[0]->getQualifiedName());
    }

    public function testTheSameNameInTwoDepartmentsIsTwoDifferentJobs(): void
    {
        $this->positions()->create('Analyst', $this->department('Ecology'));
        $this->positions()->create('Analyst', $this->department('Protection Service'));

        self::assertCount(2, $this->stored()->findAllOrdered());
    }

    public function testTheSameNameTwiceInOneDepartmentIsRefused(): void
    {
        $ecology = $this->department('Ecology');
        $this->positions()->create('Analyst', $ecology);

        $this->expectException(NameNotUniqueException::class);

        $this->positions()->create('Analyst', $ecology);
    }

    public function testRenamingIsStored(): void
    {
        $position = $this->positions()->create('Analyst', $this->department('Ecology'));

        $this->positions()->rename($position, 'Senior Analyst');

        self::assertSame('Ecology / Senior Analyst', $this->stored()->findAllOrdered()[0]->getQualifiedName());
    }

    public function testAGrantIsStoredAndReadBack(): void
    {
        $position = $this->positions()->create('Analyst', null);

        $this->positions()->setPermissions($position, [PermissionEnum::TeamManage->value]);

        self::assertSame([PermissionEnum::TeamManage->value], $this->stored()->findAllOrdered()[0]->getPermissionValues());
    }

    public function testFilingAPositionIsStored(): void
    {
        $position = $this->positions()->create('Analyst', $this->department('Ecology'));
        $protection = $this->department('Protection Service');

        $this->positions()->file($position, $protection);

        self::assertSame('Protection Service / Analyst', $this->stored()->findAllOrdered()[0]->getQualifiedName());
    }

    public function testFilingIntoADepartmentThatAlreadyOwnsTheNameIsRefused(): void
    {
        $protection = $this->department('Protection Service');
        $this->positions()->create('Analyst', $protection);
        $moving = $this->positions()->create('Analyst', $this->department('Ecology'));

        $this->expectException(NameNotUniqueException::class);

        $this->positions()->file($moving, $protection);
    }

    private function department(string $name): Department
    {
        $department = new Department()->setName($name);
        $this->em->persist($department);
        $this->em->flush();

        return $department;
    }

    private function positions(): PositionService
    {
        return $this->service(PositionService::class);
    }

    private function stored(): PositionRepository
    {
        $this->em->clear();

        return $this->service(PositionRepository::class);
    }
}
