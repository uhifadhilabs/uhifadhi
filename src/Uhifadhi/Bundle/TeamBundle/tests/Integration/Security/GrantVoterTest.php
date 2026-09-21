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
use Uhifadhi\Bundle\RegistryBundle\Entity\Module;
use Uhifadhi\Bundle\RegistryBundle\Enum\ModuleCategory;
use Uhifadhi\Bundle\RegistryBundle\Enum\ModuleStatus;
use Uhifadhi\Bundle\TeamBundle\Access\ConcernCatalogue;
use Uhifadhi\Bundle\TeamBundle\Entity\Department;
use Uhifadhi\Bundle\TeamBundle\Entity\Placement;
use Uhifadhi\Bundle\TeamBundle\Entity\Position;
use Uhifadhi\Bundle\TeamBundle\Entity\User;
use Uhifadhi\Bundle\TeamBundle\Enum\TeamRoleEnum;
use Uhifadhi\Bundle\TeamBundle\Security\GrantVoter;
use Uhifadhi\Bundle\TeamBundle\Tests\Integration\Fixtures\Area\HostArea;
use Uhifadhi\Bundle\TeamBundle\Tests\Integration\IntegrationTestCase;

/**
 * A CHECK ASKS THREE QUESTIONS, AND IT FAILS CLOSED.
 *
 *   1. Does the POSITION grant the (concern, verb) pair?
 *   2. Does the PLACEMENT cover the area the record lies in?
 *   3. Does the PLACEMENT cover the department, where the concern belongs to
 *      one at all?
 *
 * Every question that applies must answer yes, and one that cannot be
 * answered refuses. This suite is written so that each question can be seen
 * failing ALONE — the right position in the wrong area, and the right
 * position in the right area but the wrong department — because a check that
 * only ever refuses for one reason is a check whose other questions might not
 * be asked.
 */
final class GrantVoterTest extends IntegrationTestCase
{
    private function voter(): GrantVoter
    {
        return $this->service(GrantVoter::class);
    }

    /** @param list<string> $pairs */
    private function vote(User $user, array $pairs, mixed $subject = null): int
    {
        return $this->voter()->vote(
            new UsernamePasswordToken($user, 'main', $user->getRoles()),
            $subject,
            $pairs,
        );
    }

    /** @param list<string> $pairs */
    private function positionGranting(string $name, array $pairs): Position
    {
        return (new Position())->setName($name)->setGrantValues(
            $pairs,
            $this->service(ConcernCatalogue::class)->pairs(),
        );
    }

    private function staff(Position $position, ?Placement $placement): User
    {
        return (new User())->setEmail('s@example.test')->setFirstName('S')->setLastName('T')
            ->setPassword('x')->setTeamRole(TeamRoleEnum::Staff)
            ->setPosition($position)->setPlacement($placement);
    }

    private function area(string $name): HostArea
    {
        $area = new HostArea()->setName($name);
        $this->em->persist($area);
        $this->em->flush();

        return $area;
    }

    // --- question one: does the position grant it? ------------------------

    public function testAPairThePositionCarriesIsGrantedOnTheGroundItIsPlacedAt(): void
    {
        $ngorongoro = $this->area('Ngorongoro');
        $person = $this->staff(
            $this->positionGranting('Sergeant', ['directory.read']),
            new Placement()->inAreas([$ngorongoro])->acrossAllDepartments(),
        );

        self::assertSame(VoterInterface::ACCESS_GRANTED, $this->vote($person, ['directory.read'], $ngorongoro));
    }

    public function testAPairThePositionDoesNotCarryIsRefused(): void
    {
        $ngorongoro = $this->area('Ngorongoro');
        $person = $this->staff(
            $this->positionGranting('Sergeant', ['directory.read']),
            new Placement()->inAreas([$ngorongoro])->acrossAllDepartments(),
        );

        self::assertSame(VoterInterface::ACCESS_DENIED, $this->vote($person, ['directory.manage'], $ngorongoro));
    }

    /** Nothing is held unless a position says so, reading included. */
    public function testSomebodyWithNoPositionHoldsNothingAtAll(): void
    {
        $ngorongoro = $this->area('Ngorongoro');
        $person = (new User())->setEmail('n@example.test')->setFirstName('N')->setLastName('P')
            ->setPassword('x')->setTeamRole(TeamRoleEnum::Staff)
            ->setPlacement(new Placement()->acrossTheOrganization()->acrossAllDepartments());

        self::assertSame(VoterInterface::ACCESS_DENIED, $this->vote($person, ['directory.read'], $ngorongoro));
    }

