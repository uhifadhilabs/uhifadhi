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

namespace Uhifadhi\Bundle\ShellBundle\Tests\Integration;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Twig\Environment;

/**
 * A booted shell, and a Twig to render it with. Every integration test in this
 * repository starts here; there is no database fixture layer below it, and the
 * absence is the point.
 */
abstract class ShellKernelTestCase extends KernelTestCase
{
    protected static function getKernelClass(): string
    {
        return TestKernel::class;
    }

    protected function twig(): Environment
    {
        $twig = self::getContainer()->get('twig');
        \assert($twig instanceof Environment);

        return $twig;
    }
}
