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

namespace Uhifadhi\Bundle\AtlasBundle;

use Symfony\Component\AssetMapper\AssetMapperInterface;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;
use Uhifadhi\Bundle\AtlasBundle\DependencyInjection\AtlasConfiguration;
use Uhifadhi\Bundle\AtlasBundle\Model\SatelliteSource;
use Uhifadhi\Bundle\AtlasBundle\Twig\MapExtension;
use Uhifadhi\Bundle\AtlasBundle\Twig\MapPlateRuntime;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

/**
 * Map — the platform's map machinery. INFRASTRUCTURE, not a catalogue module.
 *
 * MECHANISM, NOT A SCREEN. This bundle owns no entities and no pages. What it
 * owns is everything a map is made of before anyone decides what to draw on it:
 * the map builder and the plate render_map() emits, the basemap contract (which
 * imagery, from which provider), how an area boundary is cased and scrimmed, and
 * the chrome — zoom, DIM, the base-layer menu, fullscreen, the scale bar, the
 * Ctrl/⌘-scroll bargain — that every map in the product wears.
 *
 * THE TWO TIERS. A CAPABILITY module (patrol, incident) is the per-area grid an
 * admin switches on, default off, ledgered per area. An INFRASTRUCTURE module is
 * machinery every map-bearing screen already imports: patrol plates, incident
 * plates, the area overview and the zones editor all draw with these assets, so a
 * host that omitted this bundle would not have "fewer features", it would have
 * four broken screens. That is not an opt-in, so map is not offered as one: it
 * contributes NO "uhifadhi.module" provider, appears in no catalogue, in no
 * per-area grid and in no ledger. It is installed-means-on, and enforcement is
 * the composer graph — AreaBundle hard-requires it — not a toggle.
 *
 * WHAT A HOST MUST DO (both documented in the README; the importmap mechanics
 * in docs/importmap-assets.md):
 *   1. register the bundle — the recipe's job;
 *   2. put {{ map_basemap_attributes() }} on its <body> — the one line that is
 *      genuinely the host's, because it goes in a template only the host owns.
 * The three importmap entries are not a third step: this package declares them
 * in assets/package.json and Flex writes them on install (see prependExtension
 * below).
 *
 * LEAFLET IS UX MAP'S. The map itself is created by symfony/ux-leaflet-map's
 * own Stimulus controller, which imports `leaflet` from the host's importmap —
 * one Leaflet on the page, vendored locally by AssetMapper, named by the entry
 * that bridge's own assets/package.json declares. This bundle ships no copy of
 * it and publishes no global.
 */
final class AtlasBundle extends AbstractBundle
{
    /**
     * THE PLATFORM'S ONE MAP STYLESHEET, as a host links it.
     *
     * The chrome markup this bundle's chrome.js builds — the zoom column, the DIM
     * pill, the base-layer menu, the scroll-bargain hint — and Leaflet's own
     * controls need styling, and there must be exactly ONE copy of those rules or
     * two maps on the platform drift apart. They live beside the markup that
     * emits them rather than in a host app.css, so a fresh installation that
     * draws a map gets styled chrome without writing any CSS of its own. This
     * sheet also carries the .map-plate column, the .viewer imagery frame, the
     * legend and the fullscreen rules, so every plate in the product grows the
     * same way. It is served, versioned, out of this bundle's public/ dir, which
     * AssetMapper registers by itself. Leaflet's own sheet is not linked beside
     * it: the Leaflet bridge's controller imports it.
     */
    public const string STYLESHEET = 'bundles/atlas/map.css';

    /**
     * The AssetMapper namespace this bundle's JavaScript is served under, and
     * the npm-side name in assets/package.json — which must be the composer
     * package name with an '@', because that is the key Flex works from.
     */
    public const string ASSET_NAMESPACE = '@uhifadhi/atlas-bundle';

    /** Config lives under "atlas:", not the class-derived "atlas_bundle:". */
    protected string $extensionAlias = 'atlas';

    /**
     * THE BUNDLE CLASS SITS AT THE PACKAGE ROOT, beside this bundle's own
     * composer.json, because after a split the package root IS the bundle
     * root.
     *
     * AbstractBundle assumes otherwise. Its default "assume the modern
     * directory structure" answer is `dirname($file, 2)`, which is right for a
     * bundle whose class lives in src/ and two directories too high for one
     * whose class lives at the root — public/ would be looked for outside the
     * package, and the Leaflet build with it.
     *
     * @see vendor/symfony/http-kernel/Bundle/AbstractBundle.php
     */
    public function getPath(): string
    {
        return __DIR__;
    }

    public function configure(DefinitionConfigurator $definition): void
    {
        AtlasConfiguration::define($definition->rootNode());
    }

