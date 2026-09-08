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

use Uhifadhi\Bundle\TeamBundle\Entity\User;
use Uhifadhi\Bundle\TeamBundle\Enum\PermissionEnum;
use Uhifadhi\Bundle\TeamBundle\Enum\TeamRoleEnum;
use Uhifadhi\Bundle\TeamBundle\Tests\Integration\Fixtures\Area\HostArea;

/**
 * §5.6, THE PERSON SIDE — WHAT AN AREA ADMIN MAY DO TO A PERSON'S RECORD.
 *
 * The assignment half (assign / unassign / invite) already ships. This is the
 * rest of person management: editing the record, deactivating and reactivating.
 * An area-X `team.manage` holder may do these only to somebody their authority
 * reaches — a person whose current position is filed under an area-level
 * department in their own area. A person seated in another area, or under an
 * org-level department, is past the administrator's boundary, so touching their
 * record is escalation and refused with a 403. A person with NO position holds
 * no authority anywhere and stays manageable. A tier or an org-level
 * `team.manage` holder is UNBOUNDED and touches anyone.
 */
final class AreaScopedPersonTest extends WebTestCaseWithSchema
{
    // ---- editing the record -----------------------------------------------

    /** An area-X admin edits somebody seated in their OWN area. */
    public function testAnAreaAdminEditsAPersonInTheirOwnArea(): void
    {
        $ngorongoro = $this->area('Ngorongoro');
        $this->areaAdminIn($ngorongoro);
        $mine = $this->position('Ranger', $this->areaDepartment('Anti-Poaching', $ngorongoro), [PermissionEnum::AreaView->value]);
        $grace = $this->person('Grace', 'Ndosi')->setPosition($mine);
        $this->em->flush();

        $this->post('/team/'.$grace->getUuidString(), [
            'firstName' => 'Grace', 'lastName' => 'Mushi',
            'email' => 'g.ndosi@example.test', 'rangerCode' => '',
        ]);

        self::assertResponseRedirects();
        $this->em->clear();
        self::assertSame('Mushi', $this->reload('g.ndosi@example.test')?->getLastName());
    }

    /** But NOT somebody seated OUTSIDE their area — that reaches past the boundary. */
    public function testAnAreaAdminCannotEditAPersonOutsideTheirArea(): void
    {
        $ngorongoro = $this->area('Ngorongoro');
        $this->areaAdminIn($ngorongoro);
        $orgPosition = $this->position('Analyst', $this->department('Ecology'), [PermissionEnum::AreaView->value]);
        $grace = $this->person('Grace', 'Ndosi')->setPosition($orgPosition);
        $this->em->flush();

        $this->post('/team/'.$grace->getUuidString(), [
            'firstName' => 'Grace', 'lastName' => 'Mushi',
            'email' => 'g.ndosi@example.test', 'rangerCode' => '',
        ]);

        self::assertResponseStatusCodeSame(403);
        $this->em->clear();
        self::assertSame('Ndosi', $this->reload('g.ndosi@example.test')?->getLastName(), 'Nothing was written.');
    }

    /** Somebody with NO position is nobody's to fence — editing them is allowed. */
    public function testAnAreaAdminEditsAPersonWithNoPosition(): void
    {
        $ngorongoro = $this->area('Ngorongoro');
        $this->areaAdminIn($ngorongoro);
        $grace = $this->person('Grace', 'Ndosi');
        $this->em->flush();

        $this->post('/team/'.$grace->getUuidString(), [
            'firstName' => 'Grace', 'lastName' => 'Mushi',
            'email' => 'g.ndosi@example.test', 'rangerCode' => '',
        ]);

        self::assertResponseRedirects();
        $this->em->clear();
        self::assertSame('Mushi', $this->reload('g.ndosi@example.test')?->getLastName());
    }

    // ---- deactivate / reactivate ------------------------------------------

