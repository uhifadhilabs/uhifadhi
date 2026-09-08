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

namespace Uhifadhi\Bundle\ShellBundle\Tests\Integration\Sockets;

use Uhifadhi\Bundle\ShellBundle\Contract\LayoutContract;
use Uhifadhi\Bundle\ShellBundle\Tests\Integration\ContractTestCase;

/**
 * SPEC 1 — THE NAMED SOCKETS.
 *
 * This is the file the whole repository exists for. Every Twig block a module
 * may fill is enumerated here, LITERALLY, in a test — not derived from the
 * templates, not read off a constant, typed out. That is deliberate and it is
 * the mechanism: a derived list agrees with whatever the templates happen to
 * say, and would have nothing to report the day somebody renames a block. This
 * list disagrees.
 *
 * So the contract is enforced twice over, from both ends:
 *
 *   - the frozen list below must equal LayoutContract::BLOCKS  (rename a block
 *     in the manifest and this fails)
 *   - every block in the manifest must actually be declared by the shipped
 *     templates  (rename it in a template and this fails)
 *   - and no template may declare a block the manifest does not name  (add one
 *     quietly and this fails)
 *
 * There is no way to move a socket without editing this file, which is the
 * point: "sloppiness must not compile". A socket is an API. The policy for
 * changing one is in docs/changing-the-contract.md, and it is short:
 * adding is a minor version and a new row here; renaming is a major version,
 * a deprecation cycle, and the old name kept as an alias block for one release.
 *
 * WHY THIS IS NOT PARANOIA. Before the shell existed, every module bundle
 * carried its own `{% block content %}<div class="page">…{% endblock %}` copied
 * from the host, and each copy also re-implemented flashes. A change to the
 * page frame reached the copies somebody remembered. The frame was a
 * convention, and a convention is a contract that nothing checks.
 */
final class BlockContractTest extends ContractTestCase
{
    /**
     * THE SOCKET LIST, VERSION 1. Grouped by the template that declares it and
     * annotated with who fills it — a module author reads this list, so it is
     * written to be read.
     *
     * @return list<string>
     */
    public static function contractV1(): array
    {
        return [
            /*
             * THE DOCUMENT — @Shell/document.html.twig
             * The four standard block names, unchanged. A module
             * that already knows Twig knows these, and renaming them to
             * shell_* would have bought consistency with confusion.
             */
            'title',        // filled by: every page. "<page> — <area> — <brand>".
            'stylesheets',  // filled by: module bases, calling parent() first.
            'javascripts',  // filled by: module bases, calling parent() LAST if
            //                 they need a classic script to run before the
            //                 importmap's deferred modules (the Leaflet rule).
            'importmap',    // filled by: nobody, normally. Here so a host can.
            'body',         // filled by: nobody. The shell owns it; a page that
            //                 fills this has left the shell.

            /*
             * THE SHELL — @Shell/shell.html.twig
             * The furniture around a page. A MODULE FILLS NONE OF THESE. They
             * are sockets for the HOST — the thing that knows who is signed in,
             * what an area is, and whether this response is an impersonation.
             */
            'shell_banner',         // host: impersonation, maintenance, outage.
            'shell_sidebar',        // host: replace the entire aside. Rarely.
            'shell_sidebar_brand',  // host: the mark and the wordmark.
            'shell_sidebar_nav',    // host: overridable, but see spec 2 — the
            //                           default renders the nav CONTRACT, and a host
            //                           that overrides this has opted out of it.
            'shell_sidebar_footer', // host: settings, version, support.
            'shell_topbar',         // host: replace the whole top bar.
            'shell_topbar_actions', // host: alerts, theme toggle, the user pill.
            'shell_main',           // host: replace everything right of the nav.
            'content',              // THE COMPATIBILITY SOCKET. See below.
            'shell_footer',         // host: the one line under every page.

            /*
             * THE PAGE FRAME — @Shell/page.html.twig
             * These are the module author's sockets, and the reason the bundle
             * exists. Filling them produces the platform's page shape — crumb,
             * page head, actions, tabs, flashes, body — without a module ever
             * typing `<div class="page">`, which is what every module bundle
             * does today by copy.
             */
            'shell_breadcrumbs',   // module: the trail. Text; the frame styles it.
            'shell_page_head',     // module: replace title+subtitle+actions wholesale.
            'shell_page_title',    // module: the h1.
            'shell_page_subtitle', // module: the sentence under it. Optional and
            //                          genuinely optional — an empty block renders
            //                          no element, not an empty one.
            'shell_page_actions',  // module: the buttons at the top right.
            'shell_page_tabs',     // module/host: the tab strip. See spec 3.
            'shell_flashes',       // module: overridable, but the default is right
            //                          and a module overriding it is a bug report.
            'shell_page',          // module: THE BODY. The one block a simple
            //                          module page fills and the only one it must.
        ];
    }

