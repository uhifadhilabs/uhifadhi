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
 * EVERY CLASS A PAGE WRITES IS DEFINED IN THE SHEETS **THAT PAGE** LINKS.
 *
 * THE UNION IS NOT THE ANSWER. {@see VocabularyConformanceTest} asks whether
 * anybody in the bundle's world ships a class, and that question passes while a
 * page renders unstyled: a screen that draws a map plate but forgets to link
 * the atlas sheet writes `.map-plate` and `.map-legend`, and the bundle-wide
 * check is satisfied by the sheet a DIFFERENT screen links. The page then loads
 * with a 63-pixel plate, a legend of raw browser text and a zoom control of
 * four bare squares — every class "shipped by somebody", none of them served.
 *
 * So this walks each page's own chain: the shell's sheet, which the document
 * always links, plus every stylesheet the page names in its `stylesheets`
 * block, plus every stylesheet the partials it includes name. A class used
 * outside that chain is a defect in the PAGE, not in the sheet.
 *
 * WHAT IT CANNOT SEE is a rule that exists but does not match the element it
 * was written for. That is still a rendered check's job; this closes the gap
 * one step below it, where a sheet is not served at all.
 */
final class PageSheetChainTest extends TestCase
{
    /**
     * Classes nobody's stylesheet defines because nobody's stylesheet should:
     * they are hooks a script or a library reads, and a rule for them would be
     * styling something by its wiring.
     */
    private const array NOT_STYLING = [
        // Leaflet and the UX Map bridge own these from their own stylesheet.
        'map-canvas', 'map-chrome-host', 'leaflet-container',
    ];

    /**
     * TWO CLASSES THAT PREDATE THIS TEST, named rather than tolerated.
     *
     * Both are on the areas widget-library page and both are real: `.none`
     * dims a register thumbnail that has no boundary, `.w-presetflag-active`
     * is the active flag's own modifier, and neither has a rule in any sheet
     * that page links. They are listed so this check can be green about the
     * pages it was written for without pretending those two are.
     *
     * THIS LIST ONLY EVER SHRINKS. An entry added to it is a page shipped
     * unstyled on purpose, which is not a thing anybody should be able to do
     * quietly.
     */
    private const array PRE_EXISTING = [
        'templates/area/widgets.html.twig writes .none',
        'templates/area/widgets.html.twig writes .w-presetflag-active',
    ];

    public function testEveryPageStylesTheClassesItWrites(): void
    {
        $offenders = [];

        foreach (self::pages() as $page => $twig) {
            $chain = self::chainOf($page);
            $defined = self::classesDefinedIn($chain);

            foreach (self::classesUsedBy($page) as $class) {
                if (\in_array($class, $defined, true) || \in_array($class, self::NOT_STYLING, true)) {
                    continue;
                }

                $offence = \sprintf('%s writes .%s', $page, $class);
                if (\in_array($offence, self::PRE_EXISTING, true)) {
                    continue;
                }

                $offenders[] = \sprintf('%s, which none of its own sheets (%s) defines', $offence, implode(', ', array_map(basename(...), $chain)));
            }
        }

        sort($offenders);
        self::assertSame([], $offenders, "A page renders unstyled markup:\n".implode("\n", $offenders));
    }

    /**
     * A PAGE THAT DRAWS A PLATE LINKS THE PLATE'S SHEET. Stated separately from
     * the sweep above because it is the one that was missed and because it says
     * so in one sentence: `render_map()` and the atlas stylesheet travel
     * together, or the plate has no height and the legend has no rows.
     */
    public function testEveryPageThatDrawsAPlateLinksTheAtlasSheet(): void
    {
        foreach (self::pages() as $page => $twig) {
            if (!str_contains($twig, 'render_map(')) {
                continue;
            }

            self::assertContains(
                self::atlasSheet(),
                self::chainOf($page),
                \sprintf('%s draws a map plate and does not link the atlas stylesheet.', $page),
            );
        }
    }

    // ------------------------------------------------------------- the pages

    /**
     * The bundle's PAGES — the templates that extend the shell's frame. A
     * partial has no chain of its own; it is read as part of whoever includes
     * it.
     *
     * @return array<string, string> short path to source
     */
    private static function pages(): array
    {
        $pages = [];
        foreach (self::templates() as $path => $twig) {
            if (str_contains($twig, "extends '@Shell/page.html.twig'")) {
                $pages[$path] = $twig;
            }
        }

        return $pages;
    }

    /** @return array<string, string> */
    private static function templates(): array
    {
        $found = [];
        $directory = new \RecursiveDirectoryIterator(self::bundle().'/templates', \FilesystemIterator::SKIP_DOTS);
        /** @var \SplFileInfo $file */
        foreach (new \RecursiveIteratorIterator($directory) as $file) {
            if ('twig' === $file->getExtension()) {
                $found[str_replace(self::bundle().'/', '', (string) $file->getRealPath())] = (string) file_get_contents((string) $file->getRealPath());
            }
        }

        ksort($found);

        return $found;
    }

    // ------------------------------------------------------------- the chain

