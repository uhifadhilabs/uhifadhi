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

use PHPUnit\Framework\TestCase;

/**
 * ONE PACKAGE ANSWERS FOR EVERY CONTROLLER THE CORE SHIPS.
 *
 * An installation's `assets/controllers.json` names a Composer package, and
 * StimulusBundle resolves that name through `InstalledVersions::getInstallPath()`
 * before looking for `assets/package.json` underneath it
 * ({@see \Symfony\UX\StimulusBundle\Ux\UxPackageReader::readPackageMetadata}).
 * A name this package only `replace`s has no install path of its own, so
 * `@uhifadhi/uhifadhi` is the only name an installation can write — and this
 * repository's root `assets/package.json` is therefore the one manifest every
 * core controller has to appear in.
 *
 * WHAT WOULD BREAK WITHOUT THIS TEST: a bundle gains a Stimulus controller,
 * ships it, wires a `data-controller` attribute to it, and the attribute names
 * nothing — the manifest is a separate file and nothing links the two. So the
 * files on disk are the question and the manifest is the answer, checked in
 * both directions.
 */
final class ControllerPackageTest extends TestCase
{
    private const string ROOT = __DIR__.'/../..';

    /** The bundles that ship `assets/controllers/`; Atlas contributes modules to an importmap instead. */
    private const array BUNDLES = ['ShellBundle', 'TeamBundle', 'AreaBundle'];

    public function testTheCoreShipsOneControllerManifestNamedForThePackage(): void
    {
        $manifest = self::manifest();

        self::assertArrayHasKey('name', $manifest);
        self::assertSame('@uhifadhi/uhifadhi', $manifest['name']);
    }

    /**
     * AND THE MANIFEST IS READ, which is a second thing and a separate keyword.
     *
     * On install it is Flex, not StimulusBundle, that copies a package's
     * controllers into the application's `assets/controllers.json`, and it opens
     * `assets/package.json` only for a package whose composer manifest declares
     * the keyword `symfony-ux`
     * ({@see vendor/symfony/flex/src/PackageJsonSynchronizer.php} —
     * `resolvePackageJson()` returns null before it looks at any directory, and
     * `synchronizeForAssetMapper()` therefore registers nothing for it).
     *
     * The keyword lives on the package an installation INSTALLS. Every bundle
     * here declares it already, and no installation has any of them: they are
     * names this repository `replace`s, with no install path for Flex or anybody
     * else to open. The root package is the one on disk, so the root manifest is
     * the one whose keyword decides whether twelve controllers arrive or none —
     * silently, with a green install either way.
     */
    public function testTheInstalledPackageIsMarkedAsAUxPackageOrFlexNeverOpensTheManifest(): void
    {
        $keywords = self::composer()['keywords'] ?? null;

        self::assertIsArray($keywords);
        self::assertContains(
            'symfony-ux',
            $keywords,
            'Without it Flex skips assets/package.json and an installation gets no controllers at all.',
        );
    }

    /**
     * AND EACH BUNDLE THAT SHIPS CONTROLLERS KEEPS ITS OWN, because the day one
     * of them is split out it is the installed package and the same sentence
     * applies to it. A keyword dropped from a bundle costs nothing today, which
     * is exactly why it would go unnoticed until the split.
     */
    public function testEveryBundleThatShipsControllersIsMarkedTooForTheDayItIsSplit(): void
    {
        foreach (self::BUNDLES as $bundle) {
            $keywords = self::composer(self::ROOT.'/src/Uhifadhi/Bundle/'.$bundle)['keywords'] ?? null;

            self::assertIsArray($keywords);
            self::assertContains('symfony-ux', $keywords, $bundle.' ships controllers nothing would register once it is a package of its own.');
        }
    }

    /**
     * EVERY CONTROLLER FILE IS LISTED, and the path is a real file. A `main`
     * that points at nothing throws only when an installation compiles its
     * asset map, which is somebody else's machine.
     */
    public function testEveryControllerEveryBundleShipsIsListed(): void
    {
        $mains = array_column(self::controllers(), 'main');

        foreach (self::controllerFiles() as $relative) {
            self::assertContains(
                '../'.$relative,
                $mains,
                $relative.' is shipped but no root manifest entry points at it.',
            );
        }
    }

