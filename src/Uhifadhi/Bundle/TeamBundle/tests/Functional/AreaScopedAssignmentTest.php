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

namespace Uhifadhi\Bundle\TeamBundle\Tests\Functional;

use Symfony\Component\DomCrawler\Crawler;
use Uhifadhi\Bundle\TeamBundle\Entity\Position;
use Uhifadhi\Bundle\TeamBundle\Entity\User;
use Uhifadhi\Bundle\TeamBundle\Enum\TeamRoleEnum;
use Uhifadhi\Bundle\TeamBundle\Tests\Integration\Fixtures\Area\HostArea;

/**
 * §5.6(a) — WHAT AN AREA ADMINISTRATOR MAY DO TO A PERSON.
 *
 * An area-X `team.manage` holder may assign and unassign only people their
 * authority reaches: somebody whose every placed area lies inside their own.
 * Somebody placed across the whole organization is above them, and somebody
 * placed nowhere at all is in no area they could reach them in — the model
 * fails closed, so both are refused. A tier (Super Admin / Admin) or an
 * organization-wide placement is UNBOUNDED and touches anyone.
 *
 * THE FENCE IS ROUND THE PERSON, NOT ROUND THE POSITION. It used to be round
 * the position — a position was filed under a department, the department sat
 * in an area, and "may I seat somebody here?" was answerable from the target
 * alone. A position is a job title with no ground now, so it says nothing
 * about whose boundary a move crosses; where the person is placed does, and it
 * is the same question whichever position they are moved between. That is also
 * why both pickers offer every position as one flat list: there is no such
 * thing as another area's position to keep out of it.
 *
 * Enforcement is server-side (a 403), exactly as the department controller
 * does it.
 */
final class AreaScopedAssignmentTest extends WebTestCaseWithSchema
{
    // ---- the member record: reassigning a person --------------------------

    /** An area-X admin assigns somebody placed in their OWN area. */
    public function testAnAreaAdminAssignsAPersonPlacedInTheirOwnArea(): void
    {
        $north = $this->area('Northern Reserve');
        $this->areaAdminIn($north);
        $ranger = $this->position('Ranger', ['surveys.read']);
        $grace = $this->person('Grace', 'Ndosi');
        $this->place($grace, [$north]);
        $this->em->flush();

        $token = $this->tokenFrom('/team/'.$grace->getUuidString().'/configure');
        $this->client->request('POST', '/team/'.$grace->getUuidString().'/position', [
            '_token' => $token, 'position' => $ranger->getUuidString(),
        ]);

        self::assertResponseRedirects();
        $this->em->clear();
        $stored = $this->em->getRepository(User::class)->findOneBy(['email' => 'g.ndosi@example.test']);
        self::assertInstanceOf(User::class, $stored);
        self::assertSame('Ranger', $stored->getPosition()?->getName());
    }

    /** But NOT somebody placed in another area — that reaches past their boundary. */
    public function testAnAreaAdminCannotAssignAPersonPlacedInAnotherArea(): void
    {
        $north = $this->area('Northern Reserve');
        $west = $this->area('Western Reserve');
        $this->areaAdminIn($north);
        $ranger = $this->position('Ranger', ['surveys.read']);
        $grace = $this->person('Grace', 'Ndosi');
        $this->place($grace, [$west]);
        $this->em->flush();

        $token = $this->tokenFrom('/team/'.$grace->getUuidString().'/configure');
        $this->client->request('POST', '/team/'.$grace->getUuidString().'/position', [
            '_token' => $token, 'position' => $ranger->getUuidString(),
        ]);

        self::assertResponseStatusCodeSame(403);
        $this->em->clear();
        self::assertNull($this->em->getRepository(User::class)->findOneBy(['email' => 'g.ndosi@example.test'])?->getPosition());
    }

