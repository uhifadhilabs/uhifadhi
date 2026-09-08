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

use PHPUnit\Framework\Attributes\DataProvider;
use Uhifadhi\Bundle\TeamBundle\Entity\User;
use Uhifadhi\Bundle\TeamBundle\Enum\PermissionEnum;
use Uhifadhi\Bundle\TeamBundle\Enum\TeamRoleEnum;
use Uhifadhi\Bundle\TeamBundle\Tests\Integration\Fixtures\Area\HostArea;

/**
 * §5.6, DESIGN FIDELITY — THE "SCOPED TO <AREA>" STATEMENT.
 *
 * The area-admin design draws a banner ("You are scoped to Serengeti") on the
 * team / department / position management chrome, telling a bounded admin WHICH
 * area bounds them. It is shown to a bounded (area-X) `team.manage` holder on
 * every management surface, and to nobody else — an unbounded holder (a tier or
 * an org-level admin) manages every area, so there is no one area to name. The
 * area is named through the contract's AreaInterface::getName().
 */
final class AreaScopedBannerTest extends WebTestCaseWithSchema
{
    /**
     * A bounded admin sees the banner, naming their authority-area, on every
     * management surface — the roster, the record, the invite page, the positions
     * matrix, and the departments manager.
     *
     * @return iterable<string, array{string}>
     */
    public static function managementChrome(): iterable
    {
        yield 'roster' => ['/team'];
        yield 'invite' => ['/team/invite'];
        yield 'positions' => ['/team/positions'];
        yield 'departments' => ['/departments'];
    }

    #[DataProvider('managementChrome')]
    public function testTheBannerNamesTheAreaForABoundedAdmin(string $path): void
    {
        $this->areaAdminIn($this->area('Ngorongoro'));
        $this->em->flush();

        $crawler = $this->client->request('GET', $path);

        self::assertResponseIsSuccessful();
        self::assertStringContainsString('You are scoped to Ngorongoro', $crawler->filter('.scope-fence')->html());
    }

    /** And on one person's record, the other management surface. */
    public function testTheBannerShowsOnTheMemberRecordForABoundedAdmin(): void
    {
        $this->areaAdminIn($this->area('Ngorongoro'));
        $grace = $this->person('Grace', 'Ndosi');
        $this->em->flush();

        $crawler = $this->client->request('GET', '/team/'.$grace->getUuidString());

        self::assertResponseIsSuccessful();
        self::assertStringContainsString('You are scoped to Ngorongoro', $crawler->filter('.scope-fence')->html());
    }

    /** A tier (Super Admin) is unbounded — no banner, there is no one area. */
    public function testATierSeesNoBanner(): void
    {
        $this->administrator();

        $crawler = $this->client->request('GET', '/departments');

        self::assertResponseIsSuccessful();
        self::assertCount(0, $crawler->filter('.scope-fence'));
    }

    /** An org-level team.manage holder is unbounded too — no banner. */
    public function testAnOrgLevelHolderSeesNoBanner(): void
    {
        $orgAdmin = $this->person('Amina', 'Salehe', TeamRoleEnum::Staff);
        $orgAdmin->setPosition($this->position('Coordinator', $this->department('Administration'), [PermissionEnum::TeamManage->value]));
        $this->em->flush();
        $this->client->loginUser($orgAdmin);

        $crawler = $this->client->request('GET', '/departments');

        self::assertResponseIsSuccessful();
        self::assertCount(0, $crawler->filter('.scope-fence'));
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
