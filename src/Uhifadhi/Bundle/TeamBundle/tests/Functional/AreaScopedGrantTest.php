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
use Uhifadhi\Bundle\TeamBundle\Enum\PermissionEnum;
use Uhifadhi\Bundle\TeamBundle\Enum\TeamRoleEnum;
use Uhifadhi\Bundle\TeamBundle\Tests\Integration\Fixtures\Area\HostArea;

/**
 * §5.6(c) — NO PRIVILEGE ESCALATION BY AN AREA ADMINISTRATOR.
 *
 * The person-assignment half (§5.6(a)) and the department half (§5.6(b)) already
 * ship. This is the escalation half: an area-X `team.manage` holder may not grant
 * a permission their own position does not hold, and may not confer team
 * administration at all — team.manage is org-wide authority, and creating another
 * administrator is a widening past their own boundary. Nor may a bounded
 * administrator change a person's tier: Super Admin and Admin are org-wide
 * authority, and promoting somebody to one is the plainest escalation there is.
 *
 * A tier (Super Admin / Admin) or an org-level `team.manage` holder is UNBOUNDED
 * and touches all of this. Enforcement is server-side (a 403), exactly as the
 * assignment and department controllers do it; the matrix additionally draws the
 * ungrantable rows disabled, matching the "no wider-than-self grant" guard the
 * area-admin design draws.
 */
final class AreaScopedGrantTest extends WebTestCaseWithSchema
{
    // ---- the permission matrix: grant width -------------------------------

    /** A bounded admin may grant a permission their OWN position holds. */
    public function testAnAreaAdminMayGrantAPermissionTheyThemselvesHold(): void
    {
        $north = $this->area('Northern Reserve');
        $this->areaAdminHolding($north, [PermissionEnum::TeamManage->value, PermissionEnum::AreaView->value]);
        $ranger = $this->position('Ranger', $this->areaDepartment('Anti-Poaching', $north), []);
        $this->em->flush();

        $token = $this->tokenFrom('/team/positions');
        $this->client->request('POST', '/team/positions/'.$ranger->getUuidString().'/permissions', [
            '_token' => $token, 'permissions' => [PermissionEnum::AreaView->value],
        ]);

        self::assertResponseRedirects();
        $this->em->clear();
        $stored = $this->em->getRepository(Position::class)->findOneBy(['name' => 'Ranger']);
        self::assertInstanceOf(Position::class, $stored);
        self::assertSame([PermissionEnum::AreaView->value], $stored->getPermissionValues());
    }

    /** But NOT a permission their own position does not hold — that widens power. */
    public function testAnAreaAdminCannotGrantAPermissionBeyondTheirOwnAuthority(): void
    {
        $north = $this->area('Northern Reserve');
        $this->areaAdminHolding($north, [PermissionEnum::TeamManage->value, PermissionEnum::AreaView->value]);
        $ranger = $this->position('Ranger', $this->areaDepartment('Anti-Poaching', $north), []);
        $this->em->flush();

        $token = $this->tokenFrom('/team/positions');
        $this->client->request('POST', '/team/positions/'.$ranger->getUuidString().'/permissions', [
            '_token' => $token, 'permissions' => [PermissionEnum::AreaDelete->value],
        ]);

        self::assertResponseStatusCodeSame(403);
        $this->em->clear();
        self::assertSame([], $this->em->getRepository(Position::class)->findOneBy(['name' => 'Ranger'])?->getPermissionValues());
    }

    /** And NEVER team.manage — conferring team administration is an unbounded act. */
    public function testAnAreaAdminCannotConferTeamManage(): void
    {
        $north = $this->area('Northern Reserve');
        $this->areaAdminHolding($north, [PermissionEnum::TeamManage->value, PermissionEnum::AreaView->value]);
        $deputy = $this->position('Deputy Warden', $this->areaDepartment('Anti-Poaching', $north), []);
        $this->em->flush();

        $token = $this->tokenFrom('/team/positions');
        $this->client->request('POST', '/team/positions/'.$deputy->getUuidString().'/permissions', [
            '_token' => $token, 'permissions' => [PermissionEnum::TeamManage->value],
        ]);

        self::assertResponseStatusCodeSame(403);
        $this->em->clear();
        self::assertSame([], $this->em->getRepository(Position::class)->findOneBy(['name' => 'Deputy Warden'])?->getPermissionValues());
    }

    /**
     * A bounded save neither adds NOR strips a permission beyond the admin's
     * authority: what the position already held past their reach is frozen, so an
     * unrelated save cannot silently revoke it (nor is it a way around the fence).
     */
    public function testABoundedSaveFreezesPermissionsBeyondTheAdminsAuthority(): void
    {
        $north = $this->area('Northern Reserve');
        $this->areaAdminHolding($north, [PermissionEnum::TeamManage->value, PermissionEnum::AreaView->value]);
        // The position already carries a permission the admin does not hold.
        $ranger = $this->position('Ranger', $this->areaDepartment('Anti-Poaching', $north), [PermissionEnum::AreaDelete->value]);
        $this->em->flush();

        $token = $this->tokenFrom('/team/positions');
        $this->client->request('POST', '/team/positions/'.$ranger->getUuidString().'/permissions', [
            '_token' => $token, 'permissions' => [PermissionEnum::AreaView->value],
        ]);

        self::assertResponseRedirects();
        $this->em->clear();
        $stored = $this->em->getRepository(Position::class)->findOneBy(['name' => 'Ranger']);
        self::assertInstanceOf(Position::class, $stored);
        self::assertContains(PermissionEnum::AreaView->value, $stored->getPermissionValues(), 'The grantable tick was saved.');
        self::assertContains(PermissionEnum::AreaDelete->value, $stored->getPermissionValues(), 'The untouchable grant was not stripped.');
    }

