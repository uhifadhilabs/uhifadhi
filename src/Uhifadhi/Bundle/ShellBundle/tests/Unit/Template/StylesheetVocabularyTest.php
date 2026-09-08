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
 * NOTHING MAY SPEND A NAME NOBODY SHIPS, AND NOTHING MAY RESTATE A SHARED ONE.
 *
 * The shell owns the design system: the two palettes, the derived aliases every
 * other sheet references, and the shared component classes. Two failure modes
 * follow from that ownership and neither shows up in a rendered test, because a
 * missing class does not throw — it falls back to browser defaults, which look
 * fine on a page that happens to load the design's own sheet and broken in a
 * real installation.
 *
 *   1. A CLASS OR TOKEN NOBODY SHIPS. A template writes `class="mchip"`, no
 *      sheet in the chain defines it, and the element renders as unstyled
 *      markup. This is the bug four modules shipped independently.
 *   2. A SHARED CLASS RESTATED. Two definitions of `.chip` load in whichever
 *      order the page happens to link them, and the same component renders
 *      differently on two screens. A sheet beside the shell's may USE a shared
 *      class — qualified, inside its own scope — but never redefine it.
 *
 * THE VOCABULARY IS THREE SOURCES, not one. A class earns its place by being
 * defined in a sheet, or by being named in the bundle's own JavaScript: a hook
 * a controller toggles (`w-body`, which `w-body-preview` decorates) is shipped
 * as surely as a rule is, and the JS is the file that would have to change.
 * Anything named in none of the three is nobody's.
 */
final class StylesheetVocabularyTest extends TestCase
{
    private const string ROOT = __DIR__.'/../../..';

    /** The shell's own sheet, which every page links, and the widget library's. */
    private const array SHEETS = ['shell.css', 'widget.css'];

    /** The prefix that marks a widget-library class as the library's own. */
    private const string LIBRARY_PREFIX = 'w-';

    /**
     * Every design token `widget.css` spends must be defined by `shell.css` or
     * by `widget.css` itself. A rule that references an undefined token does not
     * fail — it inherits, so a jade rail renders in the body text's colour.
     */
    public function testTheLibrarySheetSpendsNoTokenTheChainDoesNotDefine(): void
    {
        $spent = self::tokensUsed(self::sheet('widget.css'));
        $defined = self::tokensDefined(self::sheet('widget.css') + self::sheet('shell.css'));

        $missing = array_values(array_diff($spent, $defined));
        sort($missing);

        self::assertSame([], $missing, \sprintf(
            'widget.css spends token(s) [%s] nothing in the chain defines; those rules inherit instead of painting.',
            implode(', ', array_map(static fn (string $t): string => '--'.$t, $missing)),
        ));
    }

    /**
     * THE LIBRARY REDEFINES NO SHARED CLASS. It may reach for one — the toolbar
     * puts a `.tgl` at the end of its row — but only qualified by a class of its
     * own, so the rule cannot escape the library and re-style a `.tgl` on
     * somebody else's page.
     */
    public function testTheLibrarySheetRestatesNoSharedClass(): void
    {
        $offenders = [];
        foreach (self::selectors(self::read('widget.css')) as $selector) {
            $classes = self::classesIn($selector);
            if ([] === $classes) {
                continue;
            }
            foreach ($classes as $class) {
                if (str_starts_with($class, self::LIBRARY_PREFIX)) {
                    continue 2;
                }
            }
            $offenders[] = trim($selector);
        }

        self::assertSame([], $offenders, \sprintf(
            'widget.css defines [%s] without scoping it to a class of its own; a shared class defined twice '
            .'renders differently depending on which sheet loaded last.',
            implode(', ', $offenders),
        ));
    }

    /**
     * Every class the shell's templates write must be shipped by the chain — a
     * sheet's selector, or a name the shell's own JavaScript toggles.
     */
    public function testEveryClassTheTemplatesWriteIsShippedBySomebody(): void
    {
        $shipped = [...self::classesDefinedInSheets(), ...self::classesNamedInScripts()];

        $missing = array_values(array_diff(self::classesUsedInTemplates(), array_unique($shipped)));
        sort($missing);

        self::assertSame([], $missing, \sprintf(
            'The templates write [%s] and no sheet or script ships it; those elements render as unstyled markup.',
            implode(', ', array_map(static fn (string $c): string => '.'.$c, $missing)),
        ));
    }

    private static function read(string $sheet): string
    {
        $css = file_get_contents(self::ROOT.'/public/'.$sheet);
        self::assertIsString($css, $sheet.' must ship.');

        // Comments name classes and tokens as prose; only rules count.
        return (string) preg_replace('#/\*.*?\*/#s', '', $css);
    }

    /**
     * @return array<string, string> sheet name => stripped css
     */
    private static function sheet(string $name): array
    {
        return [$name => self::read($name)];
    }

