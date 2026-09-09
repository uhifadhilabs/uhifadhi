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

namespace Uhifadhi\Bundle\AreaBundle\Tests\Unit\Template;

use PHPUnit\Framework\TestCase;

/**
 * THE DESIGN WORKSPACE'S LABELS DO NOT SHIP.
 *
 * Every widget in the design files carries an identifier — AO·04, PL·A1, NX·01,
 * RO·C1 — printed in the card's tab. That is the workspace's own referencing
 * system: it is how a decisions table points at a frame, and it exists so two
 * people arguing about a design can name the thing they are arguing about. It is
 * not product. A person on an area overview has no decisions table, cannot
 * follow the reference, and reads "AO·04Needs attention" as a typo.
 *
 * THIS FILE IS WRITTEN BEFORE THE FIRST TEMPLATE, deliberately. Elsewhere in the
 * fleet these labels were ported into shipped templates and rendered in a live
 * installation before anybody noticed, and the sweep to remove them touched
 * every widget in the module. The cheapest time to pin the rule is while the
 * templates directory is still empty.
 *
 * The design COMMENTS at the top of a template still name the widgets, and
 * should — that is where the reference belongs, and a comment reaches nobody's
 * screen. Only markup is scanned.
 *
 * THE OTHER DESIGNER CHROME is pinned here too, because it comes from the same
 * files and ships the same way: `design-only` controls (the workspace's own
 * state togglers), the `.dq` decisions table, and the `ao-rej` rejected-frame
 * banner. None of the three is product, and each says so in the design source.
 */
final class NoWorkshopLabelsTest extends TestCase
{
    /**
     * The chip's own class, the identifier itself in the three spellings a
     * template can carry it, and the workspace furniture that is not product.
     *
     * The prefixes are every contributor the area surfaces draw: the host's own
     * AO, the not-installed seam's NX, and the module prefixes whose widgets are
     * rendered by THIS module's templates on the overview.
     */
    private const array FORBIDDEN = [
        'idx-chip' => '/class="[^"]*\bidx\b[^"]*"/',
        'literal middot' => '/\b(?:AO|NX|PL|IN|RO|ZN|TM|PM|DP|MB|AU)\x{00B7}/u',
        'named entity' => '/\b(?:AO|NX|PL|IN|RO|ZN|TM|PM|DP|MB|AU)&middot;/',
        'numeric entity' => '/\b(?:AO|NX|PL|IN|RO|ZN|TM|PM|DP|MB|AU)&#(?:183|xB7);/i',
        'design-only control' => '/\bdesign-only\b/',
        'decisions table' => '/class="[^"]*\bdq\b[^"]*"/',
        'rejected-frame banner' => '/\bao-rej\b/',
    ];

    /** @return iterable<string, array{string}> */
    public static function templates(): iterable
    {
        $root = \dirname(__DIR__, 3).'/templates';
        if (!is_dir($root)) {
            // This ring ships no templates yet. The rule still stands, and the
            // moment the directory appears every file in it is scanned.
            yield 'no templates yet' => [''];

            return;
        }

        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS));

        foreach ($files as $file) {
            \assert($file instanceof \SplFileInfo);
            if ('twig' !== $file->getExtension()) {
                continue;
            }
            yield substr($file->getPathname(), \strlen($root) + 1) => [$file->getPathname()];
        }
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('templates')]
    public function testNoTemplateEmitsAWorkshopLabel(string $path): void
    {
        if ('' === $path) {
            self::assertFalse(is_dir(\dirname(__DIR__, 3).'/templates'));

            return;
        }

        $markup = self::withoutComments((string) file_get_contents($path));

        foreach (self::FORBIDDEN as $what => $pattern) {
            self::assertSame(
                0,
                preg_match($pattern, $markup),
                \sprintf(
                    '%s carries a design-workshop %s. Widget identifiers and workspace furniture belong in the design files, never in shipped markup.',
                    basename($path),
                    $what,
                ),
            );
        }
    }

    /** Twig comments are not markup: a design note may name every widget it likes. */
    private static function withoutComments(string $twig): string
    {
        return (string) preg_replace('/\{#.*?#\}/s', '', $twig);
    }
}
