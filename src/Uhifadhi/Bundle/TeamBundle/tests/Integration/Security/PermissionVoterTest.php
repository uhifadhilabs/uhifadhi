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

namespace Uhifadhi\Bundle\TeamBundle\Tests\Integration\Security;

use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;
use Uhifadhi\Bundle\TeamBundle\Entity\Placement;
use Uhifadhi\Bundle\TeamBundle\Entity\Position;
use Uhifadhi\Bundle\TeamBundle\Entity\User;
use Uhifadhi\Bundle\TeamBundle\Enum\PermissionEnum;
use Uhifadhi\Bundle\TeamBundle\Enum\TeamRoleEnum;
use Uhifadhi\Bundle\TeamBundle\Security\PermissionVoter;
use Uhifadhi\Bundle\TeamBundle\Service\PermissionCatalogue;
use Uhifadhi\Bundle\TeamBundle\Tests\Integration\Fixtures\Area\HostArea;
use Uhifadhi\Bundle\TeamBundle\Tests\Integration\IntegrationTestCase;

/**
 * WHO HOLDS WHAT, AND WHERE. Super Admin and Admin hold every permission by
 * tier; Staff — which is now everybody else — hold exactly their position's
 * values, module-declared ones among them, and only on the ground their
 * PLACEMENT names. Anything outside the catalogue is none of this voter's
 * business.
 *
 * `team.manage` is decided here like any other row, which is the whole of what
 * retiring the Manager tier bought: administering the team is a permission a
 * position grants and this voter answers for, not a column beside the matrix.
 *
 * REACH USED TO BE READ THROUGH THE POSITION — `position.department.area`, so
 * that where somebody worked was a property of their job title. The ruling
 * replaced that with a {@see Placement} written against the PERSON, and the
 * two halves are now asked separately: the position says what is granted, the
 * placement says where. The consequence this suite cares about most is that
 * the model FAILS CLOSED — somebody with no placement reaches nothing, however
 * generous their position.
 */
final class PermissionVoterTest extends IntegrationTestCase
{
    private function voter(): PermissionVoter
    {
        return $this->service(PermissionVoter::class);
    }

    /** @param list<string> $attributes */
    private function vote(User $user, array $attributes): int
    {
        return $this->voter()->vote(
            new UsernamePasswordToken($user, 'main', $user->getRoles()),
            null,
            $attributes,
        );
    }

    /** @param list<string> $values */
    private function positionGranting(string $name, array $values): Position
    {
        return (new Position())->setName($name)->setPermissionValues(
            $values,
            $this->service(PermissionCatalogue::class)->values(),
        );
    }

    /**
     * A Staff member holding a position. The placement is passed explicitly —
     * including as null, which is the unplaced case and a refusal rather than
     * an oversight.
     */
    private function staffWith(Position $position, ?Placement $placement = null): User
    {
        return (new User())->setEmail('s@example.test')->setFirstName('S')->setLastName('T')
            ->setPassword('x')->setTeamRole(TeamRoleEnum::Staff)
            ->setPosition($position)->setPlacement($placement);
    }

    public function testAdminAndAboveHoldEveryPermissionByTier(): void
    {
        $admin = (new User())->setEmail('a@example.test')->setFirstName('A')->setLastName('D')
            ->setPassword('x')->setTeamRole(TeamRoleEnum::Admin);

        self::assertSame(VoterInterface::ACCESS_GRANTED, $this->vote($admin, ['area.delete']));
        // Including one no module of this deployment owns but a module declared.
        self::assertSame(VoterInterface::ACCESS_GRANTED, $this->vote($admin, ['surveys.record']));
    }