    /**
     * @param array<string, string> $sheets
     *
     * @return list<string>
     */
    private static function tokensDefined(array $sheets): array
    {
        $names = [];
        foreach ($sheets as $css) {
            preg_match_all('/--([a-z0-9_-]+)\s*:/i', $css, $matches);
            $names = [...$names, ...$matches[1]];
        }

        return array_values(array_unique($names));
    }

    /**
     * @param array<string, string> $sheets
     *
     * @return list<string>
     */
    private static function tokensUsed(array $sheets): array
    {
        $names = [];
        foreach ($sheets as $css) {
            preg_match_all('/var\(\s*--([a-z0-9_-]+)/i', $css, $matches);
            $names = [...$names, ...$matches[1]];
        }

        return array_values(array_unique($names));
    }

    /**
     * @return list<string>
     */
    private static function selectors(string $css): array
    {
        // Everything before a brace that is not itself an at-rule prelude.
        preg_match_all('/(^|\})([^{}@]+)\{/m', $css, $matches);

        return array_values(array_filter(
            array_map(trim(...), $matches[2]),
            static fn (string $selector): bool => '' !== $selector,
        ));
    }

    /**
     * @return list<string>
     */
    private static function classesIn(string $text): array
    {
        preg_match_all('/\.([a-zA-Z][a-zA-Z0-9_-]*)/', $text, $matches);

        return array_values(array_unique($matches[1]));
    }

    /**
     * @return list<string>
     */
    private static function classesDefinedInSheets(): array
    {
        $classes = [];
        foreach (self::SHEETS as $sheet) {
            foreach (self::selectors(self::read($sheet)) as $selector) {
                $classes = [...$classes, ...self::classesIn($selector)];
            }
        }

        return array_values(array_unique($classes));
    }

    /**
     * Every class the shell's own scripts name — the markup they build, the
     * classes they toggle and the selectors they query by. A hook a controller
     * reaches for is shipped as surely as a rule is: the script is the file
     * that would have to change.
     *
     * @return list<string>
     */
    private static function classesNamedInScripts(): array
    {
        $classes = [];
        $scripts = self::files(self::ROOT.'/assets', 'js');
        self::assertNotSame([], $scripts, 'There are no scripts to read.');

        foreach ($scripts as $path) {
            $js = (string) file_get_contents($path);

            preg_match_all('/class="([^"]*)"/', $js, $markup);
            preg_match_all('/classList\\.[a-zA-Z]+\\(\\s*.([a-zA-Z][a-zA-Z0-9_-]*)./', $js, $toggled);
            // A selector the script queries by is a hook it depends on too.
            preg_match_all('/querySelector(?:All)?\\(\\s*.([^\\x27"`]+)./', $js, $queried);

            foreach ($markup[1] as $attribute) {
                foreach (preg_split('/\s+/', $attribute) ?: [] as $class) {
                    if (1 === preg_match('/^[a-zA-Z][a-zA-Z0-9_-]*$/', $class)) {
                        $classes[] = $class;
                    }
                }
            }
            $classes = [...$classes, ...$toggled[1]];
            foreach ($queried[1] as $selector) {
                $classes = [...$classes, ...self::classesIn($selector)];
            }
        }

        return array_values(array_unique($classes));
    }

    /**
     * Every class literal a template writes. Interpolations are dropped: what a
     * `{{ }}` produces is somebody else's vocabulary, and guessing at it would
     * make this test fail on correct markup.
     *
     * @return list<string>
     */
    private static function classesUsedInTemplates(): array
    {
        $classes = [];
        foreach (self::files(self::ROOT.'/templates', 'twig') as $path) {
            $twig = (string) preg_replace('/\{#.*?#\}/s', '', (string) file_get_contents($path));

            preg_match_all('/class="([^"]*)"/', $twig, $attributes);
            foreach ($attributes[1] as $attribute) {
                $literal = (string) preg_replace('/\{[{%].*?[}%]\}/s', ' ', $attribute);
                foreach (preg_split('/\s+/', $literal) ?: [] as $class) {
                    if (1 === preg_match('/^[a-zA-Z][a-zA-Z0-9_-]*$/', $class)) {
                        $classes[] = $class;
                    }
                }
            }
        }

        self::assertNotSame([], $classes, 'There are no templates to sweep.');

        return array_values(array_unique($classes));
    }

    /**
     * @return list<string>
     */
    private static function files(string $directory, string $extension): array
    {
        if (!is_dir($directory)) {
            return [];
        }

        $paths = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS),
        );

        /** @var \SplFileInfo $file */
        foreach ($iterator as $file) {
            if ($extension === $file->getExtension()) {
                $paths[] = $file->getPathname();
            }
        }

        sort($paths);

        return $paths;
    }
}
