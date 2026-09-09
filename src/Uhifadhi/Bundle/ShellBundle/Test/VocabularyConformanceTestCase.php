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

namespace Uhifadhi\Bundle\ShellBundle\Test;

use PHPUnit\Framework\TestCase;
use Uhifadhi\Bundle\ShellBundle\ShellBundle;

/**
 * NOTHING MAY SPEND A NAME NOBODY SHIPS — the build failure that stands in for
 * the rendered page nobody looked at.
 *
 * A bundle draws with two vocabularies it does not own outright: the classes in
 * the stylesheets a page links, and the icons in the sets somebody registered.
 * Both fail SILENTLY. A class no sheet defines does not throw — the element
 * falls back to browser defaults, which look almost right on a developer's
 * machine, where the design's own sheet happens to be open in another tab, and
 * plainly wrong on an installation. An icon whose file nothing ships does not
 * throw either, on a deployment with no outbound network: it is an empty box.
 * Neither is caught by a functional test, because both render a 200.
 *
 * So they are caught here, by reading what the bundle SHIPS and comparing it to
 * what the bundle WRITES.
 *
 * THE TWO CHECKS.
 *
 *   STYLESHEET. Every class a template writes is defined somewhere in the chain
 *   the page actually links — the shell's sheet, this bundle's own, and any
 *   dependency's it links beside them — or named in this bundle's own
 *   JavaScript, because a hook a controller toggles is shipped as surely as a
 *   rule is. And this bundle's sheet REDEFINES no selector the chain already
 *   carries: two definitions of one component load in whichever order the page
 *   happens to link them, and the same control renders differently on two
 *   screens.
 *
 *   ICON. Every icon reference uses a prefix this bundle is allowed to use —
 *   its own alias, and `shell:`, because a page rendered inside the shell may
 *   reuse the shell's marks. Any other prefix fails, `lucide:` included: a
 *   public library's prefix belongs to the installation, which may answer it
 *   with its own artwork or not answer it at all. And every reference under
 *   this bundle's own prefix resolves to a file in the directory the bundle
 *   registers, with nothing to fetch from.
 *
 * HOW A BUNDLE ADOPTS IT. One file in the bundle's suite:
 *
 *     final class VocabularyConformanceTest extends VocabularyConformanceTestCase
 *     {
 *         protected static function bundlePath(): string { return \dirname(__DIR__, 2); }
 *         protected static function alias(): string { return 'sightings'; }
 *         protected static function ownStylesheets(): array { return ['sightings.css']; }
 *     }
 *
 * WHY IT SHIPS IN src/ RATHER THAN IN THIS BUNDLE'S OWN SUITE. A module has to
 * be able to autoload it, and a package's tests are excluded from its
 * classmap. A test base a consumer extends is part of the package's published
 * surface, so it lives beside the code and is exported with it.
 *
 * @see vendor/symfony/framework-bundle/Test/KernelTestCase.php — the same
 *      arrangement: a base class shipped in the package for consumers to
 *      extend, with the test framework left to whoever runs tests.
 */
abstract class VocabularyConformanceTestCase extends TestCase
{
    /**
     * The root of the bundle under test — the directory its composer.json sits
     * in, from which `templates/`, `assets/` and `public/` are read.
     */
    abstract protected static function bundlePath(): string;

    /**
     * The bundle's config alias. It is also the icon prefix the bundle may draw
     * with, because one package answers for one prefix.
     */
    abstract protected static function alias(): string;

    /**
     * The sheets this bundle ships, relative to its `public/` directory. A
     * bundle that ships none returns nothing and only the icon half applies.
     *
     * @return list<string>
     */
    protected static function ownStylesheets(): array
    {
        return [];
    }

    /**
     * The sheets a page links BESIDE this bundle's own, as absolute paths —
     * found through the shipping bundle's class rather than a relative path, so
     * the chain resolves wherever those packages are installed from.
     *
     * The default is the shell's sheet alone, which every page links. A bundle
     * whose pages also link a dependency's adds it; a bundle that is itself the
     * end of the chain returns nothing.
     *
     * @return list<string>
     */
    protected static function linkedStylesheets(): array
    {
        return [self::publicDir(ShellBundle::class).'/shell.css'];
    }

    /**
     * The prefixes this bundle's templates and code may name. Its own alias,
     * and the shell's — a page rendered inside the shell may reuse the shell's
     * marks rather than copying them.
     *
     * @return list<string>
     */
    protected static function allowedIconPrefixes(): array
    {
        return array_values(array_unique([static::alias(), 'shell']));
    }

    /**
     * Where the files answering this bundle's own prefix live. Null when the
     * bundle registers no icon set of its own, in which case it may only draw
     * with the shell's.
     */
    protected static function iconDirectory(): ?string
    {
        $directory = static::bundlePath().'/assets/icons/'.static::alias();

        return is_dir($directory) ? $directory : null;
    }

