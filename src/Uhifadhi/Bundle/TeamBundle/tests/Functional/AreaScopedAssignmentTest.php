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
use Uhifadhi\Bundle\TeamBundle\Entity\User;
use Uhifadhi\Bundle\TeamBundle\Enum\PermissionEnum;
use Uhifadhi\Bundle\TeamBundle\Enum\TeamRoleEnum;
use Uhifadhi\Bundle\TeamBundle\Tests\Integration\Fixtures\Area\HostArea;

/**
 * §5.6(a) — WHAT AN AREA ADMINISTRATOR MAY DO TO A PERSON.
 *
 * The department-side of area-scoped team.manage already ships (an area-X admin
 * manages only the area-level departments in their own area). This is the
 * person-assignment half of the same ruling: an area-X `team.manage` holder may
 * assign and unassign people ONLY among positions their authority reaches — a
 * position filed under an area-level department confined to their area. Assigning
 * somebody into an org-level position, or into another area's, or touching a
 * person already seated outside the area, would place authority past the
 * administrator's own boundary, which is escalation. A tier (Super Admin / Admin)
 * or an org-level `team.manage` holder is UNBOUNDED and touches anyone.
 *
 * Enforcement is server-side (a 403), exactly as the department controller does
 * it; the pickers on the member record and the invite page are narrowed to what
 * the administrator may assign, matching the confined reassignment control the
 * area-admin design draws ("the target list holds only Southern Reserve positions").
 */
final class AreaScopedAssignmentTest extends WebTestCaseWithSchema
{
    // ---- the member record: reassigning a person --------------------------

    /** An area-X admin assigns a person to a position in their OWN area. */
    public function testAnAreaAdminAssignsAPersonToAPositionInTheirOwnArea(): void
    {
        $north = $this->area('Northern Reserve');
        $this->areaAdminIn($north);
        $ranger = $this->position('Ranger', $this->areaDepartment('Anti-Poaching', $north), [PermissionEnum::AreaView->value]);
        $grace = $this->person('Grace', 'Ndosi');
        $this->em->flush();

        $token = $this->tokenFrom('/team/'.$grace->getUuidString());
        $this->client->request('POST', '/team/'.$grace->getUuidString().'/position', [
            '_token' => $token, 'position' => $ranger->getUuidString(),
        ]);

        self::assertResponseRedirects();
        $this->em->clear();
        $stored = $this->em->getRepository(User::class)->findOneBy(['email' => 'g.ndosi@example.test']);
        self::assertInstanceOf(User::class, $stored);
        self::assertSame('Anti-Poaching / Ranger', $stored->getPosition()?->getQualifiedName());
    }

    /** But NOT to another area's position — that reaches past their boundary. */
    public function testAnAreaAdminCannotAssignToAnotherAreasPosition(): void
    {
        $north = $this->area('Northern Reserve');
        $west = $this->area('Western Reserve');
        $this->areaAdminIn($north);
        $elsewhere = $this->position('Ranger', $this->areaDepartment('Anti-Poaching', $west), [PermissionEnum::AreaView->value]);
        $grace = $this->person('Grace', 'Ndosi');
        $this->em->flush();

        $token = $this->tokenFrom('/team/'.$grace->getUuidString());
        $this->client->request('POST', '/team/'.$grace->getUuidString().'/position', [
            '_token' => $token, 'position' => $elsewhere->getUuidString(),
        ]);

        self::assertResponseStatusCodeSame(403);
        $this->em->clear();
        self::assertNull($this->em->getRepository(User::class)->findOneBy(['email' => 'g.ndosi@example.test'])?->getPosition());
    }

    /** And NOT to an org-level position — that grants org-wide authority. */
    public function testAnAreaAdminCannotAssignToAnOrgLevelPosition(): void
    {
        $north = $this->area('Northern Reserve');
        $this->areaAdminIn($north);
        $orgPosition = $this->position('Analyst', $this->department('Ecology'), [PermissionEnum::AreaView->value]);
        $grace = $this->person('Grace', 'Ndosi');
        $this->em->flush();

        $token = $this->tokenFrom('/team/'.$grace->getUuidString());
        $this->client->request('POST', '/team/'.$grace->getUuidString().'/position', [
            '_token' => $token, 'position' => $orgPosition->getUuidString(),
        ]);

        self::assertResponseStatusCodeSame(403);
    }