    // --- question two: does the placement cover the area? -----------------

    /**
     * THE WORKED EXAMPLE IN THE RULING: the right position, the wrong ground.
     * It is the common case and the shortest way to see the model work.
     */
    public function testTheRightPositionInTheWrongAreaIsRefused(): void
    {
        $ngorongoro = $this->area('Ngorongoro');
        $mbozi = $this->area('Mbozi');
        $person = $this->staff(
            $this->positionGranting('Sergeant', ['directory.read']),
            new Placement()->inAreas([$ngorongoro])->acrossAllDepartments(),
        );

        self::assertSame(VoterInterface::ACCESS_GRANTED, $this->vote($person, ['directory.read'], $ngorongoro));
        self::assertSame(VoterInterface::ACCESS_DENIED, $this->vote($person, ['directory.read'], $mbozi));
    }

    public function testAnOrganizationWidePlacementIsGrantedInEveryArea(): void
    {
        $person = $this->staff(
            $this->positionGranting('Sergeant', ['directory.read']),
            new Placement()->acrossTheOrganization()->acrossAllDepartments(),
        );

        // Gazetted after the placement was written, which is why the breadth
        // is a stored answer rather than a snapshot of the area list.
        $later = $this->area('Pololeti');

        self::assertSame(VoterInterface::ACCESS_GRANTED, $this->vote($person, ['directory.read'], $later));
    }

    /**
     * THE SPECIFICATION THE WHOLE SHAPE EXISTS TO MAKE TRUE. Unplaced is not
     * "everywhere": somebody the organization has not placed reaches no
     * ground, however generous their position.
     */
    public function testSomebodyWithNoPlacementIsRefusedWhatTheirPositionGrants(): void
    {
        $ngorongoro = $this->area('Ngorongoro');
        $person = $this->staff($this->positionGranting('Sergeant', ['directory.read']), null);

        self::assertSame(VoterInterface::ACCESS_DENIED, $this->vote($person, ['directory.read'], $ngorongoro));
        self::assertSame(VoterInterface::ACCESS_DENIED, $this->vote($person, ['directory.read']), 'and not even the "may I ever?" question.');
    }

    // --- question three: does the placement cover the department? ---------

    /**
     * THE OTHER DIMENSION FAILING ALONE: the right position, the right area,
     * the wrong department. A concern belongs to the departments that run the
     * module which declared it, so a module concern is refused to somebody
     * placed only against departments that do not run it.
     */
    public function testTheRightPositionInTheRightAreaButTheWrongDepartmentIsRefused(): void
    {
        $ngorongoro = $this->area('Ngorongoro');
        $ecology = $this->department('Ecology', 'surveys');
        $protection = $this->department('Protection Service', 'surveys');

        $position = $this->positionGranting('Data Analyst', ['surveys.read']);

        $inEcology = $this->staff($position, new Placement()->inAreas([$ngorongoro])->inDepartments([$ecology]));
        self::assertSame(VoterInterface::ACCESS_GRANTED, $this->vote($inEcology, ['surveys.read'], $ngorongoro));

        // Protection runs the module too, so somebody placed there is granted
        // by the same rule from the other side.
        $inProtection = $this->staff($position, new Placement()->inAreas([$ngorongoro])->inDepartments([$protection]));
        self::assertSame(VoterInterface::ACCESS_GRANTED, $this->vote($inProtection, ['surveys.read'], $ngorongoro));

        $elsewhere = $this->staff($position, new Placement()->inAreas([$ngorongoro])->inDepartments([$this->department('ICT', null)]));
        self::assertSame(
            VoterInterface::ACCESS_DENIED,
            $this->vote($elsewhere, ['surveys.read'], $ngorongoro),
            'ICT does not run the module that declared this concern, so its figures are not theirs to read anywhere.',
        );
    }

    /**
     * A CONCERN BELONGING TO NO MODULE HAS NO DEPARTMENT DIMENSION. The
     * ground, the directory and the catalogue are the installation's, not any
     * one department's, so the third question does not arise and a question
     * that does not arise is not a refusal.
     */
    public function testACoreConcernIsNotRefusedByTheDepartmentQuestion(): void
    {
        $ngorongoro = $this->area('Ngorongoro');
        $this->department('Ecology', 'surveys');

        $person = $this->staff(
            $this->positionGranting('Sergeant', ['directory.read']),
            new Placement()->inAreas([$ngorongoro])->inDepartments([$this->department('ICT', null)]),
        );

        self::assertSame(VoterInterface::ACCESS_GRANTED, $this->vote($person, ['directory.read'], $ngorongoro));
    }