    /**
     * Nor somebody placed in their own area AND another one. A placement is
     * reached only when ALL of its ground lies inside the administrator's; half
     * of it would let a bounded administrator move somebody who also works
     * where they have no authority at all.
     */
    public function testAnAreaAdminCannotAssignAPersonPlacedPartlyOutsideTheirArea(): void
    {
        $north = $this->area('Northern Reserve');
        $west = $this->area('Western Reserve');
        $this->areaAdminIn($north);
        $ranger = $this->position('Ranger', ['surveys.read']);
        $grace = $this->person('Grace', 'Ndosi');
        $this->place($grace, [$north, $west]);
        $this->em->flush();

        $token = $this->tokenFrom('/team/'.$grace->getUuidString().'/configure');
        $this->client->request('POST', '/team/'.$grace->getUuidString().'/position', [
            '_token' => $token, 'position' => $ranger->getUuidString(),
        ]);

        self::assertResponseStatusCodeSame(403);
    }

    /** And NOT somebody placed across the whole organization — they stand above an area. */
    public function testAnAreaAdminCannotAssignAPersonPlacedAcrossTheOrganization(): void
    {
        $north = $this->area('Northern Reserve');
        $this->areaAdminIn($north);
        $ranger = $this->position('Ranger', ['surveys.read']);
        $grace = $this->person('Grace', 'Ndosi');
        $this->place($grace);
        $this->em->flush();

        $token = $this->tokenFrom('/team/'.$grace->getUuidString().'/configure');
        $this->client->request('POST', '/team/'.$grace->getUuidString().'/position', [
            '_token' => $token, 'position' => $ranger->getUuidString(),
        ]);

        self::assertResponseStatusCodeSame(403);
    }

    /**
     * AND NOT SOMEBODY PLACED NOWHERE AT ALL — the model fails closed. An
     * unplaced person stands in no area, so no bounded administrator stands in
     * one with them; placing them is the unbounded act that makes them
     * somebody's to manage.
     */
    public function testAnAreaAdminCannotAssignAnUnplacedPerson(): void
    {
        $north = $this->area('Northern Reserve');
        $this->areaAdminIn($north);
        $ranger = $this->position('Ranger', ['surveys.read']);
        $grace = $this->person('Grace', 'Ndosi');
        $this->em->flush();

        $token = $this->tokenFrom('/team/'.$grace->getUuidString().'/configure');
        $this->client->request('POST', '/team/'.$grace->getUuidString().'/position', [
            '_token' => $token, 'position' => $ranger->getUuidString(),
        ]);

        self::assertResponseStatusCodeSame(403);
        $this->em->clear();
        self::assertNull($this->em->getRepository(User::class)->findOneBy(['email' => 'g.ndosi@example.test'])?->getPosition());
    }

    /** Unassigning somebody placed in the admin's own area is allowed. */
    public function testAnAreaAdminUnassignsSomebodyPlacedInTheirOwnArea(): void
    {
        $north = $this->area('Northern Reserve');
        $this->areaAdminIn($north);
        $ranger = $this->position('Ranger', ['surveys.read']);
        $grace = $this->person('Grace', 'Ndosi')->setPosition($ranger);
        $this->place($grace, [$north]);
        $this->em->flush();

        $token = $this->tokenFrom('/team/'.$grace->getUuidString().'/configure');
        $this->client->request('POST', '/team/'.$grace->getUuidString().'/position', [
            '_token' => $token, 'position' => '',
        ]);

        self::assertResponseRedirects();
        $this->em->clear();
        self::assertNull($this->em->getRepository(User::class)->findOneBy(['email' => 'g.ndosi@example.test'])?->getPosition());
    }

    /** But unassigning somebody placed outside it is refused — taking is touching. */
    public function testAnAreaAdminCannotUnassignSomebodyPlacedOutsideTheirArea(): void
    {
        $north = $this->area('Northern Reserve');
        $west = $this->area('Western Reserve');
        $this->areaAdminIn($north);
        $ranger = $this->position('Ranger', ['surveys.read']);
        $grace = $this->person('Grace', 'Ndosi')->setPosition($ranger);
        $this->place($grace, [$west]);
        $this->em->flush();

        $token = $this->tokenFrom('/team/'.$grace->getUuidString().'/configure');
        $this->client->request('POST', '/team/'.$grace->getUuidString().'/position', [
            '_token' => $token, 'position' => '',
        ]);

        self::assertResponseStatusCodeSame(403);
        $this->em->clear();
        self::assertNotNull($this->em->getRepository(User::class)->findOneBy(['email' => 'g.ndosi@example.test'])?->getPosition(), 'Nothing was written.');
    }

