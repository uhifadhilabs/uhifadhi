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

namespace Uhifadhi\Bundle\ShellBundle\Tests\Widget\Functional;

use Symfony\Component\DomCrawler\Crawler;
use Uhifadhi\Bundle\ShellBundle\Tests\Widget\Integration\Fixtures\SightingsSurface;
use Uhifadhi\Bundle\ShellBundle\Widget\Model\WidgetDom;

/**
 * A MODULE WITH TWO WIDGET SURFACES HAS ONE LIBRARY PAGE.
 *
 * RULED with the roster's Live plate rail: the column beside the Live tab's
 * plate is a widget surface of its own, beside the module's Overview. That is
 * not a second library — a person configuring their module's widgets goes to
 * one page and finds a section per surface, each with its own arrangements,
 * its own widgets at full size and its own presets store.
 *
 * Driven through the fake contributor's kernel over real HTTP, because the
 * thing that has to hold is the markup a browser receives: two roots, two
 * catalogues, two tokens, and nothing of one section leaking into the other.
 */
final class TwoSurfaceLibraryTest extends WebTestCase
{
    private const string BOTH = '/areas/'.self::AREA.'/modules/sightings/widgets/both';

    /** ONE PAGE, ONE SECTION PER SURFACE, in the order the page listed them. */
    public function testThePageCarriesASectionPerSurfaceInTheOrderGiven(): void
    {
        $this->signIn();
        $crawler = new Crawler($this->body(self::BOTH));

        $sections = $crawler->filter('section.w-surface');
        self::assertCount(2, $sections);
        self::assertSame(
            ['The dashboard', 'The plate rail'],
            $sections->each(static fn (Crawler $n): string => trim($n->filter('h2.zone')->text())),
        );
        self::assertSame(
            ['What the module opens on.', 'The column beside the plate.'],
            $sections->each(static fn (Crawler $n): string => trim($n->filter('p.pgsub')->text())),
        );
    }

    /**
     * EACH SECTION IS A WHOLE LIBRARY — its own root, so the script arms two
     * of them, and its own catalogue, so the previews in one section compose
     * from that surface's widgets and not the other's.
     */
    public function testEachSectionIsAWholeLibraryWithItsOwnCatalogue(): void
    {
        $this->signIn();
        $crawler = new Crawler($this->body(self::BOTH));

        $roots = $crawler->filter('['.WidgetDom::ROOT.']');
        self::assertCount(2, $roots);

        $catalogues = $crawler->filter('['.WidgetDom::CATALOG.']')->each(
            static function (Crawler $node): array {
                $decoded = json_decode($node->text(), true, 512, \JSON_THROW_ON_ERROR);
                \assert(\is_array($decoded));

                return $decoded;
            },
        );

        self::assertCount(2, $catalogues);
        self::assertSame(
            [SightingsSurface::SURFACE, SightingsSurface::RAIL_SURFACE],
            array_map(static fn (array $c): mixed => $c['surface'], $catalogues),
        );

        // And the widgets in each are that surface's own.
        self::assertSame(
            [['total', 'by-species', 'map'], ['watchers', 'recent']],
            array_map(
                static fn (array $c): array => array_map(
                    static fn (mixed $w): mixed => \is_array($w) ? $w['id'] : null,
                    \is_array($c['widgets']) ? $c['widgets'] : [],
                ),
                $catalogues,
            ),
        );
    }

    /**
     * A DOOR LANDS ON THE SURFACE IT NAMES. A module with two surfaces has
     * two doors into one library page — the roster's Live tab links
     * `…/widgets#rail` — and a door that always landed at the top would make
     * the reader hunt for the thing it just opened.
     *
     * The id is the SECTION's, so the heading and its line come into view
     * with the surface rather than the strip appearing without a name above
     * it. A surface that names no anchor gets no id, because an id nothing
     * links to is a name nobody agreed on.
     */
    public function testASectionCarriesTheAnchorItsDoorNames(): void
    {
        $this->signIn();
        $crawler = new Crawler($this->body(self::BOTH));

        $ids = $crawler->filter('section.w-surface')->each(
            static fn (Crawler $n): ?string => $n->attr('id'),
        );

        self::assertSame([null, 'rail'], $ids);
        self::assertSame(
            'The plate rail',
            trim($crawler->filter('section.w-surface#rail h2.zone')->text()),
            'the door lands on the heading, not on the strip under it',
        );
    }

    /**
     * A TOKEN IS GOOD FOR ONE SURFACE. The two sections write to different
     * compositions, so they are protected separately — a page that rendered
     * one token for both would let a form from one section post to the other
     * on a token that was never meant for it.
     */
    public function testEachSurfaceCarriesItsOwnToken(): void
    {
        $this->signIn();
        $crawler = new Crawler($this->body(self::BOTH));

        $tokens = $crawler->filter('['.WidgetDom::ROOT.']')->each(
            static fn (Crawler $n): string => (string) $n->attr(WidgetDom::CSRF_TOKEN),
        );

        self::assertCount(2, array_filter($tokens));
        self::assertNotSame($tokens[0], $tokens[1]);
    }

    /**
     * AND EACH SECTION SHOWS ITS OWN PRESETS AT FULL SIZE. The rail ships one
     * design of its own; nothing of the dashboard's appears under its
     * heading, because the two are separate compositions.
     */
    public function testASectionShowsOnlyItsOwnPresetsAndWidgets(): void
    {
        $this->signIn();
        $crawler = new Crawler($this->body(self::BOTH));

        $rail = $crawler->filter('section.w-surface')->eq(1);

        self::assertSame(
            ['people-first'],
            $rail->filter('[data-preset-kind="design"]')->each(
                static fn (Crawler $n): string => (string) $n->attr('data-preset-id'),
            ),
        );
        self::assertStringNotContainsString('By species', $rail->html());
        self::assertStringContainsString('Who is watching', $rail->html());
    }

    /**
     * THE SINGLE-SURFACE CALL IS UNCHANGED. Every module that has one surface
     * passes the same parameters it always did and gets the same one library,
     * with no section wrapped around it — a heading over the only thing on a
     * page names nothing.
     */
    public function testOneSurfaceStillRendersOneLibraryWithNoSectionAroundIt(): void
    {
        $this->signIn();
        $crawler = new Crawler($this->body('/areas/'.self::AREA.'/modules/sightings/widgets'));

        self::assertCount(1, $crawler->filter('['.WidgetDom::ROOT.']'));
        self::assertCount(0, $crawler->filter('section.w-surface'));
    }
}
