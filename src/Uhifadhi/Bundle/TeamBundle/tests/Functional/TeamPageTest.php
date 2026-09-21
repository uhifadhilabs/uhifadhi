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

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Uhifadhi\Bundle\TeamBundle\Entity\Placement;
use Uhifadhi\Bundle\TeamBundle\Entity\Position;
use Uhifadhi\Bundle\TeamBundle\Entity\User;
use Uhifadhi\Bundle\TeamBundle\Enum\PermissionEnum;
use Uhifadhi\Bundle\TeamBundle\Enum\TeamRoleEnum;
use Uhifadhi\Bundle\TeamBundle\Tests\Integration\TestKernel;

/**
 * THE TEAM PAGE, RENDERED — the design's key structures asserted as markup
 * rather than as intentions.
 *
 * What is checked here is what the design argues about and a template can get
 * wrong: the attention pane collapsing to NOTHING when nobody needs a decision,
 * the three-line position cell, the tier pills as links carrying whole-roster
 * counts, the pager drawn in the state it is actually in — and the gate, which
 * is a PERMISSION and not a tier.
 */
final class TeamPageTest extends WebTestCase
{
    /**
     * NAMED IN CODE, not by KERNEL_CLASS. One repository holds several
     * packages, so one env var could only ever name one of their kernels.
     */
    protected static function getKernelClass(): string
    {
        return TestKernel::class;
    }

    private KernelBrowser $client;
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        $this->client = self::createClient();
        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get('doctrine.orm.entity_manager');
        $this->em = $em;

