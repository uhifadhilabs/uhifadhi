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

namespace Uhifadhi\Bundle\ShellBundle\Tests\Integration\Area;

use Uhifadhi\Bundle\ShellBundle\Contract\AreaShellSourceInterface;
use Uhifadhi\Bundle\ShellBundle\Model\AreaTab;
use Uhifadhi\Bundle\ShellBundle\Tests\Integration\ContractTestCase;
use Uhifadhi\Bundle\ShellBundle\Tests\Integration\Fixtures\HostKernel;

/**
 * SPEC 3 — THE AREA SHELL.
 *
 * THE HONEST SPLIT, decided rather than fudged, because the brief for this
 * bundle asked for it to be decided:
 *
 *   THE SHELL OWNS THE STRIP. That a set of sibling screens is expressed as an
 *   underlined tab strip; that it sits between the page head and the page body
 *   and nowhere else; that exactly one tab is lit; that a tab the viewer may
 *   not have is ABSENT rather than disabled; that one tab is no strip at all.
 *   These are layout decisions and there is no second opinion worth having.
 *
 *   THE SHELL OWNS NOT ONE TAB. Overview, Modules, Zones and Settings are not
 *   layout — they are the host's model of what an area is, and one of them
 *   (Modules) is the contract's. A shell carrying that list would be a shell that
 *   has to be released whenever the host grows a screen, and the first
 *   deployment that wants a fifth tab would have to fork it.
 *
 * So the strip is structure and the tabs are data, arriving through
 * {@see AreaShellSourceInterface} — the same shape as the navigation contract, for the
 * same reason. Today both lists are hardcoded twice in the host, once in
 * dashboard/_area_tabs.html.twig and again in SidebarRuntime::tabs(); the two
 * copies already have to be edited together, which is the tell.
 *
 * WHY IT IS NOT AN AREABUNDLE'S. It was worth asking, since "area" sounds like
 * a domain and the platform has room for an area bundle. The answer is that the
 * strip has no behaviour to own: it is markup plus a rule about lighting, and a
 * bundle whose entire content is one Twig partial is a dependency, not a ring.
 * If an area module is ever created, it implements this source; it does not
 * take the strip.
 */
final class AreaShellTest extends ContractTestCase
{
    /**
     * @param list<AreaTab> $tabs
     */
    private function withTabs(array $tabs): void
    {
        HostKernel::$areaTabs = $tabs;
    }

    public function testTheStripRendersTheTabsItIsGivenInTheOrderItIsGivenThem(): void
    {
        $this->withTabs([
            new AreaTab(label: 'Overview', url: '/areas/x', current: true),
            new AreaTab(label: 'Modules', url: '/areas/x/modules'),
            new AreaTab(label: 'Zones', url: '/areas/x/zones'),
        ]);

        $crawler = $this->crawl('@fixtures/module_page.html.twig');

        self::assertSame(
            ['Overview', 'Modules', 'Zones'],
            $crawler->filter('div.atabs a')->each(static fn ($n): string => trim($n->text())),
        );
    }

    /**
     * NOT ONE TAB LABEL IS WRITTEN IN THIS BUNDLE. The negative half of the
     * ruling, and the one a refactor will break first — somebody will "just
     * default" the strip to the four tabs every area has. Unit/BoundaryTest
     * sweeps for module names; this sweeps for the host's screen names.
     */
    public function testTheShellNamesNoTab(): void
    {
        $templates = glob(__DIR__.'/../../../templates/*.html.twig') ?: [];
        // A sweep over nothing passes, and a test that passes in the red suite
        // asserts nothing. The frame has to exist before its absence of tab
        // labels means anything.
        self::assertNotSame([], $templates, 'There are no templates to sweep yet.');

        $offenders = [];
        foreach (['Overview', 'Modules', 'Zones', 'Settings'] as $label) {
            foreach ($templates as $template) {
                $code = (string) file_get_contents($template);
                if (str_contains($code, '>'.$label.'<') || str_contains($code, "'".$label."'")) {
                    $offenders[] = basename($template).': '.$label;
                }
            }
        }

        self::assertSame([], $offenders, 'Which tabs an area has is the host\'s model, never the shell\'s markup.');
    }

    /**
     * EXACTLY ONE TAB IS LIT, and the shell refuses rather than renders when it
     * is handed two. A tab strip's only job is to say which of these sibling
     * screens you are on.
     */
    public function testTwoCurrentTabsAreARefusal(): void
    {
        $this->withTabs([
            new AreaTab(label: 'Overview', url: '/a', current: true),
            new AreaTab(label: 'Modules', url: '/b', current: true),
        ]);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessageMatches('/exactly one/i');
        $this->render('@fixtures/module_page.html.twig');
    }

