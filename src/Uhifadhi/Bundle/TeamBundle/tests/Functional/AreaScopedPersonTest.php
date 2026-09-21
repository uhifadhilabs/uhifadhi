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

use Uhifadhi\Bundle\TeamBundle\Entity\Position;
use Uhifadhi\Bundle\TeamBundle\Entity\User;
use Uhifadhi\Bundle\TeamBundle\Enum\TeamRoleEnum;
use Uhifadhi\Bundle\TeamBundle\Tests\Integration\Fixtures\Area\HostArea;

/**
 * §5.6, THE PERSON SIDE — WHAT AN AREA ADMIN MAY DO TO A PERSON'S RECORD.
 *
 * The assignment half (assign / unassign / invite) is its own suite. This is
 * the rest of person management: editing the record, deactivating and
 * reactivating. An area-X `team.manage` holder may do these only to somebody
 * their authority reaches — a person whose every placed area lies inside their
 * own. Somebody placed in another area, somebody placed across the whole
 * organization, and somebody placed nowhere at all are all past the boundary,
 * so touching their record is escalation and refused with a 403. A tier or an
 * organization-wide placement is UNBOUNDED and touches anyone.
 *
 * WHERE SOMEBODY STANDS IS WRITTEN ON THEM, NOT ON THEIR JOB TITLE. The reach
 * used to be read off the person's position — its department, that
 * department's area — and an unplaced person was therefore harmless and
 * manageable by anybody. The ruled model records the ground against the person
 * and FAILS CLOSED: no placement is no ground, and no ground is nobody a
 * bounded administrator can reach.
 */
final class AreaScopedPersonTest extends WebTestCaseWithSchema
{
    // ---- editing the record -----------------------------------------------

    /** An area-X admin edits somebody placed in their OWN area. */
    public function testAnAreaAdminEditsAPersonPlacedInTheirOwnArea(): void
    {
        $north = $this->area('Northern Reserve');
        $this->areaAdminIn($north);
        $grace = $this->person('Grace', 'Ndosi')->setPosition($this->position('Ranger', ['surveys.read']));
        $this->place($grace, [$north]);
        $this->em->flush();

        $this->post('/team/'.$grace->getUuidString(), [
            'firstName' => 'Grace', 'lastName' => 'Mushi',
            'email' => 'g.ndosi@example.test', 'rangerCode' => '',
        ]);

        self::assertResponseRedirects();
        $this->em->clear();
        self::assertSame('Mushi', $this->reload('g.ndosi@example.test')?->getLastName());
    }

    /** But NOT somebody placed in another area — that reaches past the boundary. */
    public function testAnAreaAdminCannotEditAPersonPlacedInAnotherArea(): void
    {
        $north = $this->area('Northern Reserve');
        $west = $this->area('Western Reserve');
        $this->areaAdminIn($north);
        $grace = $this->person('Grace', 'Ndosi')->setPosition($this->position('Analyst', ['surveys.read']));
        $this->place($grace, [$west]);
        $this->em->flush();

        $this->post('/team/'.$grace->getUuidString(), [
            'firstName' => 'Grace', 'lastName' => 'Mushi',
            'email' => 'g.ndosi@example.test', 'rangerCode' => '',
        ]);

        self::assertResponseStatusCodeSame(403);
        $this->em->clear();
        self::assertSame('Ndosi', $this->reload('g.ndosi@example.test')?->getLastName(), 'Nothing was written.');
    }

    /** And NOT somebody placed across the whole organization — they stand above an area. */
    public function testAnAreaAdminCannotEditAPersonPlacedAcrossTheOrganization(): void
    {
        $north = $this->area('Northern Reserve');
        $this->areaAdminIn($north);
        $grace = $this->person('Grace', 'Ndosi');
        $this->place($grace);
        $this->em->flush();

        $this->post('/team/'.$grace->getUuidString(), [
            'firstName' => 'Grace', 'lastName' => 'Mushi',
            'email' => 'g.ndosi@example.test', 'rangerCode' => '',
        ]);

        self::assertResponseStatusCodeSame(403);
        $this->em->clear();
        self::assertSame('Ndosi', $this->reload('g.ndosi@example.test')?->getLastName(), 'Nothing was written.');
    }

    /**
     * AND NOT SOMEBODY PLACED NOWHERE — the model fails closed. An unplaced
     * person reaches no ground, and stands in no area for a bounded
     * administrator to reach them in; placing them is an unbounded act, and
     * until it happens their record is not an area administrator's to edit.
     */
    public function testAnAreaAdminCannotEditAnUnplacedPerson(): void
    {
        $north = $this->area('Northern Reserve');
        $this->areaAdminIn($north);
        $grace = $this->person('Grace', 'Ndosi');
        $this->em->flush();

        $this->post('/team/'.$grace->getUuidString(), [
            'firstName' => 'Grace', 'lastName' => 'Mushi',
            'email' => 'g.ndosi@example.test', 'rangerCode' => '',
        ]);

        self::assertResponseStatusCodeSame(403);
        $this->em->clear();
        self::assertSame('Ndosi', $this->reload('g.ndosi@example.test')?->getLastName(), 'Nothing was written.');
    }

    // ---- deactivate / reactivate ------------------------------------------

