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

namespace Uhifadhi\Bundle\ShellBundle\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * THE BOUNDARIES, ENFORCED BY A SWEEP OF THE SHIPPED SOURCE AND templates/.
 *
 * These are cheap, crude tests that read the shipped source as text, and they
 * are the only kind that can catch what they catch. An extraction is a large
 * move under time pressure and "just this one reference, for now" is how a
 * layout acquires a domain — the more so here, because a template is the
 * easiest place in a codebase to type a module's name and the hardest place to
 * notice it later.
 *
 * Five rules:
 *
 *  1. THE SHELL NAMES NO MODULE. Not a slug, not a namespace, not in a
 *     template. A row in the sidebar and a card in the grid arrive as data.
 *  2. THE SHELL NAMES NO HOST. It is installed BY an application; it does not
 *     reach back into one.
 *  3. THE SHELL REMEMBERS ONE THING, AND IT IS FURNITURE. Dashboard layouts —
 *     which widgets a person adopted on which surface — are the frame's, the way
 *     the theme they chose is. They live under Widget/ and nowhere else; a
 *     second Entity/ directory anywhere in this bundle is a domain arriving.
 *  4. THE SHELL CLAIMS NO URL THE APPLICATION HAS NOT ASKED FOR. It ships one
 *     route and one controller, as a resource this bundle never loads; an
 *     application imports it in one line, or does not, and owns the address
 *     either way. The area URL space, its permission gates and its entity
 *     resolution stay the host's.
 *  5. THE SHELL REQUIRES NO OTHER BUNDLE. Least obvious and most load-bearing:
 *     see docs/boundaries.md on why the shell does not depend on the
 *     contract even though it draws the contract's answers.
 */
final class BoundaryTest extends TestCase
{
    private const string ROOT = __DIR__.'/../..';

    /**
     * Real modules, plus the two the platform is likeliest to smuggle in:
     * "overview" (the pinned hub) and "map" (the first core module). Both are
     * flags a provider declares, never slugs a renderer recognises.
     */
    public static function moduleNames(): \Generator
    {
        foreach (['patrol', 'incident', 'roster', 'ingestion', 'storage', 'workflow', 'uhakiki', 'forest', 'overview'] as $name) {
            yield $name => [$name];
        }
    }

    /**
     * @param non-empty-string $name
     */
    #[DataProvider('moduleNames')]
    public function testTheShellKnowsNoModuleByName(string $name): void
    {
        $offenders = [];
        foreach (self::shippedSources() as $path => $code) {
            $code = self::withoutComments($path, $code);
            // A backslash on either side means the word is a namespace segment
            // of somebody else's FQCN (Symfony's Token\Storage\, for one), not a
            // module this bundle named.
            if (1 === preg_match('/(?<![\\\\\w])'.preg_quote($name, '/').'(?![\\\\\w])/i', $code)) {
                $offenders[] = $path;
            }
        }

        self::assertSame([], $offenders, \sprintf(
            'The shell must not name the "%s" module — not in src/, and above all not in a template. '
            .'A nav row and a module card are data handed to the shell, never a slug it recognises.',
            $name,
        ));
    }

    public function testTheShellReachesIntoNoHostApplication(): void
    {
        $offenders = [];
        foreach (self::shippedSources() as $path => $code) {
            if (str_contains($code, 'Uhifadhi\\Entity')
                || str_contains($code, 'Uhifadhi\\Service')
                || str_contains($code, 'Uhifadhi\\Repository')
                || str_contains($code, 'Uhifadhi\\Controller')) {
                $offenders[] = $path;
            }
        }

        self::assertSame([], $offenders, 'The shell must not depend on a host application namespace.');
    }

    /**
     * THE SHELL REMEMBERS ONE THING, AND IT IS FURNITURE.
     *
     * A shell that remembers nothing at all is worth every line it costs, and
     * the widget machinery is the one exception. Which widgets a person adopted
     * on which surface is the same kind of fact as which theme they chose: it
     * is about the frame, not about a domain, and every module with a dashboard
     * would otherwise reinvent the storage for it.
     *
     * So the rule narrows rather than dissolves: persistence lives under
     * Widget/, and the sweep below is what keeps it there. An Entity/ directory
     * anywhere else in this bundle is a domain arriving, and a shell that
     * remembered a domain would be a shell nobody could reason about.
     */
    public function testTheShellRemembersOnlyDashboardLayouts(): void
    {
        self::assertDirectoryDoesNotExist(self::ROOT.'/Entity', 'The shell\'s only entities are the widget machinery\'s.');
        self::assertDirectoryDoesNotExist(self::ROOT.'/Repository', 'The shell\'s only repositories are the widget machinery\'s.');
        self::assertDirectoryExists(self::ROOT.'/Widget/Entity', 'Dashboard layouts are stored, and they are stored here.');
        // The DDL for those two tables ships with them. It is the one thing
        // outside Widget/ that is allowed to name Doctrine, because a migration
        // is what a stored fact costs.
        self::assertDirectoryExists(self::ROOT.'/migrations', 'The shell ships the versions that create its two tables.');

        $offenders = [];
        foreach (self::phpSources() as $path => $code) {
            if (str_starts_with($path, 'Widget/') || str_starts_with($path, 'migrations/')) {
                continue;
            }
            if (str_contains($code, 'Doctrine\\')
                || str_contains($code, 'EntityManager')
                || str_contains($code, 'DATABASE_URL')) {
                $offenders[] = $path;
            }
        }

        self::assertSame([], $offenders, 'Outside Widget/, the shell draws; it does not remember.');
    }