    /**
     * A MODULE NO DEPARTMENT RUNS IS THE SAME CASE: there is no department
     * for the placement to have to cover, so the question does not arise.
     */
    public function testAModuleConcernNoDepartmentRunsIsNotRefusedEither(): void
    {
        $ngorongoro = $this->area('Ngorongoro');
        $person = $this->staff(
            $this->positionGranting('Data Analyst', ['surveys.read']),
            new Placement()->inAreas([$ngorongoro])->inDepartments([$this->department('ICT', null)]),
        );

        self::assertSame(VoterInterface::ACCESS_GRANTED, $this->vote($person, ['surveys.read'], $ngorongoro));
    }

    /** Somebody placed across all departments is in every one of them. */
    public function testAPlacementAcrossAllDepartmentsCoversTheOneRunningTheModule(): void
    {
        $ngorongoro = $this->area('Ngorongoro');
        $this->department('Ecology', 'surveys');

        $person = $this->staff(
            $this->positionGranting('Data Analyst', ['surveys.read']),
            new Placement()->inAreas([$ngorongoro])->acrossAllDepartments(),
        );

        self::assertSame(VoterInterface::ACCESS_GRANTED, $this->vote($person, ['surveys.read'], $ngorongoro));
    }

    // --- above the matrix, and outside it ---------------------------------

    /**
     * THE TIER IGNORES BOTH PLACEMENT QUESTIONS. The two administrative tiers
     * exist so that the person who WRITES the positions is not themselves
     * described by one.
     */
    public function testTheTierBypassIgnoresThePlacementEntirely(): void
    {
        $mbozi = $this->area('Mbozi');
        $admin = (new User())->setEmail('a@example.test')->setFirstName('A')->setLastName('D')
            ->setPassword('x')->setTeamRole(TeamRoleEnum::Admin);

        self::assertSame(VoterInterface::ACCESS_GRANTED, $this->vote($admin, ['directory.manage'], $mbozi), 'placed nowhere, and still above the matrix.');
    }

    /**
     * A PAIR NOTHING DECLARES IS NOT THIS VOTER'S BUSINESS — it abstains so
     * the role voters can decide it, which is also how a pair belonging to an
     * uninstalled module stops being decidable here.
     */
    public function testAPairNothingDeclaresIsAbstainedOn(): void
    {
        $person = $this->staff(
            $this->positionGranting('Sergeant', ['directory.read']),
            new Placement()->acrossTheOrganization()->acrossAllDepartments(),
        );

        self::assertSame(VoterInterface::ACCESS_ABSTAIN, $this->vote($person, ['nothing-declares.read']));
        self::assertSame(VoterInterface::ACCESS_ABSTAIN, $this->vote($person, ['not-even-a-pair']));
        self::assertSame(VoterInterface::ACCESS_ABSTAIN, $this->vote($person, ['directory.export']), 'the directory declares no export verb, so that cell is not a pair.');
    }

    /**
     * A department, optionally running a module — which is what gives the
     * third question something to ask about. `surveys` is the slug the test
     * kernel's fake module declares its concern under.
     */
    private function department(string $name, ?string $module): Department
    {
        $department = new Department()->setName($name);
        $this->em->persist($department);

        if (null !== $module) {
            $department->attachModule($this->module($module));
        }

        $this->em->flush();

        return $department;
    }

    /**
     * The catalogue row for a module, made here rather than seeded: this
     * suite is about the check, and the registry's own sync has its own
     * tests.
     */
    private function module(string $slug): Module
    {
        $existing = $this->em->getRepository(Module::class)->findOneBy(['slug' => $slug]);
        if ($existing instanceof Module) {
            return $existing;
        }

        $module = new Module()
            ->setSlug($slug)
            ->setName(ucfirst($slug))
            ->setCategory(ModuleCategory::Operations)
            ->setStatus(ModuleStatus::Live)
            ->setDataSource('a fixture')
            ->setPinned(false)
            ->setPosition(1);
        $this->em->persist($module);
        $this->em->flush();

        return $module;
    }
}