    /**
     * THE PICKER IS ONE FLAT LIST OF EVERY POSITION, for a bounded
     * administrator as much as anybody else. A position carries no ground, so
     * there is nothing about one of them to keep out of the list — and nothing
     * to group them under either.
     */
    public function testTheMemberPickerOffersEveryPositionAsOneFlatList(): void
    {
        $north = $this->area('Northern Reserve');
        $this->areaAdminIn($north);
        $this->position('Ranger', ['surveys.read']);
        $this->position('Scout', ['surveys.read']);
        $this->position('Analyst', ['surveys.read']);
        $grace = $this->person('Grace', 'Ndosi');
        $this->place($grace, [$north]);
        $this->em->flush();

        $crawler = $this->client->request('GET', '/team/'.$grace->getUuidString().'/configure');
        $options = $this->pickerOptions($crawler);

        self::assertContains('Ranger', $options);
        self::assertContains('Scout', $options);
        self::assertContains('Analyst', $options);
        self::assertCount(0, $crawler->filter('select[name="position"] optgroup'), 'One list, because a position belongs to no department.');
    }

    // ---- the invite page: adding somebody ---------------------------------

    /**
     * An area-X admin creates somebody straight into any position. The pick
     * confers no ground, so there is nothing to fence here: what decides whose
     * person this is, is the placement written on their record afterwards.
     */
    public function testAnAreaAdminCreatesSomebodyIntoAnyPosition(): void
    {
        $north = $this->area('Northern Reserve');
        $this->areaAdminIn($north);
        $analyst = $this->position('Analyst', ['surveys.read']);
        $this->em->flush();

        $token = $this->tokenFrom('/team/invite');
        $this->client->request('POST', '/team', [
            '_token' => $token,
            'firstName' => 'Joseph', 'lastName' => 'Mrema',
            'email' => 'j.mrema@example.test', 'password' => 'a-long-enough-password',
            'position' => $analyst->getUuidString(),
        ]);

        self::assertResponseRedirects();
        $this->em->clear();
        $stored = $this->em->getRepository(User::class)->findOneBy(['email' => 'j.mrema@example.test']);
        self::assertInstanceOf(User::class, $stored);
        self::assertSame('Analyst', $stored->getPosition()?->getName());
    }

    /**
     * AND THE PERSON THEY ADDED IS NOT YET THEIRS TO MANAGE. Creating an
     * account places nobody, so the new colleague reaches no ground — and the
     * administrator who added them cannot then edit them until somebody
     * unbounded places them.
     */
    public function testSomebodyJustAddedIsPlacedNowhereAndSoBeyondTheAdminWhoAddedThem(): void
    {
        $north = $this->area('Northern Reserve');
        $this->areaAdminIn($north);
        $this->em->flush();

        $token = $this->tokenFrom('/team/invite');
        $this->client->request('POST', '/team', [
            '_token' => $token,
            'firstName' => 'Joseph', 'lastName' => 'Mrema',
            'email' => 'j.mrema@example.test', 'password' => 'a-long-enough-password',
            'position' => '',
        ]);

        self::assertResponseRedirects();
        $this->em->clear();
        $stored = $this->em->getRepository(User::class)->findOneBy(['email' => 'j.mrema@example.test']);
        self::assertInstanceOf(User::class, $stored);
        self::assertNull($stored->getPlacement(), 'Adding somebody places them nowhere.');

        $token = $this->tokenFrom('/team/'.$stored->getUuidString().'/configure');
        $this->client->request('POST', '/team/'.$stored->getUuidString().'/deactivate', ['_token' => $token]);

        self::assertResponseStatusCodeSame(403);
    }