    /**
     * A TAB THE VIEWER MAY NOT HAVE IS ABSENT, NOT DISABLED — the host's
     * existing rule, promoted from a habit to a contract. A greyed-out
     * "Settings" tells a ranger that a settings screen exists and they are not
     * trusted with it, which is a worse product than not mentioning it. This is
     * the opposite of the nav's inert row, and the difference is real: an inert
     * NAV row is a promise about the future; a disabled TAB is a statement about
     * the viewer.
     *
     * Gating happens in the source, so by the time the shell sees the list the
     * tab is simply not in it — and the shell must not have a "disabled" state
     * to fall back on.
     */
    public function testThereIsNoWayToRenderADisabledTab(): void
    {
        $this->withTabs([new AreaTab(label: 'Overview', url: '/a', current: true)]);

        $strip = (string) file_get_contents(__DIR__.'/../../../templates/_area_tabs.html.twig');
        self::assertStringNotContainsString('disabled', $strip);
        self::assertStringNotContainsString('off', $strip);

        // AreaTab has no url-less form: a tab is a destination or it is not a tab.
        $constructor = new \ReflectionMethod(AreaTab::class, '__construct');
        $url = array_values(array_filter(
            $constructor->getParameters(),
            static fn (\ReflectionParameter $p): bool => 'url' === $p->getName(),
        ))[0];
        self::assertFalse($url->allowsNull(), 'A tab always has somewhere to go; withhold it instead.');
    }

    /**
     * ONE TAB IS NOT A CHOICE. An area whose viewer can reach exactly one of its
     * screens gets no strip — a lone underlined word is furniture pretending to
     * be navigation.
     */
    public function testALoneTabRendersNoStrip(): void
    {
        $this->withTabs([new AreaTab(label: 'Overview', url: '/a', current: true)]);

        self::assertCount(0, $this->crawl('@fixtures/module_page.html.twig')->filter('div.atabs'));
    }

    /**
     * A MODULE PAGE KEEPS THE AREA'S TABS. This is the defect the contract
     * fixes, and it is the reason the strip is in the frame rather than in the
     * host's own templates: today an area's tabs vanish the moment you open one
     * of its modules, because a module page extends the layout directly and the
     * strip lives in a partial the host includes by hand. Under the frame the
     * strip is the default content of shell_page_tabs, so a module page that
     * fills only its body still shows you where you are.
     */
    public function testAModulePageInsideAnAreaStillShowsTheAreasTabs(): void
    {
        $this->withTabs([
            new AreaTab(label: 'Overview', url: '/areas/x'),
            new AreaTab(label: 'Modules', url: '/areas/x/modules', current: true),
        ]);

        $crawler = $this->crawl('@fixtures/body_only_page.html.twig');

        self::assertCount(1, $crawler->filter('div.atabs'));
        self::assertSame('Modules', trim($crawler->filter('div.atabs a.on')->text()));
    }

    /**
     * AND A PAGE MAY OPT OUT, explicitly, by filling the socket with nothing.
     * A page outside any area has no strip to show and must not have to invent
     * an empty tab list to say so.
     */
    public function testAPageOutsideAnAreaHasNoStripAndSaysSoBySayingNothing(): void
    {
        HostKernel::$areaTabs = [];

        self::assertCount(0, $this->crawl('@fixtures/body_only_page.html.twig')->filter('div.atabs'));
    }

    /**
     * THE STRIP AND THE SIDEBAR TREE READ THE SAME LIST. The host currently
     * keeps two copies — the partial and SidebarRuntime::tabs() — and they have
     * to be edited together, which means one day they will not be. One source,
     * two renderings; the shell asserts they cannot disagree.
     */
    public function testTheSidebarsAreaBranchAndTheStripCannotDisagree(): void
    {
        $this->withTabs([
            new AreaTab(label: 'Overview', url: '/areas/x'),
            new AreaTab(label: 'Modules', url: '/areas/x/modules', current: true),
        ]);
        HostKernel::$mirrorAreaTabsIntoNav = true;

        $crawler = $this->crawl('@fixtures/body_only_page.html.twig');

        self::assertSame(
            $crawler->filter('div.atabs a')->each(static fn ($n): string => trim($n->text())),
            $crawler->filter('nav.nav .ntt')->each(static fn ($n): string => trim($n->text())),
        );
    }

    public function testTheContractIsAnInterfaceAHostImplements(): void
    {
        self::assertTrue(interface_exists(AreaShellSourceInterface::class));
    }
}