        $tool = new SchemaTool($this->em);
        $metadata = $this->em->getMetadataFactory()->getAllMetadata();
        $tool->dropSchema($metadata);
        $tool->createSchema($metadata);
    }

    protected function tearDown(): void
    {
        $this->em->close();
        parent::tearDown();

        while (true) {
            $previous = set_exception_handler(static fn () => null);
            restore_exception_handler();
            if (null === $previous) {
                break;
            }
            restore_exception_handler();
        }
    }

    private function person(string $first, string $last, TeamRoleEnum $tier = TeamRoleEnum::Staff): User
    {
        $user = (new User())
            ->setEmail(strtolower($first[0].'.'.$last).'@example.test')
            ->setFirstName($first)->setLastName($last)->setPassword('x')
            ->setTeamRole($tier)->setVerified(true);
        $this->em->persist($user);

        return $user;
    }

    /**
     * A POSITION CARRIES NO DEPARTMENT. It used to take one and its name was
     * unique only inside it; the ruling made the name unique across the
     * organization, so the argument is gone from the signature rather than
     * ignored.
     *
     * @param list<PermissionEnum> $permissions
     */
    private function position(string $name, array $permissions = []): Position
    {
        $position = (new Position())->setName($name);
        $position->setPermissionValues(
            array_map(static fn (PermissionEnum $p): string => $p->value, $permissions),
            array_map(static fn (PermissionEnum $p): string => $p->value, PermissionEnum::all()),
        );
        $this->em->persist($position);

        return $position;
    }

    /** A settled installation: everybody arrived, everybody holds something, two Super Admins. */
    private function settled(): User
    {
        $senior = $this->position('Senior Ranger', [PermissionEnum::TeamManage, PermissionEnum::AreaView]);
        $ranger = $this->position('Ranger', [PermissionEnum::AreaView]);

        $naomi = $this->person('Naomi', 'Kileo', TeamRoleEnum::SuperAdmin);
        $this->person('Asha', 'Mollel', TeamRoleEnum::SuperAdmin);
        $this->person('Grace', 'Ndosi')->setPosition($senior)->setRangerCode('R-104');
        $this->person('Zawadi', 'Naisenya')->setPosition($ranger);
        $this->em->flush();

        return $naomi;
    }

    /**
     * THE GATE IS A PERMISSION, NOT A TIER. A Staff member whose position
     * carries team.manage administers the team — that is the whole of what
     * retiring the Manager tier bought.
     */
    public function testAStaffMemberHoldingTeamManageReachesThePage(): void
    {
        $senior = $this->position('Senior Ranger', [PermissionEnum::TeamManage]);
        $grace = $this->person('Grace', 'Ndosi')->setPosition($senior);
        // A PERMISSION IS ONLY HELD SOMEWHERE. The position says what Grace
        // may do and the placement says where; the model fails closed, so
        // somebody placed nowhere reaches nothing however much they hold.
        $placement = new Placement();
        $placement->acrossTheOrganization();
        $placement->acrossAllDepartments();
        $this->em->persist($placement);
        $grace->setPlacement($placement);
        $this->person('Naomi', 'Kileo', TeamRoleEnum::SuperAdmin);
        $this->em->flush();

        $this->client->loginUser($grace);
        $this->client->request('GET', '/team');

        self::assertResponseIsSuccessful();
    }

    /** And a Staff member whose position does not carry it is refused. */
    public function testAStaffMemberWithoutItIsRefused(): void
    {
        $frank = $this->person('Frank', 'Massawe');
        $this->em->flush();

        $this->client->loginUser($frank);
        $this->client->request('GET', '/team');

        self::assertResponseStatusCodeSame(403);
    }

    /** The two tiers above the matrix pass the same check, by tier. */
    public function testASuperAdminReachesThePage(): void
    {
        $naomi = $this->settled();

        $this->client->loginUser($naomi);
        $crawler = $this->client->request('GET', '/team');

        self::assertResponseIsSuccessful();
        self::assertSame('Team', $crawler->filter('h1.pg')->text());
    }

    /**
     * THE ATTENTION PANE COLLAPSES TO NOTHING. Not an empty plate saying
     * everything is fine — absent. A pane that stayed on screen to report that
     * nothing is wrong would spend the top of the page saying so, every visit.
     */
    public function testTheAttentionPaneIsAbsentWhenNobodyNeedsADecision(): void
    {
        $naomi = $this->settled();

        $this->client->loginUser($naomi);
        $crawler = $this->client->request('GET', '/team');

        self::assertCount(0, $crawler->filter('.tm-att'));
        self::assertStringNotContainsString('Needs a decision', $crawler->html());
    }

    public function testTheAttentionPaneAppearsWhenSomebodyHasNeverSignedIn(): void
    {
        $this->settled();
        $joseph = $this->person('Joseph', 'Mrema')->setVerified(false);
        $naomi = $this->em->getRepository(User::class)->findOneBy(['email' => 'n.kileo@example.test']);
        self::assertInstanceOf(User::class, $naomi);
        $joseph->markInvitedBy($naomi);
        $this->em->flush();

        $this->client->loginUser($naomi);
        $crawler = $this->client->request('GET', '/team');

        self::assertCount(1, $crawler->filter('.tm-att'));
        self::assertStringContainsString('Joseph Mrema has never signed in', $crawler->html());
        self::assertStringContainsString('Naomi Kileo invited them', $crawler->html());
    }

    /**
     * THE STANDING RISK IS NOT A TASK: it carries no action, because resolving
     * it means promoting somebody and that is a decision rather than a button.
     */
    public function testTheSoleSuperAdminRowCarriesNoAction(): void
    {
        $naomi = $this->person('Naomi', 'Kileo', TeamRoleEnum::SuperAdmin);
        $this->person('Grace', 'Ndosi')->setPosition(
            $this->position('Ranger', [PermissionEnum::AreaView]),
        );
        $this->em->flush();

        $this->client->loginUser($naomi);
        $crawler = $this->client->request('GET', '/team');

        $standing = $crawler->filter('.tm-attrow.standing');
        self::assertCount(1, $standing);
        self::assertCount(0, $standing->filter('.ad'), 'A standing risk has no done, so it has no button.');
    }

    /**
     * THE POSITION CELL: the position's name, what it grants, and the
     * administrator mark.
     *
     * IT USED TO BE QUALIFIED BY A DEPARTMENT, because a position's name was
     * unique only inside one and "Analyst" on its own was ambiguous. A position
     * belongs to no department now and its name is unique across the
     * organization, so the name IS the whole identification and there is no
     * second line to qualify it with.
     */
    public function testThePositionCellNamesThePositionAndSaysWhatItGrants(): void
    {
        $naomi = $this->settled();

        $this->client->loginUser($naomi);
        $crawler = $this->client->request('GET', '/team');

        $cell = $crawler->filter('.tm-pos.qual')->first();
        self::assertSame('Senior Ranger', $cell->filter('.n')->text());
        self::assertCount(0, $cell->filter('.q'), 'a name unique across the organization needs no qualifier');

        self::assertStringContainsString('2 granted', $crawler->html());
        // Scoped to the table: the tier explainer below it also spells
        // team.manage, and rightly — that widget's whole job is to say what the
        // permission means.
        self::assertCount(
            1,
            $crawler->filter('[data-w="roster_a"] .tm-mgr'),
            'The one position carrying team.manage is marked, in the roster.',
        );
    }

    /**
     * A SUPER ADMIN'S POSITION CELL IS NOT A COUNT. The voter grants by tier
     * before it ever looks at a position, so a number there would name the
     * wrong cause.
     */
    public function testATierAboveTheMatrixReadsEverythingByTier(): void
    {
        $naomi = $this->settled();

        $this->client->loginUser($naomi);
        $crawler = $this->client->request('GET', '/team');

        self::assertStringContainsString('everything, by tier', $crawler->html());
    }

    /**
     * THE TIER PILLS ARE LINKS carrying whole-roster counts. Links because the
     * page must work with no JavaScript; whole-roster because a chip reading
     * "Admin 0" while you search for a name is a chip lying about the
     * installation.
     */
    public function testTheTierPillsAreParamLinksWithWholeRosterCounts(): void
    {
        $naomi = $this->settled();

        $this->client->loginUser($naomi);
        $crawler = $this->client->request('GET', '/team?q=grace');

        $chips = $crawler->filter('.tm-tools a.fchip');
        self::assertCount(4, $chips, 'All, then one per tier.');
        self::assertStringContainsString('tier=super_admin', $chips->eq(1)->attr('href') ?? '');
        // Two Super Admins exist; the search for "grace" must not change that.
        self::assertSame('2', $chips->eq(1)->filter('.n')->text());
    }

    /** The tool row is a GET form, so the filter state is the URL. */
    public function testTheToolRowIsAGetFormAndTheSearchNarrowsTheTable(): void
    {
        $naomi = $this->settled();

        $this->client->loginUser($naomi);
        $crawler = $this->client->request('GET', '/team?q=ndosi');

        self::assertSame('get', strtolower($crawler->filter('form.tm-tools')->attr('method') ?? ''));
        self::assertCount(1, $crawler->filter('table.tbl tbody tr'));
        self::assertStringContainsString('showing <b>1</b> of 1', $crawler->html());
    }

    /**
     * THE POSITION FILTER IS ONE FLAT LIST, each name offered once.
     *
     * It was grouped by department, and had to be while two positions could
     * both be called "Analyst" — a flat list would then have offered the same
     * word twice with no way to tell which was which. The name is unique
     * across the organization now, so the grouping has nothing left to
     * disambiguate and the optgroups are gone.
     */
    public function testThePositionFilterIsOneFlatListWithEveryNameOnce(): void
    {
        $naomi = $this->settled();
        $this->position('Analyst');
        $this->em->flush();

        $this->client->loginUser($naomi);
        $crawler = $this->client->request('GET', '/team');

        $select = $crawler->filter('select[name="position"]');
        self::assertCount(0, $select->filter('optgroup'));
        self::assertSame(
            ['Any position', 'Analyst', 'Ranger', 'Senior Ranger', '— no position —'],
            $select->filter('option')->each(static fn (\Symfony\Component\DomCrawler\Crawler $c): string => $c->text()),
        );
    }

    /**
     * THE PAGER IS DRAWN IN THE STATE IT IS ACTUALLY IN. Four people is one
     * page, so there are no arrows: a disabled ‹ › on page 1 of 1 is chrome
     * pretending there is somewhere to go.
     */
    public function testASinglePageDrawsNoArrows(): void
    {
        $naomi = $this->settled();

        $this->client->loginUser($naomi);
        $crawler = $this->client->request('GET', '/team');

        $foot = $crawler->filter('.rdf-foot');
        self::assertStringContainsString('25 per page', $foot->text());
        self::assertStringContainsString('page 1 of 1', $foot->text());
        self::assertCount(0, $foot->filter('a'));
    }

    public function testASecondPageGetsItsArrow(): void
    {
        $naomi = $this->settled();
        for ($i = 1; $i <= 30; ++$i) {
            $this->person('Person', \sprintf('%02d', $i));
        }
        $this->em->flush();

        $this->client->loginUser($naomi);
        $crawler = $this->client->request('GET', '/team');

        self::assertCount(25, $crawler->filter('table.tbl tbody tr'));
        self::assertGreaterThan(0, $crawler->filter('.rdf-page a')->count());
    }

    /**
     * A DEACTIVATED ACCOUNT STAYS IN THE LIST, marked, with the action that
     * undoes it. "This ranger left in March" and "this ranger never existed"
     * are different facts and the roster is where the difference is read.
     */
    public function testADeactivatedAccountIsStillListedAndOffersToComeBack(): void
    {
        $naomi = $this->settled();
        $this->person('Hawa', 'Rajabu')->setVerified(false)->deactivate();
        $this->em->flush();

        $this->client->loginUser($naomi);
        $crawler = $this->client->request('GET', '/team');

        self::assertStringContainsString('Hawa Rajabu', $crawler->html());
        self::assertCount(1, $crawler->filter('tr.tm-off'));
        // Two pills, not one merged state: the axes never merge.
        self::assertStringContainsString('Deactivated', $crawler->filter('tr.tm-off')->text());
        self::assertStringContainsString('Never signed in', $crawler->filter('tr.tm-off')->text());
        // And the honest invitation line for an account nobody invited.
        self::assertStringContainsString('created directly', $crawler->filter('tr.tm-off')->text());
        self::assertStringContainsString('Reactivate', $crawler->filter('tr.tm-off')->text());
    }

    /** The KPI strip leads the page — standing rule, no exceptions. */
    public function testTheCountsAreTheFirstThingOnThePage(): void
    {
        $naomi = $this->settled();

        $this->client->loginUser($naomi);
        $crawler = $this->client->request('GET', '/team');

        $first = $crawler->filter('.w-grid > div')->first();
        self::assertSame('kpis', $first->attr('data-w'));
    }

    /**
     * ONE NUMBER, TWO MECHANISMS — and the sub-line names both, because the
     * tier column can no longer answer this on its own.
     */
    /**
     * FOUR TO A ROW, AND EVERY ONE OF THEM ABOUT A PERSON.
     *
     * The people fold used to carry five, the fifth being the count of
     * POSITIONS — the next page's own headline, drawn here with its
     * placement stripped off. A figure row is four to a row (ruled), and the
     * card to give up is the one whose question another page answers better.
     */
    public function testThePeopleFoldIsFourCardsAndEveryOneIsAboutAPerson(): void
    {
        $naomi = $this->settled();

        $this->client->loginUser($naomi);
        $crawler = $this->client->request('GET', '/team');

        $cards = $crawler->filter('[data-w="kpis"] .c.kpi');
        self::assertCount(4, $cards, 'A figure row is four to a row, and never five.');
        self::assertSame(
            ['People', 'Never signed in', 'Hold nothing', 'Can administer'],
            $cards->filter('.tab')->each(static fn (\Symfony\Component\DomCrawler\Crawler $c): string => $c->text()),
        );
    }

    public function testTheAdministratorCountNamesBothMechanisms(): void
    {
        $naomi = $this->settled();

        $this->client->loginUser($naomi);
        $crawler = $this->client->request('GET', '/team');

        $kpi = $crawler->filter('[data-w="kpis"]')->text();
        self::assertStringContainsString('Can administer', $kpi);
        self::assertStringContainsString('2 by tier', $kpi);
        self::assertStringContainsString('1 by', $kpi);
    }

    /**
     * A FRESH INSTALLATION GETS NO STRIP OF ZEROS. One account is the whole
     * roster, and the page says so in a sentence with something to do next.
     */
    public function testAFreshInstallationIsASentenceRatherThanAStripOfZeros(): void
    {
        $naomi = $this->person('Naomi', 'Kileo', TeamRoleEnum::SuperAdmin);
        $this->em->flush();

        $this->client->loginUser($naomi);
        $crawler = $this->client->request('GET', '/team');

        self::assertCount(0, $crawler->filter('.dp-kstrip'));
        self::assertStringContainsString('You are the only person here', $crawler->html());
        // And no tool row: a table with one row and a filter bar filtering
        // nothing is a table pretending to be a list.
        self::assertCount(0, $crawler->filter('form.tm-tools'));
    }
}
