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
use Uhifadhi\Bundle\TeamBundle\Enum\PermissionEnum;
use Uhifadhi\Bundle\TeamBundle\Enum\TeamRoleEnum;
use Uhifadhi\Bundle\TeamBundle\Repository\PositionTitleRepository;
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

    /** FIVE KPI CARDS, FIVE OR NONE — the area overview's own row. */
    public function testTheOverviewOpensWithTheFiveKpiCards(): void
    {
        $this->installation();

        self::assertSame(
            ['People', 'Positions', 'Seats filled', 'Postings', 'Roles'],
            $this->visit('/team/overview')->filter('.kstrip .kpi .tab')->each(static fn (Crawler $c): string => $c->text()),
        );
    }

    /**
     * NO MOVEMENT IS CLAIMED. The drawn row carries a delta pill and this
     * installation records no previous figure for any of these; a pill reading
     * zero would be a claim it cannot make.
     */
    public function testNoKpiClaimsAMovementTheInstallationCannotMeasure(): void
    {
        $this->installation();

        self::assertCount(0, $this->visit('/team/overview')->filter('.kstrip .delta'));
    }

    /** The identity band states the five facts, in the ruled order. */
    public function testTheOverviewBandStatesTheFiveFacts(): void
    {
        $this->installation();

        self::assertSame(
            ['People', 'Positions', 'Held', 'Postings', 'Roles'],
            $this->visit('/team/overview')->filter('.factband .f .k')->each(static fn (Crawler $c): string => $c->text()),
        );
    }

    /** A person with no position is a row on the bars, not a rounding. */
    public function testPeopleWithNoDepartmentAreTheirOwnRowOnTheBars(): void
    {
        $this->installation();

        $labels = $this->visit('/team/overview')->filter('.c')->eq(5)->filter('.sxbar .l')
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

    /** The words each department already writes its positions with. */
    public function testTheVocabularyScreenListsTheTitlesInUsePerDepartment(): void
    {
        $this->installation();
        $rows = $this->visit('/team/configure/positions')->filter('.c')->last()->filter('tbody tr');

        self::assertSame(
            ['Administration', 'Ecology'],
            $rows->each(static fn (Crawler $c): string => $c->filter('td')->eq(0)->text()),
        );
        self::assertStringContainsString('Analyst', $rows->eq(1)->text());
    }

    /**
     * THE SAME WORD TWICE IS LEGAL — two jobs that share a name — and the
     * screen says which words those are rather than merging them.
     */
    public function testAWordInTwoDepartmentsIsNamedAndNeverMerged(): void
    {
        $this->installation();

        self::assertStringContainsString(
            'Analyst',
            $this->visit('/team/configure/positions')->filter('.c')->last()->filter('.sxfoot')->text(),
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
        $administration = $this->department('Administration');
        $ecology = $this->department('Ecology');
        $this->position('Warden', $administration, [PermissionEnum::TeamManage->value]);
        $this->position('Analyst', $administration);
        $this->position('Analyst', $ecology);

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
}