    public function prependExtension(ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        /*
         * The three shared map modules, shipped under an AssetMapper namespace
         * exactly as symfony/ux-turbo does (TurboExtension::prepend).
         *
         * This registers the DIRECTORY, which is all a BUNDLE can do: importmap
         * entries are read from the host's single importmap.php and AssetMapper
         * offers no extension point for a bundle to add to it.
         *
         * The IMPORT NAMES — uhifadhi/basemaps, uhifadhi/boundary,
         * uhifadhi/map-chrome — are contributed by the PACKAGE instead, from
         * assets/package.json's symfony.importmap block: Flex reads it on
         * install (given the symfony-ux keyword in composer.json) and runs
         * importmap:require once per entry. The two halves meet here — the
         * entries name files under this directory — so a rename on either side
         * without the other is a blank map, which is what
         * tests/Unit/Assets/ImportmapContributionTest.php exists to catch.
         *
         * Guarded, because AssetMapper is optional: a host could install this
         * bundle for the Leaflet build and the provider config alone.
         */
        if ($builder->hasExtension('framework') && interface_exists(AssetMapperInterface::class)) {
            $container->extension('framework', [
                'asset_mapper' => [
                    'paths' => [
                        __DIR__.'/assets' => self::ASSET_NAMESPACE,
                    ],
                ],
            ]);
        }

        /*
         * The plate template's namespace. Registered rather than left to the
         * bundle-name convention because the plate is rendered through an
         * INJECTED Twig environment, which resolves no bundle-relative path of
         * its own. Guarded the same way: an installation with no twig draws no
         * plate, and must still boot for the Leaflet build's sake.
         */
        if ($builder->hasExtension('twig')) {
            $container->extension('twig', [
                'paths' => [__DIR__.'/templates' => 'Atlas'],
            ], prepend: true);
        }
    }

    /**
     * @param array<string, mixed> $config
     */
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        // Static service wiring lives in a PHP config file (see config/services.php
        // for why PHP, not YAML). loadExtension keeps only the config-DRIVEN bits.
        $container->import('config/services.php');

        // Explicit wiring, no autowire/autoconfigure — see config/services.php for
        // the Symfony reusable-bundle rule and its citation.
        $services = $container->services();

        $satellite = self::stringKeyed($config['satellite'] ?? null);
        $google = self::stringKeyed($satellite['google'] ?? null);
        $custom = self::stringKeyed($satellite['custom'] ?? null);

        $provider = \is_string($satellite['provider'] ?? null) ? $satellite['provider'] : AtlasConfiguration::PROVIDER_ESRI;
        $maxZoom = \is_int($satellite['max_zoom'] ?? null) ? $satellite['max_zoom'] : AtlasConfiguration::DEFAULT_MAX_ZOOM;

        $builder->setParameter('atlas.satellite.provider', $provider);
        $builder->setParameter('atlas.satellite.max_zoom', $maxZoom);

        /*
         * The configured source as ONE service, so the Twig contract and the
         * catalogue tile cannot disagree about what this deployment draws.
         *
         * The api key normally arrives as an env placeholder and stays one: it is
         * passed straight through as an argument and resolved at runtime, so a
         * cached container is not a file with a key in it.
         */
        $services->set('atlas.satellite_source', SatelliteSource::class)
            ->args([
                $provider,
                \is_string($google['api_key'] ?? null) ? $google['api_key'] : '',
                \is_string($custom['url_template'] ?? null) ? $custom['url_template'] : null,
                \is_string($custom['attribution'] ?? null) ? $custom['attribution'] : null,
                $maxZoom,
            ]);

        /*
         * The Twig function that publishes it, registered wherever there is a
         * Twig at all. Checked through kernel.bundles rather than class_exists():
         * twig/twig is a hard dependency of this package, so the class is
         * autoloadable in our own test runs even when TwigBundle is absent, and
         * the tag would then reference a twig service that does not exist.
         */
        $bundles = $builder->hasParameter('kernel.bundles') ? $builder->getParameter('kernel.bundles') : [];
        if (\is_array($bundles) && isset($bundles['TwigBundle'])) {
            $services->set('atlas.twig_extension', MapExtension::class)
                ->args([service('atlas.satellite_source')])
                ->tag('twig.extension');
        }

        /*
         * The plate's renderer, registered only where UX Map is registered too:
         * the runtime references UX Map's own renderer service, and referencing
         * a service the container does not have would refuse to boot a host that
         * installed this bundle for the Leaflet build alone.
         *
         * The FUNCTION is declared either way — it is on the extension above —
         * so the failure a host sees for a missing bundle is "no runtime for
         * render_map", which names what is absent.
         */
        if (\is_array($bundles) && isset($bundles['TwigBundle'], $bundles['UXMapBundle'])) {
            $services->set('atlas.twig_plate_runtime', MapPlateRuntime::class)
                ->args([service('twig'), service('ux_map.renderers')])
                ->tag('twig.runtime');
        }
    }

    /**
     * Narrow a config sub-tree to the shape the rest of this class relies on.
     * The tree guarantees it already; the analyser sees only mixed.
     *
     * @return array<string, mixed>
     */
    private static function stringKeyed(mixed $value): array
    {
        if (!\is_array($value)) {
            return [];
        }

        $narrowed = [];
        foreach ($value as $key => $item) {
            if (\is_string($key)) {
                $narrowed[$key] = $item;
            }
        }

        return $narrowed;
    }
}
