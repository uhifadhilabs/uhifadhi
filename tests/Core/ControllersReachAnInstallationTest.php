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

namespace Uhifadhi\Core\Tests\Core;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * A CONTROLLER AN INSTALLATION NEVER RECEIVES IS A CONTROLLER THAT DOES
 * NOTHING — and it fails silently, which is the whole problem.
 *
 * Flex hands a package's Stimulus controllers to an installation on two
 * conditions, and BOTH are in the package manifest: the package is keyworded
 * `symfony-ux`, and the controller is declared in its `assets/package.json`.
 * Miss either and the page renders, the markup carries `data-controller`, and
 * nothing happens — no error, no warning, no failing test anywhere. A module
 * lost its controllers exactly that way.
 *
 * SO IT IS CHECKED HERE, over the manifests as they are published. A file
 * added to `assets/controllers/` without a line in the manifest beside it is
 * what this catches, because that is the mistake somebody actually makes.
 *
 * @see https://symfony.com/bundles/StimulusBundle/current/index.html#ux-packages
 */
#[CoversNothing]
final class ControllersReachAnInstallationTest extends TestCase
{
    /** @return \Generator<string, array{string}> */
    public static function bundles(): \Generator
    {
        foreach (glob(self::root().'/src/Uhifadhi/Bundle/*', \GLOB_ONLYDIR) ?: [] as $path) {
            yield basename($path) => [$path];
        }
    }

    /**
     * EVERY CONTROLLER A BUNDLE SHIPS IS DECLARED IN ITS OWN MANIFEST.
     *
     * The file is the thing somebody adds; the manifest line is the thing
     * they forget, and nothing else in the build notices.
     */
    #[DataProvider('bundles')]
    public function testEveryControllerABundleShipsIsDeclaredInItsManifest(string $bundle): void
    {
        $shipped = self::shippedControllers($bundle);
        if ([] === $shipped) {
            self::assertFileDoesNotExist($bundle.'/assets/controllers', 'a bundle with no controllers ships no directory for them');

            return;
        }

        $declared = [];
        foreach (self::manifest($bundle)['controllers'] as $controller) {
            $declared[] = basename($controller['main']);
        }
        sort($declared);

        self::assertSame($shipped, $declared, \sprintf(
            '%s ships a controller its manifest does not declare. Flex hands over what the manifest names, '
            .'so the page renders, the markup carries data-controller, and nothing happens.',
            basename($bundle),
        ));
    }

    /**
     * AND THE PACKAGE SAYS IT IS A UX PACKAGE. Flex only looks at the
     * controllers of a package keyworded `symfony-ux`; without it the
     * manifest is complete and still never read.
     */
    #[DataProvider('bundles')]
    public function testABundleThatShipsControllersIsKeywordedSymfonyUx(string $bundle): void
    {
        if ([] === self::shippedControllers($bundle)) {
            // A bundle with no controllers needs no keyword, and saying so is
            // the whole assertion: the registry ships none and must not be
            // made to claim it does.
            self::assertFileDoesNotExist($bundle.'/assets/controllers');

            return;
        }

        $composer = json_decode((string) file_get_contents($bundle.'/composer.json'), true, 512, \JSON_THROW_ON_ERROR);
        self::assertIsArray($composer);
        $keywords = $composer['keywords'] ?? [];
        self::assertIsArray($keywords);

        self::assertContains('symfony-ux', $keywords, \sprintf(
            '%s ships Stimulus controllers, so its package has to say so — Flex reads the controllers of a '
            .'symfony-ux package and nobody else\'s.',
            basename($bundle),
        ));
    }

    /**
     * AND THE INSTALLATION'S OWN MANIFEST NAMES EVERY ONE OF THEM. The
     * application here is not installed through Flex — it IS the monorepo —
     * so its `assets/package.json` is where the same list has to be written
     * by hand, and the two drifting is how a controller works in a park
     * install and not in this one.
     */
    public function testTheApplicationsManifestNamesEveryControllerTheBundlesShip(): void
    {
        $fromBundles = [];
        foreach (self::bundles() as [$bundle]) {
            foreach (self::shippedControllers($bundle) as $file) {
                $fromBundles[] = $file;
            }
        }
        sort($fromBundles);

        $application = [];
        foreach (self::manifest(self::root())['controllers'] as $controller) {
            if (str_contains($controller['main'], '/Bundle/')) {
                $application[] = basename($controller['main']);
            }
        }
        sort($application);

        self::assertSame($fromBundles, $application);
    }

    /**
     * The controller files a bundle ships, sorted.
     *
     * @return list<string>
     */
    private static function shippedControllers(string $bundle): array
    {
        $files = glob($bundle.'/assets/controllers/*_controller.js') ?: [];
        $names = array_map(basename(...), $files);
        sort($names);

        return $names;
    }

    /**
     * One manifest's `symfony` block.
     *
     * @return array{controllers: array<string, array{main: string}>}
     */
    private static function manifest(string $path): array
    {
        $package = json_decode((string) file_get_contents($path.'/assets/package.json'), true, 512, \JSON_THROW_ON_ERROR);
        self::assertIsArray($package);
        $symfony = $package['symfony'] ?? [];
        self::assertIsArray($symfony);
        $controllers = $symfony['controllers'] ?? [];
        self::assertIsArray($controllers);

        /** @var array{controllers: array<string, array{main: string}>} $shape */
        $shape = ['controllers' => $controllers];

        return $shape;
    }

    private static function root(): string
    {
        return \dirname(__DIR__, 2);
    }
}
