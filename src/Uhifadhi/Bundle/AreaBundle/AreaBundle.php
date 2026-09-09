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

namespace Uhifadhi\Bundle\AreaBundle;

use Symfony\Component\AssetMapper\AssetMapperInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;
use Uhifadhi\Bundle\AreaBundle\Entity\AreaOfInterest;
use Uhifadhi\Contracts\Entity\AreaInterface;

/**
 * AREA — the named piece of ground an installation manages.
 *
 * The skeleton is the application, the seam carries the modules, the shell is
 * what you see, team is who is looking, and this is WHERE. An area is the axis
 * the whole product is filed under: a patrol happens in one, an incident is
 * reported in one, a module is switched on for one. Until this module existed
 * every installation wrote its own, because the platform asked for an area and
 * shipped none.
 *
 * ZERO-CONFIG, AND THERE IS NO HAND-STEP LEFT. Registering the bundle maps its
 * own entity (no doctrine mappings block for `area_of_interest`) and ANSWERS THE
 * SEAM'S AREA CONTRACT (no `resolve_target_entities` either). With this bundle
 * and team installed, a bare installation reaches
 * `doctrine:migrations:diff` with zero doctrine edits — which is the whole
 * point, and was not true of any installation before this ring.
 *
 * IT DECLARES NO MODULE, deliberately. Every other bundle in the fleet carries
 * the `uhifadhi.module` tag and takes a tile in the catalogue. The catalogue is
 * indexed BY AREA — the seam's `area_module` row says "this area has this module
 * switched on" — so a provider here would write a row for every area saying the
 * area has areas: switchable, meaningless, and shown in the module grid of the
 * page it is the subject of. Areas are the AXIS the catalogue is indexed by, not
 * an entry in it. That is a different thing from being a BASE module, which is
 * still a capability an area HAS; this is the thing an area IS. Pinned by
 * Integration\CatalogueAbstentionTest.
 *
 * NO CONFIG TREE, EITHER. There is nothing here an installation would set: the
 * entity has no options, the table name is a compatibility promise rather than a
 * preference, and the one decision anybody could want to make — "my areas are my
 * own class" — is made by Symfony's own override rule below and not by a key
 * invented here. So there is no `configure()`, no `Configuration`, and the
 * recipe ships no `config/packages/area.yaml` to sit empty.
 */
final class AreaBundle extends AbstractBundle
{
    /**
     * WHERE THIS BUNDLE'S OWN VOCABULARY IS SERVED FROM. The area screens are
     * the shell's frame plus this sheet — the shell draws frames and knows
     * nothing about an identity band or an attention row, so those rules ship
     * here. Stated once, as a constant, because templates/_stylesheets.html.twig
     * links it and anyone theming these screens has to be able to name it.
     *
     * AssetMapper exposes a bundle's public/ directory under
     * `bundles/<lowercased bundle name>/` on its own; there is nothing to
     * configure for this to resolve.
     */
    public const string STYLESHEET = 'bundles/area/area.css';

    /**
     * WHERE THE REGISTER'S BEHAVIOUR IS MAPPED IN FROM. The search, filter and
     * sort controls are a Stimulus controller, and a bundle ships those through
     * AssetMapper the way every symfony/ux package does: an `assets/` directory
     * with a package.json naming the controllers, mapped under this namespace.
     * Flex writes the installation's assets/controllers.json on install and
     * nothing is built.
     */
    public const string ASSET_NAMESPACE = '@uhifadhi/area-bundle';

    /** Config would live under "area:", not the class-derived "uhifadhi_area:" — if there were any. */
    protected string $extensionAlias = 'area';

    /**
     * THE BUNDLE CLASS SITS AT THE PACKAGE ROOT, beside this bundle's own
     * composer.json, because after a split the package root IS the bundle
     * root.
     *
     * AbstractBundle assumes otherwise. Its default "assume the modern
     * directory structure" answer is `dirname($file, 2)`, which is right for a
     * bundle whose class lives in src/ and two directories too high for one
     * whose class lives at the root — templates/ and public/ would be looked
     * for outside the package.
     *
     * @see vendor/symfony/http-kernel/Bundle/AbstractBundle.php
     */
    public function getPath(): string
    {
        return __DIR__;
    }