    /** The invite picker is one flat list of every position too. */
    public function testTheInvitePickerOffersEveryPositionAsOneFlatList(): void
    {
        $north = $this->area('Northern Reserve');
        $this->areaAdminIn($north);
        $this->position('Ranger', ['surveys.read']);
        $this->position('Scout', ['surveys.read']);
        $this->position('Analyst', ['surveys.read']);
        $this->em->flush();

        $crawler = $this->client->request('GET', '/team/invite');
        $options = $this->pickerOptions($crawler);

        self::assertContains('Ranger', $options);
        self::assertContains('Scout', $options);
        self::assertContains('Analyst', $options);
        self::assertCount(0, $crawler->filter('select[name="position"] optgroup'));
    }

    // ---- the unbounded remain unbounded -----------------------------------

    /** Somebody placed across the organization is unbounded: they assign anywhere. */
    public function testAnOrganizationWideHolderAssignsInAnyArea(): void
    {
        $west = $this->area('Western Reserve');
        $orgAdmin = $this->person('Amina', 'Salehe', TeamRoleEnum::Staff);
        $orgAdmin->setPosition($this->administratorPosition('Coordinator'));
        $this->place($orgAdmin);
        $ranger = $this->position('Ranger', ['surveys.read']);
        $grace = $this->person('Grace', 'Ndosi');
        $this->place($grace, [$west]);
        $this->em->flush();
        $this->client->loginUser($orgAdmin);

        $token = $this->tokenFrom('/team/'.$grace->getUuidString().'/configure');
        $this->client->request('POST', '/team/'.$grace->getUuidString().'/position', [
            '_token' => $token, 'position' => $ranger->getUuidString(),
        ]);

        self::assertResponseRedirects();
        $this->em->clear();
        self::assertSame('Ranger', $this->em->getRepository(User::class)->findOneBy(['email' => 'g.ndosi@example.test'])?->getPosition()?->getName());
    }

    /** And they may seat somebody nobody has placed yet, which is how placing starts. */
    public function testAnOrganizationWideHolderAssignsAnUnplacedPerson(): void
    {
        $orgAdmin = $this->person('Amina', 'Salehe', TeamRoleEnum::Staff);
        $orgAdmin->setPosition($this->administratorPosition('Coordinator'));
        $this->place($orgAdmin);
        $ranger = $this->position('Ranger', ['surveys.read']);
        $grace = $this->person('Grace', 'Ndosi');
        $this->em->flush();
        $this->client->loginUser($orgAdmin);

        $token = $this->tokenFrom('/team/'.$grace->getUuidString().'/configure');
        $this->client->request('POST', '/team/'.$grace->getUuidString().'/position', [
            '_token' => $token, 'position' => $ranger->getUuidString(),
        ]);

        self::assertResponseRedirects();
    }

    // ---- the cast ---------------------------------------------------------

    /**
     * Sign in as an AREA-X administrator — a Staff member holding team.manage
     * through their position and PLACED at $area, which is where their reach
     * now comes from.
     */
    private function areaAdminIn(HostArea $area): User
    {
        $admin = $this->person('Naomi', 'Kileo', TeamRoleEnum::Staff);
        $admin->setPosition($this->administratorPosition('Warden'));
        $this->place($admin, [$area]);
        $this->em->flush();
        $this->client->loginUser($admin);

        return $admin;
    }

    /**
     * The option labels the position picker offers on the current page.
     *
     * @return list<string>
     */
    private function pickerOptions(Crawler $crawler): array
    {
        return $crawler->filter('select[name="position"] option')
            ->each(static fn (Crawler $c): string => trim(explode('—', $c->text(), 2)[0]) ?: trim($c->text()));
    }

    /**
     * WHAT ADMINISTERING THE TEAM IS, WRITTEN AS PAIRS. `team.manage` was one
     * flat value; it is eight (concern, verb) pairs now, and these are the
     * eight the upgrade backfills it into, so a fixture that used to say
     * "this person administers the team" still says exactly that.
     */
    private function administratorPosition(string $name): Position
    {
        return $this->position($name, [
            'directory.read',
            'directory.manage',
            'personal-details.read',
            'personal-details.manage',
            'positions.read',
            'positions.configure',
            'departments.read',
            'departments.configure',
        ]);
    }
}