    /** The matrix draws the rows beyond the admin's authority disabled, with the guard note. */
    public function testTheMatrixDisablesPermissionsBeyondTheAdminsAuthority(): void
    {
        $north = $this->area('Northern Reserve');
        $this->areaAdminHolding($north, [PermissionEnum::TeamManage->value, PermissionEnum::AreaView->value]);
        $ranger = $this->position('Ranger', $this->areaDepartment('Anti-Poaching', $north), []);
        $this->em->flush();

        $crawler = $this->client->request('GET', '/team/positions?position='.$ranger->getUuidString());

        // A permission the admin holds is grantable — its box is enabled.
        self::assertCount(0, $crawler->filter('form.pane input.pm-check[value="'.PermissionEnum::AreaView->value.'"][disabled]'), 'A held permission is grantable.');
        // One the admin does not hold, and team.manage, are disabled.
        self::assertCount(1, $crawler->filter('form.pane input.pm-check[value="'.PermissionEnum::AreaDelete->value.'"][disabled]'), 'A permission beyond authority is disabled.');
        self::assertCount(1, $crawler->filter('form.pane input.pm-check[value="'.PermissionEnum::TeamManage->value.'"][disabled]'), 'team.manage is never grantable by a bounded admin.');
        self::assertStringContainsString('no wider-than-self grant', $crawler->filter('form.pane')->html());
    }

    /** An org-level team.manage holder is unbounded: they grant anything, team.manage included. */
    public function testAnOrgLevelAdminMayGrantAnything(): void
    {
        $north = $this->area('Northern Reserve');
        $orgAdmin = $this->person('Amina', 'Salehe', TeamRoleEnum::Staff);
        $orgAdmin->setPosition($this->position('Coordinator', $this->department('Administration'), [PermissionEnum::TeamManage->value]));
        $ranger = $this->position('Ranger', $this->areaDepartment('Anti-Poaching', $north), []);
        $this->em->flush();
        $this->client->loginUser($orgAdmin);

        $token = $this->tokenFrom('/team/positions');
        $this->client->request('POST', '/team/positions/'.$ranger->getUuidString().'/permissions', [
            '_token' => $token, 'permissions' => [PermissionEnum::AreaDelete->value, PermissionEnum::TeamManage->value],
        ]);

        self::assertResponseRedirects();
        $this->em->clear();
        $stored = $this->em->getRepository(Position::class)->findOneBy(['name' => 'Ranger']);
        self::assertInstanceOf(Position::class, $stored);
        self::assertSame([PermissionEnum::AreaDelete->value, PermissionEnum::TeamManage->value], $stored->getPermissionValues());
    }

    // ---- the member record: changing a tier -------------------------------

    /** A bounded admin may not change a person's tier — Super Admin / Admin are org-wide. */
    public function testAnAreaAdminCannotChangeAPersonsTier(): void
    {
        $north = $this->area('Northern Reserve');
        $this->areaAdminHolding($north, [PermissionEnum::TeamManage->value]);
        $grace = $this->person('Grace', 'Ndosi');
        $this->em->flush();

        $token = $this->tokenFrom('/team/'.$grace->getUuidString());
        $this->client->request('POST', '/team/'.$grace->getUuidString().'/tier', [
            '_token' => $token, 'tier' => TeamRoleEnum::Admin->value,
        ]);

        self::assertResponseStatusCodeSame(403);
        $this->em->clear();
        self::assertSame(TeamRoleEnum::Staff, $this->em->getRepository(User::class)->findOneBy(['email' => 'g.ndosi@example.test'])?->getTeamRole());
    }

    /** An org-level team.manage holder is unbounded: they may change a tier. */
    public function testAnOrgLevelAdminMayChangeATier(): void
    {
        $orgAdmin = $this->person('Amina', 'Salehe', TeamRoleEnum::Staff);
        $orgAdmin->setPosition($this->position('Coordinator', $this->department('Administration'), [PermissionEnum::TeamManage->value]));
        $grace = $this->person('Grace', 'Ndosi');
        $this->em->flush();
        $this->client->loginUser($orgAdmin);

        $token = $this->tokenFrom('/team/'.$grace->getUuidString());
        $this->client->request('POST', '/team/'.$grace->getUuidString().'/tier', [
            '_token' => $token, 'tier' => TeamRoleEnum::Admin->value,
        ]);

        self::assertResponseRedirects();
        $this->em->clear();
        self::assertSame(TeamRoleEnum::Admin, $this->em->getRepository(User::class)->findOneBy(['email' => 'g.ndosi@example.test'])?->getTeamRole());
    }

    // ---- the cast ---------------------------------------------------------

    /**
     * Sign in as an AREA-X administrator whose OWN position carries exactly the
     * given permissions — a Staff member in an area-level department confined to
     * $area, so their authority-area is $area and their reach is what they hold.
     *
     * @param list<string> $permissions
     */
    private function areaAdminHolding(HostArea $area, array $permissions): User
    {
        $office = $this->areaDepartment('Warden Office', $area);
        $admin = $this->person('Naomi', 'Kileo', TeamRoleEnum::Staff);
        $admin->setPosition($this->position('Warden', $office, $permissions));
        $this->em->flush();
        $this->client->loginUser($admin);

        return $admin;
    }
}