    /**
     * THE SHELL CLAIMS NO URL WITHOUT THE APPLICATION'S CONSENT.
     *
     * The shell ships a route and a controller — the welcome page's — and it
     * loads neither. They are reachable only because an application imports
     * `@ShellBundle/config/routes/welcome.php` in its own
     * config/routes/shell.yaml, in one line it can read and delete. The full
     * proof is Integration/Routing/RouteResourceTest, which boots the same host
     * with and without that line; what is checked here is the crude half a
     * sweep can check — that no controller reaches for a base class it should
     * not have, and that the URL space stays the application's.
     *
     * NOTE WHAT IS NOT ASSERTED, deliberately: neither the absence of
     * src/Controller nor the absence of routes. Both were rules once and both
     * were the wrong rule — they described the shell's habits rather than its
     * boundary, and a page with real logic (the welcome screen's live reading
     * of what is installed) earns a controller under the boundary as stated.
     * A second Uhifadhi\Bundle\ShellBundle\Controller\* is ordinary work, not a rule change,
     * so long as it is presentation only and reachable only via the import.
     */
    public function testTheShellClaimsNoUrlWithoutTheApplicationsConsent(): void
    {
        self::assertFileExists(
            self::ROOT.'/config/routes/welcome.php',
            'The shell\'s routes exist as a resource an application imports.',
        );

        $offenders = [];
        foreach (self::phpSources() as $path => $code) {
            if (str_contains($code, 'extends AbstractController')) {
                $offenders[] = $path;
            }
        }

        self::assertSame([], $offenders, \sprintf(
            'A reusable bundle\'s controller takes its dependencies in its constructor: %s extends the host-application base class.',
            implode(', ', $offenders),
        ));
    }

    /**
     * ITS CONTROLLERS ARE PRESENTATION, AND PRESENTATION IS ALL THEY MAY BE.
     *
     * What a shell controller may read is what the shell can read for itself:
     * Composer's runtime metadata and the shell's own configured state. What it
     * may not do is reach for domain data — an entity, a repository, the registry.
     * Those arrive through the tagged source interfaces in src/Contract, the
     * same way the sidebar's rows do, and the same way they will for whatever
     * page comes next. testTheShellOwnsNoData and
     * testTheShellRequiresNoOtherBundle are the other two faces of this
     * rule; this one says it about the layer where it is easiest to break.
     */
    public function testItsControllersReadNothingButTheShellsOwnState(): void
    {
        $offenders = [];
        foreach (self::read(self::ROOT.'/Controller', 'Controller', ['php']) as $path => $code) {
            foreach (['Doctrine\\', 'Repository', 'EntityManager', 'Uhifadhi\\Contract', 'Uhifadhi\\Module'] as $forbidden) {
                if (str_contains($code, $forbidden)) {
                    $offenders[] = $path.' → '.$forbidden;
                }
            }
        }

        self::assertSame([], $offenders, 'A shell controller renders the shell\'s own state. Domain data arrives through src/Contract.');
    }

    /**
     * THE SHELL REQUIRES NO MODULE, and this is the ruling worth arguing —
     * docs/boundaries.md argues it at length. The short form: the shell draws the
     * module contract's answers but does not read them. They arrive already composed,
     * because composing them needs an area, a viewer and a department lens,
     * none of which the registry has either. A require here would make the shell
     * unusable on an installation with no module contract, and would put contract
     * entities inside templates, which is exactly where a `module.getSlug()`
     * comparison gets typed.
     *
     * THE ONE EXCEPTION IS the contracts package, and it proves rather than breaks
     * the rule: it is pure interfaces and value objects with no runtime (php is
     * its only require), so it drags in no module. It is where a contract the shell
     * CONSUMES lives — the user-badge card a team-aware source composes and the
     * shell only draws — which a module can then implement depending on
     * contracts alone, never on the shell. That is the whole reason the contract
     * moved here from the shell: an implementing module (team) keeps the shell
     * in require-dev.
     */
    public function testTheShellRequiresNoOtherBundle(): void
    {
        // the contracts package is the one uhifadhi/* dependency allowed: it is a
        // package of pure interfaces and value objects with no runtime of its
        // own (its only require is php), so requiring it drags in no module and
        // no contract runtime. It is where a cross-module contract the shell CONSUMES
        // lives — the user-badge contract a team-aware source implements — as
        // opposed to the module-provider contract the shell only DRAWS and still
        // must not require (see docs/boundaries.md).
        $modules = array_filter(
            array_keys(self::composerRequire()),
            static fn (string $package): bool => str_starts_with($package, 'uhifadhi/')
                && 'uhifadhi/contracts' !== $package,
        );

        self::assertSame([], array_values($modules), 'The shell requires no module: domain data reaches it through its contracts, and the only uhifadhi/* dependency is the contracts package.');
    }

