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

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Uhifadhi\Bundle\AtlasBundle\AtlasBundle;
use Uhifadhi\Bundle\AtlasBundle\Tests\Integration\Asset\AssetContractTest;
use Uhifadhi\Bundle\AtlasBundle\Twig\MapPlateRuntime;

/**
 * NOBODY TYPES THIS BUNDLE'S IMPORTMAP ENTRIES BY HAND.
 *
 * "A bundle cannot contribute an importmap entry" is true of AssetMapper and is
 * not the whole story, because the thing that writes an application's
 * importmap.php on install is not AssetMapper, it is Flex.
 *
 * Flex's PackageJsonSynchronizer reads a package's assets/package.json and, when
 * the host has an importmap.php, runs `importmap:require` once per entry of the
 * `symfony.importmap` block — including `path:` entries pointing back into the
 * package's own files (PackageJsonSynchronizer::resolveImportMapPackages and
 * ::updateImportMap). That is exactly the three lines, written by machine.
 *
 * It reads that file ONLY if the composer package declares the keyword
 * `symfony-ux` (::resolvePackageJson). Without the keyword everything installs
 * and nothing is written — no error, just a blank map on every page that draws
 * one. So the keyword is asserted here rather than trusted to survive the next
 * edit of composer.json.
 *
 * What this file pins is the contribution as a contract: the keyword, the
 * package name Flex keys by, the three bare specifiers (which are the public
 * names every importer in the product types), and that each one points at a file
 * this bundle actually ships.
 */
final class ImportmapContributionTest extends TestCase
{
    /**
     * The three shared modules, as `specifier => file under assets/`.
     *
     * The specifiers are the same list AssetContractTest resolves against a real
     * AssetMapper; that file owns the logical paths, this one owns what Flex is
     * told to write, and testTheContributionCoversEverySharedModule below keeps
     * the two from drifting apart.
     *
     * @return iterable<string, array{string, string}>
     */
    public static function sharedModules(): iterable
    {
        yield 'uhifadhi/basemaps' => ['uhifadhi/basemaps', 'basemaps.js'];
        yield 'uhifadhi/boundary' => ['uhifadhi/boundary', 'boundary.js'];
        yield 'uhifadhi/map-chrome' => ['uhifadhi/map-chrome', 'chrome.js'];
    }

    /**
     * THE ONE KEYWORD THAT IS NOT DECORATION. Flex opens assets/package.json only
     * for a package that declares it; drop it and the importmap block below is a
     * file nobody reads.
     */
    public function testThePackageIsMarkedAsAUxPackageOrFlexWillNotLookInside(): void
    {
        $keywords = self::composer()['keywords'] ?? null;

        self::assertIsArray($keywords);
        self::assertContains('symfony-ux', $keywords);
    }

    /**
     * The npm-side name is the composer name with an '@' — which is also the
     * AssetMapper namespace this bundle prepends, so the two cannot disagree.
     */
    public function testTheAssetPackageIsNamedTheWayFlexWillKeyIt(): void
    {
        $composer = self::composer();
        self::assertIsString($composer['name'] ?? null);

        self::assertSame('@'.$composer['name'], AtlasBundle::ASSET_NAMESPACE);
        self::assertSame(AtlasBundle::ASSET_NAMESPACE, self::assetPackage()['name'] ?? null);
    }

    /**
     * THE THREE LINES, DECLARED. `path:%PACKAGE%/…` is the form Flex resolves —
     * %PACKAGE% becomes the directory holding assets/package.json, so the entry
     * names a real file in this bundle whatever the host's vendor layout is. It
     * is the same form symfony/stimulus-bundle ships its loader with.
     */
    #[DataProvider('sharedModules')]
    public function testEverySharedModuleIsDeclaredAsAPathEntryIntoThisBundle(string $specifier, string $file): void
    {
        $importmap = self::importmap();

        self::assertArrayHasKey($specifier, $importmap, \sprintf('%s is not declared, so Flex writes nothing and every map importing it is blank.', $specifier));
        self::assertSame('path:%PACKAGE%/'.$file, $importmap[$specifier]);
        self::assertFileExists(self::root().'/assets/'.$file);
    }

    /**
     * Nothing else, and nothing missing. An entry here is a line written into
     * every host on the planet, so the block is the whole list rather than a
     * superset of it — and the list is the one AssetContractTest proves resolves.
     */
    public function testTheContributionCoversEverySharedModule(): void
    {
        $specifiers = [];
        $logicalPaths = [];
        foreach (self::sharedModules() as [$specifier, $file]) {
            $specifiers[] = $specifier;
            $logicalPaths[$specifier] = AtlasBundle::ASSET_NAMESPACE.'/'.$file;
        }

        self::assertSame($specifiers, array_keys(self::importmap()));

        $resolved = [];
        foreach (AssetContractTest::sharedModules() as $specifier => [$logicalPath]) {
            $resolved[$specifier] = $logicalPath;
        }

        self::assertSame($resolved, $logicalPaths, 'The specifiers Flex writes and the logical paths AssetMapper resolves are one list; they have drifted.');
    }

    /**
     * THE ONE CONTROLLER, DECLARED. A Stimulus controller is not an importmap
     * entry: StimulusBundle reads it from the same assets/package.json, under
     * `symfony.controllers`, and an installation enables it in its own
     * controllers.json.
     *
     * The `name` is explicit and asserted, because the identifier StimulusBundle
     * would DERIVE is the package key plus the controller name — and the core
     * ships as one composer package whose assets/package.json aggregates every
     * bundle's controllers, so the derived identifier would say
     * "uhifadhi--uhifadhi--map-plate". Every plate on the platform carries the
     * name below instead, written by render_map().
     */
    public function testThePlateControllerIsDeclaredUnderTheNameEveryPlateCarries(): void
    {
        $symfony = self::assetPackage()['symfony'] ?? null;
        self::assertIsArray($symfony);
        $controllers = $symfony['controllers'] ?? null;
        self::assertIsArray($controllers);

        self::assertSame(['map-plate'], array_keys($controllers));

        $plate = $controllers['map-plate'];
        self::assertIsArray($plate);
        self::assertSame(MapPlateRuntime::CONTROLLER, $plate['name'] ?? null);
        self::assertTrue($plate['enabled'] ?? false);
        self::assertIsString($plate['main'] ?? null);
        self::assertFileExists(self::root().'/assets/'.$plate['main']);
    }

    /**
     * @return array<string, mixed>
     */
    private static function importmap(): array
    {
        $symfony = self::assetPackage()['symfony'] ?? null;
        self::assertIsArray($symfony);
        $importmap = $symfony['importmap'] ?? null;
        self::assertIsArray($importmap);

        /** @var array<string, mixed> $importmap */
        return $importmap;
    }

    /**
     * @return array<string, mixed>
     */
    private static function assetPackage(): array
    {
        return self::json(self::root().'/assets/package.json');
    }

    /**
     * @return array<string, mixed>
     */
    private static function composer(): array
    {
        return self::json(self::root().'/composer.json');
    }

    /**
     * @return array<string, mixed>
     */
    private static function json(string $path): array
    {
        self::assertFileExists($path);
        $decoded = json_decode((string) file_get_contents($path), true, 512, \JSON_THROW_ON_ERROR);
        self::assertIsArray($decoded);

        /** @var array<string, mixed> $decoded */
        return $decoded;
    }

    private static function root(): string
    {
        return \dirname(__DIR__, 3);
    }
}
