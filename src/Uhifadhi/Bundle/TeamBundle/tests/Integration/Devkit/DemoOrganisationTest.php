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

namespace Uhifadhi\Bundle\TeamBundle\Tests\Integration\Devkit;

use PHPUnit\Framework\Attributes\CoversClass;
use Uhifadhi\Bundle\TeamBundle\Devkit\TeamContentProvider;
use Uhifadhi\Bundle\TeamBundle\Enum\PermissionEnum;
use Uhifadhi\Bundle\TeamBundle\Enum\TeamRoleEnum;
use Uhifadhi\Bundle\TeamBundle\Repository\DepartmentRepository;
use Uhifadhi\Bundle\TeamBundle\Repository\PositionRepository;
use Uhifadhi\Bundle\TeamBundle\Repository\UserRepository;
use Uhifadhi\Bundle\TeamBundle\Tests\Integration\Fixtures\DevkitContentCollector;
use Uhifadhi\Bundle\TeamBundle\Tests\Integration\IntegrationTestCase;

/**
 * THE DEMO ORGANISATION, SEEDED THROUGH THE COLLECTOR THAT WILL SEED IT.
 *
 * The bundle offers content; devkit collects it in a dev install and runs it.
 * The collector standing in here does the same much, so what is proved is the
 * arrangement rather than a method call: the provider is reachable through the
 * tag, its dependencies are satisfiable, and running it leaves an organisation
 * somebody could have built from the screens.
 */
#[CoversClass(TeamContentProvider::class)]
final class DemoOrganisationTest extends IntegrationTestCase
{
    public function testTheBundleOffersItsContentThroughTheDevkitContracts(): void
    {
        self::assertContains('team', $this->collector()->keys());
    }

    /** It hangs on nothing, because this bundle knows about people and not about areas. */
    public function testTheContentStandsAlone(): void
    {
        self::assertSame([], $this->collector()->get('team')->dependsOn());
    }

    public function testSeedingLeavesAnOrganisationWithDepartmentsPositionsAndPeople(): void
    {
        $this->collector()->seed('team');
        $this->em->clear();

        self::assertCount(3, $this->service(DepartmentRepository::class)->findAllOrdered());
        self::assertCount(4, $this->service(PositionRepository::class)->findAllOrdered());
        self::assertCount(6, $this->service(UserRepository::class)->findAllByName());
    }

    /**
     * SEEDING TWICE IS SEEDING ONCE. A developer running the demo seeder again
     * is repeating a command, not asking for a second organisation — and the
     * slices seeded after this one only run if this one does not refuse.
     */
    public function testSeedingTwiceLeavesTheOrganisationTheFirstRunLeft(): void
    {
        $this->collector()->seed('team');
        $this->em->clear();

        $this->collector()->seed('team');
        $this->em->clear();

        self::assertCount(3, $this->service(DepartmentRepository::class)->findAllOrdered());
        self::assertCount(4, $this->service(PositionRepository::class)->findAllOrdered());
        self::assertCount(6, $this->service(UserRepository::class)->findAllByName());
    }

    /**
     * SOMEBODY CAN ADMINISTER IT. A demo organisation whose every account is
     * Staff is a demo nobody can open the team screens from.
     */
    public function testSomebodySeededCanAdministerTheTeam(): void
    {
        $this->collector()->seed('team');
        $this->em->clear();

        self::assertGreaterThan(0, $this->service(UserRepository::class)->countActiveSuperAdmins());
    }

    /**
     * THE GRANT IS A REAL ONE. A permission is only real if the catalogue
     * provides it, so a position holding one proves the content went through
     * the validated write path rather than around it.
     */
    public function testAPositionHoldsAPermissionTheCatalogueProvides(): void
    {
        $this->collector()->seed('team');
        $this->em->clear();

        $granted = [];
        foreach ($this->service(PositionRepository::class)->findAllOrdered() as $position) {
            $granted = [...$granted, ...$position->getPermissionValues()];
        }

        self::assertContains(PermissionEnum::TeamManage->value, $granted);
    }

    /**
     * A PERSON WITH NO POSITION IS A STATE THE ROSTER HAS TO DRAW, so the demo
     * organisation has one.
     */
    public function testSomebodyIsSeededWithNoPositionAtAll(): void
    {
        $this->collector()->seed('team');
        $this->em->clear();

        $unseated = array_filter(
            $this->service(UserRepository::class)->findAllByName(),
            static fn ($user): bool => null === $user->getPosition(),
        );

        self::assertCount(1, $unseated);
    }

    /**
     * EVERY SEEDED ACCOUNT IS ONE THE FIREWALL WOULD ACCEPT — verified, active,
     * and carrying a credential nobody typed and nothing printed.
     */
    public function testEverySeededAccountIsUsableAndItsCredentialIsNobodysToKnow(): void
    {
        $this->collector()->seed('team');
        $this->em->clear();

        foreach ($this->service(UserRepository::class)->findAllByName() as $user) {
            self::assertTrue($user->isVerified(), (string) $user->getEmail());
            self::assertTrue($user->isActive(), (string) $user->getEmail());
            self::assertNotSame('', (string) $user->getPassword());
        }
    }

    public function testTheSeededTiersAreTheOnesTheInstallationHas(): void
    {
        $this->collector()->seed('team');
        $this->em->clear();

        foreach ($this->service(UserRepository::class)->findAllByName() as $user) {
            self::assertContains($user->getTeamRole(), TeamRoleEnum::cases());
        }
    }

    private function collector(): DevkitContentCollector
    {
        $collector = static::getContainer()->get('test_public.devkit_content');
        self::assertInstanceOf(DevkitContentCollector::class, $collector);

        return $collector;
    }
}
