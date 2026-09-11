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

use Uhifadhi\Bundle\ShellBundle\Widget\Model\WidgetDom;

/**
 * THE WHOLE ROUND TRIP A PERSON MAKES, THROUGH THE REAL ROUTES.
 *
 * A widget library is not ported until, ON THE RENDERED PAGE, a preset can be
 * previewed, switched, and the page says the new one is active. Every part of
 * that had unit tests already and the class of miss survived all of them: what
 * is stored was right, what the resolver returned was right, and the page a
 * person actually opened either could not preview or did not say what was on.
 *
 * So this file asks the page. It renders the library over a real database with a
 * real person signed in, drives the writes through the real endpoint with the
 * real CSRF manager, and then reads the surface's own page and the library
 * again — for an AREA-SCOPED surface, because a module's dashboard is remembered
 * per area and an assertion that ignored the area would pass on a layout stored
 * for nobody's area at all.
 */
final class AreaWidgetLibraryTest extends WebTestCase
{
    private const string LIBRARY = '/areas/'.self::AREA.'/modules/sightings/widgets';
    private const string DASHBOARD = '/areas/'.self::AREA.'/modules/sightings';

    /**
     * WHERE EVERYBODY STARTS: the design the surface ships is on, it is the card
     * wearing Active, and the other card offers to be previewed.
     */
    public function testTheLibraryOpensOnTheShippedDesignAndMarksItActive(): void
    {
        $this->signIn();

        $body = $this->body(self::LIBRARY);

        self::assertSame(200, $this->browser()->getResponse()->getStatusCode());
        self::assertSame(1, substr_count($body, 'w-presetflag-active'), 'exactly one card wears Active');
        self::assertStringContainsString('data-preset-kind="design" data-preset-id="default"', $body);
        self::assertStringContainsString('data-preset-kind="design" data-preset-id="wide"', $body);
        self::assertStringContainsString('Preview', $body);
    }

    /**
     * PREVIEW IS WHY THE PAGE CARRIES A CATALOGUE. Clicking a card re-composes
     * the canvas from what the page already holds, so the page has to hold every
     * preset's layout and a cloneable render of every widget. A page missing
     * either draws cards that do nothing — the failure this suite exists for.
     */
    public function testTheLibraryCarriesEverythingAPreviewNeedsWithoutARoundTrip(): void
    {
        $this->signIn();

        $body = $this->body(self::LIBRARY);

        $catalog = self::embeddedCatalog($body);
        self::assertSame('sightings', $catalog['surface']);
        self::assertSame(['default', 'wide'], array_column($catalog['builtins'], 'id'));
        foreach ($catalog['builtins'] as $preset) {
            self::assertNotSame([], $preset['layout'], $preset['id'].' has no layout to preview from.');
        }

        foreach (['total', 'by-species', 'map'] as $id) {
            self::assertStringContainsString(WidgetDom::TEMPLATE.'="'.$id.'"', $body, $id.' has no cloneable render to preview with.');
        }
    }

    /**
     * THE SWITCH, END TO END: apply the other design, and the surface's own page
     * renders exactly that composition while the library says it is the one on.
     */
    public function testApplyingADesignSwitchesTheDashboardAndTheLibraryMarksItActive(): void
    {
        $this->signIn();
        $token = self::csrfToken($this->body(self::LIBRARY));

        $applied = $this->post(self::LIBRARY.'/preset/wide', $token);
        self::assertSame(302, $applied->getStatusCode());

        // The surface's page is the composition, not the catalogue: "wide" names
        // the total and the map, so the species widget is ABSENT rather than
        // drawn small.
        $dashboard = $this->body(self::DASHBOARD);
        self::assertStringContainsString('data-dash-widget="total" data-dash-cols="12"', $dashboard);
        self::assertStringContainsString('data-dash-widget="map" data-dash-cols="12"', $dashboard);
        self::assertStringNotContainsString('data-dash-widget="by-species"', $dashboard);

        $library = $this->body(self::LIBRARY);
        self::assertStringContainsString('Your dashboard shows <b>Wide</b>', $library);
        self::assertSame(1, substr_count($library, 'w-presetflag-active'));
        self::assertMatchesRegularExpression(
            '/data-preset-id="wide"[^>]*aria-pressed="true"/',
            $library,
            'the library does not mark the applied design as active.',
        );
    }

