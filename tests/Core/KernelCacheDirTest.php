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

use PHPUnit\Framework\TestCase;
use Uhifadhi\Core\Tests\Application\Kernel;
use Uhifadhi\Core\Tests\Core\Fixtures\OtherCheckoutKernel;

/**
 * TWO CHECKOUTS OF THIS REPOSITORY MUST NEVER SHARE A COMPILED CONTAINER.
 *
 * A test kernel writes its cache under the system temp directory, which is
 * shared by every checkout on the machine; two worktrees then compile into one
 * place, and a suite in one of them loads classes the other compiled. Each
 * kernel keys that path on `getProjectDir()` — the checkout it belongs to —
 * and these are the three things that has to mean.
 *
 * @see https://symfony.com/doc/current/configuration/override_dir_structure.html#override-the-cache-directory
 * @see vendor/symfony/http-kernel/Kernel.php — `getCacheDir()`, `getBuildDir()` and `getLogDir()` are all derived from `getProjectDir()`
 */
final class KernelCacheDirTest extends TestCase
{
    /** The repository root: the checkout this run belongs to. */
    private const string ROOT = __DIR__.'/../..';

    private string $otherCheckout;

    protected function setUp(): void
    {
        $path = sys_get_temp_dir().'/uhifadhi-core-tests-other-checkout/'.bin2hex(random_bytes(6));
        mkdir($path, 0o777, true);
        $this->otherCheckout = $path;
    }

    protected function tearDown(): void
    {
        self::remove($this->otherCheckout);

        // The framework's debug exception handler is registered while a kernel
        // boots and never popped, which PHPUnit reports as a risky test.
        while (true) {
            $previous = set_exception_handler(static fn () => null);
            restore_exception_handler();
            if (null === $previous) {
                break;
            }
            restore_exception_handler();
        }
    }

    /**
     * The two kernels ask for the same suffix and differ in nothing but the
     * directory they were checked out into, so a shared cache directory here is
     * a shared container everywhere.
     */
    public function testTwoCheckoutsDoNotShareACompiledContainer(): void
    {
        $here = new Kernel('test', true);
        $there = new OtherCheckoutKernel($this->otherCheckout);

        $here->boot();
        $there->boot();

        try {
            self::assertNotSame($here->getCacheDir(), $there->getCacheDir(), 'Two checkouts must compile into two directories.');
            self::assertNotSame($here->getLogDir(), $there->getLogDir(), 'Two checkouts must log into two directories.');
            self::assertDirectoryExists($here->getCacheDir());
            self::assertDirectoryExists($there->getCacheDir());
        } finally {
            $here->shutdown();
            $there->shutdown();
        }
    }

    /** And the key is the checkout, not the run: the same one is the same path every time. */
    public function testOneCheckoutKeepsOneCacheDirectoryAcrossBoots(): void
    {
        $first = new Kernel('test', true);
        $first->boot();
        $path = $first->getCacheDir();
        $first->shutdown();

        $second = new Kernel('test', true);
        $second->boot();

        try {
            self::assertSame($path, $second->getCacheDir());
            self::assertSame($first->getLogDir(), $second->getLogDir());
        } finally {
            $second->shutdown();
        }
    }

    /**
     * EVERY KERNEL IN THE REPOSITORY, not only the ones a specification happens
     * to boot here. Each package keys its own path — a suite is a package, so
     * none of them may reach into another's test tree for it — and the sweep is
     * what keeps the eleventh kernel from being written the old way.
     */
    public function testEveryTestKernelKeysItsCacheDirectoryToTheCheckout(): void
    {
        $offenders = [];
        $traits = [];

        foreach (self::testTreeSources() as $path => $code) {
            if (str_ends_with($path, '/CheckoutTempDirTrait.php')) {
                $traits[$path] = $code;
                continue;
            }
            if ((str_contains($code, 'function getCacheDir') || str_contains($code, 'function getLogDir'))
                && str_contains($code, 'sys_get_temp_dir')) {
                $offenders[] = $path;
            }
        }

        self::assertSame([], $offenders, 'A kernel directory under the system temp directory is keyed by the checkout, through CheckoutTempDirTrait.');
        self::assertNotSame([], $traits, 'the packages key their kernel directories with a trait, so there is one to find');

        foreach ($traits as $path => $code) {
            self::assertStringContainsString('$this->getProjectDir()', $code, $path.' must key on the project directory.');
        }
    }

    /**
     * @return array<string, string> path => source, for every test tree in the repository
     */
    private static function testTreeSources(): array
    {
        $roots = glob(self::ROOT.'/src/Uhifadhi/{Bundle/*,*}/tests', \GLOB_BRACE) ?: [];
        $roots[] = self::ROOT.'/tests';

        $sources = [];
        foreach ($roots as $root) {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS),
            );

            /** @var \SplFileInfo $file */
            foreach ($iterator as $file) {
                if ('php' !== $file->getExtension()) {
                    continue;
                }
                // The sweep names the two methods it looks for, so it would
                // find itself.
                if (realpath($file->getPathname()) === realpath(__FILE__)) {
                    continue;
                }
                $code = file_get_contents($file->getPathname());
                if (false !== $code) {
                    $sources[$file->getPathname()] = $code;
                }
            }
        }

        ksort($sources);

        return $sources;
    }

    private static function remove(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );

        /** @var \SplFileInfo $entry */
        foreach ($iterator as $entry) {
            $entry->isDir() ? rmdir($entry->getPathname()) : unlink($entry->getPathname());
        }

        rmdir($path);
    }
}