    public function testEveryIconReferenceUsesAPrefixThisBundleMayUse(): void
    {
        $allowed = static::allowedIconPrefixes();

        $offenders = [];
        foreach (self::iconReferences() as $name => $where) {
            if (!\in_array(strstr($name, ':', true), $allowed, true)) {
                $offenders[] = $name.' ('.$where.')';
            }
        }

        sort($offenders);

        self::assertSame([], $offenders, \sprintf(
            'This bundle draws [%s] under a prefix it may not use — the prefixes it may use are %s. '
            .'Ship the glyph under your own prefix: copy the SVG into the directory your bundle registers '
            .'as ux_icons.icon_sets.%s.path and draw it as %s:<name>.',
            implode(', ', $offenders),
            implode(', ', array_map(static fn (string $p): string => $p.':', $allowed)),
            static::alias(),
            static::alias(),
        ));
    }

    /**
     * With on-demand fetching off — which is what an installation configures —
     * a name is answered by a file or by nothing at all. So the file is what is
     * asked for.
     */
    public function testEveryIconUnderThisBundlesPrefixResolvesFromTheDirectoryItShips(): void
    {
        $directory = static::iconDirectory();
        $own = static::alias().':';

        $missing = [];
        foreach (self::iconReferences() as $name => $where) {
            if (!str_starts_with($name, $own)) {
                continue;
            }

            if (null === $directory || !is_file($directory.'/'.substr($name, \strlen($own)).'.svg')) {
                $missing[] = $name.' ('.$where.')';
            }
        }

        sort($missing);

        self::assertSame([], $missing, \sprintf(
            'No file in %s answers to [%s]. On a deployment with fetching disabled each of those is an empty box.',
            $directory ?? 'a directory this bundle does not ship',
            implode(', ', $missing),
        ));
    }

    public function testEveryClassTheTemplatesWriteIsShippedBySomebody(): void
    {
        $written = self::classesUsedInTemplates();
        if ([] === $written) {
            self::assertSame([], $written, 'A bundle with no templates writes no classes.');

            return;
        }

        $shipped = [...self::classesDefinedIn(self::chain()), ...self::classesNamedInScripts()];

        $missing = array_values(array_diff($written, array_unique($shipped)));
        sort($missing);

        self::assertSame([], $missing, \sprintf(
            'The templates write [%s] and no sheet or script in the chain ships it; those elements render as unstyled markup.',
            implode(', ', array_map(static fn (string $c): string => '.'.$c, $missing)),
        ));
    }

    public function testTheOwnSheetsSpendNoTokenTheChainDoesNotDefine(): void
    {
        $spent = self::tokensUsed(self::ownCss());
        $defined = self::tokensDefined(self::chain());

        $missing = array_values(array_diff($spent, $defined));
        sort($missing);

        self::assertSame([], $missing, \sprintf(
            'This bundle spends token(s) [%s] nothing in the chain defines; those rules inherit instead of painting.',
            implode(', ', array_map(static fn (string $t): string => '--'.$t, $missing)),
        ));
    }

    /**
     * THIS BUNDLE STATES NO RULE THE CHAIN ALREADY STATES. It may reach for a
     * shared class — decorating one, qualified inside a scope of its own — but
     * the moment it writes a rule for a selector the chain already carries,
     * two definitions of one component exist and the page renders whichever
     * loaded last.
     *
     * The comparison is by selector, and comma-separated groups are compared a
     * side at a time. A higher-specificity restyle is not caught by this and is
     * not endorsed by it either; consolidating those is design work with a
     * rendered page to check, not a text sweep.
     */
    public function testTheOwnSheetsRestateNoSelectorTheChainShips(): void
    {
        $shipped = self::selectors(implode("\n", self::linkedCss()));

        // A `:root` block is where a sheet DECLARES its own tokens, so every
        // sheet in a chain carries one and none of them is restating a
        // component.
        $offenders = array_values(array_filter(
            array_intersect(self::selectors(self::ownCss()), $shipped),
            static fn (string $selector): bool => !str_starts_with($selector, ':root'),
        ));
        sort($offenders);

        self::assertSame([], $offenders, \sprintf(
            'This bundle restates [%s]; a rule written twice renders differently depending on which sheet loaded last.',
            implode(', ', $offenders),
        ));
    }

    /**
     * Every icon name this bundle asks for, mapped to one place it is asked
     * from — templates first, where a name is written literally, then the PHP
     * and JavaScript, where navigation rows and rendered markup carry one as
     * data.
     *
     * @return array<string, string>
     */
    final protected static function iconReferences(): array
    {
        $names = [];

        foreach (self::sourceFiles() as $path) {
            $contents = (string) file_get_contents($path);
            $where = self::shortPath($path);

            preg_match_all('/ux_icon\(\s*[\'"]([a-z0-9-]+:[a-z0-9:-]+)[\'"]/', $contents, $called);
            preg_match_all('/<twig:ux:icon[^>]*\sname="([a-z0-9-]+:[a-z0-9:-]+)"/', $contents, $component);
            preg_match_all('/icon:\s*[\'"]([a-z0-9-]+:[a-z0-9-]+)[\'"]/', $contents, $named);

            foreach ([...$called[1], ...$component[1], ...$named[1]] as $name) {
                $names[$name] ??= $where;
            }
        }

        return $names;
    }

