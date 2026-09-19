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

namespace Uhifadhi\Bundle\ShellBundle\Tests\Integration\Sheets;

use Symfony\Component\DomCrawler\Crawler;
use Uhifadhi\Bundle\ShellBundle\Tests\Integration\ContractTestCase;
use Uhifadhi\Bundle\ShellBundle\Tests\Integration\Fixtures\HostKernel;

/**
 * THE SHEETS A PAGE LINKS FOR COMPONENTS IT DOES NOT KNOW IT WILL DRAW.
 *
 * THE BUG THIS CONTRACT EXISTS FOR, stated as a test: a month drawn by
 * `atlas_calendar()` on a module's own tab rendered as a LIST, because
 * the rules for its grid were in a sheet only map pages linked. The
 * page could not link it — it has never heard of the component — and
 * the component cannot link it either, since a stylesheet link outside
 * the head is not conforming HTML. So the head asks first, and this is
 * the asking.
 */
final class StylesheetContractTest extends ContractTestCase
{
    /** A contributed sheet reaches the head of every page, unasked. */
    public function testAContributedSheetIsLinkedInTheHead(): void
    {
        HostKernel::$stylesheets = ['bundles/atlas/calendar.css'];

        self::assertContains('calendar.css', $this->linked($this->crawl('@fixtures/bare_shell_page.html.twig')));
    }

    /**
     * AND ON A PAGE WITH NO FURNITURE AT ALL, because the rung a page
     * sits on says what chrome it wears and never which components it
     * may contain.
     */
    public function testEvenTheBareDocumentLinksThem(): void
    {
        HostKernel::$stylesheets = ['bundles/atlas/calendar.css'];

        self::assertContains('calendar.css', $this->linked($this->crawl('@fixtures/bare_document_page.html.twig')));
    }

    /**
     * THE SHELL'S OWN SHEET IS FIRST, and a contributed one follows it:
     * a component's rules are written against the tokens, so a sheet
     * that landed before them would be a sheet spending names nothing
     * had defined yet.
     */
    public function testTheShellsOwnSheetComesFirst(): void
    {
        HostKernel::$stylesheets = ['bundles/atlas/calendar.css'];

        $linked = $this->linked($this->crawl('@fixtures/bare_shell_page.html.twig'));

        self::assertLessThan(
            array_search('calendar.css', $linked, true),
            array_search('shell.css', $linked, true),
        );
    }

    /** Two packages naming one sheet is one link, not two. */
    public function testASheetTwoPackagesBothNameIsLinkedOnce(): void
    {
        HostKernel::$stylesheets = ['bundles/atlas/calendar.css', 'bundles/atlas/calendar.css'];

        self::assertSame(
            1,
            \count(array_keys($this->linked($this->crawl('@fixtures/bare_shell_page.html.twig')), 'calendar.css', true)),
        );
    }

    /** And a page on an installation where nobody contributes one links only the shell's. */
    public function testAnInstallationWithNoComponentSheetsLinksOnlyTheShells(): void
    {
        self::assertSame(
            ['shell.css'],
            $this->linked($this->crawl('@fixtures/bare_shell_page.html.twig')),
        );
    }

    /**
     * The sheets the head links, each named by its FILE rather than by
     * its address: AssetMapper versions the ones it knows about, and a
     * test pinned to a digest would fail on the next edit to the sheet
     * it is not about.
     *
     * @return list<string>
     */
    private function linked(Crawler $page): array
    {
        return $page->filter('head link[rel="stylesheet"]')->each(
            static fn (Crawler $node): string => preg_replace(
                '/-[A-Za-z0-9_-]{7,}\.css$/', '.css', basename((string) $node->attr('href')),
            ) ?? '',
        );
    }
}