    /**
     * WHO ADMINISTERS THE TEAM IS A POSITION'S ANSWER. A Staff member whose
     * position carries `team.manage` administers the team, and holds nothing
     * else they were not given.
     */
    public function testAStaffMemberAdministersTheTeamWhenTheirPositionSaysSo(): void
    {
        $position = $this->positionGranting('Warden', [
            PermissionEnum::AreaView->value,
            PermissionEnum::TeamManage->value,
        ]);
        $grace = $this->staffWith($position, $this->placedAcrossTheOrganization());

        self::assertSame(VoterInterface::ACCESS_GRANTED, $this->vote($grace, ['team.manage']));
        self::assertSame(VoterInterface::ACCESS_GRANTED, $this->vote($grace, ['area.view']));
        // And nothing beyond it: the tier grants her nothing at all.
        self::assertSame(VoterInterface::ACCESS_DENIED, $this->vote($grace, ['area.delete']));
    }

    /** Take the row off the position and the authority goes with it, in one click. */
    public function testRevokingTeamManageEndsTheAuthority(): void
    {
        $position = $this->positionGranting('Warden', [PermissionEnum::TeamManage->value]);
        $grace = $this->staffWith($position, $this->placedAcrossTheOrganization());
        self::assertSame(VoterInterface::ACCESS_GRANTED, $this->vote($grace, ['team.manage']));

        $position->setPermissionValues([], $this->service(PermissionCatalogue::class)->values());

        self::assertSame(VoterInterface::ACCESS_DENIED, $this->vote($grace, ['team.manage']));
    }

    public function testStaffHoldExactlyTheirPositionIncludingAModulesDeclaration(): void
    {
        $position = $this->positionGranting('Recorder', ['area.view', 'surveys.record']);
        $placement = $this->placedAcrossTheOrganization();

        self::assertSame(VoterInterface::ACCESS_GRANTED, $this->vote($this->staffWith($position, $placement), ['area.view']));
        self::assertSame(VoterInterface::ACCESS_GRANTED, $this->vote($this->staffWith($position, $placement), ['surveys.record']));
        self::assertSame(VoterInterface::ACCESS_DENIED, $this->vote($this->staffWith($position, $placement), ['module.create']));
    }

    public function testStaffWithNoPositionHoldNothing(): void
    {
        $orphan = (new User())->setEmail('o@example.test')->setFirstName('O')->setLastName('R')
            ->setPassword('x')->setTeamRole(TeamRoleEnum::Staff);

        self::assertSame(VoterInterface::ACCESS_DENIED, $this->vote($orphan, ['area.view']));
    }

    public function testItAbstainsOnAnythingOutsideTheCatalogue(): void
    {
        $admin = (new User())->setEmail('a2@example.test')->setFirstName('A')->setLastName('D')
            ->setPassword('x')->setTeamRole(TeamRoleEnum::Admin);

        // Roles are the role voters' business, and so is anything invented.
        self::assertSame(VoterInterface::ACCESS_ABSTAIN, $this->vote($admin, ['ROLE_ADMIN']));
        self::assertSame(VoterInterface::ACCESS_ABSTAIN, $this->vote($admin, ['invented.power']));
    }

    // ---- AREA-SCOPED: "may this person do X *here*?" -----------------------

    /**
     * THE MODEL FAILS CLOSED. A position may grant `area.view` as loudly as it
     * likes: until somebody has been PLACED, there is no ground for the grant
     * to apply to, and the answer is no — in a named area and for the
     * no-area-in-context question alike.
     *
     * This is the specification the whole shape exists to make true. Reach
     * used to be inherited from the position's department, so a position with
     * a permission was very nearly a permission granted everywhere; now the
     * second half has to be written down on purpose, and the absence of it is
     * a refusal rather than a gap somebody discovers in production.
     */
    public function testAStaffMemberWithNoPlacementIsRefusedWhatTheirPositionGrants(): void
    {
        $south = $this->area('Southern Reserve');
        $position = $this->positionGranting('Field', [PermissionEnum::AreaView->value]);
        $unplaced = $this->staffWith($position, null);

        self::assertSame(VoterInterface::ACCESS_DENIED, $this->voteOn($unplaced, 'area.view', $south));
        self::assertSame(VoterInterface::ACCESS_DENIED, $this->voteOn($unplaced, 'area.view', null));
    }

