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

namespace Uhifadhi\Bundle\AreaBundle\Tests\Integration\Web;

/**
 * THE LANDING FOLLOWS THE LIBRARY — the whole round trip, through the real
 * routes.
 *
 * A preset library is not ported until the SURFACE ITSELF draws what was adopted
 * in it. The areas library had every part of that except the last one: a person
 * could preview all five layouts and apply one, and `/areas` went on drawing the
 * wall of workspaces, because the adoption lived in the browser's own store and
 * no server ever read it.
 *
 * So this file applies a preset the way a person does — the library page, its
 * token, its POST — and then asks the REGISTER what it draws. The five layouts
 * are told apart by the composition each one owns: the card gallery, the
 * operational table, the map and its dock, the three attention bands, the hero.
 */
final class AreaLandingPresetTest extends WebTestCase
{
    /** The markup only one of the five layouts ever emits. */
    private const array SIGNATURE = [
        'wall' => 'ax-cards',
        'register' => 'ax-reg',
        'map' => 'ax-docklist',
        'attention' => 'ax-tri',
        'flagship' => 'ax-hero',
    ];

    /** WHERE EVERYBODY STARTS: nobody has adopted anything, so the landing is the wall. */
    public function testTheLandingDrawsTheShippedWallUntilSomethingIsAdopted(): void
    {
        $this->arrange();

        $landing = $this->body('/areas');

        self::assertStringContainsString(self::SIGNATURE['wall'], $landing);
        self::assertStringNotContainsString(self::SIGNATURE['register'], $landing);
    }

    /**
     * THE SWITCH, END TO END: adopt "The register" in the library and the landing
     * is the register — the table, not the wall.
     */
    public function testAdoptingTheRegisterMakesItTheLanding(): void
    {
        $this->arrange();

        $this->adopt('register');

        $landing = $this->body('/areas');
        self::assertStringContainsString(self::SIGNATURE['register'], $landing);
        self::assertStringNotContainsString(self::SIGNATURE['wall'], $landing);
    }

    /** And every other layout is the same trip. */
    public function testEveryLayoutTheLibraryOffersBecomesTheLanding(): void
    {
        $this->arrange();

        foreach (self::SIGNATURE as $key => $signature) {
            $this->adopt($key);

            self::assertStringContainsString($signature, $this->body('/areas'), $key.' was adopted but the landing does not draw it');
        }
    }

    /** The landing says which layout it is wearing, in the layout's own words. */
    public function testTheLandingWearsTheAdoptedLayoutsOwnSubtitle(): void
    {
        $this->arrange();

        $this->adopt('attention');

        self::assertStringContainsString('grouped by what needs the operator', $this->body('/areas'));
    }

    /** The library marks the adopted layout as the active one, read off the server. */
    public function testTheLibraryMarksTheAdoptedLayoutActive(): void
    {
        $this->arrange();

        $this->adopt('flagship');

        $library = $this->body('/areas/widgets');
        self::assertMatchesRegularExpression('/data-preset-id="flagship"[^>]*aria-pressed="true"/', $library);
        self::assertSame(1, substr_count($library, 'w-presetflag-active'), 'exactly one card wears Active');
    }

    /** Reset drops the adoption and the landing is the shipped wall again. */
    public function testResetPutsTheShippedWallBack(): void
    {
        $this->arrange();
        $this->adopt('map');

        $this->post('/areas/widgets/reset');

        self::assertStringContainsString(self::SIGNATURE['wall'], $this->body('/areas'));
    }

    /** A layout this surface does not ship is refused rather than stored. */
    public function testALayoutTheSurfaceDoesNotShipIsRefused(): void
    {
        $this->arrange();

        $this->post('/areas/widgets/preset/gallery');

        self::assertSame(422, $this->browser()->getResponse()->getStatusCode());
        self::assertStringContainsString(self::SIGNATURE['wall'], $this->body('/areas'));
    }

    /** An adoption is a write, so it carries a token. */
    public function testAnAdoptionWithoutATokenIsRefused(): void
    {
        $this->arrange();

        $this->browser()->request('POST', '/areas/widgets/preset/register');

        self::assertContains($this->browser()->getResponse()->getStatusCode(), [401, 403]);
        self::assertStringContainsString(self::SIGNATURE['wall'], $this->body('/areas'));
    }

    // ---- the arrangement ---------------------------------------------------

    /**
     * ONE LIVE AREA AND A PERSON TO KEEP A LAYOUT FOR. The flagship and the
     * attention board only have something to draw for a live area, and a widget
     * preference is a record about somebody — so the token holds the
     * installation's own account entity, exactly as it does in a real one.
     */
    private function arrange(): void
    {
        $this->boot();
        $this->signInAsPerson();
        $this->aLiveArea();
    }

    /** Adopt a layout the way a person does: the library page, its token, its POST. */
    private function adopt(string $key): void
    {
        $this->post('/areas/widgets/preset/'.$key);
    }

    private function post(string $path): void
    {
        $this->browser()->request('POST', $path, ['_token' => $this->tokenFromTheLibrary()]);
    }

    private function tokenFromTheLibrary(): string
    {
        return (string) $this->browser()->request('GET', '/areas/widgets')
            ->filter('input[name="_token"]')->first()->attr('value');
    }

    private function body(string $path): string
    {
        $this->browser()->request('GET', $path);

        return (string) $this->browser()->getResponse()->getContent();
    }
}
