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

namespace Uhifadhi\Bundle\AtlasBundle\Tests\Unit\Twig;

use PHPUnit\Framework\TestCase;
use Uhifadhi\Bundle\AtlasBundle\DependencyInjection\AtlasConfiguration;
use Uhifadhi\Bundle\AtlasBundle\Model\SatelliteSource;
use Uhifadhi\Bundle\AtlasBundle\Twig\MapExtension;

/**
 * The attribute a host puts on its <body>. It is written into a double-quoted
 * HTML attribute and marked safe, so the escaping is this class's own
 * responsibility and is tested as such.
 */
final class MapExtensionTest extends TestCase
{
    public function testItRendersOneDataAttributeCarryingThePayload(): void
    {
        $html = new MapExtension(new SatelliteSource())->basemapAttributes();

        self::assertStringStartsWith('data-map-satellite="', $html);
        self::assertStringContainsString('&quot;provider&quot;:&quot;esri&quot;', $html);
    }

    /**
     * The JSON's own double quotes must not close the attribute. This is the
     * assertion that makes `is_safe: html` honest.
     */
    public function testTheAttributeCannotBeClosedByItsOwnPayload(): void
    {
        $html = new MapExtension(new SatelliteSource(
            AtlasConfiguration::PROVIDER_CUSTOM,
            '',
            'https://tiles.example.org/{z}/{x}/{y}.jpg',
            'Imagery © "the agency" <b>',
        ))->basemapAttributes();

        // Exactly two raw double quotes: the ones this class wrote itself.
        self::assertSame(2, substr_count($html, '"'), 'A quote from the payload would end the attribute early.');
        self::assertStringNotContainsString('<b>', $html);
    }

    public function testThePayloadIsAlsoAvailableRawForAHostThatPlacesItItself(): void
    {
        $payload = new MapExtension(new SatelliteSource(AtlasConfiguration::PROVIDER_GOOGLE, 'AIza-test'))->basemapPayload();

        self::assertSame('google', $payload['provider']);
        self::assertSame('AIza-test', $payload['key'] ?? null);
    }

    public function testBothFunctionsAreRegisteredUnderTheirPublicNames(): void
    {
        $names = array_map(
            static fn ($function) => $function->getName(),
            new MapExtension(new SatelliteSource())->getFunctions(),
        );

        self::assertContains('map_basemap_attributes', $names);
        self::assertContains('map_basemap_payload', $names);
    }
}
