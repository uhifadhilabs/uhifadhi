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
 * EVERY DOOR IS SPELLED ONE WAY, so that a test can walk them.
 *
 * A door is a link, a button or a section leading somewhere a permission
 * guards, and every one of them asks `door('<concern>.<verb>')`. The rule is
 * not tidiness: it is what makes the second of the four proofs possible. A
 * product whose doors are written three ways has no way to hold them against
 * the routes, and the failure that follows is the one nobody sees — a control
 * drawn for somebody who will be refused when they click it, or a control
 * hidden from somebody who was entitled to it.
 *
 * `is_granted` IS REFUSED IN A SHIPPED TEMPLATE, and it is refused precisely
 * because it works. It takes any string at all, so a template can name a pair
 * nothing declares, a role, or a typo, and the door simply never opens with
 * nothing anywhere saying why. `door()` takes the same string and is
 * enumerable.
 *
 * A ROLE IS NOT A DOOR. `is_granted('ROLE_…')` is the firewall's vocabulary
 * and belongs in `security.yaml`, not in a page.
 */
#[CoversNothing]
final class EveryDoorGoesThroughTheHelperTest extends TestCase
{
    /** @return \Generator<string, array{string}> */
    public static function bundles(): \Generator
    {
        foreach (glob(\dirname(__DIR__, 2).'/src/Uhifadhi/Bundle/*', \GLOB_ONLYDIR) ?: [] as $path) {
            yield basename($path) => [$path];
        }
    }

    #[DataProvider('bundles')]
    public function testNoShippedTemplateAsksTheCheckerDirectly(string $bundle): void
    {
        $direct = [];

        foreach (self::templates($bundle) as $path) {
            $markup = (string) file_get_contents($path);

            // Comments first: a rule quoted in prose is not a door.
            $markup = (string) preg_replace('/\{#.*?#\}/s', '', $markup);

            if (1 === preg_match('/\bis_granted\s*\(/', $markup)) {
                $direct[] = self::shortPath($bundle, $path);
            }
        }

        sort($direct);

        self::assertSame([], $direct, \sprintf(
            "These templates ask the authorization checker directly [%s].\n".
            'Every door goes through `door(\'<concern>.<verb>\', subject)`, which is what lets a test walk '.
            'them all and hold them against the routes. `is_granted` takes any string, so a pair nothing '.
            'declares looks exactly like a door that is correctly shut.',
            implode(', ', $direct),
        ));
    }

    /**
     * A DOOR NAMES A PAIR AND NOT A ROLE. The firewall's vocabulary is the
     * installation's `security.yaml`; a page speaks concerns and verbs.
     */
    #[DataProvider('bundles')]
    public function testNoDoorNamesARoleInsteadOfAPair(string $bundle): void
    {
        $roles = [];

        foreach (self::templates($bundle) as $path) {
            $markup = (string) preg_replace('/\{#.*?#\}/s', '', (string) file_get_contents($path));

            preg_match_all("/door\(\s*'([^']*)'/", $markup, $matches);
            foreach ($matches[1] as $named) {
                if (1 !== preg_match('/^[a-z0-9]+(-[a-z0-9]+)*\.[a-z]+$/', $named)) {
                    $roles[] = self::shortPath($bundle, $path).' -> '.$named;
                }
            }
        }

        sort($roles);

        self::assertSame([], $roles, \sprintf(
            "These doors name something that is not a (concern, verb) pair [%s].\n".
            'A pair is `<concern>.<verb>`, lowercase, the verb being the segment after the last dot.',
            implode(', ', $roles),
        ));
    }

    /** @return list<string> */
    private static function templates(string $bundle): array
    {
        $directory = $bundle.'/templates';
        if (!is_dir($directory)) {
            return [];
        }

        $paths = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS),
        );

        /** @var \SplFileInfo $file */
        foreach ($iterator as $file) {
            if ('twig' === $file->getExtension()) {
                $paths[] = $file->getPathname();
            }
        }

        sort($paths);

        return $paths;
    }

    private static function shortPath(string $bundle, string $path): string
    {
        return str_starts_with($path, $bundle) ? ltrim(substr($path, \strlen($bundle)), '/') : $path;
    }
}
