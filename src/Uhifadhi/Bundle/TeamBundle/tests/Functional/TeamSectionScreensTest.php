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
use Uhifadhi\Bundle\TeamBundle\Enum\TeamRoleEnum;
use Uhifadhi\Bundle\TeamBundle\Repository\PositionTitleRepository;
use Uhifadhi\Bundle\TeamBundle\Service\PerformanceHistory;
use Uhifadhi\Bundle\TeamBundle\Service\TeamFigures;
use Uhifadhi\Bundle\TeamBundle\Tests\Integration\Fixtures\FakeStationDirectory;

/**
 * THE TEAM SECTION'S OWN SCREENS — the overview it opens on, and the two
 * configure screens behind its one action.
 *
 * THE OVERVIEW OWNS NO FIGURE. Every one of them belongs to the register, to
 * Positions or to the station in the area, which is what makes it safe to open
 * first — and is why the assertions below are about what it READS, never about
 * what it changes.
 */
final class TeamSectionScreensTest extends WebTestCaseWithSchema
{
    protected function setUp(): void
    {
        parent::setUp();
        FakeStationDirectory::clear();
    }

    // ---- the overview ----------------------------------------------------

    /**
     * FOUR KPI CARDS, FOUR OR NONE — the area overview's own row, and four to
     * a row is the ruled shape of every figure row in the product.
     *
     * ROLES IS NOT ONE OF THEM: the tiers are three and never move, so the
     * card's figure is a constant of the product. It keeps its place in the
     * identity band, where a fact that does not move belongs.
     */
    public function testTheOverviewOpensWithTheFourKpiCards(): void
    {
        $this->installation();

        $cards = $this->visit('/team/overview')->filter('.kstrip .kpi');

        self::assertCount(4, $cards, 'A figure row is four to a row, and never five.');
        self::assertSame(
            ['People', 'Positions', 'Seats filled', 'Assignments'],
            $cards->filter('.tab')->each(static fn (Crawler $c): string => $c->text()),
        );
    }

    /**
     * A PERIOD NOBODY WROTE HAS NO PILL. An installation whose snapshot has
     * never run has no previous figure, and a delta reading zero would say
     * the figure held steady — a claim it cannot make.
     */
    public function testNoKpiClaimsAMovementTheInstallationNeverWroteDown(): void
    {
        $this->installation();

        self::assertCount(0, $this->visit('/team/overview')->filter('.kstrip .delta'));
    }

    /**
     * AND A PERIOD THAT WAS WRITTEN IS COMPARED AGAINST — read from the
     * history, never recomputed, because a closed period cannot be worked out
     * again from tables that hold what is true now.
     *
     * THE MOVEMENT IS ABSOLUTE: two more people is "+2", not a percentage.
     */
    public function testAKpiComparesAgainstTheLastClosedPeriodTheSnapshotWroteDown(): void
    {
        $this->installation();

        $history = $this->history();
        $closed = PerformanceHistory::monthKey(new \DateTimeImmutable('first day of last month'));
        $history->recordForInstallation($closed, TeamFigures::PEOPLE, 2.0);
        // A figure nobody wrote stays without a pill even in a period that
        // has some: the hole is per figure, not per period.
        $history->recordForInstallation($closed, TeamFigures::POSTINGS, 0.0);

        $cards = $this->visit('/team/overview')->filter('.kstrip .c.kpi');

        self::assertStringContainsString('+2', $cards->eq(0)->filter('.delta')->text());
        self::assertSame('delta good', $cards->eq(0)->filter('.delta')->attr('class'));
        self::assertCount(0, $cards->eq(1)->filter('.delta'), 'Positions was never written down');
        self::assertCount(1, $cards->eq(3)->filter('.delta'), 'Postings was written, and has not moved');
    }

    private function history(): PerformanceHistory
    {
        /** @var PerformanceHistory $history */
        $history = static::getContainer()->get(PerformanceHistory::class);

        return $history;
    }

    /** The identity band states the five facts, in the ruled order. */
    public function testTheOverviewBandStatesTheFiveFacts(): void
    {
        $this->installation();

        self::assertSame(
            ['People', 'Positions', 'Held', 'Assignments', 'Roles'],
            $this->visit('/team/overview')->filter('.factband .f .k')->each(static fn (Crawler $c): string => $c->text()),
        );
    }

    /** A person with no position is a row on the bars, not a rounding. */
    public function testPeopleWithNoDepartmentAreTheirOwnRowOnTheBars(): void
    {
        $this->installation();

        $labels = $this->visit('/team/overview')->filter('.c')->eq(4)->filter('.sxbar .l')
            ->each(static fn (Crawler $c): string => $c->text());

        self::assertContains('No department', $labels);
    }

    /** The attention cards name the people they are about, and link to them. */
    public function testTheAttentionCardsNameThePeopleAndLinkToThem(): void
    {
        $this->installation();
        $page = $this->visit('/team/overview');

        self::assertStringContainsString('Frank Massawe', $page->text());
        self::assertStringContainsString('Ibrahim Mrema', $page->text());
    }

    /** THE OVERVIEW WRITES NOTHING: it carries no control that changes anybody. */
    public function testTheOverviewCarriesNoForm(): void
    {
        $this->installation();

        self::assertCount(0, $this->visit('/team/overview')->filter('form[method="post"]'));
    }

