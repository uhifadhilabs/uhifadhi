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

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * ONE SPELLING, EVERYWHERE A READER CAN SEE IT: "organization" (ruled by the
 * owner, 2026-09-21).
 *
 * WHY A TEST AND NOT A STYLE NOTE. The product had both spellings at once —
 * the sidebar heading read ORGANIZATION while the Settings tab beside it
 * read Organisation, and the scope control read "Organisation — all areas"
 * over a page whose heading did not. Nobody types the second spelling on
 * purpose; it arrives one string at a time, from whoever wrote that screen,
 * and it is invisible until somebody reads two screens side by side.
 *
 * WHAT IS SWEPT: every shipped template, which is where user-facing words
 * live. Translation catalogues are swept too the day this product grows
 * one — the glob is here already, so the first `.xlf` anybody adds is
 * covered without a change to this file.
 *
 * WHAT IS NOT SWEPT, AND WHY: PHP identifiers. Route names
 * (`organisation_dashboard`), service ids, class names
 * (`OrganisationIdentity`), enum cases and the `/settings/organisation`
 * address are things installations and modules REFERENCE, so they turn over
 * on the two-release rule rather than in the same commit as the copy.
 *
 * AND AN IDENTIFIER CAN REACH A TEMPLATE, which is why this does not simply
 * search for the letters: `{{ scope.isOrganisation }}` is a method call, not
 * a word a reader sees. So what is flagged is the WORD — bounded by
 * non-identifier characters, and never reached through `.`, `::` or `$`. A
 * sentence is caught; a call is not. The day an identifier is renamed, this
 * test does not need touching.
 */
#[CoversNothing]
final class OneSpellingOfOrganizationTest extends TestCase
{
    /** The one spelling the product uses. */
    private const string RIGHT = 'organization';

    /** The one it does not. */
    private const string WRONG = 'organisation';

    /**
     * Every template this repository ships, and every translation catalogue
     * it grows.
     *
     * @return \Generator<string, array{string}>
     */
    public static function userFacingFiles(): \Generator
    {
        $root = \dirname(__DIR__, 2);

        $files = [];
        foreach (['*.twig', '*.xlf', '*.yaml'] as $pattern) {
            foreach (glob($root.'/src/Uhifadhi/Bundle/*/templates', \GLOB_ONLYDIR) ?: [] as $templates) {
                $files = [...$files, ...self::under($templates, $pattern)];
            }
            foreach (glob($root.'/src/Uhifadhi/Bundle/*/translations', \GLOB_ONLYDIR) ?: [] as $catalogue) {
                $files = [...$files, ...self::under($catalogue, $pattern)];
            }
        }

        self::assertNotEmpty($files, 'The sweep found no templates at all, which means it is sweeping nothing.');

        foreach ($files as $file) {
            yield substr($file, \strlen($root) + 1) => [$file];
        }
    }

    /**
     * A SHIPPED TEMPLATE SPELLS IT ONE WAY.
     *
     * The comparison is case-insensitive because the heading, the tab and
     * the sentence each capitalise it differently and all three are the same
     * word.
     */
    #[DataProvider('userFacingFiles')]
    public function testNoShippedTemplateUsesTheOtherSpelling(string $file): void
    {
        self::assertSame(
            [],
            self::wordsIn((string) file_get_contents($file)),
            \sprintf(
                '%s spells it "%s" where a reader can see it. The product spells it "%s" (ruled 2026-09-21) — '
                .'one spelling, everywhere. A PHP identifier may still carry the other spelling this release '
                .'and a template may call one; a WORD in a template may not, because a template is read.',
                basename($file),
                self::WRONG,
                self::RIGHT,
            ),
        );
    }

    /**
     * THE WORD, NOT THE LETTERS — every standalone occurrence, with the ones
     * that are part of an identifier or reached through `.`, `::` or `$`
     * left out. The same boundary the sweep itself used, so the two cannot
     * disagree about what counts as prose.
     *
     * @return list<string>
     */
    private static function wordsIn(string $contents): array
    {
        preg_match_all(
            '/(?<![A-Za-z0-9_])(?<!\.)(?<!:)(?<!\$)'.self::WRONG.'(?![A-Za-z0-9_])/i',
            $contents,
            $found,
        );

        return $found[0];
    }

    /**
     * AND THE SWEEP IS REALLY LOOKING. A test that would pass over an empty
     * set, or whose needle never matched anything, is a test that stops
     * catching the thing it was written for — so the right spelling is
     * asserted to be present somewhere, which it is on every organisation
     * surface the core ships.
     */
    public function testTheSweepIsActuallyReadingTheTemplates(): void
    {
        $found = 0;
        foreach (self::userFacingFiles() as [$file]) {
            if (str_contains(strtolower((string) file_get_contents($file)), self::RIGHT)) {
                ++$found;
            }
        }

        self::assertGreaterThan(0, $found, 'No template uses the word at all, so this suite proves nothing.');
    }

    /**
     * @return list<string>
     */
    private static function under(string $directory, string $pattern): array
    {
        $found = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS),
        );

        /** @var \SplFileInfo $file */
        foreach ($iterator as $file) {
            if (fnmatch($pattern, $file->getFilename())) {
                $found[] = $file->getPathname();
            }
        }

        sort($found);

        return $found;
    }
}
