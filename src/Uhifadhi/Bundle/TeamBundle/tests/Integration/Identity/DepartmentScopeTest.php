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
use Uhifadhi\Bundle\TeamBundle\Enum\DepartmentScopeEnum;
use Uhifadhi\Bundle\TeamBundle\Repository\DepartmentRepository;
use Uhifadhi\Bundle\TeamBundle\Tests\Integration\Fixtures\Area\HostArea;
use Uhifadhi\Bundle\TeamBundle\Tests\Integration\IntegrationTestCase;
use Uhifadhi\Contracts\Entity\AreaInterface;

/**
 * A DEPARTMENT IS ORG-LEVEL OR AREA-LEVEL, AND THE SCOPE IS DERIVED FROM ONE
 * NULLABLE AREA.
 *
 * The area is referenced through the platform's {@see AreaInterface} contract (the contracts) and
 * resolved to a host entity by the kernel — never by this bundle — exactly as a
 * real installation resolves it through AreaBundle. So these tests are
 * also the proof that a department can point at an area without this bundle
 * requiring an area package.
 */
final class DepartmentScopeTest extends IntegrationTestCase
{
    /** A department created with no area is org-level, and that is the default. */
    public function testADepartmentWithNoAreaIsOrgLevel(): void
    {
        $department = new Department()->setName('Ecology');

        $this->em->persist($department);
        $this->em->flush();
        $this->em->clear();

        $stored = $this->service(DepartmentRepository::class)->findOneByName('Ecology');
        self::assertInstanceOf(Department::class, $stored);
        self::assertNull($stored->getArea());
        self::assertTrue($stored->isOrgLevel());
        self::assertFalse($stored->isAreaLevel());
        self::assertSame(DepartmentScopeEnum::Org, $stored->getScope());
    }

    /** A department given an area is area-level, and reads that area back. */
    public function testADepartmentWithAnAreaIsAreaLevel(): void
    {
        $area = new HostArea()->setName('Northern Reserve');
        $this->em->persist($area);

        $department = new Department()->setName('Wetland Management')->setArea($area);
        $this->em->persist($department);
        $this->em->flush();
        $areaId = $area->getId();
        $this->em->clear();

        $stored = $this->service(DepartmentRepository::class)->findOneByName('Wetland Management');
        self::assertInstanceOf(Department::class, $stored);
        self::assertTrue($stored->isAreaLevel());
        self::assertFalse($stored->isOrgLevel());
        self::assertSame(DepartmentScopeEnum::Area, $stored->getScope());

        $storedArea = $stored->getArea();
        self::assertInstanceOf(AreaInterface::class, $storedArea);
        self::assertSame($areaId, $storedArea->getId());
    }

    /**
     * THE AREA ASSOCIATION IS TO THE CONTRACT, NOT TO A CONCRETE CLASS — the
     * property the whole arrangement rests on. The mapping targets
     * AreaInterface, which the installation resolves; this bundle names no area
     * entity of its own anywhere in the mapping.
     */
    public function testTheAreaAssociationTargetsTheAreaContract(): void
    {
        $mapping = $this->em->getClassMetadata(Department::class)->getAssociationMapping('area');

        // Resolved, in this suite, to the host's stand-in — proving the target is
        // the interface and the resolution is what supplies the concrete class.
        self::assertSame(HostArea::class, $mapping['targetEntity']);
    }

    /**
     * THE AREA IS NULLABLE AT THE DATABASE — org-level is a stored state, not an
     * unfinished row. Proven behaviourally: a department with no area flushes,
     * which a NOT NULL column would refuse. {@see testADepartmentWithNoAreaIsOrgLevel}
     * already persists exactly that, so this is the same guarantee stated as its
     * own decision rather than a diff nobody read.
     */
    public function testADepartmentPersistsWithNoArea(): void
    {
        $department = new Department()->setName('Administration');

        $this->em->persist($department);
        $this->em->flush();

        self::assertNotNull($department->getId(), 'A null area must be storable — org-level is a real row.');
    }

    /** The repository splits the register the way the design groups it. */
    public function testTheRepositorySeparatesAreaLevelFromOrgLevel(): void
    {
        $area = new HostArea()->setName('Northern Reserve');
        $this->em->persist($area);
        $this->em->persist(new Department()->setName('Wetland Management')->setArea($area));
        $this->em->persist(new Department()->setName('Ecology'));
        $this->em->persist(new Department()->setName('Administration'));
        $this->em->flush();
        $this->em->clear();

        $repository = $this->service(DepartmentRepository::class);

        $areaLevel = array_map(static fn (Department $d): ?string => $d->getName(), $repository->findAreaLevelOrdered());
        $orgLevel = array_map(static fn (Department $d): ?string => $d->getName(), $repository->findOrgLevelOrdered());

        self::assertSame(['Wetland Management'], $areaLevel);
        self::assertSame(['Administration', 'Ecology'], $orgLevel);
    }
}
