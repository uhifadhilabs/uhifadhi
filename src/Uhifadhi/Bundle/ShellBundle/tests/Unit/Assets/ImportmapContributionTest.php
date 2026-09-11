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

namespace Uhifadhi\Bundle\ShellBundle\Tests\Unit\Assets;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Uhifadhi\Bundle\ShellBundle\ShellBundle;

/**
 * NOBODY TYPES THIS BUNDLE'S IMPORTMAP ENTRIES BY HAND.
 *
 * A bundle cannot contribute an importmap entry; a PACKAGE can. The thing that
 * writes an installation's importmap.php is not AssetMapper, it is Flex: its
 * PackageJsonSynchronizer reads assets/package.json and runs `importmap:require`
 * once per entry of the `symfony.importmap` block, `path:` entries pointing back
 * into the package's own files included
 * (PackageJsonSynchronizer::resolveImportMapPackages and ::updateImportMap).
 * It opens that file only for a package that declares the keyword `symfony-ux`
 * (::resolvePackageJson) — without it everything installs, nothing is written,
 * and there is no error to read.
 *
 * WHAT WOULD BREAK WITHOUT THIS TEST is precisely what did: the library's script
 * shipped, every module's library page imported it by name, and the name was
 * published nowhere. A browser answers that with
 * `Failed to resolve module specifier`, on somebody else's machine, on every
 * library page in the installation — and every server-side test stays green,
 * because no test asks what a bare specifier resolves to.
 *
 * So the question is asked of the SOURCES: every name this bundle's templates
 * and documentation tell an importer to write is read off them here, and the
 * manifest is the answer.
 *
 * @see \Uhifadhi\Core\Tests\Core\ImportmapPackageTest for the second half — the
 *      root manifest is the file Flex actually opens, and it has to carry the
 *      same names
 */
final class ImportmapContributionTest extends TestCase
{
    /**
     * The shared modules this bundle publishes, as `specifier => file under assets/`.
     *
     * @return iterable<string, array{string, string}>
     */
    public static function sharedModules(): iterable
    {
        yield 'uhifadhi/widgets' => ['uhifadhi/widgets', 'widgets.js'];
    }

    /**
     * THE ONE KEYWORD THAT IS NOT DECORATION. Flex opens assets/package.json
     * only for a package that declares it.
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

        self::assertSame('@'.$composer['name'], ShellBundle::ASSET_NAMESPACE);
        self::assertSame(ShellBundle::ASSET_NAMESPACE, self::assetPackage()['name'] ?? null);
    }

    /**
     * `path:%PACKAGE%/…` is the form Flex resolves — %PACKAGE% becomes the
     * directory holding assets/package.json, so the entry names a real file
     * whatever the host's vendor layout is. It is the same form
     * symfony/stimulus-bundle ships its loader with.
     */
    #[DataProvider('sharedModules')]
    public function testEverySharedModuleIsDeclaredAsAPathEntryIntoThisBundle(string $specifier, string $file): void
    {
        $importmap = self::importmap();

        self::assertArrayHasKey($specifier, $importmap, \sprintf('%s is not declared, so Flex writes nothing and every page importing it is dead.', $specifier));
        self::assertSame('path:%PACKAGE%/'.$file, $importmap[$specifier]);
        self::assertFileExists(self::root().'/assets/'.$file);
    }

    /** Nothing else: an entry here is a line written into every installation. */
    public function testTheContributionCoversTheSharedModulesAndNothingElse(): void
    {
        $specifiers = [];
        foreach (self::sharedModules() as [$specifier]) {
            $specifiers[] = $specifier;
        }

        self::assertSame($specifiers, array_keys(self::importmap()));
    }

    /**
     * EVERY NAME THE SOURCES TELL SOMEBODY TO IMPORT IS PUBLISHED. The templates
     * and the docs are where a module author reads what to write; a name written
     * there and published nowhere is the defect this file exists for.
     */
    public function testEveryNameTheTemplatesAndDocsTellAnImporterToWriteIsPublished(): void
    {
        $declared = array_keys(self::importmap());
        $found = self::importedSpecifiers();

        self::assertNotSame([], $found, 'No source tells anybody what to import, which cannot be right.');

        foreach ($found as $specifier => $sources) {
            self::assertContains(
                $specifier,
                $declared,
                \sprintf('"%s" is imported by %s and published by nothing.', $specifier, implode(', ', $sources)),
            );
        }
    }

    /**
     * Every bare `uhifadhi/…` specifier this bundle's templates and docs put
     * inside an `import`, mapped to the files that write it.
     *
     * @return array<string, list<string>>
     */
    private static function importedSpecifiers(): array
    {
        $found = [];
        foreach (self::sourceFiles() as $file) {
            preg_match_all(
                '/\bimport\s+(?:[^\'"\n]*?\bfrom\s+)?[\'"](uhifadhi\/[a-z0-9-]+)[\'"]/',
                (string) file_get_contents($file),
                $matches,
            );

            foreach ($matches[1] as $specifier) {
                $found[$specifier][] = basename($file);
            }
        }

        return $found;
    }

    /** @return list<string> */
    private static function sourceFiles(): array
    {
        $files = [];
        foreach (['templates', 'docs'] as $directory) {
            /** @var iterable<\SplFileInfo> $iterator */
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator(self::root().'/'.$directory, \FilesystemIterator::SKIP_DOTS),
            );

            foreach ($iterator as $file) {
                if ($file->isFile()) {
                    $files[] = $file->getPathname();
                }
            }
        }

        sort($files);

        return $files;
    }

    /** @return array<string, mixed> */
    private static function importmap(): array
    {
        $symfony = self::assetPackage()['symfony'] ?? null;
        self::assertIsArray($symfony);
        $importmap = $symfony['importmap'] ?? null;
        self::assertIsArray($importmap);

        /** @var array<string, mixed> $importmap */
        return $importmap;
    }

    /** @return array<string, mixed> */
    private static function assetPackage(): array
    {
        return self::json(self::root().'/assets/package.json');
    }

    /** @return array<string, mixed> */
    private static function composer(): array
    {
        return self::json(self::root().'/composer.json');
    }

    /** @return array<string, mixed> */
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
