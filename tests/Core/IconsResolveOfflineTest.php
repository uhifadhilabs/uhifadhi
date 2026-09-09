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

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\UX\Icons\IconRendererInterface;
use Uhifadhi\Core\Tests\Application\Kernel;

/**
 * EVERY GLYPH THE CORE DRAWS IS A FILE THE CORE SHIPS.
 *
 * ux-icons will happily fetch an unknown icon from a remote API and cache it,
 * which makes a missing icon invisible on a developer's machine and a blank
 * square on a deployment. An installation therefore turns that fetching off —
 * `ux_icons.iconify.on_demand: false` — and the throwaway application here is
 * configured the same way, so this suite asks the only question that matters:
 * with nothing to fetch from, does every name the core's own templates and
 * navigation ask for still draw?
 *
 * The names are READ OFF THE SOURCE rather than listed here. A list would be a
 * second place to maintain, and the failure it is meant to catch is exactly the
 * one where somebody adds a name and forgets the second place.
 *
 * @see https://symfony.com/bundles/ux-icons/current/index.html#icons-on-demand
 */
final class IconsResolveOfflineTest extends KernelTestCase
{
    /** Resolved, because every path below is matched against `/tests/` and this one contains it. */
    private static function root(): string
    {
        return (string) realpath(__DIR__.'/../..');
    }

    /** @var list<string> */
    private const array BUNDLES = ['RegistryBundle', 'ShellBundle', 'TeamBundle', 'AtlasBundle', 'AreaBundle'];

    protected static function getKernelClass(): string
    {
        return Kernel::class;
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        while (true) {
            $previous = set_exception_handler(static fn () => null);
            restore_exception_handler();
            if (null === $previous) {
                break;
            }
            restore_exception_handler();
        }
    }

    public function testTheCoreReferencesIconsAtAll(): void
    {
        // A guard on the reader itself: a regex that silently stopped matching
        // would turn every assertion below into a green loop over nothing.
        self::assertGreaterThan(15, \count(self::referencedIcons()));
    }

    public function testEveryIconTheCoreDrawsResolvesWithNothingToFetchFrom(): void
    {
        self::bootKernel();

        $renderer = self::getContainer()->get(IconRendererInterface::class);
        self::assertInstanceOf(IconRendererInterface::class, $renderer);

        foreach (self::referencedIcons() as $name => $where) {
            self::assertStringStartsWith(
                '<svg',
                $renderer->renderIcon($name),
                $name.' is drawn by '.$where.' and no file in the core answers to it.',
            );
        }
    }

    /**
     * Every icon name the five bundles ask for, mapped to one place it is asked
     * from. Templates name them literally; navigation rows carry them as data,
     * so the PHP sources are read for the same two prefixes.
     *
     * @return array<string, string>
     */
    private static function referencedIcons(): array
    {
        $names = [];

        foreach (self::BUNDLES as $bundle) {
            foreach (self::sourceFiles($bundle) as $file) {
                $contents = (string) file_get_contents($file);
                $short = substr($file, \strlen(self::root()) + 1);

                if (str_ends_with($file, '.twig')) {
                    preg_match_all('/ux_icon\(\s*\'([a-z0-9:-]+)\'/', $contents, $literal);
                    preg_match_all('/<twig:ux:icon[^>]*\sname="([a-z0-9:-]+)"/', $contents, $component);
                    foreach ([...$literal[1], ...$component[1]] as $name) {
                        $names[$name] ??= $short;
                    }

                    continue;
                }

                preg_match_all('/\'((?:lucide|shell):[a-z0-9-]+)\'/', $contents, $data);
                foreach ($data[1] as $name) {
                    $names[$name] ??= $short;
                }
            }
        }

        return $names;
    }

    /**
     * The bundle's shipped templates and PHP — never its tests, whose fixtures
     * name icons no installation ever draws.
     *
     * @return list<string>
     */
    private static function sourceFiles(string $bundle): array
    {
        $root = self::root().'/src/Uhifadhi/Bundle/'.$bundle;

        $files = [];
        /** @var iterable<\SplFileInfo> $iterator */
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            $path = $file->getPathname();
            if (!$file->isFile() || str_contains($path, '/tests/') || str_contains($path, '/docs/')) {
                continue;
            }
            if (str_ends_with($path, '.twig') || str_ends_with($path, '.php')) {
                $files[] = $path;
            }
        }

        return $files;
    }
}
