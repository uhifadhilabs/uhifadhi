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

namespace Uhifadhi\Bundle\ShellBundle\Tests\Integration\Welcome;

use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;
use Uhifadhi\Bundle\ShellBundle\Tests\Integration\Fixtures\ImportingHostKernel;
use Uhifadhi\Bundle\ShellBundle\Tests\Integration\ShellKernelTestCase;

/**
 * THE FIRST PAGE A NEW INSTALLATION HAS, served the way an installation
 * actually serves it: over HTTP, through the shell's own controller, on a
 * kernel whose only difference from a bare one is the import line in
 * `config/routes/shell.yaml`.
 *
 * A fresh installation is the registry and the shell and nothing else, so before
 * this page existed it answered `/` with Symfony's welcome-404 — a correct
 * installation looking like a broken one, on its first minute. The shell ships
 * the page, the controller behind it and the route resource that addresses it;
 * the application decides, in one line it owns, whether any of that is reachable
 * (see Integration/Routing/RouteResourceTest).
 *
 * It is written to be deleted the day the installation grows a real home screen,
 * and it renders with NOTHING under it — no registry, no nav sources, no areas, no
 * user, no database — because that is exactly the installation it is for.
 */
final class WelcomePageTest extends ShellKernelTestCase
{
    protected static function getKernelClass(): string
    {
        return ImportingHostKernel::class;
    }

    /**
     * It is a page in the frame, not a loose fragment: sidebar, main column,
     * the one `.page` the frame owns. If the welcome screen typed its own
     * furniture it would be the first template in this bundle to do so.
     */
    public function testTheWelcomePageRendersInsideThePageFrame(): void
    {
        $crawler = $this->get('/');

        self::assertCount(1, $crawler->filter('aside.side'), 'The shell comes with the frame.');
        self::assertCount(1, $crawler->filter('main.main'));
        self::assertCount(1, $crawler->filter('main.main div.page'), 'The frame owns .page.');
        self::assertCount(1, $crawler->filter('div.pghead h1.pg'), 'A welcome page has a title like every other page.');
    }

    /**
     * IT LISTS WHAT IS ACTUALLY INSTALLED, and it asks Composer rather than
     * remembering. Naming packages in prose would be true of one installation
     * and wrong the moment anybody ran a `composer require`, which is the one
     * instruction the page itself gives — a first-day operator would then be
     * told, on the platform's own welcome screen, that the module they had just
     * installed was not there.
     *
     * INSTALLED MEANS A DIRECTORY, not a name Composer can resolve. The core
     * `replace`s the packages it can be split into, so those names all resolve
     * to it; a list built from the resolvable names would print a row for each,
     * at the core's version, none of them anything an operator has. The raw
     * data separates them: a real install carries an `install_path`.
     *
     * Naming a package is still not naming a module — nothing here is
     * recognised, compared or switched on; the shell prints whatever the vendor
     * directory reports.
     *
     * @see https://getcomposer.org/doc/07-runtime.md#installed-versions
     */
    public function testItListsEveryInstalledUhifadhiPackage(): void
    {
        $rows = $this->get('/')->filter('div.pgbody .wpkgs .wpkg-row');

        $installed = [];
        $replaced = [];
        foreach (\Composer\InstalledVersions::getAllRawData() as $set) {
            foreach ($set['versions'] as $package => $entry) {
                if (!str_starts_with($package, 'uhifadhi/')) {
                    continue;
                }
                if (\is_string($entry['install_path'] ?? null)) {
                    $installed[$package] = true;
                } else {
                    $replaced[$package] = true;
                }
            }
        }

        self::assertNotSame([], $installed, 'The suite runs inside an installation of this very package.');
        self::assertNotSame([], $replaced, 'and that installation replaces the names it can be split into');
        self::assertCount(\count($installed), $rows, 'The list is what is on disk, not every name Composer can resolve.');

        $text = implode("\n", $rows->each(static fn ($row): string => $row->text()));
        foreach (array_keys($installed) as $package) {
            self::assertStringContainsString($package, $text);
        }

        // And not one row for a name that is only replaced.
        foreach (array_keys($replaced) as $package) {
            self::assertStringNotContainsString($package, $text);
        }
    }

