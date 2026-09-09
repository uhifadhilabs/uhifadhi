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

namespace Uhifadhi\Bundle\AtlasBundle\Tests\Unit\Assets;

use PHPUnit\Framework\TestCase;

/**
 * THE PLATFORM'S BASEMAP CONTRACT — assets/basemaps.js.
 *
 * This test came with the asset out of the host repository, and it belongs with
 * it: the defect it guards is a defect of THIS file, so it must fail in the
 * repository where someone would edit it, not two repositories away.
 *
 * The defect. Google's Map Tiles API refuses satellite outright for an
 * EEA-billed account —
 *
 *     403 · "satellite tiles and 3D tiles are not available for your account
 *            and region"
 *
 * — which is a settled fact about the account, not a blip. The contract cached only
 * SUCCESS, so that settled refusal was never remembered: every map on a page
 * asked again, and every remount asked again after that. A two-map page fired
 * two createSession calls and the console filled with 403s for an outcome the
 * code had already accepted.
 *
 * A refusal is an answer. These assertions read the shipped asset as TEXT —
 * there is no JS runner in this project, and this is a defect no HTTP test can
 * see.
 *
 * The provider contract added a second class of defect worth the same treatment:
 * this file must not go back to preferring a vendor. Whose imagery a deployment
 * draws is decided in PHP and read from the document; the day this file names a
 * default again, every deployment quietly inherits somebody else's billing.
 */
final class BasemapContractAssetsTest extends TestCase
{
    private static function basemapsJs(): string
    {
        return (string) file_get_contents(\dirname(__DIR__, 3).'/assets/basemaps.js');
    }

    public function testTheSessionIsAskedForAtMostOncePerDocument(): void
    {
        $js = self::basemapsJs();

        // One shared attempt, held at module scope. Eight widgets on a dashboard
        // are eight satelliteLayer() calls; they must produce one request.
        self::assertMatchesRegularExpression(
            '/^let pendingSession = null;/m',
            $js,
            'The contract must hold its one attempt at module scope so every map on the page shares it.',
        );
        self::assertStringContainsString(
            'pendingSession ??=',
            $js,
            'The first caller starts the attempt and every later one awaits the same promise.',
        );
        // Only createSession may talk to the network from here.
        self::assertSame(
            1,
            substr_count($js, 'fetch('),
            'The basemap contract makes exactly one kind of network call: createSession.',
        );
    }

    public function testARefusalIsRememberedSoItIsNotAskedAgain(): void
    {
        $js = self::basemapsJs();

        // The negative answer is written to the same store as the positive one.
        // Without this line the 403 is re-earned on every mount.
        self::assertStringContainsString(
            'cacheSession(null,',
            $js,
            'A refused session must be cached: Google saying no is an answer, not a missing one.',
        );
        self::assertMatchesRegularExpression(
            '/const NEGATIVE_TTL_MS = /',
            $js,
            'A remembered refusal needs its own lifetime, so an account that gains access recovers.',
        );
    }

    public function testNothingKnownIsDistinctFromAskedAndRefused(): void
    {
        $js = self::basemapsJs();

        // The cache reader has three answers, and collapsing two of them is how
        // a remembered refusal would silently become another request.
        self::assertStringContainsString(
            'return undefined; // nothing known',
            $js,
            'The reader must say "nothing known" distinctly from "asked, and refused" (null).',
        );
        self::assertStringContainsString(
            'undefined !== cached',
            $js,
            'A cached refusal (null) must short-circuit the request just as a cached token does.',
        );
    }

    /**
     * The provider ruling, held in the asset itself: nothing is asked of Google
     * unless the deployment named Google.
     */
    public function testGoogleIsOnlyContactedWhenTheDeploymentAskedForIt(): void
    {
        $js = self::basemapsJs();

        self::assertStringContainsString(
            'if (PROVIDER_GOOGLE !== source.provider) {',
            $js,
            'The contract must return the keyless layer before it ever reaches for a session.',
        );
        self::assertStringContainsString(
            'dataset?.mapSatellite',
            $js,
            'The configured provider is read from the document, not decided here.',
        );
    }

    /**
     * The key is not read off a Google-shaped attribute of its own: it arrives
     * inside the provider payload, so a page that draws Esri carries no Google
     * key at all.
     */
    public function testTheContractReadsNoGoogleShapedAttribute(): void
    {
        self::assertStringNotContainsString(
            'googleMapsApiKey',
            self::basemapsJs(),
            'The key belongs to the google provider payload, not to a global attribute of its own.',
        );
    }
}
