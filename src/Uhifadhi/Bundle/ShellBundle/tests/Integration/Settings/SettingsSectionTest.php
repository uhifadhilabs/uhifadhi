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

namespace Uhifadhi\Bundle\ShellBundle\Tests\Integration\Settings;

use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Uhifadhi\Bundle\ShellBundle\Tests\Integration\Fixtures\SettingsHostKernel;
use Uhifadhi\Bundle\ShellBundle\Tests\Integration\ShellKernelTestCase;
use Uhifadhi\Contracts\Settings\SettingsTab;
use Uhifadhi\Contracts\Shell\NavGroup;

/**
 * THE SETTINGS SECTION, SERVED THE WAY AN INSTALLATION SERVES IT — over HTTP,
 * through the shell's own controller, on a kernel whose only difference from
 * a bare one is the import line in `config/routes/shell.yaml`.
 *
 * ON AN INSTALLATION WITH NOTHING IN IT, which is the harder case and the one
 * worth testing: no areas bundle, no team, no registry, no database. Every
 * fact the section draws arrives through a contract, so a kernel with no
 * contributor at all proves the screens state their own absence rather than
 * failing — which is the state a real installation is in on its first minute
 * and again whenever somebody removes a package.
 */
final class SettingsSectionTest extends ShellKernelTestCase
{
    protected static function getKernelClass(): string
    {
        return SettingsHostKernel::class;
    }

    /**
     * @return \Generator<string, array{SettingsTab}>
     */
    public static function screens(): \Generator
    {
        foreach (SettingsTab::cases() as $tab) {
            yield $tab->value => [$tab];
        }
    }

    /**
     * THE SECTION WEARS THE AREA IDIOM: the same head on every screen — the
     * section's name — a strip of the screens it owns, and a subline per
     * screen saying what THIS one answers. A reader who has learnt an area
     * has learnt this.
     */
    #[DataProvider('screens')]
    public function testEveryScreenWearsTheSectionsOwnHead(SettingsTab $tab): void
    {
        $crawler = $this->get($this->address($tab));

        self::assertCount(1, $crawler->filter('aside.side'), 'The section renders in the frame like every other page.');
        self::assertCount(1, $crawler->filter('main.main div.page'), 'The frame owns .page.');
        self::assertSame('Settings', trim($crawler->filter('div.pghead h1.pg')->text()), 'The head is the section, on every screen.');
        self::assertSame($tab->subtitle(), trim($crawler->filter('div.pghead p.pgsub')->text()));
    }

    /**
     * THE ONE CONTROL IN THE ACTION ROW IS A STATEMENT, NOT A SWITCH.
     * Everything here is true of the whole installation, so a scope dropdown
     * would have one setting for ever; the chip says which scope the reader
     * is in, and the area's own settings and a module's are elsewhere.
     */
    #[DataProvider('screens')]
    public function testEveryScreenStatesItsScopeAndOffersNoControlOverIt(SettingsTab $tab): void
    {
        $crawler = $this->get($this->address($tab));

        self::assertSame('organisation scope', trim($crawler->filter('div.pgact span.chip.acc')->text()));
        self::assertCount(0, $crawler->filter('div.pgact .ov-ctl'), 'The dashboard is the organisation; there is nothing to widen.');
    }

    /**
     * THE STRIP IS EVERY SCREEN OF THE SECTION, in the declared order, with
     * exactly one lit. It and the sidebar's subtree are built from the SAME
     * declaration, which is what stops a tab and a sidebar row disagreeing
     * about which screens the section has.
     */
    #[DataProvider('screens')]
    public function testTheStripCarriesEveryScreenAndLightsExactlyTheOneBeingRead(SettingsTab $tab): void
    {
        $tabs = $this->get($this->address($tab))->filter('div.atabs a');

        self::assertSame(
            array_map(static fn (SettingsTab $one): string => $one->label(), SettingsTab::cases()),
            $tabs->each(static fn (Crawler $one): string => trim($one->text())),
        );
        self::assertCount(1, $tabs->filter('.on'));
        self::assertSame($tab->label(), trim($tabs->filter('.on')->text()));
    }

    /** The first screen is the bare address, and every other hangs one segment below it. */
    public function testTheFirstScreenIsTheBareAddress(): void
    {
        $crawler = $this->get('/settings');

        self::assertSame(SettingsTab::first()->label(), trim($crawler->filter('div.atabs a.on')->text()));
    }

    /**
     * A SEGMENT THAT NAMES NO SCREEN IS A 404, not a quiet fall back to the
     * first one: a mistyped address that drew something is how somebody
     * bookmarks a page they did not mean.
     */
    public function testASegmentThatNamesNoScreenIsNotAPage(): void
    {
        $response = self::bootKernel()->handle(Request::create('/settings/nothing-like-this'), catch: true);

        self::assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
    }

