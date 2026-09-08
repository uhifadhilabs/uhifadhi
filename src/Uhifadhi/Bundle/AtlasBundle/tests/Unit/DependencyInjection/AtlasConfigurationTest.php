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

namespace Uhifadhi\Bundle\AtlasBundle\Tests\Unit\DependencyInjection;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Definition\Processor;
use Uhifadhi\Bundle\AtlasBundle\DependencyInjection\AtlasConfiguration;

/**
 * The satellite provider tree — the config a deployment writes to say which
 * imagery its maps draw.
 */
final class AtlasConfigurationTest extends TestCase
{
    /**
     * @param list<array<string, mixed>> $configs
     *
     * @return array<string, mixed>
     */
    private static function process(array $configs): array
    {
        $tree = new TreeBuilder('atlas');
        AtlasConfiguration::define($tree->getRootNode());

        /** @var array<string, mixed> $processed */
        $processed = new Processor()->process($tree->buildTree(), $configs);

        return $processed;
    }

    /**
     * THE RULING THIS TREE EXISTS FOR: a host that configures nothing gets the
     * keyless source. Not Google-with-a-silent-fallback — Esri, decided here,
     * so no createSession call is ever made that nobody asked for.
     */
    public function testAHostThatConfiguresNothingGetsTheKeylessSource(): void
    {
        $config = self::process([[]]);

        self::assertIsArray($config['satellite']);
        self::assertSame(AtlasConfiguration::PROVIDER_ESRI, $config['satellite']['provider']);
        self::assertSame(19, $config['satellite']['max_zoom']);
    }

    public function testGoogleIsOptedIntoByNameAndTakesItsKeyFromTheConfig(): void
    {
        $config = self::process([[
            'satellite' => ['provider' => 'google', 'google' => ['api_key' => 'AIza-test']],
        ]]);

        self::assertIsArray($config['satellite']);
        self::assertSame(AtlasConfiguration::PROVIDER_GOOGLE, $config['satellite']['provider']);
        self::assertIsArray($config['satellite']['google']);
        self::assertSame('AIza-test', $config['satellite']['google']['api_key']);
    }

    public function testACustomSourceCarriesItsTemplateAndItsAttribution(): void
    {
        $config = self::process([[
            'satellite' => ['provider' => 'custom', 'custom' => [
                'url_template' => 'https://tiles.example.org/{z}/{x}/{y}.jpg',
                'attribution' => 'Imagery © the mapping agency',
            ]],
        ]]);

        self::assertIsArray($config['satellite']);
        self::assertIsArray($config['satellite']['custom']);
        self::assertSame('https://tiles.example.org/{z}/{x}/{y}.jpg', $config['satellite']['custom']['url_template']);
        self::assertSame('Imagery © the mapping agency', $config['satellite']['custom']['attribution']);
    }

    /**
     * A custom source with no url is a blank map at 3am. It has to be an error
     * at deploy time instead.
     */
    public function testACustomSourceWithoutAUrlIsRefusedAtCompileTime(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessageMatches('/url_template must be set/');

        self::process([['satellite' => ['provider' => 'custom']]]);
    }

    public function testAnUnknownProviderIsRefused(): void
    {
        $this->expectException(InvalidConfigurationException::class);

        self::process([['satellite' => ['provider' => 'maplibre']]]);
    }

    /**
     * The tree is closed: a typo must fail loudly rather than be ignored into a
     * default nobody chose.
     */
    public function testAnInventedKeyIsRefused(): void
    {
        $this->expectException(InvalidConfigurationException::class);

        self::process([['satellite' => ['providr' => 'esri']]]);
    }
}