    /** And switching back is the same trip in the other direction. */
    public function testSwitchingBackToTheShippedDesignIsTheSameTrip(): void
    {
        $this->signIn();
        $token = self::csrfToken($this->body(self::LIBRARY));

        $this->post(self::LIBRARY.'/preset/wide', $token);
        $this->post(self::LIBRARY.'/preset/default', $token);

        $dashboard = $this->body(self::DASHBOARD);
        self::assertStringContainsString('data-dash-widget="by-species"', $dashboard);
        self::assertStringNotContainsString('data-dash-widget="map"', $dashboard);

        self::assertMatchesRegularExpression(
            '/data-preset-id="default"[^>]*aria-pressed="true"/',
            $this->body(self::LIBRARY),
        );
    }

    /**
     * A DESIGN IS COPIED, NOT EDITED. The copy becomes the active preset at
     * once, it is the person's own, and the toolbar offers what it offers for
     * one of theirs — rename and delete instead of "make a copy".
     */
    public function testCopyingADesignMakesTheCopyTheActivePresetAndEditable(): void
    {
        $this->signIn();
        $token = self::csrfToken($this->body(self::LIBRARY));

        self::assertSame(302, $this->post(self::LIBRARY.'/preset/wide/copy', $token, ['name' => 'My wall'])->getStatusCode());

        $library = $this->body(self::LIBRARY);
        self::assertStringContainsString('Your dashboard shows <b>My wall</b>', $library);
        self::assertStringContainsString('Edits below go straight into it', $library);
        self::assertStringContainsString('data-preset-kind="mine"', $library);
        self::assertSame(1, substr_count($library, 'w-presetflag-active'));
    }

    /** Rename and delete travel the same page, and the page says what happened. */
    public function testACustomPresetIsRenamedAndDeletedThroughTheSameLibrary(): void
    {
        $this->signIn();
        $token = self::csrfToken($this->body(self::LIBRARY));
        $this->post(self::LIBRARY.'/preset/wide/copy', $token, ['name' => 'My wall']);

        $uuid = self::activeCustomPresetId($this->body(self::LIBRARY));

        self::assertSame(302, $this->post(self::LIBRARY.'/presets/'.$uuid.'/rename', $token, ['name' => 'Night wall'])->getStatusCode());
        self::assertStringContainsString('Your dashboard shows <b>Night wall</b>', $this->body(self::LIBRARY));

        self::assertSame(302, $this->post(self::LIBRARY.'/presets/'.$uuid.'/delete', $token)->getStatusCode());

        // Deleting the one that was on puts the surface's own design back on,
        // rather than leaving a dashboard with nothing active.
        $library = $this->body(self::LIBRARY);
        self::assertStringNotContainsString('Night wall', $library);
        self::assertSame(1, substr_count($library, 'w-presetflag-active'));
        self::assertStringContainsString('one of the product’s own designs', $library);
    }

    /** A WRITE WITHOUT THE PAGE'S TOKEN CHANGES NOTHING, and says so with a 403. */
    public function testAWriteWithoutTheTokenTheLibraryRenderedIsRefused(): void
    {
        $this->signIn();
        $this->body(self::LIBRARY);

        self::assertSame(403, $this->post(self::LIBRARY.'/preset/wide', 'not-the-token')->getStatusCode());
        self::assertStringNotContainsString('data-dash-widget="map"', $this->body(self::DASHBOARD));
    }

    /** The token the page rendered, read off the page the way a browser reads it. */
    private static function csrfToken(string $body): string
    {
        self::assertSame(1, preg_match('/'.preg_quote(WidgetDom::CSRF_TOKEN, '/').'="([^"]+)"/', $body, $matches));
        $captured = $matches[1] ?? null;
        self::assertIsString($captured);

        return $captured;
    }

    /** The uuid of the person's own preset that is currently active. */
    private static function activeCustomPresetId(string $body): string
    {
        self::assertSame(1, preg_match('/data-preset-kind="mine" data-preset-id="([^"]+)"/', $body, $matches));
        $captured = $matches[1] ?? null;
        self::assertIsString($captured);

        return $captured;
    }

    /**
     * The catalogue the page embedded for the script to preview from.
     *
     * @return array{surface: string, builtins: list<array{id: string, layout: array<string, int>}>}
     */
    private static function embeddedCatalog(string $body): array
    {
        self::assertSame(1, preg_match('/'.preg_quote(WidgetDom::CATALOG, '/').'>(.+?)<\/script>/s', $body, $matches));
        $captured = $matches[1] ?? null;
        self::assertIsString($captured);

        $catalog = json_decode(html_entity_decode($captured, \ENT_QUOTES), true, 512, \JSON_THROW_ON_ERROR);
        self::assertIsArray($catalog);

        /** @var array{surface: string, builtins: list<array{id: string, layout: array<string, int>}>} $catalog */
        return $catalog;
    }
}
