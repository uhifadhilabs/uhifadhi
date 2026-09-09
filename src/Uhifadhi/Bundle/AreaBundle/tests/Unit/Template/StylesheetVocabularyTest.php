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
use Uhifadhi\Bundle\AtlasBundle\AtlasBundle;
use Uhifadhi\Bundle\ShellBundle\ShellBundle;

/**
 * NOTHING MAY SPEND A NAME NOBODY SHIPS, AND NOTHING MAY RESTATE A SHARED ONE.
 *
 * The shell owns the design system — the palettes, the derived aliases every
 * other sheet references, and the shared component classes — and the atlas owns
 * what a map wears. This bundle's sheet carries what is genuinely its own, and
 * two failure modes follow from that division. Neither shows up in a rendered
 * test, because a missing class does not throw: it falls back to browser
 * defaults, which look fine on a page that happens to load the design's own
 * sheet and broken in a real installation.
 *
 *   1. A CLASS OR TOKEN NOBODY SHIPS. A template writes a class name, no sheet
 *      in the chain defines it, and the element renders as unstyled markup.
 *   2. A SHARED CLASS RESTATED. Two definitions of the same class load in
 *      whichever order the page happens to link them, and one component renders
 *      differently on two screens. This sheet may USE a shared class —
 *      qualified, inside a scope of its own — but never redefine it.
 *
 * THE CHAIN IS THE SHEETS A PAGE ACTUALLY LINKS, and nothing else: the shell's
 * document links shell.css, an area page that draws a map links the atlas's,
 * and this bundle's own sheet is last because it is the one allowed to
 * override. The widget library's sheet is deliberately absent — no template
 * here links it, so a name it happens to define is a name these pages do not
 * get.
 *
 * THE VOCABULARY IS THREE SOURCES, not one. A class earns its place by being
 * defined in a sheet, or by being named in this bundle's own JavaScript: a hook
 * a controller toggles is shipped as surely as a rule is, and the script is the
 * file that would have to change.
 */
final class StylesheetVocabularyTest extends TestCase
{
    private const string ROOT = __DIR__.'/../../..';

    /** This bundle's own sheet — the only one it may write rules in. */
    private const string OWN = 'area.css';

    public function testTheSheetSpendsNoTokenTheChainDoesNotDefine(): void
    {
        $spent = self::tokensUsed(self::own());
        $defined = self::tokensDefined([...self::chain(), self::own()]);

        $missing = array_values(array_diff($spent, $defined));
        sort($missing);

        self::assertSame([], $missing, \sprintf(
            '%s spends token(s) [%s] nothing in the chain defines; those rules inherit instead of painting.',
            self::OWN,
            implode(', ', array_map(static fn (string $t): string => '--'.$t, $missing)),
        ));
    }

    /**
     * THIS SHEET STATES NO RULE THE CHAIN ALREADY STATES. It may reach for a
     * shared class — a pin wears the shell's `.mchip` and adds what makes it a
     * pin — but the moment it writes a rule for a selector the chain already
     * carries, two definitions of one component exist and the page renders
     * whichever loaded last.
     *
     * THE COMPARISON IS BY SELECTOR, and comma-separated groups are compared a
     * side at a time, so `.fld` here against `.fld` there is caught wherever
     * either was written. A HIGHER-SPECIFICITY restyle of a shared class —
     * `.c.kpi .disp` over the shell's `.kpi .disp` — is not caught by this line
     * and is not endorsed by it either; consolidating those is design work with
     * a rendered page to check, not a text sweep.
     */
    public function testTheSheetStatesNoRuleTheChainAlreadyStates(): void
    {
        $shared = self::selectors(implode("\n", self::chain()));

        $offenders = array_values(array_intersect(self::selectors(self::own()), $shared));
        sort($offenders);

        self::assertSame([], $offenders, \sprintf(
            '%s restates [%s]; a rule written twice renders differently depending on which sheet loaded last.',
            self::OWN,
            implode(', ', $offenders),
        ));
    }

    public function testEveryClassTheTemplatesWriteIsShippedBySomebody(): void
    {
        $shipped = [
            ...self::classesDefinedIn([...self::chain(), self::own()]),
            ...self::classesNamedInScripts(),
        ];

        $missing = array_values(array_diff(self::classesUsedInTemplates(), array_unique($shipped)));
        sort($missing);

        self::assertSame([], $missing, \sprintf(
            'The templates write [%s] and no sheet or script ships it; those elements render as unstyled markup.',
            implode(', ', array_map(static fn (string $c): string => '.'.$c, $missing)),
        ));
    }

    /**
     * The sheets a page links BESIDE this one, read from where their own bundles
     * ship them — through the bundle class rather than a relative path, so the
     * chain is found wherever those packages are installed from.
     *
     * @return list<string>
     */
    private static function chain(): array
    {
        return [
            self::read(self::publicDir(ShellBundle::class).'/shell.css'),
            self::read(self::publicDir(AtlasBundle::class).'/map.css'),
        ];
    }

    private static function own(): string
    {
        return self::read(self::ROOT.'/public/'.self::OWN);
    }

    /**
     * @param class-string $bundle
     */
    private static function publicDir(string $bundle): string
    {
        $file = new \ReflectionClass($bundle)->getFileName();
        self::assertIsString($file, $bundle.' must be autoloadable from a file.');

        return \dirname($file).'/public';
    }

    private static function read(string $path): string
    {
        $css = file_get_contents($path);
        self::assertIsString($css, $path.' must ship.');

        // Comments name classes and tokens as prose; only rules count.
        return (string) preg_replace('#/\*.*?\*/#s', '', $css);
    }

    /**
     * @param list<string> $sheets
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
     * @return list<string>
     */
    private static function tokensUsed(string $css): array
    {
        preg_match_all('/var\(\s*--([a-z0-9_-]+)/i', $css, $matches);

        return array_values(array_unique($matches[1]));
    }

    /**
     * @return list<string>
     */
    private static function selectors(string $css): array
    {
        // Everything before a brace that is not itself an at-rule prelude.
        preg_match_all('/(^|\})([^{}@]+)\{/m', $css, $matches);

        $selectors = [];
        foreach ($matches[2] as $group) {
            foreach (explode(',', $group) as $selector) {
                $selector = (string) preg_replace('/\s+/', ' ', trim($selector));
                if ('' !== $selector) {
                    $selectors[] = $selector;
                }
            }
        }

        return array_values(array_unique($selectors));
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
     * @param list<string> $sheets
     *
     * @return list<string>
     */
    private static function classesDefinedIn(array $sheets): array
    {
        $classes = [];
        foreach ($sheets as $css) {
            foreach (self::selectors($css) as $selector) {
                $classes = [...$classes, ...self::classesIn($selector)];
            }
        }

        return array_values(array_unique($classes));
    }

    /**
     * Every class this bundle's own scripts name — the markup they build, the
     * classes they toggle and the selectors they query by.
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
            preg_match_all('/classList\.[a-zA-Z]+\(\s*.([a-zA-Z][a-zA-Z0-9_-]*)./', $js, $toggled);
            // A selector the script queries by is a hook it depends on too.
            preg_match_all('/querySelector(?:All)?\(\s*.([^\x27"`]+)./', $js, $queried);

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