    /**
     * THE LIST REACHES THE TEMPLATE AS A PLAIN VARIABLE, from the controller.
     *
     * A `shell_packages()` Twig function would put a global on every render in
     * the platform to serve one page in the bundle that declared it. The
     * controller earns its keep instead: the page has real logic, the logic has
     * one caller, and a template variable is where a page's own data belongs.
     * No such function is declared, and that absence is asserted rather than
     * assumed — a stray global is invisible until somebody uses it.
     */
    public function testTheControllerSuppliesTheListRatherThanAGlobalTwigFunction(): void
    {
        self::bootKernel();
        $twig = self::getContainer()->get('twig');
        self::assertInstanceOf(Environment::class, $twig);

        self::assertNull(
            $twig->getFunction('shell_packages'),
            'The welcome page reads its own data through its own controller; the shell adds no global for it.',
        );
    }

    /**
     * A NAME WITHOUT A VERSION IS HALF AN ANSWER — "which shell am I running"
     * is the question this page is looked at to answer on the day something is
     * wrong, and `composer show` is the second place somebody looks, not the
     * first.
     */
    public function testEveryPackageIsShownWithItsVersion(): void
    {
        $rows = $this->get('/')->filter('div.pgbody .wpkgs .wpkg-row');

        foreach ($rows as $row) {
            $name = (new Crawler($row))->filter('.wpkg')->text();
            $version = (new Crawler($row))->filter('.chip')->text();

            self::assertStringStartsWith('uhifadhi/', trim($name));
            self::assertNotSame('', trim($version), \sprintf('%s is listed with no version.', $name));
        }
    }

    /**
     * THE CORE'S ROW OPENS ONTO ITS PARTS. One row reading `uhifadhi/uhifadhi`
     * is honest about what was installed and silent about what it contains, so
     * the parts hang beneath it — each named and described by its own manifest.
     */
    public function testTheCoreRowCarriesTheCoresOwnPartsBeneathIt(): void
    {
        $names = $this->corePartRows()->each(static fn (Crawler $row): string => trim($row->filter('.wpkg-sub-name')->text()));

        self::assertContains('uhifadhi/contracts', $names);
        self::assertContains('uhifadhi/shell-bundle', $names);
    }

    /**
     * INSTALLED MEANS REGISTERED. Every part of the core is on disk in this very
     * repository, and this kernel boots one of them; a page that listed the rest
     * would tell an operator that screens exist where none do.
     */
    public function testAPartOfTheCoreThisKernelDoesNotBootIsNotListed(): void
    {
        $names = $this->corePartRows()->each(static fn (Crawler $row): string => trim($row->filter('.wpkg-sub-name')->text()));

        self::assertNotContains('uhifadhi/area-bundle', $names);
        self::assertNotContains('uhifadhi/team-bundle', $names);
    }

    /**
     * NO VERSION ON A PART, and the page says why once rather than per row: the
     * parts ride with the core, and a version beside each would invite somebody
     * to update one alone.
     */
    public function testTheCoresPartsCarryNoVersionOfTheirOwn(): void
    {
        foreach ($this->corePartRows() as $row) {
            self::assertCount(0, new Crawler($row)->filter('.chip'));
        }

        self::assertStringContainsString('ride with the core', $this->get('/')->filter('div.pgbody .wpkg-cap')->text());
    }

    /** Each part says what it is, in the one line its own manifest carries. */
    public function testEachPartIsDescribedByItsOwnManifest(): void
    {
        foreach ($this->corePartRows() as $row) {
            self::assertNotSame('', trim(new Crawler($row)->filter('.wpkg-note')->text()));
        }
    }

