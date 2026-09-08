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

namespace Uhifadhi\Bundle\ShellBundle\Tests\Unit\Template;

use PHPUnit\Framework\TestCase;

/**
 * EVERY TEMPLATE NAMESPACE THE SHELL SPENDS IS ONE SOMEBODY REGISTERS.
 *
 * A Twig namespace is resolved at RENDER time, so a stale `@Something/…` in a
 * partial that only one screen includes is invisible until that screen is
 * loaded — and it is invisible to this bundle's own suite for as long as no
 * test renders that particular branch. Read as text, the reference is checkable
 * without a kernel and without the page.
 *
 * The list is the namespaces a bundle in this package registers, which is its
 * own name with the `Bundle` suffix dropped.
 */
final class TemplateNamespaceTest extends TestCase
{
    /**
     * Every bundle in the core, twice: as Twig addresses a template (the
     * bundle's name with the `Bundle` suffix dropped) and as the kernel
     * addresses a file inside the package (the bundle's name whole, which is
     * how a routing resource is written).
     */
    private const array KNOWN = [
        '@Shell', '@Team', '@Atlas', '@Area',
        '@ShellBundle', '@TeamBundle', '@AtlasBundle', '@AreaBundle',
    ];

    public function testEveryNamespaceReferencedIsOneTheCoreRegisters(): void
    {
        $offenders = [];

        foreach (self::sources() as $path => $code) {
            preg_match_all('/@[A-Z][A-Za-z]*(?=\/)/', $code, $matches);
            foreach (array_unique($matches[0]) as $namespace) {
                if (!\in_array($namespace, self::KNOWN, true)) {
                    $offenders[] = $path.': '.$namespace;
                }
            }
        }

        self::assertSame([], $offenders, 'A template namespace nothing registers is a page that 500s the first time somebody opens it.');
    }

    /**
     * @return array<string, string> relative path => source
     */
    private static function sources(): array
    {
        $root = realpath(__DIR__.'/../../..');
        \assert(false !== $root);

        $files = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS),
        );

        /** @var \SplFileInfo $file */
        foreach ($iterator as $file) {
            if (!\in_array($file->getExtension(), ['twig', 'js'], true)) {
                continue;
            }
            $relative = substr($file->getPathname(), \strlen($root) + 1);
            if (str_starts_with($relative, 'tests/')) {
                continue;
            }
            $code = file_get_contents($file->getPathname());
            if (false === $code) {
                continue;
            }
            $files[$relative] = $code;
        }

        ksort($files);

        return $files;
    }
}
