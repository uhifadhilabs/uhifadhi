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

namespace Uhifadhi\Bundle\RegistryBundle\Tests\Integration;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Shared plumbing for every kernel test here.
 *
 * The kernel is named in code rather than through `KERNEL_CLASS`: the core is
 * one repository with one phpunit config and several bundles, so an env var can
 * only ever name one of their kernels.
 *
 * The framework's debug exception handler is registered while a kernel boots and
 * never popped, which PHPUnit reports as a risky test. Pop whatever is left.
 */
abstract class RegistryKernelTestCase extends KernelTestCase
{
    protected static function getKernelClass(): string
    {
        return TestKernel::class;
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        while (true) {
            $previous = set_exception_handler(static fn () => null);
            restore_exception_handler();
            if (null === $previous) {
                break;
            }
            restore_exception_handler();
        }
    }
}