    public function prependExtension(ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        /*
         * THE TEMPLATE NAMESPACE. Registered here rather than left to the
         * bundle-name convention because these templates are rendered through an
         * injected Twig environment — a reusable bundle's controller extends
         * nothing and so has no render() to resolve a bundle-relative path for
         * it. Guarded: an installation with no twig is one that mounted none of
         * these screens, and it must still boot for the entity's sake.
         */
        if ($builder->hasExtension('twig')) {
            $container->extension('twig', [
                'paths' => [__DIR__.'/templates' => 'Area'],
            ], prepend: true);
        }

        /*
         * The register's Stimulus controller. The bundle's public/ directory
         * needs none of this — AssetMapper exposes it as `bundles/area/`
         * on its own — but assets/ has to be mapped by name.
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

        if (!$builder->hasExtension('doctrine')) {
            return;
        }

        /*
         * PREPENDED, AND THE FLAG IS LOAD-BEARING. `extension()` APPENDS by
         * default even when called from prependExtension() — which would put
         * this config LAST, where it OVERRULES the installation instead of
         * deferring to it. With `prepend: true` it goes first and the
         * application's own doctrine config wins, which is the entire reason
         * shipping a resolution here is safe rather than presumptuous.
         */
        $container->extension('doctrine', [
            'orm' => [
                // Zero-config persistence: the bundle maps its own entity, so an
                // installation never writes a mappings block for area_of_interest.
                'mappings' => [
                    'Area' => [
                        'type' => 'attribute',
                        'dir' => __DIR__.'/Entity',
                        'prefix' => 'Uhifadhi\\Bundle\\AreaBundle\\Entity',
                        'is_bundle' => false,
                    ],
                ],

                /*
                 * WHOEVER KNOWS THE ANSWER STATES THE RESOLUTION.
                 *
                 * The seam owns the record of which modules an area has switched
                 * on, and CANNOT name an area class: it holds that table for
                 * installations whose area model is their own. So it maps the
                 * association at a contract and somebody has to close the loop.
                 * For as long as this module is installed the answer is not in
                 * doubt — it is this module's AreaOfInterest — and the package
                 * that provides the answer is the package that states it.
                 *
                 * IT USED TO BE A DOCUMENTED HAND-STEP, and it was the fleet's
                 * oldest: write a placeholder class, then uncomment a block in
                 * config/packages/seam.yaml. Wrong shape twice over. A hand-step
                 * is for a decision only the installation can make, and "what is
                 * an area" was only a decision because nothing shipped one. And
                 * its cost was real, because forgetting either half fails a long
                 * way from its cause: the container compiles, the kernel boots,
                 * and `doctrine:migrations:diff` stops on "Class
                 * 'Uhifadhi\Contracts\Entity\AreaInterface' does not exist" with
                 * nothing pointing back at the paragraph that was missed.
                 *
                 * THE ESCAPE HATCH IS SYMFONY'S OWN RULE and not a switch
                 * invented here: prepended configuration LOSES to the
                 * application's. An installation whose areas are its own entity
                 * names that class under `doctrine.orm.resolve_target_entities`
                 * in its own config and its answer wins, with nothing to disable
                 * first. That property is precisely what makes shipping a
                 * default safe, so it is tested rather than assumed — see
                 * tests/Integration/Resolution/ResolveTargetEntitiesTest.
                 */
                'resolve_target_entities' => [
                    AreaInterface::class => AreaOfInterest::class,
                ],
            ],
        ], prepend: true);
    }

    /**
     * @param array<string, mixed> $config
     */
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        // Static service wiring lives in a PHP config file (see config/services.php
        // for why PHP, not YAML). There are no config-DRIVEN bits: this bundle
        // has no configuration tree.
        $container->import('config/services.php');

        /*
         * THE SCREENS ARE CONDITIONAL, AND THE ENTITY IS NOT.
         *
         * This module is a model an installation persists AND a set of pages.
         * The model has no opinion about twig: an installation that wants only
         * the area entity — a console importer, an API, this bundle's own bare
         * test kernel — must still boot. A controller registered there would
         * fail at COMPILE time with "has a dependency on a non-existent service
         * twig", a long way from the decision that caused it.
         *
         * CHECKED ON `kernel.bundles`, NOT ON hasExtension(), and that is a real
         * constraint rather than a preference. During extension LOAD the
         * container handed to a bundle is MergeExtensionConfigurationContainerBuilder,
         * a restricted builder that carries no extensions at all — so
         * `$builder->hasExtension('twig')` is FALSE here even in an application
         * that plainly has TwigBundle, and every screen would silently vanish.
         * `hasExtension()` is meaningful in prependExtension() (where it is used
         * above) and not in this method.
         *
         * The bundle list is the right signal anyway: `twig/twig` can arrive as a
         * transitive dependency without TwigBundle ever being registered, and it
         * is the BUNDLE — the thing that defines the `twig` service — that these
         * controllers need.
         *
         * ASKED BY NAME, NOT BY CLASS CONSTANT. `kernel.bundles` is keyed by a
         * bundle's short name, so the question can be put without naming
         * either class — which is what keeps twig-bundle and security-bundle
         * optional here in fact as well as in the manifest: a `::class`
         * constant in this file would be a symbol from a package this bundle
         * does not require, and the boundary check says so.
         *
         * @see vendor/symfony/framework-bundle/DependencyInjection/FrameworkExtension.php,
         *      which reads the same parameter to decide what a bundle's
         *      presence enables
         */
        $bundles = $builder->getParameter('kernel.bundles');
        \assert(\is_array($bundles));

        if (isset($bundles['TwigBundle'], $bundles['SecurityBundle'])) {
            $container->import('config/screens.php');
        }
    }
}