    private function corePartRows(): Crawler
    {
        $rows = $this->get('/')->filter('div.pgbody .wpkgs .wpkg-tree .wpkg-sub');
        self::assertGreaterThan(0, $rows->count(), 'The core is installed here, so it has parts to show.');

        return $rows;
    }

    /**
     * THE TEACHING IS ON THE LIST, not beside it. "The registry" and "the shell"
     * are words that mean nothing on a first day, so the two packages the shell
     * can honestly speak for carry a line saying what they are — as annotations
     * on their own rows. There is no second inventory anywhere on the page: one
     * list, one source of truth, and the descriptions live on it.
     */
    public function testTheTwoPackagesTheShellCanSpeakForAreDescribedOnTheirOwnRows(): void
    {
        $described = $this->get('/')->filter('div.pgbody .wpkgs .wpkg-row:has(.wpkg-note)');
        self::assertGreaterThan(0, $described->count());

        foreach ($described as $row) {
            $name = trim((new Crawler($row))->filter('.wpkg')->text());
            self::assertContains($name, ['uhifadhi/uhifadhi', 'uhifadhi/uhifadhi'], \sprintf(
                'The shell described %s. It can speak for itself and for the registry, and for nothing else.',
                $name,
            ));
        }

        self::assertStringContainsString(
            'uhifadhi/uhifadhi',
            $described->text(),
            'This suite runs inside an installation of the shell, so the shell describes itself here.',
        );
    }

    /**
     * A PACKAGE THE SHELL CANNOT SPEAK FOR GETS NO INVENTED SENTENCE, and the
     * page says the honest generic thing once instead: that a package without
     * sidebar rows is not a broken one.
     */
    public function testItSaysOnceThatAPackageWithoutRowsIsNotBroken(): void
    {
        self::assertStringContainsString('not a broken', $this->get('/')->filter('div.pgbody')->text());
    }

    /**
     * The empty sidebar beside it is not a bug and the page says so, in the
     * sentence that also says how to fill it. A person who reads this page and
     * then runs one `composer require` has understood the platform's shape.
     */
    public function testItExplainsTheEmptySidebarAndHowToFillIt(): void
    {
        $text = $this->get('/')->filter('div.pgbody')->text();

        self::assertStringContainsString('composer require', $text);
        self::assertStringContainsString('sidebar', $text);
    }

    /**
     * AND IT SAYS WHERE ITS ADDRESS COMES FROM. The page is reachable because
     * the application imported the shell's route resource in a file the
     * application owns, and replacing the homepage means editing or deleting
     * that one line — so the page names the file rather than leaving somebody
     * to grep for it.
     */
    public function testItNamesTheFileThatPutsItAtTheRoot(): void
    {
        self::assertStringContainsString(
            'config/routes/shell.yaml',
            $this->get('/')->filter('div.pgbody')->text(),
        );
    }

    /**
     * It renders with no contracts implemented at all — no nav sources, no tabs, no
     * place — because the installation it greets has none. This is the
     * stand-alone rule, asserted on the one page that is guaranteed to be looked at first.
     */
    public function testItRendersOnAnInstallationThatHasNothingUnderItYet(): void
    {
        $crawler = $this->get('/');

        self::assertCount(0, $crawler->filter('nav.nav .nav-hd'), 'A fresh installation has no rows to show, and shows none.');
        self::assertCount(0, $crawler->filter('div.atabs'), 'It is inside no place, and says so by saying nothing.');
    }

    private function get(string $path): Crawler
    {
        $kernel = self::bootKernel();
        $response = $kernel->handle(Request::create($path), catch: false);

        self::assertSame(Response::HTTP_OK, $response->getStatusCode(), \sprintf('GET %s did not serve.', $path));

        return new Crawler((string) $response->getContent());
    }
}
