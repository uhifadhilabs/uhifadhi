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
use Uhifadhi\Bundle\TeamBundle\Enum\PermissionEnum;
use Uhifadhi\Bundle\TeamBundle\Enum\TeamRoleEnum;
use Uhifadhi\Bundle\TeamBundle\Tests\Integration\Fixtures\Area\HostArea;

/**
 * §5.6(b) — WHAT AN AREA ADMINISTRATOR MAY DO TO A POSITION.
 *
 * The person-assignment half (§5.6(a)), the department half (§5.6(b)'s
 * department side), and the escalation half (§5.6(c)) already ship. This is the
 * position-create/rename side of §5.6(b): an area-X `team.manage` holder may
 * create and rename positions ONLY under a department their authority reaches —
 * an area-level department confined to their own area. Creating or renaming a
 * position under an org-level department, under another area's, or under NO
 * department at all (a loose position, which has no scope to speak of) files a
 * position past the administrator's own boundary, which is escalation.
 *
 * A tier (Super Admin / Admin) or an org-level `team.manage` holder is UNBOUNDED
 * and touches all of this. Enforcement is server-side (a 403), exactly as the
 * department and assignment controllers do it; the create picker is additionally
 * narrowed to the departments the administrator may file into, matching the
 * area-admin design (org-level departments drawn read-only, no add control).
 */
final class AreaScopedPositionTest extends WebTestCaseWithSchema
{
    // ---- creating a position ----------------------------------------------

    /** An area-X admin creates a position under a department in their OWN area. */
    public function testAnAreaAdminCreatesAPositionUnderADepartmentInTheirOwnArea(): void
    {
        $north = $this->area('Northern Reserve');
        $this->areaAdminIn($north);
        $antiPoaching = $this->areaDepartment('Anti-Poaching', $north);
        $this->em->flush();

        $token = $this->tokenFrom('/team/positions');
        $this->client->request('POST', '/team/positions', [
            '_token' => $token, 'name' => 'Field Ranger', 'department' => $antiPoaching->getUuidString(),
        ]);

        self::assertResponseRedirects();
        $this->em->clear();
        $stored = $this->em->getRepository(Position::class)->findOneBy(['name' => 'Field Ranger']);
        self::assertInstanceOf(Position::class, $stored);
        self::assertSame('Anti-Poaching / Field Ranger', $stored->getQualifiedName());
    }

    /** But NOT under another area's department — that files past their boundary. */
    public function testAnAreaAdminCannotCreateAPositionUnderAnotherAreasDepartment(): void
    {
        $north = $this->area('Northern Reserve');
        $west = $this->area('Western Reserve');
        $this->areaAdminIn($north);
        $elsewhere = $this->areaDepartment('Anti-Poaching', $west);
        $this->em->flush();

        $token = $this->tokenFrom('/team/positions');
        $this->client->request('POST', '/team/positions', [
            '_token' => $token, 'name' => 'Field Ranger', 'department' => $elsewhere->getUuidString(),
        ]);

        self::assertResponseStatusCodeSame(403);
        $this->em->clear();
        self::assertNull($this->em->getRepository(Position::class)->findOneBy(['name' => 'Field Ranger']));
    }

    /** And NOT under an org-level department — that grants an org-wide job. */
    public function testAnAreaAdminCannotCreateAPositionUnderAnOrgLevelDepartment(): void
    {
        $north = $this->area('Northern Reserve');
        $this->areaAdminIn($north);
        $ecology = $this->department('Ecology');
        $this->em->flush();

        $token = $this->tokenFrom('/team/positions');
        $this->client->request('POST', '/team/positions', [
            '_token' => $token, 'name' => 'Analyst', 'department' => $ecology->getUuidString(),
        ]);

        self::assertResponseStatusCodeSame(403);
        $this->em->clear();
        self::assertNull($this->em->getRepository(Position::class)->findOneBy(['name' => 'Analyst']));
    }

    /** And NOT a loose position with no department — a position with no scope. */
    public function testAnAreaAdminCannotCreateALoosePosition(): void
    {
        $north = $this->area('Northern Reserve');
        $this->areaAdminIn($north);
        $this->em->flush();

        $token = $this->tokenFrom('/team/positions');
        $this->client->request('POST', '/team/positions', [
            '_token' => $token, 'name' => 'Floater', 'department' => '',
        ]);

        self::assertResponseStatusCodeSame(403);
        $this->em->clear();
        self::assertNull($this->em->getRepository(Position::class)->findOneBy(['name' => 'Floater']));
    }

    // ---- renaming a position ----------------------------------------------

    /** An area-X admin renames a position in a department in their OWN area. */
    public function testAnAreaAdminRenamesAPositionInTheirOwnArea(): void
    {
        $north = $this->area('Northern Reserve');
        $this->areaAdminIn($north);
        $ranger = $this->position('Ranger', $this->areaDepartment('Anti-Poaching', $north), []);
        $this->em->flush();

        $token = $this->tokenFrom('/team/positions');
        $this->client->request('POST', '/team/positions/'.$ranger->getUuidString().'/rename', [
            '_token' => $token, 'name' => 'Senior Ranger',
        ]);

        self::assertResponseRedirects();
        $this->em->clear();
        self::assertNotNull($this->em->getRepository(Position::class)->findOneBy(['name' => 'Senior Ranger']));
    }