    public function testTheSocketListIsExactlyThis(): void
    {
        self::assertSame(self::contractV1(), LayoutContract::BLOCKS, <<<'WHY'
            A socket was added, removed or renamed. That is a change to a public
            API — every module bundle fills these names.

            Adding one: append it to contractV1() above, in the group it belongs
            to, with the comment saying who fills it; bump the minor version.
            Renaming one: major version, and the old name stays as an alias block
            for one release. Removing one: same, plus a note in
            docs/changing-the-contract.md.

            Editing this test to make a build pass is the failure mode it exists
            to catch.
            WHY);
    }

    public function testTheContractIsVersionedSoAHostCanCheckWhatItIsMounting(): void
    {
        // A module bundle can require a shell that has the sockets it fills.
        // Without a number, "the shell supports shell_page_tabs" is a fact
        // nobody can assert except by rendering.
        self::assertSame(1, LayoutContract::VERSION);
    }

    /**
     * EVERY SOCKET IS REAL. The manifest is a promise; this is the compile-time
     * end of it. Twig's getBlockNames() returns declared and inherited blocks
     * together, so the page frame — the deepest template — must see all of them.
     */
    public function testEverySocketInTheManifestIsDeclaredByTheShippedFrame(): void
    {
        $declared = $this->blocksOf(LayoutContract::PAGE);

        foreach (LayoutContract::BLOCKS as $block) {
            self::assertContains($block, $declared, \sprintf(
                'The manifest promises the "%s" socket and no shipped template declares it.',
                $block,
            ));
        }
    }

    /**
     * AND NOTHING ELSE IS. The other direction, and the one that matters more:
     * an undocumented block is a socket somebody will fill, and then it is a
     * socket whether it was meant to be one or not. The frame may have private
     * helper templates; it may not have private blocks.
     */
    public function testTheFrameDeclaresNoSocketTheManifestDoesNotName(): void
    {
        $undocumented = array_values(array_diff($this->blocksOf(LayoutContract::PAGE), LayoutContract::BLOCKS));

        self::assertSame([], $undocumented, 'A block a module can fill is a socket. Name it in the manifest or do not declare it.');
    }

    /**
     * The three frames are addressed by constant, not by string, because their
     * paths appear in every module bundle and a path typed twice is
     * a path that eventually differs.
     */
    public function testTheFramesAreAddressableByConstant(): void
    {
        self::assertSame('@Shell/document.html.twig', LayoutContract::DOCUMENT);
        self::assertSame('@Shell/shell.html.twig', LayoutContract::SHELL);
        self::assertSame('@Shell/page.html.twig', LayoutContract::PAGE);

        foreach ([LayoutContract::DOCUMENT, LayoutContract::SHELL, LayoutContract::PAGE] as $frame) {
            self::assertTrue($this->twig()->getLoader()->exists($frame));
        }
    }

    /**
     * THE THREE FRAMES ARE A LADDER, and a module may step onto any rung. A
     * print view or an embedded map wants the document without the shell; the
     * shell without the page frame is a full-bleed screen. Inheritance, not
     * three unrelated files — otherwise a fix to the document reaches one of
     * them.
     */
    public function testTheFramesInheritRatherThanRepeat(): void
    {
        self::assertSame([], array_diff($this->blocksOf(LayoutContract::DOCUMENT), LayoutContract::BLOCKS));

        // Each rung sees everything below it.
        self::assertSame(
            [],
            array_diff($this->blocksOf(LayoutContract::DOCUMENT), $this->blocksOf(LayoutContract::SHELL)),
            'The shell must extend the document, not restate it.',
        );
        self::assertSame(
            [],
            array_diff($this->blocksOf(LayoutContract::SHELL), $this->blocksOf(LayoutContract::PAGE)),
            'The page frame must extend the shell, not restate it.',
        );
    }

    /**
     * `content` IS IN THE CONTRACT ON PURPOSE, and it is the one socket named
     * for compatibility rather than for clarity.
     *
     * Every page in the host and every module bundle today fills
     * `{% block content %}` against the host's layout.html.twig. The extraction
     * would break all of them at once if the shell renamed it, and a big-bang
     * rename across five repositories is exactly the "casual rewiring" this
     * bundle exists to end. So `content` stays, as the shell's main slot; the
     * page frame fills it with the framed page, and a page that wants the shell
     * WITHOUT the frame fills it directly.
     *
     * This is a documented alias, not an accident, and the test says so.
     */
    public function testTheContentSocketSurvivesForThePagesThatAlreadyFillIt(): void
    {
        self::assertContains('content', LayoutContract::BLOCKS);
        self::assertContains('content', $this->blocksOf(LayoutContract::SHELL));

        // Filling `content` against the shell gets you the furniture and no
        // frame: no crumb, no page head, and no `.page` wrapper you did not ask
        // for. That is the escape hatch, and it has to keep working.
        $html = $this->render('@fixtures/bare_shell_page.html.twig');

        self::assertStringContainsString('id="bare-slot"', $html);
        self::assertStringNotContainsString('class="crumb"', $html);
        self::assertStringNotContainsString('class="pghead"', $html);
    }
}
