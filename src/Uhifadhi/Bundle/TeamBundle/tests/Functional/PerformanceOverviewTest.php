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
use Uhifadhi\Bundle\TeamBundle\Enum\TeamRoleEnum;
use Uhifadhi\Bundle\TeamBundle\Tests\Integration\Fixtures\Area\HostArea;

/**
 * `/departments/performance` — the organisation's own page.
 *
 * IT WEARS THE AREA IDIOM, which is the whole of the ruling it ports: a
 * header with the scope and the period in it, a strip of sibling
 * screens, and a body. A reader who has learnt an area has learnt this.
 *
 * AND IT COMPUTES NOTHING. Every figure on it comes from a topic
 * through the performance seam; what is asserted here is that the page
 * ASKS for the right scope and period and draws what it is given in the
 * one grammar every matrix is drawn in.
 */
final class PerformanceOverviewTest extends WebTestCaseWithSchema
{
    private HostArea $north;

    /** The page is the area idiom: a head, a tab strip, a body. */
    public function testThePageWearsTheAreaIdiomWithItsThreeSiblingScreens(): void
    {
        $crawler = $this->page();

        self::assertResponseIsSuccessful();
        self::assertSame('Performance', $crawler->filter('h1.pg')->text());
        self::assertSame(
            ['Overview', 'Topics', 'Briefing'],
            $crawler->filter('.atabs a')->each(static fn (Crawler $c): string => $c->text()),
        );
        self::assertSame('Overview', $crawler->filter('.atabs a.on')->text());
    }

    /**
     * THE SUBLINE NAMES THE SCOPE, THE PERIOD AND WHAT IT IS COMPARED
     * WITH — the three things every figure on the page depends on, said
     * once, where the design says them.
     */
    public function testTheSublineNamesTheScopeThePeriodAndTheComparison(): void
    {
        $subline = $this->page()->filter('p.pgsub')->text();

        self::assertStringContainsString('Organisation', $subline);
        self::assertStringContainsString(new \DateTimeImmutable()->format('F Y'), $subline);
        self::assertStringContainsString(new \DateTimeImmutable('first day of last month')->format('F Y'), $subline);
    }

    /**
     * THE PERIOD IS IN THE ADDRESS, as three segments — so a reader who
     * wants this quarter does not open a menu, and the page they are
     * looking at can be sent to somebody.
     */
    public function testThePeriodIsASegmentedGroupOfAddresses(): void
    {
        $crawler = $this->page();

        self::assertSame(
            ['Month', 'Quarter', 'Year'],
            $crawler->filter('.periodpick a')->each(static fn (Crawler $c): string => $c->text()),
        );
        self::assertSame('Month', $crawler->filter('.periodpick a.on')->text());

        $quarter = $this->client->request('GET', '/departments/performance?period=quarter');

        self::assertSame('Quarter', $quarter->filter('.periodpick a.on')->text());
        self::assertStringContainsString('Quarter', $quarter->filter('p.pgsub')->text());
    }

    /** An address naming a window nobody has is the month, not a wall. */
    public function testAnAddressNamingNoKnownWindowReadsAsTheMonth(): void
    {
        $this->page();

        $crawler = $this->client->request('GET', '/departments/performance?period=fortnight');

        self::assertResponseIsSuccessful();
        self::assertSame('Month', $crawler->filter('.periodpick a.on')->text());
    }

    /** The scope is a place too: the organisation, or one area, by address. */
    public function testTheScopeIsAnAddressAndTheAreasAreItsOptions(): void
    {
        $crawler = $this->page();

        $options = $crawler->filter('.i-ddmenu .i-ddopt-l')->each(static fn (Crawler $c): string => $c->text());

        self::assertContains('Northern Reserve', $options);

        $area = $this->client->request('GET', '/departments/performance?area='.$this->north->getUuidString());

        self::assertResponseIsSuccessful();
        self::assertStringContainsString('Northern Reserve', $area->filter('p.pgsub')->text());
    }

    /** One card a topic, and the host's three are always among them. */
    public function testEveryTopicIsACardWithItsHeadlineFigure(): void
    {
        $titles = $this->page()->filter('.tpk .c.kpi > .tab')->each(static fn (Crawler $c): string => $c->text());

        self::assertContains('Staffing', $titles);
        self::assertContains('Goals', $titles);
        self::assertContains('Attention & output', $titles);
    }

    /**
     * AND THE MATRIX WHOSE COLUMNS ARE THE TOPICS — drawn by the one
     * renderer every matrix in the product is drawn by, with the legend
     * that says what a shade is not.
     */
    public function testTheBoardIsTheOneMatrixGrammar(): void
    {
        $crawler = $this->page();

        self::assertCount(1, $crawler->filter('#tp-heat.pfc'));
        self::assertSame('Departments across the topics', $crawler->filter('#tp-heat .pfc-hd .t')->text());
        self::assertGreaterThan(0, $crawler->filter('#tp-heat .legend')->count());

        $columns = $crawler->filter('#tp-heat thead th.sortable')->each(static fn (Crawler $c): string => $c->text());

        self::assertSame('Department', $columns[0]);
        self::assertContains('Staffing', array_map(static fn (string $c): string => trim(explode("\n", $c)[0]), $columns));
    }

    /** Every department is a row, in its own band, with the way into it. */
    public function testEveryDepartmentIsARowWithTheWayIntoIt(): void
    {
        $crawler = $this->page();

        $names = $crawler->filter('#tp-heat .dept b')->each(static fn (Crawler $c): string => $c->text());

        self::assertContains('Ecology', $names);
        self::assertContains('Wetland Management', $names);
        self::assertGreaterThan(0, $crawler->filter('#tp-heat .pfscope')->count());
        self::assertGreaterThan(0, $crawler->filter('#tp-heat a.open-btn')->count());
    }

    /** Performance is in the sidebar, under Observatory, with its screens. */
    public function testTheSidebarCarriesPerformanceAndItsThreeScreens(): void
    {
        $crawler = $this->page();

        $row = $crawler->filter('.nav .nav-item')
            ->reduce(static fn (Crawler $c): bool => str_contains($c->text(), 'Performance'));

        self::assertCount(1, $row);
    }

    /** The page is the org chart's, so it is gated exactly as the register is. */
    public function testSomebodyWithoutTeamManageDoesNotGetThePage(): void
    {
        $this->seed();
        $ranger = $this->person('Juma', 'Ranger', TeamRoleEnum::Staff);
        $this->em->flush();
        $this->client->loginUser($ranger);

        $this->client->request('GET', '/departments/performance');

        self::assertResponseStatusCodeSame(403);
    }

    private function page(): Crawler
    {
        $this->seed();

        return $this->client->request('GET', '/departments/performance');
    }

    private function seed(): void
    {
        $this->north = $this->area('Northern Reserve');

        $wetland = $this->areaDepartment('Wetland Management', $this->north);
        $ecology = $this->department('Ecology');
        $protection = $this->department('Protection Service');

        $this->position('Wetland Ecologist', $wetland);
        $this->position('Analyst', $ecology);
        $this->position('Analyst', $protection);

        $this->em->flush();

        $this->administrator();
    }
}
