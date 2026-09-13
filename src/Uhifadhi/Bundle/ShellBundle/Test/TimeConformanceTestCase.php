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
use Uhifadhi\Bundle\ShellBundle\Model\TimeShape;

/**
 * NO INSTANT IS PRINTED IN THE SERVER'S ZONE — the build failure that stands in
 * for the reading nobody checked.
 *
 * An instant is stored as UTC and printed once, server-side, in whatever single
 * zone the server happens to run in. A ranger in the field and an analyst three
 * timezones away then read the same wall-clock off the same screen and one of
 * them reads it wrong. Nothing throws: the page renders a 200 with a plausible
 * time on it, and a functional test asserting the text passes on the wrong
 * answer.
 *
 * The frame already fixes it, for any instant that reaches the browser as an
 * element rather than as text: `<time datetime="…">` is rewritten to the
 * reader's own zone by the shell's `localtime` controller. So the only thing
 * left to catch is a template that printed a date some other way — and that is
 * a text sweep, which is what this is.
 *
 * THE THREE CHECKS.
 *
 *   Every `|date(` a template writes is INSIDE a `<time datetime=…>` element —
 *   either as the machine attribute or as the fallback text between the tags.
 *   A date printed anywhere else is a date in the server's zone forever.
 *
 *   THE ONE EXCEPTION IS THE RELATIVE LABEL. "6 min ago", "3 days ago": a
 *   relative reading is a duration, not a wall-clock, so it is right in every
 *   zone at once and must NOT be rewritten. It is written as a plain
 *   `<span title="{{ t|date('c') }}">3 days ago</span>` — never a `<time>`,
 *   which the frame would localise into an absolute stamp and lose the reading
 *   the design draws — and this check accepts a `|date('c')` inside a `title`
 *   for exactly that. Only `date('c')` is accepted there: an offset-qualified
 *   machine instant is an annotation, while a formatted one in a tooltip is the
 *   same defect one attribute further in.
 *
 *   Every `<time>` element carries a `datetime` attribute. Without it the frame
 *   has no instant to read and skips the element, so the server's text stands.
 *
 *   Every `data-localtime-format` names a shape the shell ships. A misspelt
 *   shape is not an error in the browser; it silently falls back to the verbose
 *   default, in one cell, on one screen.
 *
 * HOW A BUNDLE ADOPTS IT. One file in the bundle's suite:
 *
 *     final class TimeConformanceTest extends TimeConformanceTestCase
 *     {
 *         protected static function bundlePath(): string { return \dirname(__DIR__, 3); }
 *     }
 *
 * A bundle that prefers a command to a test gets the same sweep from the shell
 * without PHPUnit; `docs/theming.md` carries the grep.
 *
 * WHY IT SHIPS IN src/ RATHER THAN IN THIS BUNDLE'S OWN SUITE. A module has to
 * be able to autoload it, and a package's tests are excluded from its classmap.
 * A test base a consumer extends is part of the package's published surface, so
 * it lives beside the code and is exported with it.
 *
 * @see VocabularyConformanceTestCase — the same arrangement, for classes and icons
 */
abstract class TimeConformanceTestCase extends TestCase
{
    /**
     * The root of the bundle under test — the directory its composer.json sits
     * in, from which `templates/` is read.
     */
    abstract protected static function bundlePath(): string;

    /**
     * The templates this sweep skips, as paths relative to `templates/`. A
     * bundle documenting the platform's own markup prints `|date(` as prose in
     * a code sample; nothing else has an excuse, and each entry says which.
     *
     * @return list<string>
     */
    protected static function exemptTemplates(): array
    {
        return [];
    }