    /** An area-X admin deactivates somebody in their own area. */
    public function testAnAreaAdminDeactivatesAPersonInTheirOwnArea(): void
    {
        $ngorongoro = $this->area('Ngorongoro');
        $this->areaAdminIn($ngorongoro);
        $mine = $this->position('Ranger', $this->areaDepartment('Anti-Poaching', $ngorongoro), [PermissionEnum::AreaView->value]);
        $grace = $this->person('Grace', 'Ndosi')->setPosition($mine);
        $this->em->flush();

        $this->post('/team/'.$grace->getUuidString().'/deactivate');

        self::assertResponseRedirects();
        $this->em->clear();
        self::assertFalse($this->reload('g.ndosi@example.test')?->isActive());
    }

    /** But NOT somebody seated outside their area. */
    public function testAnAreaAdminCannotDeactivateAPersonOutsideTheirArea(): void
    {
        $ngorongoro = $this->area('Ngorongoro');
        $this->areaAdminIn($ngorongoro);
        $orgPosition = $this->position('Analyst', $this->department('Ecology'), [PermissionEnum::AreaView->value]);
        $grace = $this->person('Grace', 'Ndosi')->setPosition($orgPosition);
        $this->em->flush();

        $this->post('/team/'.$grace->getUuidString().'/deactivate');

        self::assertResponseStatusCodeSame(403);
        $this->em->clear();
        self::assertTrue($this->reload('g.ndosi@example.test')?->isActive(), 'Nothing was written.');
    }

    /** Reactivating somebody outside their area is refused too. */
    public function testAnAreaAdminCannotReactivateAPersonOutsideTheirArea(): void
    {
        $ngorongoro = $this->area('Ngorongoro');
        $this->areaAdminIn($ngorongoro);
        $orgPosition = $this->position('Analyst', $this->department('Ecology'), [PermissionEnum::AreaView->value]);
        $grace = $this->person('Grace', 'Ndosi')->setPosition($orgPosition);
        $grace->deactivate();
        $this->em->flush();

        $this->post('/team/'.$grace->getUuidString().'/reactivate');

        self::assertResponseStatusCodeSame(403);
        $this->em->clear();
        self::assertFalse($this->reload('g.ndosi@example.test')?->isActive(), 'Still deactivated.');
    }

    /** Reactivating somebody in their own area is allowed. */
    public function testAnAreaAdminReactivatesAPersonInTheirOwnArea(): void
    {
        $ngorongoro = $this->area('Ngorongoro');
        $this->areaAdminIn($ngorongoro);
        $mine = $this->position('Ranger', $this->areaDepartment('Anti-Poaching', $ngorongoro), [PermissionEnum::AreaView->value]);
        $grace = $this->person('Grace', 'Ndosi')->setPosition($mine);
        $grace->deactivate();
        $this->em->flush();

        $this->post('/team/'.$grace->getUuidString().'/reactivate');

        self::assertResponseRedirects();
        $this->em->clear();
        self::assertTrue($this->reload('g.ndosi@example.test')?->isActive());
    }

    // ---- the unbounded remain unbounded -----------------------------------

    /** An org-level team.manage holder edits and deactivates anyone, anywhere. */
    public function testAnOrgLevelHolderManagesAnyone(): void
    {
        $ngorongoro = $this->area('Ngorongoro');
        $orgAdmin = $this->person('Amina', 'Salehe', TeamRoleEnum::Staff);
        $orgAdmin->setPosition($this->position('Coordinator', $this->department('Administration'), [PermissionEnum::TeamManage->value]));
        $mine = $this->position('Ranger', $this->areaDepartment('Anti-Poaching', $ngorongoro), [PermissionEnum::AreaView->value]);
        $grace = $this->person('Grace', 'Ndosi')->setPosition($mine);
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
        $show = preg_replace('#/(deactivate|reactivate)$#', '', $path) ?? $path;
        $token = $this->tokenFrom($show);
        $this->client->request('POST', $path, ['_token' => $token, ...$fields]);
    }

    private function reload(string $email): ?User
    {
        return $this->em->getRepository(User::class)->findOneBy(['email' => $email]);
    }

    /**
     * Sign in as an AREA-X administrator — a Staff member whose team.manage comes
     * through a position in an area-level department confined to $area.
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
}