    /**
     * The whole chain a page reads from: what somebody else ships, then this
     * bundle's own, which is last because it is the one allowed to decorate.
     *
     * @return list<string>
     */
    final protected static function chain(): array
    {
        return [...self::linkedCss(), self::ownCss()];
    }

    /** @return list<string> */
    private static function linkedCss(): array
    {
        return array_map(self::read(...), static::linkedStylesheets());
    }

    private static function ownCss(): string
    {
        $sheets = array_map(
            static fn (string $name): string => self::read(static::bundlePath().'/public/'.$name),
            static::ownStylesheets(),
        );

        return implode("\n", $sheets);
    }

    /** @param class-string $bundle */
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
     * Every token a sheet SPENDS WITHOUT A FALLBACK. `var(--x, #333)` names a
     * token the sheet does not require anybody to define — the fallback is what
     * paints when nothing does, which is a deliberate arrangement rather than
     * the silent inheritance this is looking for.
     *
     * @return list<string>
     */
    private static function tokensUsed(string $css): array
    {
        preg_match_all('/var\(\s*--([a-z0-9_-]+)\s*([,)])/i', $css, $matches, \PREG_SET_ORDER);

        $names = [];
        foreach ($matches as $match) {
            if (')' === $match[2]) {
                $names[] = $match[1];
            }
        }

        return array_values(array_unique($names));
    }

    /** @return list<string> */
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

    /** @return list<string> */
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
     * classes they toggle and the selectors they query by. A hook a script
     * reaches for is shipped as surely as a rule is: the script is the file
     * that would have to change.
     *
     * @return list<string>
     */
    private static function classesNamedInScripts(): array
    {
        $classes = [];

        foreach (self::files(static::bundlePath().'/assets', 'js') as $path) {
            $js = (string) file_get_contents($path);

            preg_match_all('/class="([^"]*)"/', $js, $markup);
            preg_match_all('/classList\.[a-zA-Z]+\(\s*.([a-zA-Z][a-zA-Z0-9_-]*)./', $js, $toggled);
            // A selector the script queries by is a hook it depends on too.
            preg_match_all('/querySelector(?:All)?\(\s*.([^\x27"`]+)./', $js, $queried);

            foreach ($markup[1] as $attribute) {
                $classes = [...$classes, ...self::classNames($attribute)];
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
     * fail this on correct markup.
     *
     * @return list<string>
     */
    private static function classesUsedInTemplates(): array
    {
        $classes = [];

        foreach (self::files(static::bundlePath().'/templates', 'twig') as $path) {
            $twig = (string) preg_replace('/\{#.*?#\}/s', '', (string) file_get_contents($path));

            preg_match_all('/class="([^"]*)"/', $twig, $attributes);
            foreach ($attributes[1] as $attribute) {
                $literal = (string) preg_replace('/\{[{%].*?[}%]\}/s', ' ', $attribute);
                $classes = [...$classes, ...self::classNames($literal)];
            }
        }

        return array_values(array_unique($classes));
    }

    /** @return list<string> */
    private static function classNames(string $attribute): array
    {
        $classes = [];
        foreach (preg_split('/\s+/', trim($attribute)) ?: [] as $class) {
            // A name ending in a hyphen is the literal half of a name an
            // interpolation completes (`w-span-{{ n }}`), and no shipped class
            // ends in one. Reading it as a whole name would fail this on
            // correct markup.
            if (1 === preg_match('/^[a-zA-Z][a-zA-Z0-9_-]*[a-zA-Z0-9_]$/', $class)) {
                $classes[] = $class;
            }
        }

        return $classes;
    }

    /**
     * The bundle's shipped templates, PHP and JavaScript — never its own tests,
     * whose fixtures name things no installation ever draws.
     *
     * @return list<string>
     */
    private static function sourceFiles(): array
    {
        $paths = [];
        foreach (['twig', 'php', 'js'] as $extension) {
            $paths = [...$paths, ...self::files(static::bundlePath(), $extension)];
        }

        $tests = static::bundlePath().'/tests/';

        return array_values(array_filter(
            $paths,
            static fn (string $path): bool => !str_starts_with($path, $tests),
        ));
    }

    /** @return list<string> */
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

    private static function shortPath(string $path): string
    {
        $root = static::bundlePath();

        return str_starts_with($path, $root) ? ltrim(substr($path, \strlen($root)), '/') : $path;
    }
}