    public function testEveryInstantATemplatePrintsIsInsideATimeElement(): void
    {
        $offenders = [];
        foreach (self::templates() as $path => $twig) {
            // The element is stripped whole — opening tag, fallback text and
            // all — so a `|date(` in either position is accounted for and only
            // a print somewhere else is left behind.
            $outside = (string) preg_replace('#<time\b[^>]*\bdatetime=.*?</time>#s', '', $twig);

            // AND THE RELATIVE LABEL, which is the one reading that is right in
            // every zone at once. "6 min ago" carries no wall-clock to be wrong
            // about, so it is deliberately NOT a `<time>` and deliberately not
            // localised — it prints as it is, with the exact instant riding in a
            // `title` for anybody who wants it. Only `date('c')` is accepted
            // there: an offset-qualified machine instant is an annotation,
            // whereas a formatted one in a tooltip is the same defect one
            // attribute further in.
            $outside = (string) preg_replace('/\btitle="[^"]*\|\s*date\(\s*.c.\s*\)[^"]*"/s', '', $outside);

            preg_match_all('/\|\s*date\([^)]*\)/', $outside, $prints);
            foreach (array_unique($prints[0]) as $print) {
                $offenders[] = $path.': '.$print;
            }
        }

        sort($offenders);

        self::assertSame([], $offenders, \sprintf(
            'A date is printed where nothing can read it in the viewer\'s zone: [%s]. Whatever zone the '
            .'server runs in is the zone every reader gets. Write it as '
            .'<time datetime="{{ x|date(\'c\') }}" data-localtime-format="stamp">fallback</time> and let the '
            .'frame rewrite it — or, for a relative reading that needs no zone, as '
            .'<span title="{{ x|date(\'c\') }}">3 days ago</span>.',
            implode(', ', $offenders),
        ));
    }

    public function testEveryTimeElementCarriesTheMachineInstant(): void
    {
        $offenders = [];
        foreach (self::templates() as $path => $twig) {
            preg_match_all('/<time\b[^>]*>/', $twig, $tags);
            foreach ($tags[0] as $tag) {
                if (!str_contains($tag, 'datetime=')) {
                    $offenders[] = $path.': '.$tag;
                }
            }
        }

        sort($offenders);

        self::assertSame([], $offenders, \sprintf(
            'A <time> element without a datetime attribute is one the frame cannot read, so its server-rendered '
            .'text stands: [%s].',
            implode(', ', $offenders),
        ));
    }

    public function testEveryShapeATemplateAsksForIsOneTheShellShips(): void
    {
        $shapes = TimeShape::names();

        $offenders = [];
        foreach (self::templates() as $path => $twig) {
            preg_match_all('/data-localtime-format="([^"]*)"/', $twig, $asked);
            foreach ($asked[1] as $shape) {
                if (!\in_array($shape, $shapes, true)) {
                    $offenders[] = $path.': '.$shape;
                }
            }
        }

        sort($offenders);

        self::assertSame([], $offenders, \sprintf(
            'A shape the frame does not answer falls back to the verbose default instead of failing: [%s]. '
            .'The shapes are %s.',
            implode(', ', $offenders),
            implode(', ', $shapes),
        ));
    }

    /**
     * The bundle's shipped templates, keyed by the path a failure names, with
     * Twig comments removed: a comment explaining the idiom writes the idiom
     * out, and prose is not markup.
     *
     * @return array<string, string>
     */
    final protected static function templates(): array
    {
        $root = static::bundlePath().'/templates';
        if (!is_dir($root)) {
            return [];
        }

        $exempt = static::exemptTemplates();

        $templates = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS),
        );

        /** @var \SplFileInfo $file */
        foreach ($iterator as $file) {
            if ('twig' !== $file->getExtension()) {
                continue;
            }

            $short = ltrim(substr($file->getPathname(), \strlen($root)), '/');
            if (\in_array($short, $exempt, true)) {
                continue;
            }

            $templates[$short] = (string) preg_replace(
                '/\{#.*?#\}/s',
                '',
                (string) file_get_contents($file->getPathname()),
            );
        }

        ksort($templates);

        return $templates;
    }
}