    /**
     * FLAT FOLDERS, BY TECHNICAL KIND. src/Domain and its relatives are banned
     * across the platform; the shell has no domain to put in one anyway. templates/
     * is not an exception to the rule — it is not a domain folder, it is this
     * bundle's entire subject, and it must exist.
     */
    public function testItKeepsTheFlatFolderConvention(): void
    {
        self::assertDirectoryExists(self::ROOT.'/templates', 'templates/ is the shell\'s heart, not an afterthought.');

        foreach (['Domain', 'Application', 'Infrastructure', 'UI', 'Presentation'] as $banned) {
            self::assertDirectoryDoesNotExist(self::ROOT.'/'.$banned, \sprintf('%s/ is banned: folders are named by technical kind.', $banned));
            self::assertDirectoryDoesNotExist(self::ROOT.'/Widget/'.$banned, \sprintf('Widget/%s is banned: folders are named by technical kind.', $banned));
        }
    }

    /**
     * COMMENTS ARE PROSE, AND PROSE IS NOT A DEPENDENCY.
     *
     * The sweep above matches a bare word, which is the only thing crude enough
     * to catch every way a slug can be typed — and crude enough to trip over
     * "the storage for it" in a docblock. A module named in a comment is a
     * sentence; a module named in code is the bug. So the comments come out
     * first, and the rule stays about what the shell RUNS.
     *
     * @return string the source with its comments removed
     */
    private static function withoutComments(string $path, string $code): string
    {
        if (str_ends_with($path, '.twig')) {
            return preg_replace('/\{#.*?#\}/s', '', $code) ?? $code;
        }

        $stripped = '';
        foreach (token_get_all($code) as $token) {
            if (\is_array($token) && \in_array($token[0], [\T_COMMENT, \T_DOC_COMMENT], true)) {
                continue;
            }
            $stripped .= \is_array($token) ? $token[1] : $token;
        }

        return $stripped;
    }

    /**
     * @return array<string, string> relative path => contents (php + twig)
     */
    private static function shippedSources(): array
    {
        return self::phpSources() + self::templates();
    }

    /**
     * @return array<string, string>
     */
    private static function phpSources(): array
    {
        // Everything the package ships, minus its own suite: .gitattributes
        // export-ignores tests/, and the suite names modules on purpose.
        return array_filter(
            self::read(self::ROOT, '', ['php']),
            static fn (string $path): bool => !str_starts_with($path, 'tests/')
                // Symfony auto-dumps config/reference.php ("for apps only") when
                // a configured bundle boots in debug; it is gitignored, not shipped.
                && 'config/reference.php' !== $path,
            \ARRAY_FILTER_USE_KEY,
        );
    }

    /**
     * @return array<string, string>
     */
    private static function templates(): array
    {
        return self::read(self::ROOT.'/templates', 'templates', ['twig']);
    }

    /**
     * @return array<string, string>
     */
    private static function composerRequire(): array
    {
        $json = file_get_contents(self::ROOT.'/composer.json');
        self::assertIsString($json);
        $manifest = json_decode($json, true, 512, \JSON_THROW_ON_ERROR);
        self::assertIsArray($manifest);
        $require = $manifest['require'] ?? [];
        self::assertIsArray($require);

        /** @var array<string, string> $require */
        return $require;
    }

    /**
     * @param list<string> $extensions
     *
     * @return array<string, string> relative path => contents
     */
    private static function read(string $directory, string $label, array $extensions): array
    {
        if (!is_dir($directory)) {
            return [];
        }

        $files = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS),
        );

        /** @var \SplFileInfo $file */
        foreach ($iterator as $file) {
            if (!\in_array($file->getExtension(), $extensions, true)) {
                continue;
            }
            $code = file_get_contents($file->getPathname());
            if (false === $code) {
                continue;
            }
            $relative = substr($file->getPathname(), \strlen($directory) + 1);
            $files['' === $label ? $relative : $label.'/'.$relative] = $code;
        }

        ksort($files);

        return $files;
    }
}
