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

namespace Uhifadhi\Bundle\ShellBundle\Tests\Integration\Nav;

use Symfony\Component\DomCrawler\Crawler;
use Uhifadhi\Bundle\ShellBundle\Service\OrgModulesNavigation;
use Uhifadhi\Bundle\ShellBundle\Tests\Integration\ContractTestCase;
use Uhifadhi\Bundle\ShellBundle\Tests\Integration\Fixtures\FixtureOrgModule;
use Uhifadhi\Contracts\Shell\OrgPage;

/**
 * A MODULE THAT ANSWERS AT ORGANISATION LEVEL, MOUNTED BY THE SHELL.
 *
 * RULED: an area module's screens are wanted ONCE across every area — who is
 * due today anywhere, every patrol out now — and that is not a second product
 * surface, it is the area page with the area filter widened. So the module
 * contributes its page set through the seam and the shell mounts it: the
 * module writes no sidebar item, no tab strip and no scope control, and the
 * host writes no module code.
 *
 * ONE MORE PROVIDER, NOT A NEW SECTION, which is what this file is about.
 * The stand-in contributor's slug is invented on purpose — a seam that only
 * works for the modules that exist today is a hardcoded list with extra
 * steps.
 */
final class OrgPagesContractTest extends ContractTestCase
{
    /** @return list<string> */
    private function observatoryRows(Crawler $crawler): array
    {
        return $crawler
            ->filter('nav.nav .nav-hd:contains("Observatory") ~ .nav-item span')
            ->each(static fn (Crawler $n): string => trim($n->text()));
    }

    public function testAModulesPageSetBecomesARowInObservatoryWithItsScreens(): void
    {
        FixtureOrgModule::$pages = [
            new OrgPage('overview', 'Overview', 'fixture_org_overview'),
            new OrgPage('today', 'Today', 'fixture_org_today'),
        ];

        $crawler = $this->crawl('@fixtures/body_only_page.html.twig');

        self::assertContains('Sightings', $this->observatoryRows($crawler));
        self::assertSame(
            ['Overview', 'Today'],
            $crawler->filter('nav.nav .ntree .ntt')->each(static fn (Crawler $n): string => trim($n->text())),
            'the screens are the module\'s own, at the screen rung',
        );
    }

    /**
     * AND THE ROW OPENS ON THE FIRST SCREEN. A module's own name is a door,
     * not a heading: clicking it lands somewhere.
     */
    public function testTheRowOpensOnTheFirstScreen(): void
    {
        FixtureOrgModule::$pages = [
            new OrgPage('overview', 'Overview', 'fixture_org_overview'),
            new OrgPage('today', 'Today', 'fixture_org_today'),
        ];

        $crawler = $this->crawl('@fixtures/body_only_page.html.twig');

        $row = $crawler->filter('nav.nav a.nav-item')->reduce(
            static fn (Crawler $n): bool => 'Sightings' === trim($n->filter('span')->text()),
        );

        self::assertCount(1, $row);
        self::assertSame('/sightings', $row->attr('href'));
    }

    /**
     * A SCREEN WHOSE ROUTE THE APPLICATION HAS NOT MOUNTED IS SKIPPED, not
     * drawn inert: the addresses belong to the application, and a row
     * pointing at a route nobody mounted is a link to a 404 — unlike a
     * planned surface, which is a promise the product is making on purpose.
     */
    public function testAScreenWhoseRouteIsNotMountedIsLeftOut(): void
    {
        FixtureOrgModule::$pages = [
            new OrgPage('overview', 'Overview', 'fixture_org_overview'),
            new OrgPage('live', 'Live', 'fixture_org_live_not_mounted'),
        ];

        $crawler = $this->crawl('@fixtures/body_only_page.html.twig');

        self::assertSame(
            ['Overview'],
            $crawler->filter('nav.nav .ntree .ntt')->each(static fn (Crawler $n): string => trim($n->text())),
        );
    }

    /** A module with nothing mounted at all contributes no row, rather than an empty one. */
    public function testAModuleWithNothingMountedContributesNoRow(): void
    {
        FixtureOrgModule::$pages = [new OrgPage('live', 'Live', 'fixture_org_live_not_mounted')];

        $crawler = $this->crawl('@fixtures/body_only_page.html.twig');

        self::assertNotContains('Sightings', $this->observatoryRows($crawler));
    }

    /** And a module that contributes no page set is an area module with nothing missing. */
    public function testAModuleThatContributesNoPagesIsNotInTheSidebar(): void
    {
        FixtureOrgModule::$pages = [];

        $crawler = $this->crawl('@fixtures/body_only_page.html.twig');

        self::assertCount(0, $crawler->filter('nav.nav .nav-item'));
    }

    /** The section is the one the areas and performance file under, after them. */
    public function testTheRowsFileUnderObservatoryAfterTheSectionsBeforeThem(): void
    {
        self::assertSame('Observatory', OrgModulesNavigation::SECTION);
        self::assertGreaterThan(15, OrgModulesNavigation::POSITION, 'after Performance');
    }
}