    /**
     * AN AREA-LEVEL STAFF MEMBER holds their permission IN THEIR OWN AREA and
     * NOT in another. This is the thesis of area-scoped authority: the same
     * grant answers differently per area.
     */
    public function testAnAreaLevelStaffMemberIsGrantedInTheirAreaAndDeniedInAnother(): void
    {
        $south = $this->area('Southern Reserve');
        $north = $this->area('Northern Reserve');
        $ranger = $this->areaLevelStaffWith([PermissionEnum::AreaView->value], [$south]);

        self::assertSame(VoterInterface::ACCESS_GRANTED, $this->voteOn($ranger, 'area.view', $south));
        self::assertSame(VoterInterface::ACCESS_DENIED, $this->voteOn($ranger, 'area.view', $north));
    }

    /**
     * A PLACEMENT NAMES A SET, NOT A PLACE. Somebody covering two reserves is
     * one person holding one position on two pieces of ground, and a third
     * reserve is still none of theirs — the case the old one-area-per-position
     * shape could only express by inventing a second position.
     */
    public function testAPlacementNamingTwoAreasGrantsInBothAndRefusesInAThird(): void
    {
        $south = $this->area('Southern Reserve');
        $north = $this->area('Northern Reserve');
        $east = $this->area('Eastern Reserve');
        $ranger = $this->areaLevelStaffWith([PermissionEnum::AreaView->value], [$south, $north]);

        self::assertSame(VoterInterface::ACCESS_GRANTED, $this->voteOn($ranger, 'area.view', $south));
        self::assertSame(VoterInterface::ACCESS_GRANTED, $this->voteOn($ranger, 'area.view', $north));
        self::assertSame(VoterInterface::ACCESS_DENIED, $this->voteOn($ranger, 'area.view', $east));
    }

    /**
     * A NULL SUBJECT — the nav/flag question "may I ever…?" — grants for an
     * area-level holder (grant-if-any-authority), leaving the real per-area gate
     * for once an area is named.
     */
    public function testANullSubjectGrantsForAnAreaLevelHolder(): void
    {
        $south = $this->area('Southern Reserve');
        $ranger = $this->areaLevelStaffWith([PermissionEnum::AreaView->value], [$south]);

        self::assertSame(VoterInterface::ACCESS_GRANTED, $this->voteOn($ranger, 'area.view', null));
    }

    /** AN ORGANIZATION-WIDE PLACEMENT holds it in every area. */
    public function testAnOrganizationWidePlacementIsGrantedInEveryArea(): void
    {
        $south = $this->area('Southern Reserve');
        $north = $this->area('Northern Reserve');

        $position = $this->positionGranting('Analyst', [PermissionEnum::AreaView->value]);
        $this->em->persist($position);
        $analyst = $this->staffWith($position, $this->placedAcrossTheOrganization());
        $this->em->flush();

        self::assertSame(VoterInterface::ACCESS_GRANTED, $this->voteOn($analyst, 'area.view', $south));
        self::assertSame(VoterInterface::ACCESS_GRANTED, $this->voteOn($analyst, 'area.view', $north));
    }

    /**
     * "THE WHOLE ORGANIZATION" MEANS THE ORGANIZATION AS IT WILL BE, not the
     * list of areas that happened to exist on the day the placement was
     * written. A reserve gazetted afterwards is covered without anybody
     * revisiting the record — which is exactly why the breadth is stored as
     * its own answer rather than as a snapshot of every area.
     */
    public function testAnOrganizationWidePlacementCoversAnAreaCreatedAfterwards(): void
    {
        $position = $this->positionGranting('Analyst', [PermissionEnum::AreaView->value]);
        $this->em->persist($position);
        $analyst = $this->staffWith($position, $this->placedAcrossTheOrganization());
        $this->em->flush();

        $gazettedLater = $this->area('Western Reserve');

        self::assertSame(VoterInterface::ACCESS_GRANTED, $this->voteOn($analyst, 'area.view', $gazettedLater));
    }