    /** But NOT a position under another area's department. */
    public function testAnAreaAdminCannotRenameAPositionUnderAnotherAreasDepartment(): void
    {
        $north = $this->area('Northern Reserve');
        $west = $this->area('Western Reserve');
        $this->areaAdminIn($north);
        $elsewhere = $this->position('Ranger', $this->areaDepartment('Anti-Poaching', $west), []);
        $this->em->flush();

        $token = $this->tokenFrom('/team/positions');
        $this->client->request('POST', '/team/positions/'.$elsewhere->getUuidString().'/rename', [
            '_token' => $token, 'name' => 'Senior Ranger',
        ]);

        self::assertResponseStatusCodeSame(403);
        $this->em->clear();
        self::assertNotNull($this->em->getRepository(Position::class)->findOneBy(['name' => 'Ranger']), 'Nothing was renamed.');
    }

    /** And NOT an org-level position. */
    public function testAnAreaAdminCannotRenameAnOrgLevelPosition(): void
    {
        $north = $this->area('Northern Reserve');
        $this->areaAdminIn($north);
        $analyst = $this->position('Analyst', $this->department('Ecology'), []);
        $this->em->flush();

        $token = $this->tokenFrom('/team/positions');
        $this->client->request('POST', '/team/positions/'.$analyst->getUuidString().'/rename', [
            '_token' => $token, 'name' => 'Senior Analyst',
        ]);

        self::assertResponseStatusCodeSame(403);
    }

    /** And NOT a loose position (no department, no scope). */
    public function testAnAreaAdminCannotRenameALoosePosition(): void
    {
        $north = $this->area('Northern Reserve');
        $this->areaAdminIn($north);
        $loose = $this->position('Floater', null, []);
        $this->em->flush();

        $token = $this->tokenFrom('/team/positions');
        $this->client->request('POST', '/team/positions/'.$loose->getUuidString().'/rename', [
            '_token' => $token, 'name' => 'Utility',
        ]);

        self::assertResponseStatusCodeSame(403);
    }

    // ---- the create picker ------------------------------------------------

    /** The create picker offers only the admin's own area's departments. */
    public function testTheCreatePickerOffersOnlyTheAdminsAreaDepartments(): void
    {
        $north = $this->area('Northern Reserve');
        $west = $this->area('Western Reserve');
        $this->areaAdminIn($north);
        $this->areaDepartment('Anti-Poaching', $north);
        $this->areaDepartment('Scouts', $west);
        $this->department('Ecology');
        $this->em->flush();

        $crawler = $this->client->request('GET', '/team/positions');
        $options = $this->departmentOptions($crawler);

        self::assertContains('Anti-Poaching', $options, 'the admin’s own area department is offered');
        self::assertNotContains('Scouts', $options, 'another area’s department is not');
        self::assertNotContains('Ecology', $options, 'an org-level department is not');
        // The loose "No department yet…" option has no scope, so a bounded admin
        // may not file into it — it is absent too.
        self::assertNotContains('No department yet…', $options, 'the loose option is not offered to a bounded admin');
    }

    // ---- the unbounded remain unbounded -----------------------------------

    /** An org-level team.manage holder is unbounded: they create anywhere. */
    public function testAnOrgLevelAdminMayCreateUnderAnyDepartment(): void
    {
        $west = $this->area('Western Reserve');
        $orgAdmin = $this->person('Amina', 'Salehe', TeamRoleEnum::Staff);
        $orgAdmin->setPosition($this->position('Coordinator', $this->department('Administration'), [PermissionEnum::TeamManage->value]));
        $elsewhere = $this->areaDepartment('Anti-Poaching', $west);
        $this->em->flush();
        $this->client->loginUser($orgAdmin);

        $token = $this->tokenFrom('/team/positions');
        $this->client->request('POST', '/team/positions', [
            '_token' => $token, 'name' => 'Field Ranger', 'department' => $elsewhere->getUuidString(),
        ]);

        self::assertResponseRedirects();
        $this->em->clear();
        self::assertSame('Anti-Poaching / Field Ranger', $this->em->getRepository(Position::class)->findOneBy(['name' => 'Field Ranger'])?->getQualifiedName());
    }

    /** And the create picker offers every department, loose option included. */
    public function testTheCreatePickerOffersEverythingToAnUnboundedAdmin(): void
    {
        $north = $this->area('Northern Reserve');
        $this->administrator();
        $this->areaDepartment('Anti-Poaching', $north);
        $this->department('Ecology');
        $this->em->flush();

        $crawler = $this->client->request('GET', '/team/positions');
        $options = $this->departmentOptions($crawler);

        self::assertContains('Anti-Poaching', $options);
        self::assertContains('Ecology', $options);
        self::assertContains('No department yet…', $options);
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
     * The department labels the create picker offers on the positions page.
     *
     * @return list<string>
     */
    private function departmentOptions(Crawler $crawler): array
    {
        return $crawler->filter('form.assign select[name="department"] option')
            ->each(static fn (Crawler $c): string => trim($c->text()));
    }
}