    /** Four doors at the foot, and each one opens. */
    public function testTheDoorsAtTheFootAllOpen(): void
    {
        $this->installation();
        $doors = $this->visit('/team/overview')->filter('.sxdoors .sxdoor');

        self::assertCount(4, $doors);
        foreach ($doors->each(static fn (Crawler $c): ?string => $c->attr('href')) as $href) {
            $this->client->request('GET', (string) $href);
            self::assertResponseIsSuccessful();
        }
    }

    // ---- team settings ---------------------------------------------------

    /**
     * A SCREEN DOES NOT NAME AN ACTION THAT DOES NOT EXIST. Accounts are
     * deactivated, kept and listed; naming a delete or a recycle bin would
     * teach a reader to look for one.
     */
    public function testTheSettingsScreenNamesNoActionTheProductDoesNotHave(): void
    {
        $this->installation();
        $text = $this->visit('/team/configure')->filter('.pgbody')->text();

        self::assertStringNotContainsString('Recycle bin', $text);
        self::assertStringNotContainsString('Deleting an account', $text);
        self::assertStringContainsString('Deactivated accounts are refused at sign-in, and stay listed.', $text);
    }

    /** The two figures on it are the answer to an access question. */
    public function testTheAccessCardSaysHowManyPeopleTheRuleNames(): void
    {
        $this->installation();
        $text = $this->visit('/team/configure')->filter('.pgbody')->text();

        self::assertStringContainsString('2 of 4 people', $text);
        self::assertStringContainsString('by tier, 2 people', $text);
    }

    /** SETTINGS IS READ-ONLY: it states the rules and changes none of them. */
    public function testTheSettingsScreenWritesNothing(): void
    {
        $this->installation();

        self::assertCount(0, $this->visit('/team/configure')->filter('form[method="post"]'));
    }

    // ---- positions vocabulary -------------------------------------------

    /**
     * THE WORDS THIS INSTALLATION WRITES ITS POSITIONS WITH, AS ONE FLAT LIST.
     *
     * The screen used to group the names by department and footnote the words
     * that appeared in more than one — a position's name was unique only inside
     * its department, so "Analyst" twice was two jobs sharing a word. A position
     * belongs to no department now and its name is unique across the
     * organization, so there is one list and each name is on it once.
     */
    public function testTheVocabularyScreenListsEveryPositionNameOnce(): void
    {
        $this->installation();
        $rows = $this->visit('/team/configure/positions')->filter('.c')->last()->filter('tbody tr');

        self::assertSame(
            ['Analyst', 'Warden'],
            $rows->each(static fn (Crawler $c): string => $c->filter('td')->eq(0)->text()),
        );
    }

    /** A title is added from the create card at the top of its own screen. */
    public function testATitleIsAddedFromTheCreateCard(): void
    {
        $this->installation();
        $crawler = $this->visit('/team/configure/positions');

        $this->client->submit($crawler->filter('#add-title form')->form([
            'name' => 'Armoury Officer',
            'leads' => '1',
        ]));
        self::assertResponseRedirects('/team/configure/positions');

        $title = $this->titles()->findOneByName('Armoury Officer');
        self::assertNotNull($title);
        self::assertTrue($title->leadsStation());
    }

    /** One word, once: a second title by the same name is refused and says so. */
    public function testASecondTitleByTheSameNameIsRefused(): void
    {
        $this->installation();
        $this->submitTitle('Armoury Officer');
        $this->submitTitle('Armoury Officer');

        $crawler = $this->client->followRedirect();
        self::assertStringContainsString('There is already a position title called "Armoury Officer".', $crawler->text());
        self::assertCount(1, $this->titles()->findAllOrdered());
    }

    /** A title with no name is not a title, and the screen says so. */
    public function testATitleNeedsAName(): void
    {
        $this->installation();
        $this->submitTitle('   ');

        self::assertStringContainsString('A title needs a name.', $this->client->followRedirect()->text());
        self::assertSame([], $this->titles()->findAllOrdered());
    }

    /** And it is renamed in place, from its own row. */
    public function testATitleIsRenamedFromItsOwnRow(): void
    {
        $this->installation();
        $this->submitTitle('Armoury Officer');
        $this->client->followRedirect();

        $crawler = $this->visit('/team/configure/positions');
        $this->client->submit($crawler->filter('.c form.staddrow')->first()->form([
            'name' => 'Armourer',
            'leads' => '0',
        ]));

        self::assertNotNull($this->titles()->findOneByName('Armourer'));
        self::assertNull($this->titles()->findOneByName('Armoury Officer'));
    }

    private function submitTitle(string $name): void
    {
        $crawler = $this->visit('/team/configure/positions');
        $this->client->submit($crawler->filter('#add-title form')->form(['name' => $name]));
    }

    private function titles(): PositionTitleRepository
    {
        /** @var PositionTitleRepository $repository */
        $repository = static::getContainer()->get(PositionTitleRepository::class);

        return $repository;
    }

    /**
     * Four people: a Super Admin, an Admin, a Staff member whose position
     * carries the grant, and one who holds nothing and has never signed in.
     */
    private function installation(): void
    {
        $this->department('Administration');
        $this->department('Ecology');
        $this->administratorPosition('Warden');
        $this->position('Analyst');

        // Two above the matrix by tier, and two Staff holding nothing: the
        // model's zero, and an account that has never signed in.
        $this->person('Salum', 'Mwaipopo', TeamRoleEnum::Admin);
        $this->person('Frank', 'Massawe');
        $this->person('Ibrahim', 'Mrema')->setVerified(false);
        $this->administrator();
    }

    private function visit(string $path): Crawler
    {
        $crawler = $this->client->request('GET', $path);
        self::assertResponseIsSuccessful();

        return $crawler;
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
