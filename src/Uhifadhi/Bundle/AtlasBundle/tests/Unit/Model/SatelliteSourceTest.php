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

namespace Uhifadhi\Bundle\AtlasBundle\Tests\Unit\Model;

use PHPUnit\Framework\TestCase;
use Uhifadhi\Bundle\AtlasBundle\DependencyInjection\AtlasConfiguration;
use Uhifadhi\Bundle\AtlasBundle\Model\SatelliteSource;

/**
 * What the browser is told, and — just as importantly — what it is not told.
 */
final class SatelliteSourceTest extends TestCase
{
    public function testTheDefaultSourceIsTheKeylessOne(): void
    {
        $source = new SatelliteSource();

        self::assertSame(AtlasConfiguration::PROVIDER_ESRI, $source->effectiveProvider());
        self::assertSame(['provider' => 'esri', 'maxZoom' => 19], $source->toBrowserPayload());
    }

    /**
     * A "google" deployment with no key can only ever earn a failed
     * createSession call. It is not sent to make it.
     */
    public function testGoogleWithoutAKeyIsSimplyTheKeylessSource(): void
    {
        $source = new SatelliteSource(AtlasConfiguration::PROVIDER_GOOGLE);

        self::assertSame(AtlasConfiguration::PROVIDER_ESRI, $source->effectiveProvider());
        self::assertArrayNotHasKey('key', $source->toBrowserPayload());
    }

    /**
     * The key normally arrives as `%env(default::UHIFADHI_GOOGLE_MAPS_API_KEY)%`,
     * which is what this bundle's own recipe writes. Symfony's `default`
     * processor treats an UNSET var — and an var set to the empty string, which
     * is what the recipe's .env line leaves behind — as "no value", so what
     * actually reaches this constructor is null, not ''. A container built with
     * no key present must still build: an image is compiled with no runtime
     * secrets, and a deployment that has not bought a key yet is a normal one.
     */
    public function testAnUnresolvedKeyEnvArrivesAsNullAndIsSimplyNoKey(): void
    {
        $source = new SatelliteSource(AtlasConfiguration::PROVIDER_GOOGLE, null);

        self::assertSame(AtlasConfiguration::PROVIDER_ESRI, $source->effectiveProvider());
        self::assertArrayNotHasKey('key', $source->toBrowserPayload());
    }

    public function testGoogleWithAKeyPublishesIt(): void
    {
        $source = new SatelliteSource(AtlasConfiguration::PROVIDER_GOOGLE, 'AIza-test');

        self::assertSame(AtlasConfiguration::PROVIDER_GOOGLE, $source->effectiveProvider());
        self::assertSame(
            ['provider' => 'google', 'maxZoom' => 19, 'key' => 'AIza-test'],
            $source->toBrowserPayload(),
        );
    }

    /**
     * A key is never emitted on a page that is not going to use it — an esri
     * deployment's HTML says nothing about Google at all.
     */
    public function testAKeyIsNeverPublishedByAProviderThatDoesNotUseIt(): void
    {
        $source = new SatelliteSource(AtlasConfiguration::PROVIDER_ESRI, 'AIza-test');

        self::assertArrayNotHasKey('key', $source->toBrowserPayload());
        self::assertStringNotContainsString('AIza-test', $source->toJson());
    }

    public function testACustomSourceCarriesItsTemplateAndCredit(): void
    {
        $source = new SatelliteSource(
            AtlasConfiguration::PROVIDER_CUSTOM,
            '',
            'https://tiles.example.org/{z}/{x}/{y}.jpg',
            'Imagery © the mapping agency',
            17,
        );

        self::assertSame(
            [
                'provider' => 'custom',
                'maxZoom' => 17,
                'urlTemplate' => 'https://tiles.example.org/{z}/{x}/{y}.jpg',
                'attribution' => 'Imagery © the mapping agency',
            ],
            $source->toBrowserPayload(),
        );
    }

    /**
     * Imagery always carries a credit. A custom source whose deployment forgot
     * to write one still says SOMETHING, because a blank attribution control is
     * how a licence gets breached quietly.
     */
    public function testACustomSourceIsNeverCreditedToNobody(): void
    {
        $source = new SatelliteSource(AtlasConfiguration::PROVIDER_CUSTOM, '', 'https://tiles.example.org/{z}/{x}/{y}.jpg');
        $payload = $source->toBrowserPayload();

        self::assertArrayHasKey('attribution', $payload);
        self::assertNotSame('', $payload['attribution'] ?? '');
    }

    public function testACustomSourceWithoutATemplateFallsBackRatherThanDrawingNothing(): void
    {
        $source = new SatelliteSource(AtlasConfiguration::PROVIDER_CUSTOM);

        self::assertSame(AtlasConfiguration::PROVIDER_ESRI, $source->effectiveProvider());
    }

    /**
     * The payload is read by JavaScript, so its keys are camelCase and its
     * slashes are not escaped into `\/` (a url template read by a human in the
     * page source is a url template a human can debug).
     */
    public function testTheJsonIsShapedForAReaderInTheBrowser(): void
    {
        $json = new SatelliteSource(
            AtlasConfiguration::PROVIDER_CUSTOM,
            '',
            'https://tiles.example.org/{z}/{x}/{y}.jpg',
            'Imagery © the mapping agency',
        )->toJson();

        self::assertStringContainsString('"urlTemplate"', $json);
        self::assertStringContainsString('https://tiles.example.org', $json);
    }
}
