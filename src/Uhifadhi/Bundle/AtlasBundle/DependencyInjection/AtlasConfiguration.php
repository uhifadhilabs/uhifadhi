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

namespace Uhifadhi\Bundle\AtlasBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\NodeDefinition;

/**
 * The bundle's semantic configuration — how a host configures the map platform
 * in config/packages/map.yaml:
 *
 *   map:
 *     satellite:
 *       provider: esri                  # esri (default) | google | custom
 *       max_zoom: 19
 *       google:
 *         api_key: '%env(default::UHIFADHI_GOOGLE_MAPS_API_KEY)%'
 *       custom:
 *         url_template: 'https://…/{z}/{x}/{y}.jpg'
 *         attribution: 'Imagery © …'
 *
 * WHY THE DEFAULT IS ESRI. A Google-first default would have every map on every
 * page open by asking Google's Map Tiles API for a session token. For an
 * EEA-billed account Google answers 403 — "satellite tiles and 3D tiles are not
 * available for your account and region" — so the whole product would run on the
 * fallback while filling the console with refusals for an answer it had already
 * accepted. Esri's World Imagery needs no key and no session, so it is what a
 * deployment gets until it says otherwise. A deployment
 * that HAS a served Google key opts in by naming the provider; nothing is
 * downgraded, and no key is smuggled in by default.
 *
 * THREE PROVIDERS, NOT A HARDCODED VENDOR:
 *   esri    — keyless World Imagery. No configuration, works offline of any
 *             account. The default.
 *   google  — the Map Tiles API, session-token flow, key from an env var. Esri
 *             still draws underneath while the session resolves, and stays if
 *             the session is refused: a map is never blank for want of a key.
 *   custom  — any XYZ/WMTS url template plus the attribution its licence
 *             requires. This is how a deployment brings its OWN imagery — a
 *             national mapping agency's WMTS, a drone mosaic on the org's own
 *             tile server — without this bundle learning about it.
 *
 * The tree is closed, so an invented key fails loudly. url_template is REQUIRED
 * for the custom provider and checked here, because a custom source with no url
 * is a blank map at 3am rather than an error at deploy time. api_key is NOT
 * validated: it normally arrives as an env placeholder, which has no value at
 * compile time, and an absent key is already handled honestly at runtime (the
 * layer simply stays on Esri).
 *
 * Static so the tree is testable with a plain Processor and shared verbatim by
 * the bundle's configure().
 */
final class AtlasConfiguration
{
    /** Keyless Esri World Imagery — the default, and the understudy for every other provider. */
    public const string PROVIDER_ESRI = 'esri';

    /** Google Map Tiles API: a session token per document, then XYZ tiles. */
    public const string PROVIDER_GOOGLE = 'google';

    /** A deployment's own XYZ/WMTS url template. */
    public const string PROVIDER_CUSTOM = 'custom';

    /** @var list<string> */
    public const array PROVIDERS = [self::PROVIDER_ESRI, self::PROVIDER_GOOGLE, self::PROVIDER_CUSTOM];

    /** Leaflet's own default, and as deep as either keyless source is worth asking. */
    public const int DEFAULT_MAX_ZOOM = 19;

    public static function define(NodeDefinition|ArrayNodeDefinition $root): void
    {
        if (!$root instanceof ArrayNodeDefinition) {
            throw new \LogicException('The map root node must be an array node.');
        }

        $root
            ->children()
                ->arrayNode('satellite')
                    ->info('Which imagery the Satellite base layer draws.')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->enumNode('provider')
                            ->info('esri (keyless, the default) | google (Map Tiles API, keyed) | custom (your own XYZ/WMTS source).')
                            ->values(self::PROVIDERS)
                            ->defaultValue(self::PROVIDER_ESRI)
                        ->end()
                        ->integerNode('max_zoom')
                            ->info('Deepest zoom the satellite layer is asked for.')
                            ->min(1)->max(24)
                            ->defaultValue(self::DEFAULT_MAX_ZOOM)
                        ->end()
                        ->arrayNode('google')
                            ->addDefaultsIfNotSet()
                            ->children()
                                ->scalarNode('api_key')
                                    ->info('Google Maps API key. Public by nature (it travels in every tile URL) — restrict it by HTTP referrer at Google.')
                                    ->defaultValue('')
                                ->end()
                            ->end()
                        ->end()
                        ->arrayNode('custom')
                            ->addDefaultsIfNotSet()
                            ->children()
                                ->scalarNode('url_template')
                                    ->info('XYZ/WMTS template with {z}/{x}/{y} placeholders.')
                                    ->defaultNull()
                                ->end()
                                ->scalarNode('attribution')
                                    ->info('The credit line the source\'s licence requires. Shown on every map that draws it.')
                                    ->defaultNull()
                                ->end()
                            ->end()
                        ->end()
                    ->end()
                    ->validate()
                        ->ifTrue(static function (mixed $satellite): bool {
                            if (!\is_array($satellite) || self::PROVIDER_CUSTOM !== ($satellite['provider'] ?? null)) {
                                return false;
                            }
                            $custom = $satellite['custom'] ?? null;
                            $template = \is_array($custom) ? ($custom['url_template'] ?? null) : null;

                            return !\is_string($template) || '' === $template;
                        })
                        ->thenInvalid('atlas.satellite.provider is "custom", so atlas.satellite.custom.url_template must be set — a custom source with no url is a blank map.')
                    ->end()
                ->end()
            ->end()
        ;
    }
}
