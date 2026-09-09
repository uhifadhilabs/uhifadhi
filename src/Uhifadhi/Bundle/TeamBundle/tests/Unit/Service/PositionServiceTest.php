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
use Uhifadhi\Bundle\TeamBundle\Entity\Department;
use Uhifadhi\Bundle\TeamBundle\Entity\Position;
use Uhifadhi\Bundle\TeamBundle\Enum\PermissionEnum;
use Uhifadhi\Bundle\TeamBundle\Exception\UnknownPermissionException;
use Uhifadhi\Bundle\TeamBundle\Service\PermissionCatalogue;
use Uhifadhi\Bundle\TeamBundle\Service\PositionService;

/**
 * WHAT A POSITION BECOMES when it is created, renamed, filed or granted —
 * asked of the objects, with no database in the room.
 *
 * The catalogue here is a real one with no modules installed, which is the
 * state of a fresh installation: this bundle's own seven and nothing else. That
 * is exactly the state in which "a value nothing provides is refused" has to
 * hold.
 */
#[CoversClass(PositionService::class)]
final class PositionServiceTest extends TestCase
{
    public function testAPositionIsNamedInsideItsDepartment(): void
    {
        $department = new Department()->setName('Ecology');

        $position = self::service()->create('Analyst', $department);

        self::assertSame('Analyst', $position->getName());
        self::assertSame($department, $position->getDepartment());
        self::assertSame('Ecology / Analyst', $position->getQualifiedName());
    }

    /** A position created before anybody decided which department owns it exists. */
    public function testAPositionMayBeFiledUnderNoDepartmentAtAll(): void
    {
        self::assertNull(self::service()->create('Analyst', null)->getDepartment());
    }

    public function testRenamingChangesOnlyTheName(): void
    {
        $department = new Department()->setName('Ecology');
        $position = self::service()->create('Analyst', $department);

        self::service()->rename($position, 'Senior Analyst');

        self::assertSame('Senior Analyst', $position->getName());
        self::assertSame($department, $position->getDepartment());
    }

    public function testFilingMovesAPositionAndChangesNothingAboutWhatItGrants(): void
    {
        $position = self::service()->create('Analyst', new Department()->setName('Ecology'));
        self::service()->setPermissions($position, [PermissionEnum::TeamManage->value]);

        self::service()->file($position, $protection = new Department()->setName('Protection Service'));

        self::assertSame($protection, $position->getDepartment());
        self::assertSame([PermissionEnum::TeamManage->value], $position->getPermissionValues());
    }

    /** The empty destination is a destination, not a missing value. */
    public function testAPositionCanLeaveADepartmentItShouldNeverHaveBeenIn(): void
    {
        $position = self::service()->create('Analyst', new Department()->setName('Ecology'));

        self::service()->file($position, null);

        self::assertNull($position->getDepartment());
    }

    public function testTheGrantIsReplacedWholesaleBecauseWhatIsAbsentWasRevoked(): void
    {
        $position = self::service()->create('Analyst', null);

        self::service()->setPermissions($position, [PermissionEnum::TeamManage->value]);
        self::assertSame([PermissionEnum::TeamManage->value], $position->getPermissionValues());

        self::service()->setPermissions($position, []);
        self::assertSame([], $position->getPermissionValues());
    }

    public function testAValueNoInstalledModuleProvidesIsRefusedRatherThanStored(): void
    {
        $position = self::service()->create('Analyst', null);

        $this->expectException(UnknownPermissionException::class);

        self::service()->setPermissions($position, ['sightings.record']);
    }

    private static function service(): PositionService
    {
        return new PositionService(
            self::createStub(EntityManagerInterface::class),
            new PermissionCatalogue(),
        );
    }
}