    /**
     * The sheets THIS page is served: the shell's, which the document links for
     * every page, then everything the page or its partials name.
     *
     * @return list<string> absolute paths
     */
    private static function chainOf(string $page): array
    {
        $chain = [self::sheetOf('Uhifadhi\Bundle\ShellBundle\ShellBundle', 'STYLESHEET')];

        foreach (self::reachableFrom($page) as $twig) {
            preg_match_all("/constant\\('([^']+)::(\\w+)'\\)/", str_replace('\\\\', '\\', $twig), $matches, \PREG_SET_ORDER);
            foreach ($matches as [, $class, $name]) {
                $sheet = self::sheetOf($class, $name);
                if ('' !== $sheet && !\in_array($sheet, $chain, true)) {
                    $chain[] = $sheet;
                }
            }
        }

        return $chain;
    }

    /**
     * The page and every template it includes, transitively — a partial's
     * classes are the page's classes, and so are the sheets it names.
     *
     * @return list<string>
     */
    private static function reachableFrom(string $page): array
    {
        $templates = self::templates();
        $seen = [];
        $queue = [$page];
        $sources = [];

        while ([] !== $queue) {
            $path = array_shift($queue);
            if (isset($seen[$path]) || !isset($templates[$path])) {
                continue;
            }
            $seen[$path] = true;
            $sources[] = $templates[$path];

            preg_match_all("/include\\('@Area\\/([^']+)'/", $templates[$path], $matches);
            foreach ($matches[1] as $included) {
                $queue[] = 'templates/'.$included;
            }

            /*
             * A COMPOSED SURFACE INCLUDES BY VARIABLE, and a partial nothing
             * names statically is a partial this check cannot see — which is
             * how three cells of the area overview came to be rendered with
             * classes no linked sheet defines. The area's own cells are
             * reachable by the pattern their contributor publishes, so the
             * pattern is followed here too.
             */
            if (str_contains($templates[$path], 'partials[cell.id]')) {
                foreach (array_keys($templates) as $candidate) {
                    if (str_starts_with($candidate, 'templates/area/overview/_w_')) {
                        $queue[] = $candidate;
                    }
                }
            }
        }

        return $sources;
    }

    /**
     * A stylesheet constant resolved to the file it names. The constant holds a
     * served path (`bundles/atlas/map.css`); the file is that basename under
     * the declaring bundle's own `public/`.
     */
    private static function sheetOf(string $class, string $name): string
    {
        if (!class_exists($class) || !\defined($class.'::'.$name)) {
            return '';
        }

        $value = \constant($class.'::'.$name);
        if (!\is_string($value) || !str_ends_with($value, '.css')) {
            return '';
        }

        $directory = \dirname((string) new \ReflectionClass($class)->getFileName());

        return $directory.'/public/'.basename($value);
    }

    private static function atlasSheet(): string
    {
        return self::sheetOf('Uhifadhi\Bundle\AtlasBundle\AtlasBundle', 'STYLESHEET');
    }

    // ------------------------------------------------------------ the classes

    /**
     * Every class the page writes, its partials included.
     *
     * @return list<string>
     */
    private static function classesUsedBy(string $page): array
    {
        $classes = [];
        foreach (self::reachableFrom($page) as $twig) {
            preg_match_all('/\bclass="([^"]*)"/', $twig, $matches);
            foreach ($matches[1] as $attribute) {
                $classes = [...$classes, ...self::classNames($attribute)];
            }
        }

        return array_values(array_unique($classes));
    }

    /**
     * The literal class names in one attribute. Twig's own expressions are not
     * names — but the STRINGS inside them are, which is how `{{ x ? ' slim' }}`
     * puts a class on an element.
     *
     * @return list<string>
     */
    private static function classNames(string $attribute): array
    {
        $literals = '';
        preg_match_all("/'([^']*)'|\"([^\"]*)\"/", $attribute, $quoted, \PREG_SET_ORDER);
        foreach ($quoted as $match) {
            $literals .= ' '.($match[2] ?? '').' '.($match[1] ?? '');
        }

        $plain = (string) preg_replace('/\{\{.*?\}\}|\{%.*?%\}/s', ' ', $attribute);

        $names = [];
        foreach (preg_split('/\s+/', $plain.' '.$literals) ?: [] as $name) {
            // A NAME ENDING IN A HYPHEN is the literal half of one an
            // interpolation completes (`w-span-{{ n }}`), and no shipped
            // class ends in one — reading it whole would fail this on
            // correct markup. The sibling union test says the same.
            if (1 === preg_match('/^[a-z][a-z0-9-]*[a-z0-9]$/i', $name)) {
                $names[] = $name;
            }
        }

        return $names;
    }

    /**
     * @param list<string> $sheets
     *
     * @return list<string>
     */
    private static function classesDefinedIn(array $sheets): array
    {
        $defined = [];
        foreach ($sheets as $sheet) {
            if (!is_file($sheet)) {
                continue;
            }
            preg_match_all('/\.([a-z][a-z0-9-]*)/i', (string) file_get_contents($sheet), $matches);
            $defined = [...$defined, ...$matches[1]];
        }

        return array_values(array_unique($defined));
    }

    private static function bundle(): string
    {
        return \dirname(__DIR__, 3);
    }
}