    /** An area-X admin deactivates somebody placed in their own area. */
    public function testAnAreaAdminDeactivatesAPersonPlacedInTheirOwnArea(): void
    {
        $north = $this->area('Northern Reserve');
        $this->areaAdminIn($north);
        $grace = $this->person('Grace', 'Ndosi')->setPosition($this->position('Ranger', ['surveys.read']));
        $this->place($grace, [$north]);
        $this->em->flush();

        $this->post('/team/'.$grace->getUuidString().'/deactivate');

        self::assertResponseRedirects();
        $this->em->clear();
        self::assertFalse($this->reload('g.ndosi@example.test')?->isActive());
    }

    /** But NOT somebody placed outside their area. */
    public function testAnAreaAdminCannotDeactivateAPersonPlacedOutsideTheirArea(): void
    {
        $north = $this->area('Northern Reserve');
        $west = $this->area('Western Reserve');
        $this->areaAdminIn($north);
        $grace = $this->person('Grace', 'Ndosi')->setPosition($this->position('Analyst', ['surveys.read']));
        $this->place($grace, [$west]);
        $this->em->flush();

        $this->post('/team/'.$grace->getUuidString().'/deactivate');

        self::assertResponseStatusCodeSame(403);
        $this->em->clear();
        self::assertTrue($this->reload('g.ndosi@example.test')?->isActive(), 'Nothing was written.');
    }

    /** Reactivating somebody placed outside their area is refused too. */
    public function testAnAreaAdminCannotReactivateAPersonPlacedOutsideTheirArea(): void
    {
        $north = $this->area('Northern Reserve');
        $west = $this->area('Western Reserve');
        $this->areaAdminIn($north);
        $grace = $this->person('Grace', 'Ndosi')->setPosition($this->position('Analyst', ['surveys.read']));
        $this->place($grace, [$west]);
        $grace->deactivate();
        $this->em->flush();

        $this->post('/team/'.$grace->getUuidString().'/reactivate');

        self::assertResponseStatusCodeSame(403);
        $this->em->clear();
        self::assertFalse($this->reload('g.ndosi@example.test')?->isActive(), 'Still deactivated.');
    }

    /** Reactivating somebody placed in their own area is allowed. */
    public function testAnAreaAdminReactivatesAPersonPlacedInTheirOwnArea(): void
    {
        $north = $this->area('Northern Reserve');
        $this->areaAdminIn($north);
        $grace = $this->person('Grace', 'Ndosi')->setPosition($this->position('Ranger', ['surveys.read']));
        $this->place($grace, [$north]);
        $grace->deactivate();
        $this->em->flush();

        $this->post('/team/'.$grace->getUuidString().'/reactivate');

        self::assertResponseRedirects();
        $this->em->clear();
        self::assertTrue($this->reload('g.ndosi@example.test')?->isActive());
    }

    // ---- the unbounded remain unbounded -----------------------------------

    /** An organization-wide holder edits and deactivates anyone, anywhere. */
    public function testAnOrganizationWideHolderManagesAnyone(): void
    {
        $north = $this->area('Northern Reserve');
        $orgAdmin = $this->person('Amina', 'Salehe', TeamRoleEnum::Staff);
        $orgAdmin->setPosition($this->administratorPosition('Coordinator'));
        $this->place($orgAdmin);
        $grace = $this->person('Grace', 'Ndosi')->setPosition($this->position('Ranger', ['surveys.read']));
        $this->place($grace, [$north]);
        $this->em->flush();
        $this->client->loginUser($orgAdmin);

        $this->post('/team/'.$grace->getUuidString().'/deactivate');

        self::assertResponseRedirects();
        $this->em->clear();
        self::assertFalse($this->reload('g.ndosi@example.test')?->isActive());
    }

    /** Including somebody nobody has placed yet, which is the whole point of being unbounded. */
    public function testAnOrganizationWideHolderManagesAnUnplacedPerson(): void
    {
        $orgAdmin = $this->person('Amina', 'Salehe', TeamRoleEnum::Staff);
        $orgAdmin->setPosition($this->administratorPosition('Coordinator'));
        $this->place($orgAdmin);
        $grace = $this->person('Grace', 'Ndosi');
        $this->em->flush();
        $this->client->loginUser($orgAdmin);

        $this->post('/team/'.$grace->getUuidString().'/deactivate');

        self::assertResponseRedirects();
        $this->em->clear();
        self::assertFalse($this->reload('g.ndosi@example.test')?->isActive());
    }

    // ---- the cast ---------------------------------------------------------

    /**
     * Post to a member route with a real CSRF token — always pulled off the
     * record page, since the deactivate / reactivate routes are POST-only and one
     * `team_member` token covers every write on the record.
     *
     * @param array<string, string> $fields
     */
    private function post(string $path, array $fields = []): void
    {
        // THE TOKEN COMES FROM THE CONFIGURE PAGE: the record carries no form.
        $show = preg_replace('#/(deactivate|reactivate)$#', '', $path) ?? $path;
        $token = $this->tokenFrom($show.'/configure');
        $this->client->request('POST', $path, ['_token' => $token, ...$fields]);
    }

    private function reload(string $email): ?User
    {
        return $this->em->getRepository(User::class)->findOneBy(['email' => $email]);
    }

    /**
     * Sign in as an AREA-X administrator — a Staff member holding team.manage
     * through their position and PLACED at $area.
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