    /** A person already seated OUTSIDE the area cannot be reassigned either. */
    public function testAnAreaAdminCannotReassignAPersonSeatedOutsideTheirArea(): void
    {
        $north = $this->area('Northern Reserve');
        $this->areaAdminIn($north);
        $orgPosition = $this->position('Analyst', $this->department('Ecology'), [PermissionEnum::AreaView->value]);
        $mine = $this->position('Ranger', $this->areaDepartment('Anti-Poaching', $north), [PermissionEnum::AreaView->value]);
        $grace = $this->person('Grace', 'Ndosi')->setPosition($orgPosition);
        $this->em->flush();

        // Even moving them INTO the admin's own area is refused — the person is
        // not theirs to move, because they sit in an org-level department.
        $token = $this->tokenFrom('/team/'.$grace->getUuidString());
        $this->client->request('POST', '/team/'.$grace->getUuidString().'/position', [
            '_token' => $token, 'position' => $mine->getUuidString(),
        ]);

        self::assertResponseStatusCodeSame(403);
    }

    /** Unassigning somebody in the admin's own area is allowed. */
    public function testAnAreaAdminUnassignsSomebodyInTheirOwnArea(): void
    {
        $north = $this->area('Northern Reserve');
        $this->areaAdminIn($north);
        $mine = $this->position('Ranger', $this->areaDepartment('Anti-Poaching', $north), [PermissionEnum::AreaView->value]);
        $grace = $this->person('Grace', 'Ndosi')->setPosition($mine);
        $this->em->flush();

        $token = $this->tokenFrom('/team/'.$grace->getUuidString());
        $this->client->request('POST', '/team/'.$grace->getUuidString().'/position', [
            '_token' => $token, 'position' => '',
        ]);

        self::assertResponseRedirects();
        $this->em->clear();
        self::assertNull($this->em->getRepository(User::class)->findOneBy(['email' => 'g.ndosi@example.test'])?->getPosition());
    }

    /** But unassigning somebody OUTSIDE the area is refused. */
    public function testAnAreaAdminCannotUnassignSomebodyOutsideTheirArea(): void
    {
        $north = $this->area('Northern Reserve');
        $this->areaAdminIn($north);
        $orgPosition = $this->position('Analyst', $this->department('Ecology'), [PermissionEnum::AreaView->value]);
        $grace = $this->person('Grace', 'Ndosi')->setPosition($orgPosition);
        $this->em->flush();

        $token = $this->tokenFrom('/team/'.$grace->getUuidString());
        $this->client->request('POST', '/team/'.$grace->getUuidString().'/position', [
            '_token' => $token, 'position' => '',
        ]);

        self::assertResponseStatusCodeSame(403);
        $this->em->clear();
        self::assertNotNull($this->em->getRepository(User::class)->findOneBy(['email' => 'g.ndosi@example.test'])?->getPosition(), 'Nothing was written.');
    }

    /** The member picker offers only the admin's own area's positions. */
    public function testTheMemberPickerOffersOnlyTheAdminsAreaPositions(): void
    {
        $north = $this->area('Northern Reserve');
        $west = $this->area('Western Reserve');
        $this->areaAdminIn($north);
        $this->position('Ranger', $this->areaDepartment('Anti-Poaching', $north), [PermissionEnum::AreaView->value]);
        $this->position('Scout', $this->areaDepartment('Anti-Poaching', $west), [PermissionEnum::AreaView->value]);
        $this->position('Analyst', $this->department('Ecology'), [PermissionEnum::AreaView->value]);
        $grace = $this->person('Grace', 'Ndosi');
        $this->em->flush();

        $crawler = $this->client->request('GET', '/team/'.$grace->getUuidString());
        $options = $this->pickerOptions($crawler);

        self::assertContains('Ranger', $options, 'the admin’s own area position is offered');
        self::assertNotContains('Scout', $options, 'another area’s position is not');
        self::assertNotContains('Analyst', $options, 'an org-level position is not');
    }

    // ---- the invite page: adding somebody ---------------------------------

    /** An area-X admin creates somebody straight into their own area's position. */
    public function testAnAreaAdminCreatesSomebodyIntoTheirOwnAreasPosition(): void
    {
        $north = $this->area('Northern Reserve');
        $this->areaAdminIn($north);
        $ranger = $this->position('Ranger', $this->areaDepartment('Anti-Poaching', $north), [PermissionEnum::AreaView->value]);
        $this->em->flush();

        $token = $this->tokenFrom('/team/invite');
        $this->client->request('POST', '/team', [
            '_token' => $token,
            'firstName' => 'Joseph', 'lastName' => 'Mrema',
            'email' => 'j.mrema@example.test', 'password' => 'a-long-enough-password',
            'position' => $ranger->getUuidString(),
        ]);

        self::assertResponseRedirects();
        $this->em->clear();
        $stored = $this->em->getRepository(User::class)->findOneBy(['email' => 'j.mrema@example.test']);
        self::assertInstanceOf(User::class, $stored);
        self::assertSame('Anti-Poaching / Ranger', $stored->getPosition()?->getQualifiedName());
    }