    /**
     * THE SIDEBAR'S LAST GROUP HOLDS ONE ROW, AND ITS CHILDREN ARE THE
     * SCREENS (ruled 2026-09-20). Settings was a foot item; the owner ruled
     * it a category of its own, last, holding a single row whose children are
     * this page's tabs — the shape an area and the files section already
     * wear, which is why the foot is gone.
     */
    public function testTheSidebarsLastGroupIsOneSettingsRowWithItsScreensUnderIt(): void
    {
        $crawler = $this->get('/settings');

        $headings = $crawler->filter('nav.nav .nav-hd')->each(static fn (Crawler $one): string => trim($one->text()));
        self::assertNotSame([], $headings, 'The group is drawn.');
        self::assertSame(NavGroup::SETTINGS, end($headings), 'And it is the last of them.');

        $rows = $crawler->filter('nav.nav > a.nav-item, nav.nav > span.nav-item');
        self::assertSame(['Settings'], $rows->each(static fn (Crawler $one): string => trim($one->filter('span')->first()->text())));

        $children = $crawler->filter('nav.nav .ntree a')->each(static fn (Crawler $one): string => trim($one->text()));
        self::assertSame(
            array_map(static fn (SettingsTab $one): string => $one->label(), SettingsTab::cases()),
            $children,
        );
    }

    /**
     * EXACTLY HERE, NOT UNDER HERE. The first screen's address is the PREFIX
     * of every other screen's, so the prefix comparison the other navigation
     * sources use would light it on all four and the sidebar would say the
     * viewer was in two places at once.
     */
    #[DataProvider('screens')]
    public function testTheSidebarLightsOneScreenAndNotThePrefixOfTheRest(SettingsTab $tab): void
    {
        $lit = $this->get($this->address($tab))->filter('nav.nav .ntree a.on');

        self::assertCount(1, $lit, 'One ground per tree.');
        self::assertSame($tab->label(), trim($lit->text()));
    }

    /**
     * THE INSTALLATION SCREEN IS WHAT THE FRONT DOOR USED TO BE: what is
     * installed, at what version, which areas run it, and its health. All
     * four sections are drawn even where nothing can answer one of them,
     * because a missing heading reads as a broken page and a heading over an
     * honest absence reads as an installation with nothing in it — which is
     * what this one is.
     */
    public function testTheInstallationScreenDrawsWhatIsInstalledWhereItRunsAndItsHealth(): void
    {
        $crawler = $this->get('/settings/installation');

        self::assertSame(
            ['What is installed', 'What runs where', 'Health'],
            $crawler->filter('div.pgbody h2.zone')->each(static fn (Crawler $one): string => trim($one->text())),
        );

        self::assertCount(4, $crawler->filter('div.pgbody .kstrip .kpi'), 'A figure row is four to a row.');
        self::assertGreaterThan(1, $crawler->filter('div.pgbody table.tbl')->first()->filter('tr')->count(), 'Every installed package has a row.');
    }

    /**
     * AND IT NAMES THE PACKAGES THIS INSTALLATION ACTUALLY HAS, read from
     * composer at render time. A screen reporting on an installation that
     * reported a list somebody typed would be wrong the first time anybody
     * ran a `composer require`.
     */
    public function testTheInstallationScreenNamesTheCoreItIsRunningIn(): void
    {
        $crawler = $this->get('/settings/installation');

        self::assertStringContainsString('uhifadhi/uhifadhi', $crawler->filter('div.pgbody table.tbl')->first()->text());
    }

    /**
     * WITH NO AREAS BUNDLE THERE IS NO MATRIX, and both screens that draw one
     * say so rather than rendering an empty table with headers over nothing.
     */
    public function testWithNobodyAnsweringWhatRunsWhereBothScreensSaySo(): void
    {
        foreach (['/settings/installation', '/settings/modules'] as $address) {
            $body = $this->get($address)->filter('div.pgbody')->text();

            self::assertStringContainsString('No area is registered here yet', $body, $address.' should state the absence.');
        }
    }

    /**
     * A FRESH INSTALLATION HAS BEEN GIVEN NO NAME OF ITS OWN, and the screen
     * draws the wordmark it was shipped with with every other field reading
     * "not set" — the page telling somebody exactly what there is to do.
     */
    public function testTheOrganisationScreenDrawsTheFallbackAndNamesWhatIsNotSet(): void
    {
        $rows = $this->get('/settings/organisation')->filter('div.pgbody table.tbl tr');

        self::assertCount(5, $rows, 'Name, short name, logo, time zone, country.');
        self::assertStringContainsString('Uhifadhi', trim($rows->eq(0)->text()));
        self::assertSame(4, substr_count($this->get('/settings/organisation')->filter('div.pgbody')->html(), 'not set'));
    }

    /**
     * EVERY SCREEN THAT IS DRAWN TO ITS SCOPE RATHER THAN TO ITS FULL DEPTH
     * SAYS SO, once, at the bottom. A reader who cannot tell whether a
     * control is missing or the feature is has been left to guess.
     */
    #[DataProvider('screens')]
    public function testEveryScreenExplainsItselfOnceAndAtTheBottom(SettingsTab $tab): void
    {
        $crawler = $this->get($this->address($tab));

        self::assertCount(1, $crawler->filter('div.pgbody .pghint'), 'One hint per page.');
    }

    private function address(SettingsTab $tab): string
    {
        return null === $tab->segment() ? '/settings' : '/settings/'.$tab->segment();
    }

    private function get(string $path): Crawler
    {
        $kernel = self::bootKernel();
        $response = $kernel->handle(Request::create($path), catch: false);

        self::assertSame(Response::HTTP_OK, $response->getStatusCode(), \sprintf('GET %s did not serve.', $path));

        return new Crawler((string) $response->getContent());
    }
}
