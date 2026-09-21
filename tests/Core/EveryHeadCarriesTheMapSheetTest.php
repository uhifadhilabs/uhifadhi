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

namespace Uhifadhi\Core\Tests\Core;

use PHPUnit\Framework\Attributes\CoversNothing;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Twig\Environment;
use Uhifadhi\Bundle\AtlasBundle\AtlasBundle;
use Uhifadhi\Core\Tests\Application\Kernel;

/**
 * THE MAP SHEET IS IN EVERY HEAD, AND NO TEMPLATE LINKS IT BY HAND.
 *
 * THE DEFECT THIS CLOSES, and the owner on it: "we have had this problem a
 * million times." The rule used to be that a page which draws a plate links
 * `map.css`, and it held while a page could know. Then the plate became
 * something a WIDGET draws — on a composed surface ANY cell may draw one —
 * and the organization Overview composed a map cell onto a page that linked
 * no map sheet: `.map-plate`, `.map-legend` and `.lay .sw` had no rules at
 * all, the plate came apart, and nothing failed anywhere. The page returned
 * 200 and the test suite was green.
 *
 * SO THE HEAD IS NOT DECIDED BY WHAT A PAGE COMPOSES. The sheet joins the
 * shell's head contract beside `chart.css` and `calendar.css`, and both
 * halves of that are asserted here: a page with no plate in it still carries
 * the sheet, and no template in the core links it by hand — a hand-written
 * link is now a second copy of those rules at another point in the load
 * order.
 */
#[CoversNothing]
final class EveryHeadCarriesTheMapSheetTest extends KernelTestCase
{
    protected static function getKernelClass(): string
    {
        return Kernel::class;
    }

    /**
     * A PAGE WITH NO PLATE, RENDERED THROUGH THE INSTALLATION'S OWN HEAD —
     * not the list of sources read back, which would pass just as well with
     * nothing linking them.
     */
    public function testAPageWithNoPlateStillCarriesTheMapSheet(): void
    {
        $head = $this->renderAPageWithNoPlate();

        self::assertStringNotContainsString('map-plate', $head, 'this page draws no plate — that is the point of it');
        self::assertSame(
            1,
            preg_match_all(self::served(AtlasBundle::STYLESHEET), $head),
            'the head carries the map sheet exactly once, from the head contract',
        );
    }

    /** And the two that were already there stayed there. */
    public function testTheChartAndTheMonthAreStillCarriedBesideIt(): void
    {
        $head = $this->renderAPageWithNoPlate();

        self::assertMatchesRegularExpression(self::served(AtlasBundle::CHART_STYLESHEET), $head);
        self::assertMatchesRegularExpression(self::served(AtlasBundle::CALENDAR_STYLESHEET), $head);
    }

    /**
     * NO CORE TEMPLATE LINKS IT BY HAND. Ten did, one per page that knew it
     * drew a plate; every one of them is now a restatement, and the module
     * conformance rule refuses the same thing in a module's templates.
     */
    public function testNoCoreTemplateLinksTheAtlasSheetsByHand(): void
    {
        $offenders = [];

        foreach (self::templates() as $path) {
            $twig = (string) preg_replace('/\{#.*?#\}/s', '', (string) file_get_contents($path));

            preg_match_all('/<link\b[^>]*>/i', $twig, $links);
            foreach ($links[0] as $link) {
                foreach ([AtlasBundle::STYLESHEET, 'AtlasBundle::STYLESHEET', 'AtlasBundle::CHART_STYLESHEET', 'AtlasBundle::CALENDAR_STYLESHEET'] as $named) {
                    if (str_contains($link, $named)) {
                        $offenders[] = str_replace(self::root().'/', '', $path);

                        continue 2;
                    }
                }
            }
        }

        $offenders = array_values(array_unique($offenders));
        sort($offenders);

        self::assertSame([], $offenders, \sprintf(
            'These templates link an atlas sheet by hand [%s]. The shell carries it.',
            implode(', ', $offenders),
        ));
    }

    /**
     * A sheet as AssetMapper serves it: the same path with the content
     * digest in its name, which is what a head actually carries.
     */
    private static function served(string $sheet): string
    {
        return '#'.preg_quote(substr($sheet, 0, -4), '#').'(?:-[A-Za-z0-9_-]+)?\\.css#';
    }

    /** The shell's own document, with a body that draws nothing. */
    private function renderAPageWithNoPlate(): string
    {
        self::bootKernel();

        $twig = self::getContainer()->get('twig');
        \assert($twig instanceof Environment);

        return $twig->render($twig->createTemplate(
            // The importmap socket is emptied because this installation
            // mounts no `app` entrypoint; what is under test is the head's
            // stylesheet chain, which is the block above it.
            '{% extends "@Shell/document.html.twig" %}'
            .'{% block importmap %}{% endblock %}'
            .'{% block body %}<p>a page with no plate on it</p>{% endblock %}',
        ));
    }

    /**
     * Every template the core ships — not the suite's own fixtures, which
     * name things no installation ever draws, and one of which links the
     * sheet on purpose to prove the conformance rule can fail.
     *
     * @return list<string>
     */
    private static function templates(): array
    {
        $paths = [];

        /** @var \SplFileInfo $file */
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(self::root().'/src')) as $file) {
            if ('twig' === $file->getExtension() && !str_contains($file->getPathname(), '/tests/')) {
                $paths[] = $file->getPathname();
            }
        }

        sort($paths);
        self::assertNotSame([], $paths, 'no templates found — the sweep is broken, not the core');

        return $paths;
    }

    private static function root(): string
    {
        return \dirname(__DIR__, 2);
    }
}