    /**
     * A GLOBAL permission (only `area.create` among the core seven) skips the
     * area comparison — an area-level holder is granted regardless of target.
     */
    public function testAGlobalPermissionSkipsTheAreaComparison(): void
    {
        $south = $this->area('Southern Reserve');
        $north = $this->area('Northern Reserve');
        $planner = $this->areaLevelStaffWith([PermissionEnum::AreaCreate->value], [$south]);

        self::assertSame(VoterInterface::ACCESS_GRANTED, $this->voteOn($planner, 'area.create', $north));
        self::assertSame(VoterInterface::ACCESS_GRANTED, $this->voteOn($planner, 'area.create', null));
    }

    /** A MODULE-DECLARED permission is area-scoped by default — it compares too. */
    public function testAModuleDeclaredPermissionIsAreaScopedByDefault(): void
    {
        $south = $this->area('Southern Reserve');
        $north = $this->area('Northern Reserve');
        $recorder = $this->areaLevelStaffWith(['surveys.record'], [$south]);

        self::assertSame(VoterInterface::ACCESS_GRANTED, $this->voteOn($recorder, 'surveys.record', $south));
        self::assertSame(VoterInterface::ACCESS_DENIED, $this->voteOn($recorder, 'surveys.record', $north));
    }

    /** THE TIER BYPASS is total — an Admin holds it in every area, subject or not. */
    public function testTheTierBypassIgnoresTheTargetArea(): void
    {
        $south = $this->area('Southern Reserve');
        $admin = (new User())->setEmail('a3@example.test')->setFirstName('A')->setLastName('D')
            ->setPassword('x')->setTeamRole(TeamRoleEnum::Admin);

        self::assertSame(VoterInterface::ACCESS_GRANTED, $this->voteOn($admin, 'area.view', $south));
    }

    /**
     * THE TIER BYPASS IGNORES THE PLACEMENT ENTIRELY. An Admin placed at one
     * reserve — or at none at all, which refuses a Staff member everything —
     * still holds every permission everywhere: area-scoping only ever narrows
     * a Staff member, and step one of the check never reaches the placement.
     */
    public function testTheTierBypassIgnoresThePlacement(): void
    {
        $south = $this->area('Southern Reserve');
        $north = $this->area('Northern Reserve');

        $narrowlyPlaced = (new User())->setEmail('a4@example.test')->setFirstName('A')->setLastName('D')
            ->setPassword('x')->setTeamRole(TeamRoleEnum::Admin)
            ->setPlacement($this->placedAtAreas([$south]));
        $unplaced = (new User())->setEmail('a5@example.test')->setFirstName('A')->setLastName('E')
            ->setPassword('x')->setTeamRole(TeamRoleEnum::Admin);

        self::assertSame(VoterInterface::ACCESS_GRANTED, $this->voteOn($narrowlyPlaced, 'area.view', $north));
        self::assertSame(VoterInterface::ACCESS_GRANTED, $this->voteOn($unplaced, 'area.view', $north));
    }

    private function area(string $name): HostArea
    {
        $area = (new HostArea())->setName($name);
        $this->em->persist($area);
        $this->em->flush();

        return $area;
    }

    /** The whole organization, every department — the widest ground there is. */
    private function placedAcrossTheOrganization(): Placement
    {
        $placement = (new Placement())->acrossTheOrganization()->acrossAllDepartments();
        $this->em->persist($placement);

        return $placement;
    }

    /**
     * Named ground, every department — the departments dimension is not what
     * the voter's area question is about, so it is held open here.
     *
     * @param list<HostArea> $areas
     */
    private function placedAtAreas(array $areas): Placement
    {
        $placement = (new Placement())->inAreas($areas)->acrossAllDepartments();
        $this->em->persist($placement);

        return $placement;
    }

    /**
     * @param list<string>   $values
     * @param list<HostArea> $areas
     */
    private function areaLevelStaffWith(array $values, array $areas): User
    {
        $position = $this->positionGranting('Field', $values);
        $this->em->persist($position);
        $staff = $this->staffWith($position, $this->placedAtAreas($areas));
        $this->em->flush();

        return $staff;
    }

    private function voteOn(User $user, string $attribute, mixed $subject): int
    {
        return $this->voter()->vote(
            new UsernamePasswordToken($user, 'main', $user->getRoles()),
            $subject,
            [$attribute],
        );
    }
}