    /** But NOT into another area's position, and no half-made account survives. */
    public function testAnAreaAdminCannotCreateSomebodyIntoAnotherAreasPosition(): void
    {
        $north = $this->area('Northern Reserve');
        $west = $this->area('Western Reserve');
        $this->areaAdminIn($north);
        $elsewhere = $this->position('Ranger', $this->areaDepartment('Anti-Poaching', $west), [PermissionEnum::AreaView->value]);
        $this->em->flush();

        $token = $this->tokenFrom('/team/invite');
        $this->client->request('POST', '/team', [
            '_token' => $token,
            'firstName' => 'Joseph', 'lastName' => 'Mrema',
            'email' => 'j.mrema@example.test', 'password' => 'a-long-enough-password',
            'position' => $elsewhere->getUuidString(),
        ]);

        self::assertResponseStatusCodeSame(403);
        $this->em->clear();
        self::assertNull($this->em->getRepository(User::class)->findOneBy(['email' => 'j.mrema@example.test']), 'No half-made account is left behind.');
    }

    /** And NOT into an org-level position. */
    public function testAnAreaAdminCannotCreateSomebodyIntoAnOrgLevelPosition(): void
    {
        $north = $this->area('Northern Reserve');
        $this->areaAdminIn($north);
        $orgPosition = $this->position('Analyst', $this->department('Ecology'), [PermissionEnum::AreaView->value]);
        $this->em->flush();

        $token = $this->tokenFrom('/team/invite');
        $this->client->request('POST', '/team', [
            '_token' => $token,
            'firstName' => 'Joseph', 'lastName' => 'Mrema',
            'email' => 'j.mrema@example.test', 'password' => 'a-long-enough-password',
            'position' => $orgPosition->getUuidString(),
        ]);

        self::assertResponseStatusCodeSame(403);
    }

    /** The invite picker offers only the admin's own area's positions. */
    public function testTheInvitePickerOffersOnlyTheAdminsAreaPositions(): void
    {
        $north = $this->area('Northern Reserve');
        $west = $this->area('Western Reserve');
        $this->areaAdminIn($north);
        $this->position('Ranger', $this->areaDepartment('Anti-Poaching', $north), [PermissionEnum::AreaView->value]);
        $this->position('Scout', $this->areaDepartment('Anti-Poaching', $west), [PermissionEnum::AreaView->value]);
        $this->position('Analyst', $this->department('Ecology'), [PermissionEnum::AreaView->value]);
        $this->em->flush();

        $crawler = $this->client->request('GET', '/team/invite');
        $options = $this->pickerOptions($crawler);

        self::assertContains('Ranger', $options);
        self::assertNotContains('Scout', $options);
        self::assertNotContains('Analyst', $options);
    }

    // ---- the unbounded remain unbounded -----------------------------------

    /** An org-level team.manage holder is unbounded: they assign anywhere. */
    public function testAnOrgLevelTeamManageHolderAssignsToAnyArea(): void
    {
        $west = $this->area('Western Reserve');
        // The admin's own position is org-level (no area), so their authority is
        // org-wide — they may seat somebody in any area's position.
        $orgAdmin = $this->person('Amina', 'Salehe', TeamRoleEnum::Staff);
        $orgAdmin->setPosition($this->position('Coordinator', $this->department('Administration'), [PermissionEnum::TeamManage->value]));
        $elsewhere = $this->position('Ranger', $this->areaDepartment('Anti-Poaching', $west), [PermissionEnum::AreaView->value]);
        $grace = $this->person('Grace', 'Ndosi');
        $this->em->flush();
        $this->client->loginUser($orgAdmin);

        $token = $this->tokenFrom('/team/'.$grace->getUuidString());
        $this->client->request('POST', '/team/'.$grace->getUuidString().'/position', [
            '_token' => $token, 'position' => $elsewhere->getUuidString(),
        ]);

        self::assertResponseRedirects();
        $this->em->clear();
        self::assertSame('Anti-Poaching / Ranger', $this->em->getRepository(User::class)->findOneBy(['email' => 'g.ndosi@example.test'])?->getPosition()?->getQualifiedName());
    }

    // ---- the cast ---------------------------------------------------------

    /**
     * Sign in as an AREA-X administrator — a Staff member whose team.manage comes
     * through a position in an area-level department confined to $area, so their
     * authority-area is $area.
     */
    private function areaAdminIn(HostArea $area): User
    {
        $office = $this->areaDepartment('Warden Office', $area);
        $admin = $this->person('Naomi', 'Kileo', TeamRoleEnum::Staff);
        $admin->setPosition($this->position('Warden', $office, [PermissionEnum::TeamManage->value]));
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
            ->each(static fn (Crawler $c): string => trim($c->text()));
    }
}