    /** And nothing is listed that is not there. */
    public function testEveryListedControllerIsAFileTheCoreShips(): void
    {
        foreach (self::controllers() as $key => $controller) {
            self::assertFileExists(
                self::ROOT.'/assets/'.$controller['main'],
                'the manifest entry "'.$key.'" points at no file.',
            );
        }
    }

    /**
     * THE NAME IN THE MARKUP IS DECLARED, NOT DERIVED.
     *
     * StimulusBundle names a controller after the package it came from unless
     * the manifest says otherwise — so resolving these through the core package
     * rather than a per-bundle one would rename every controller in every
     * template. Each entry carries an explicit `name`, which is the name the
     * templates use and the name a split per-bundle package would produce.
     */
    public function testEveryEntryDeclaresTheNameTheMarkupUses(): void
    {
        foreach (self::controllers() as $key => $controller) {
            self::assertMatchesRegularExpression(
                '/^uhifadhi--[a-z-]+-bundle--[a-z-]+$/',
                $controller['name'],
                'the entry "'.$key.'" does not declare the name the markup uses.',
            );
        }
    }

    /** Every name a template asks for is a name the manifest declares. */
    public function testEveryControllerNameTheTemplatesAskForIsDeclared(): void
    {
        $declared = array_column(self::controllers(), 'name');

        foreach (self::BUNDLES as $bundle) {
            foreach (self::twigFiles($bundle) as $file) {
                preg_match_all('/data-controller="([^"]+)"/', (string) file_get_contents($file), $matches);
                foreach ($matches[1] as $attribute) {
                    foreach (explode(' ', trim($attribute)) as $name) {
                        if (str_starts_with($name, 'uhifadhi--')) {
                            self::assertContains($name, $declared, $file.' asks for a controller the manifest does not declare.');
                        }
                    }
                }
            }
        }
    }

    /**
     * The manifest's controller entries, keyed as the manifest keys them.
     *
     * @return array<string, array{main: string, name: string}>
     */
    private static function controllers(): array
    {
        $manifest = self::manifest();
        self::assertIsArray($manifest['symfony'] ?? null);
        self::assertIsArray($manifest['symfony']['controllers'] ?? null);

        $controllers = [];
        foreach ($manifest['symfony']['controllers'] as $key => $controller) {
            self::assertIsString($key);
            self::assertIsArray($controller);
            self::assertArrayHasKey('main', $controller, 'the entry "'.$key.'" names no file.');
            self::assertArrayHasKey('name', $controller, 'the entry "'.$key.'" derives its name instead of declaring it.');
            self::assertIsString($controller['main']);
            self::assertIsString($controller['name']);

            $controllers[$key] = ['main' => $controller['main'], 'name' => $controller['name']];
        }

        return $controllers;
    }

    /**
     * A composer manifest, the root's unless another directory is named.
     *
     * @return array<mixed>
     */
    private static function composer(?string $directory = null): array
    {
        $path = ($directory ?? self::ROOT).'/composer.json';
        self::assertFileExists($path);

        $data = json_decode((string) file_get_contents($path), true, 512, \JSON_THROW_ON_ERROR);
        self::assertIsArray($data);

        return $data;
    }

    /** @return array<mixed> */
    private static function manifest(): array
    {
        $path = self::ROOT.'/assets/package.json';
        self::assertFileExists($path, 'the core ships one controller manifest, at assets/package.json.');

        $data = json_decode((string) file_get_contents($path), true, 512, \JSON_THROW_ON_ERROR);
        self::assertIsArray($data);

        return $data;
    }

    /**
     * Every controller file the bundles ship, as a path relative to the
     * repository root.
     *
     * @return list<string>
     */
    private static function controllerFiles(): array
    {
        $files = [];
        foreach (self::BUNDLES as $bundle) {
            $dir = 'src/Uhifadhi/Bundle/'.$bundle.'/assets/controllers';
            foreach (glob(self::ROOT.'/'.$dir.'/*_controller.js') ?: [] as $file) {
                $files[] = $dir.'/'.basename($file);
            }
        }

        return $files;
    }

    /** @return list<string> */
    private static function twigFiles(string $bundle): array
    {
        $files = [];
        $dir = self::ROOT.'/src/Uhifadhi/Bundle/'.$bundle.'/templates';
        /** @var iterable<\SplFileInfo> $iterator */
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            if ($file->isFile() && str_ends_with($file->getFilename(), '.html.twig')) {
                $files[] = $file->getPathname();
            }
        }

        return $files;
    }
}
